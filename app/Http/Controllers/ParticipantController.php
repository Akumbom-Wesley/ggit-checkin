<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Support\DatasetVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ParticipantController extends Controller
{
    /** Full list, loaded once per device. Search then runs in the browser with no server round-trips. */
    public function index(): JsonResponse
    {
        return response()->json([
            'now'          => now()->toIso8601String(),
            'dataset'      => DatasetVersion::current(),
            'participants' => Participant::orderBy('full_name')->get()->map->toClient(),
        ]);
    }

    /** Everything changed since this device last synced: other desks' check-ins and additions. */
    public function changes(Request $request): JsonResponse
    {
        $now   = now();
        $since = rescue(fn () => Carbon::parse($request->query('since')), null, false);

        $query = Participant::query();
        if ($since) {
            // 2s overlap covers second-precision timestamps; the browser de-duplicates by id.
            $query->where('updated_at', '>=', $since->copy()->subSeconds(2));
        }

        return response()->json([
            'now'          => $now->toIso8601String(),
            'dataset'      => DatasetVersion::current(),
            'participants' => $query->get()->map->toClient(),
        ]);
    }

    public function checkin(Participant $participant): JsonResponse
    {
        $now = now();

        // Atomic: if two desks tap the same person at once, only the first one wins.
        $won = Participant::whereKey($participant->id)
            ->whereNull('checked_in_at')
            ->update(['checked_in_at' => $now, 'updated_at' => $now]);

        return response()->json(
            ['participant' => $participant->refresh()->toClient()],
            $won ? 200 : 409   // 409 = already checked in (returns their check-in time)
        );
    }

    public function undo(Participant $participant): JsonResponse
    {
        $participant->forceFill(['checked_in_at' => null])->save();

        return response()->json(['participant' => $participant->toClient()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name'    => ['required', 'string', 'min:2', 'max:120'],
            'phone'        => ['required', 'string', 'max:32', 'regex:/^[\d\s+()\-]{8,}$/'],
            'email'        => ['required', 'email', 'max:160'],
            'category'     => ['required', Rule::in(array_keys(Participant::CATEGORIES))],
            'partner_name' => ['nullable', 'required_if:category,partner', 'string', 'max:120'],
            'notes'        => ['nullable', 'string', 'max:500'],
            'amount'       => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'check_in'     => ['boolean'],
            'force'        => ['boolean'],
        ], [
            'phone.regex'              => 'Enter a valid phone number.',
            'partner_name.required_if' => 'Which partner or sponsor sent them?',
            'amount.integer'           => 'Enter the amount as a whole number.',
        ]);

        $data['full_name'] = preg_replace('/\s+/', ' ', trim($data['full_name']));

        // Warn before creating a likely duplicate (same phone or same email).
        if (! $request->boolean('force')) {
            $matches = Participant::query()
                ->where(fn ($q) => $q
                    ->where('phone_key', Participant::phoneKey($data['phone']))
                    ->orWhere('email', mb_strtolower(trim($data['email']))))
                ->limit(5)->get();

            if ($matches->isNotEmpty()) {
                return response()->json([
                    'message' => 'possible_duplicate',
                    'matches' => $matches->map->toClient(),
                ], 409);
            }
        }

        // Amount typed by staff, otherwise the ticket price for paying categories (walk-ins), otherwise 0.
        $amount = $request->filled('amount')
            ? (int) $request->input('amount')
            : (in_array($data['category'], config('checkin.paying_categories'), true) ? (int) config('checkin.ticket_price') : 0);

        $participant = Participant::create([
            'full_name'     => $data['full_name'],
            'phone'         => trim($data['phone']),
            'email'         => $data['email'],
            'category'      => $data['category'],
            'partner_name'  => $data['category'] === 'partner' ? trim($data['partner_name']) : null,
            'notes'         => $data['notes'] ?? null,
            'paid'          => true,
            'amount'        => $amount,
            'paid_at'       => $amount > 0 ? now() : null,
            'registered_at' => now(),
            'checked_in_at' => $request->boolean('check_in', true) ? now() : null,
        ]);

        return response()->json(['participant' => $participant->toClient()], 201);
    }
}