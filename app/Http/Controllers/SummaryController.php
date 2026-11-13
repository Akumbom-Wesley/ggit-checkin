<?php

namespace App\Http\Controllers;

use App\Models\Participant;

/** Summary page: arrivals, attendance chart, money collected today, partners, latest arrivals. */
class SummaryController extends Controller
{
    public function __invoke()
    {
        $byCategory = Participant::query()
            ->selectRaw('category, COUNT(*) AS total, COUNT(checked_in_at) AS checked_in')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $byPartner = Participant::query()
            ->where('category', 'partner')
            ->selectRaw("COALESCE(partner_name, 'Not specified') AS partner, COUNT(*) AS total, COUNT(checked_in_at) AS checked_in")
            ->groupBy('partner')
            ->orderByDesc('total')
            ->get();

        $latestArrivals = Participant::query()
            ->whereNotNull('checked_in_at')
            ->latest('checked_in_at')
            ->limit(10)
            ->get();

        // Money collected at the door today
        $paidToday = Participant::query()->where('amount', '>', 0)->whereDate('paid_at', today());

        return view('summary.index', [
            'categories'     => Participant::CATEGORIES,
            'byCategory'     => $byCategory,
            'byPartner'      => $byPartner,
            'latestArrivals' => $latestArrivals,
            'total'          => (int) $byCategory->sum('total'),
            'checkedIn'      => (int) $byCategory->sum('checked_in'),
            'collectedToday' => (int) (clone $paidToday)->sum('amount'),
            'paymentsToday'  => (clone $paidToday)->count(),
        ]);
    }
}