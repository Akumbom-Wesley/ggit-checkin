<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Support\SimpleXlsx;

class ExportController extends Controller
{
    public function __invoke()
    {
        // Checked-in people first (in arrival order), then everyone still expected, A–Z.
        $people = Participant::query()
            ->orderByRaw('checked_in_at IS NULL')
            ->orderBy('checked_in_at')
            ->orderBy('full_name')
            ->get();

        $currency = config('checkin.currency');

        // ---- Sheet 1: Attendance ----
        $attendance = [[
            '#', 'Full name', 'Category', 'Partner / Sponsor', 'Phone', 'Email',
            'Checked in', 'Check-in time', "Amount paid ({$currency})", 'Paid at', 'Notes', 'Registered on',
        ]];

        foreach ($people as $i => $p) {
            $attendance[] = [
                $i + 1,
                $p->full_name,
                Participant::CATEGORIES[$p->category] ?? $p->category,
                $p->partner_name,
                $p->phone,                                   // kept as text so digits are never lost
                $p->email,
                $p->checked_in_at ? 'Yes' : 'No',
                $p->checked_in_at?->format('H:i'),
                $p->amount ?: null,                          // blank = paid before the event / free
                $p->paid_at?->format('d/m/Y H:i'),
                $p->notes,
                $p->registered_at?->format('d/m/Y H:i'),
            ];
        }

        // ---- Sheet 2: Summary ----
        $summary = [['Category', 'Expected', 'Checked in', 'Not yet', '% arrived', "Collected ({$currency})"]];

        foreach (Participant::CATEGORIES as $key => $label) {
            $group = $people->where('category', $key);
            $total = $group->count();
            $in = $group->whereNotNull('checked_in_at')->count();
            $summary[] = [$label, $total, $in, $total - $in, $total ? round($in / $total * 100).'%' : '-', (int) $group->sum('amount')];
        }

        $total = $people->count();
        $in = $people->whereNotNull('checked_in_at')->count();
        $summary[] = ['TOTAL', $total, $in, $total - $in, $total ? round($in / $total * 100).'%' : '-', (int) $people->sum('amount')];

        $file = SimpleXlsx::build([
            ['name' => 'Attendance', 'rows' => $attendance, 'widths' => [5, 32, 18, 24, 15, 32, 11, 13, 18, 17, 40, 17]],
            ['name' => 'Summary', 'rows' => $summary, 'widths' => [22, 11, 12, 10, 11, 18]],
        ]);

        $name = 'GGIT-Attendance-'.now()->format('Y-m-d-Hi').'.xlsx';

        return response()
            ->download($file, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend(true);
    }
}