<?php

namespace App\Services;

use App\Models\Participant;

/** Numbers and lists for printed/downloaded reports. */
class AttendanceReport
{
    public function build(): array
    {
        // Checked-in people first (in arrival order), then everyone still expected, A–Z.
        $people = Participant::query()
            ->orderByRaw('checked_in_at IS NULL')
            ->orderBy('checked_in_at')
            ->orderBy('full_name')
            ->get();

        $categories = [];
        foreach (Participant::CATEGORIES as $key => $label) {
            $group = $people->where('category', $key);
            $total = $group->count();
            $in = $group->whereNotNull('checked_in_at')->count();

            $categories[$key] = [
                'label'  => $label,
                'total'  => $total,
                'in'     => $in,
                'pct'    => $total ? (int) round($in / $total * 100) : 0,
                'amount' => (int) $group->sum('amount'),
            ];
        }

        $total = $people->count();
        $checkedIn = $people->whereNotNull('checked_in_at')->count();
        $paidToday = $people->filter(fn ($p) => $p->amount > 0 && $p->paid_at?->isToday());

        return [
            'people'         => $people,
            'categories'     => $categories,
            'total'          => $total,
            'checkedIn'      => $checkedIn,
            'pct'            => $total ? (int) round($checkedIn / $total * 100) : 0,
            'collectedToday' => (int) $paidToday->sum('amount'),
            'paymentsToday'  => $paidToday->count(),
            'maxCategory'    => max(1, max(array_column($categories, 'total'))),
            'generatedAt'    => now(),
        ];
    }
}