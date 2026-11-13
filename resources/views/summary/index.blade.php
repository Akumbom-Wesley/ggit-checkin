@extends('layouts.app')
@section('title', 'Summary')

@php
    $barColours = [
        'registered' => 'bg-ggit-blue',
        'partner' => 'bg-ggit-purple',
        'invited' => 'bg-amber-500',
        'walkin' => 'bg-emerald-500',
    ];
    $percent = fn ($part, $whole) => $whole ? (int) round($part / $whole * 100) : 0;
@endphp

@section('body')
<div x-data="{ menuOpen: false }"
     x-init="setInterval(() => { if (document.visibilityState === 'visible' && !menuOpen) location.reload() }, 20000)"
     x-effect="document.body.classList.toggle('overflow-hidden', menuOpen)"
     class="min-h-dvh pb-16">

    <x-app-header title="Summary" />
    <x-app-drawer />
    
    <main class="mx-auto max-w-4xl space-y-6 px-4 pt-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">Summary</h1>
                <p class="mt-1 text-sm text-gray-500">Updates automatically every 20 seconds.</p>
            </div>
            <button type="button" onclick="location.reload()"
                    class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-ggit-purple hover:text-ggit-purple">
                Refresh
            </button>
        </div>

        {{-- ===== Overall ===== --}}
        <section class="rounded-2xl bg-white p-5 ring-1 ring-gray-200 sm:p-6">
            <h2 class="text-sm font-medium text-gray-500">Arrivals</h2>
            <p class="mt-1 text-4xl font-semibold tabular-nums">
                {{ $checkedIn }}<span class="text-xl font-normal text-gray-400"> of {{ $total }}</span>
            </p>
            <div class="mt-4 h-3 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-ggit-purple" style="width: {{ $percent($checkedIn, $total) }}%"></div>
            </div>
            <div class="mt-3 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-xl bg-gray-50 p-3">
                    <p class="text-xl font-semibold tabular-nums text-ggit-purple">{{ $percent($checkedIn, $total) }}%</p>
                    <p class="text-xs text-gray-500">arrived</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-3">
                    <p class="text-xl font-semibold tabular-nums text-emerald-700">{{ $checkedIn }}</p>
                    <p class="text-xs text-gray-500">checked in</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-3">
                    <p class="text-xl font-semibold tabular-nums text-gray-600">{{ $total - $checkedIn }}</p>
                    <p class="text-xs text-gray-500">still expected</p>
                </div>
            </div>
        </section>

        {{-- ===== By category ===== --}}
        <section class="rounded-2xl bg-white p-5 ring-1 ring-gray-200 sm:p-6">
            <h2 class="text-base font-semibold">By category</h2>
            <ul class="mt-4 space-y-4">
                @foreach ($categories as $key => $label)
                    @php
                        $row = $byCategory[$key] ?? null;
                        $catTotal = (int) ($row->total ?? 0);
                        $catIn = (int) ($row->checked_in ?? 0);
                    @endphp
                    <li>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">{{ $label }}</span>
                            <span class="tabular-nums text-gray-500">
                                <span class="font-medium text-ggit-ink">{{ $catIn }}</span> / {{ $catTotal }}
                            </span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full {{ $barColours[$key] ?? 'bg-gray-400' }}" style="width: {{ $percent($catIn, $catTotal) }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- ===== By partner / sponsor ===== --}}
        @if ($byPartner->isNotEmpty())
            <section class="rounded-2xl bg-white p-5 ring-1 ring-gray-200 sm:p-6">
                <h2 class="text-base font-semibold">By partner / sponsor</h2>
                <ul class="mt-3 divide-y divide-gray-100">
                    @foreach ($byPartner as $partner)
                        <li class="flex items-center justify-between py-2.5 text-sm">
                            <span class="truncate pr-3 text-gray-700">{{ $partner->partner }}</span>
                            <span class="shrink-0 tabular-nums text-gray-500">
                                <span class="font-medium text-ggit-ink">{{ (int) $partner->checked_in }}</span> / {{ (int) $partner->total }} arrived
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- ===== Latest arrivals ===== --}}
        <section class="rounded-2xl bg-white p-5 ring-1 ring-gray-200 sm:p-6">
            <h2 class="text-base font-semibold">Latest arrivals</h2>
            @if ($latestArrivals->isEmpty())
                <p class="mt-3 text-sm text-gray-500">No one has checked in yet.</p>
            @else
                <ul class="mt-3 divide-y divide-gray-100">
                    @foreach ($latestArrivals as $person)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ $person->full_name }}</p>
                                <p class="truncate text-xs text-gray-500">
                                    {{ $categories[$person->category] ?? $person->category }}@if ($person->partner_name) · {{ $person->partner_name }}@endif
                                </p>
                            </div>
                            <span class="shrink-0 rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium tabular-nums text-emerald-800">
                                {{ $person->checked_in_at->format('H:i') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </main>
</div>
@endsection