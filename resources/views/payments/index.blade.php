@extends('layouts.app')
@section('title', 'Payments')

@php
    $badges = [
        'registered' => 'bg-ggit-blue/10 text-ggit-blue',
        'speaker'    => 'bg-rose-100 text-rose-700',
        'partner'    => 'bg-ggit-purple/10 text-ggit-purple',
        'invited'    => 'bg-amber-100 text-amber-800',
        'walkin'     => 'bg-emerald-100 text-emerald-800',
    ];
    $currency = config('checkin.currency');
@endphp

@section('body')
<div x-data="{ menuOpen: false }"
     x-effect="document.body.classList.toggle('overflow-hidden', menuOpen)"
     class="min-h-dvh pb-16">

    <x-app-header title="Payments" />
    <x-app-drawer />

    <main class="mx-auto max-w-4xl space-y-6 px-4 pt-6">
        {{-- title + date --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('summary') }}" class="text-sm text-gray-500 hover:text-ggit-purple">← Summary</a>
                <h1 class="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">Payments at the door</h1>
                <p class="mt-1 text-sm text-gray-500">Money recorded when adding participants on the day.</p>
            </div>
            <form method="GET" action="{{ route('payments.index') }}">
                <label for="date" class="sr-only">Day</label>
                <select id="date" name="date" onchange="this.form.submit()"
                        class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-ggit-purple focus:ring-2 focus:ring-ggit-purple/15">
                    @foreach ($dayOptions as $option)
                        <option value="{{ $option['value'] }}" @selected($option['value'] === $date->toDateString())>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        {{-- messages --}}
        @if (session('status'))
            <div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900 ring-1 ring-emerald-200">{{ session('status') }}</div>
        @endif
        @error('amount')
            <div class="rounded-xl bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200">{{ $message }}</div>
        @enderror

        {{-- totals --}}
        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200 sm:col-span-1">
                <p class="text-xs text-gray-500">Total collected</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700">{{ number_format($total) }} <span class="text-sm font-normal text-gray-400">{{ $currency }}</span></p>
            </div>
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Payments</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $payments->count() }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">By type</p>
                @forelse ($byCategory as $key => $row)
                    <p class="mt-1 flex justify-between text-sm">
                        <span class="text-gray-700">{{ $categories[$key] ?? $key }} ({{ $row['count'] }})</span>
                        <span class="font-medium tabular-nums">{{ number_format($row['total']) }}</span>
                    </p>
                @empty
                    <p class="mt-1 text-sm text-gray-400">None</p>
                @endforelse
            </div>
        </section>

        {{-- list --}}
        <section class="overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h2 class="text-base font-semibold">Who paid</h2>
                <p class="text-xs text-gray-500">Change an amount, then tap Save · 0 removes the payment</p>
            </div>

            @if ($payments->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-gray-500">No payments recorded for this day.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($payments as $p)
                        <li class="flex flex-col gap-3 px-5 py-3 sm:flex-row sm:items-center">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $p->full_name }}</p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                    <span class="rounded-md px-1.5 py-0.5 font-medium {{ $badges[$p->category] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $categories[$p->category] ?? $p->category }}@if ($p->partner_name) · {{ $p->partner_name }}@endif
                                    </span>
                                    @if ($p->phone)<span class="tabular-nums">{{ $p->phone }}</span>@endif
                                    <span class="tabular-nums">Paid {{ $p->paid_at?->format('H:i') }}</span>
                                </p>
                            </div>

                            <form method="POST" action="{{ route('payments.update', $p) }}"
                                  x-data="{ value: {{ (int) $p->amount }}, original: {{ (int) $p->amount }} }"
                                  class="flex items-center gap-2 sm:justify-end">
                                @csrf
                                @method('PATCH')
                                <div class="relative">
                                    <input name="amount" type="number" inputmode="numeric" min="0" step="500" x-model.number="value"
                                           aria-label="Amount paid by {{ $p->full_name }}"
                                           class="h-9 w-32 rounded-lg border border-gray-300 bg-white pl-3 pr-12 text-right text-sm tabular-nums outline-none focus:border-ggit-purple focus:ring-2 focus:ring-ggit-purple/15">
                                    <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">{{ $currency }}</span>
                                </div>
                                <button type="submit" x-show="value !== original" x-cloak
                                        class="h-9 rounded-lg bg-ggit-purple px-3 text-xs font-semibold text-white transition hover:bg-ggit-purple-dark">
                                    Save
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </main>
</div>
@endsection