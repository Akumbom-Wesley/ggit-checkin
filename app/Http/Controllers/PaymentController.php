<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Payments collected at the door: who, when, how much. One day at a time (today by default). */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $date = rescue(fn () => Carbon::parse($request->query('date', today()->toDateString()))->startOfDay(), today(), false);

        $payments = Participant::query()
            ->where('amount', '>', 0)
            ->whereDate('paid_at', $date)
            ->orderByDesc('paid_at')
            ->get();

        // Days that have payments, for the date picker (today is always listed).
        $days = Participant::query()
            ->where('amount', '>', 0)
            ->whereNotNull('paid_at')
            ->selectRaw('DATE(paid_at) AS day')
            ->groupBy('day')
            ->orderByDesc('day')
            ->pluck('day');

        $dayOptions = collect([today()->toDateString()])
            ->merge($days)
            ->unique()
            ->sortDesc()
            ->map(fn ($day) => [
                'value' => $day,
                'label' => Carbon::parse($day)->isToday() ? 'Today, '.Carbon::parse($day)->format('d M Y') : Carbon::parse($day)->format('D d M Y'),
            ])
            ->values();

        $byCategory = $payments->groupBy('category')->map(fn ($group) => [
            'count' => $group->count(),
            'total' => (int) $group->sum('amount'),
        ]);

        return view('payments.index', [
            'date'       => $date,
            'dayOptions' => $dayOptions,
            'payments'   => $payments,
            'total'      => (int) $payments->sum('amount'),
            'byCategory' => $byCategory,
            'categories' => Participant::CATEGORIES,
        ]);
    }

    /** Correct an amount. Setting it to 0 removes the person from the payments list. */
    public function update(Request $request, Participant $participant)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:0', 'max:10000000'],
        ], [
            'amount.required' => 'Enter an amount (0 to remove the payment).',
            'amount.integer'  => 'Enter the amount as a whole number.',
        ]);

        $participant->amount = (int) $data['amount'];
        $participant->paid_at = $participant->amount > 0 ? ($participant->paid_at ?? now()) : null;
        $participant->save();

        return back()->with('status', $participant->amount > 0
            ? "{$participant->full_name}: amount changed to ".Participant::money($participant->amount).'.'
            : "{$participant->full_name}: payment removed.");
    }
}