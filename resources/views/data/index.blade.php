@extends('layouts.app')
@section('title', 'Data & Reports')

@php
    $fieldLabels = [
        'full_name' => 'Full name', 'first_name' => 'First name', 'last_name' => 'Last name',
        'email' => 'Email', 'phone' => 'Phone', 'category' => 'Category', 'partner_name' => 'Partner / Sponsor',
        'notes' => 'Notes', 'payment_proof' => 'Payment proof', 'registered_at' => 'Registered on',
    ];
@endphp

@section('body')
<div x-data="{ confirmAction: null, menuOpen: false }"
     x-effect="document.body.classList.toggle('overflow-hidden', confirmAction !== null || menuOpen)"
     class="min-h-dvh pb-16">

    <x-app-header title="Data & Reports" />
    <x-app-drawer />

    <main class="mx-auto max-w-4xl space-y-6 px-4 pt-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">Data &amp; Reports</h1>
            <p class="mt-1 text-sm text-gray-500">Download the attendance report, import participant lists, or reset the data after testing.</p>
        </div>

        {{-- ===== Messages ===== --}}
        @if (session('status'))
            <div class="flex items-start gap-3 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900 ring-1 ring-emerald-200">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        @if (session('importReport'))
            @php($report = session('importReport'))
            <section class="rounded-2xl bg-white p-5 ring-1 ring-emerald-200 sm:p-6">
                <h2 class="flex items-center gap-2 text-base font-semibold text-emerald-800">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                    Import finished
                </h2>
                <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-emerald-50 p-3">
                        <p class="text-2xl font-semibold tabular-nums text-emerald-700">{{ $report['imported'] }}</p>
                        <p class="text-xs text-emerald-800">imported</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-3">
                        <p class="text-2xl font-semibold tabular-nums text-amber-700">{{ $report['duplicates'] }}</p>
                        <p class="text-xs text-amber-800">already on the list</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-3">
                        <p class="text-2xl font-semibold tabular-nums text-gray-600">{{ $report['empty'] }}</p>
                        <p class="text-xs text-gray-600">rows without a name</p>
                    </div>
                </div>

                <p class="mt-4 text-xs text-gray-500">
                    Columns used:
                    @foreach ($report['columns'] as $field => $header)
                        <span class="mr-1 inline-block rounded bg-gray-100 px-1.5 py-0.5 text-gray-700">{{ $fieldLabels[$field] ?? $field }} ← “{{ $header }}”</span>
                    @endforeach
                </p>

                @if (count($report['duplicate_names']))
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer font-medium text-amber-800">Show who was skipped</summary>
                        <p class="mt-2 text-gray-600">{{ implode(', ', $report['duplicate_names']) }}@if ($report['duplicates'] > count($report['duplicate_names'])) … @endif</p>
                    </details>
                @endif
            </section>
        @endif

        {{-- ===== Overview ===== --}}
        <section class="grid grid-cols-3 gap-3">
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Participants</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $total }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Checked in</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700">{{ $checkedIn }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Not arrived</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-600">{{ $total - $checkedIn }}</p>
            </div>
        </section>

        {{-- ===== 1. Download ===== --}}
        <section class="rounded-2xl bg-white p-5 ring-1 ring-gray-200 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Attendance report</h2>
                    <p class="mt-1 text-sm text-gray-500">Excel file with every participant, category, partner, phone, email and check-in time, plus a summary sheet.</p>
                </div>
                <a href="{{ route('export') }}"
                   class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-ggit-purple px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-ggit-purple-dark">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>
                    Download Excel
                </a>
            </div>
        </section>

        {{-- ===== 2. Import ===== --}}
        <section class="rounded-2xl bg-white p-5 ring-1 ring-gray-200 sm:p-6"
                    x-data="{ category: 'registered' }">
            <h2 class="text-base font-semibold">Import participants</h2>
            <p class="mt-1 text-sm text-gray-500">
                Upload an Excel (.xlsx) or CSV file. The first row must be the column headers.
                Your Google Forms export works as it is.
            </p>

            <form method="POST" action="{{ route('data.import') }}" enctype="multipart/form-data" class="mt-5 space-y-5">
                @csrf

                <div>
                    <label for="file" class="field-label">File</label>
                    <input id="file" name="file" type="file" accept=".xlsx,.csv" required
                           class="mt-1 block w-full cursor-pointer rounded-xl border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600
                                  file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-ggit-purple/10 file:px-4 file:py-2
                                  file:text-sm file:font-medium file:text-ggit-purple hover:file:bg-ggit-purple/15">
                    @error('file')
                        <p class="field-msg">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="category" class="field-label">Category for this list</label>
                                                <select id="category" name="category" x-model="category" class="field">
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}" @selected($key === 'registered')>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Default: <strong>Registered</strong>. Change it only for partner or invited-guest lists.</p>
                    </div>

                    <div x-show="category === 'partner'" x-cloak>
                        <label for="partner_name" class="field-label">Partner / Sponsor name</label>
                        <input id="partner_name" name="partner_name" value="{{ old('partner_name') }}" class="field"
                               placeholder="e.g. MTN Foundation">
                        <p class="mt-1 text-xs text-gray-500">Used when the file has no partner column.</p>
                    </div>
                </div>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-gray-50 px-4 py-3">
                    <input type="hidden" name="skip_duplicates" value="0">
                    <input type="checkbox" name="skip_duplicates" value="1" class="mt-0.5 h-5 w-5 accent-ggit-purple"
                           @checked(old('skip_duplicates', '1') === '1')>
                    <span class="text-sm">
                        <span class="font-medium">Skip people already on the list</span>
                        <span class="block text-xs text-gray-500">Same phone number or email. Recommended.</span>
                    </span>
                </label>

                <details class="text-sm text-gray-600">
                    <summary class="cursor-pointer font-medium text-ggit-purple">Which columns are recognised?</summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-gray-500">
                        <li><strong>Name</strong> (required): “Full name”, or “First name” + “Last name”</li>
                        <li><strong>Phone</strong>: “Phone”, “WhatsApp”, “Mobile”</li>
                        <li><strong>Email</strong>: “Email”, “Email address”</li>
                        <li><strong>Category</strong>: values like Registered, Partner, Sponsor, Invited, Guest, Walk-in</li>
                        <li><strong>Partner / Sponsor</strong>: “Partner”, “Sponsor”, “Organisation”, “Company”</li>
                        <li><strong>Notes</strong>: “Notes”, “Comments”, “Anything we should know?”</li>
                    </ul>
                </details>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('data.template') }}" class="text-center text-sm font-medium text-ggit-blue underline-offset-2 hover:underline">
                        Download a CSV template
                    </a>
                    <button type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-ggit-blue px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-ggit-blue-dark">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20V9m0 0-4 4m4-4 4 4M5 4h14"/></svg>
                        Import file
                    </button>
                </div>
            </form>
        </section>

        {{-- ===== 3. Reset & delete ===== --}}
        <section class="rounded-2xl bg-white p-5 ring-1 ring-red-200 sm:p-6">
            <h2 class="text-base font-semibold text-red-700">Reset &amp; delete</h2>
            <p class="mt-1 text-sm text-gray-500">Use these after testing, before the event starts. Each one asks you to confirm.</p>

            <div class="mt-5 divide-y divide-gray-100">
                <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium">Clear all check-ins</p>
                        <p class="text-xs text-gray-500">Everyone goes back to “not arrived”. Participants are kept.</p>
                    </div>
                    <button type="button" @click="confirmAction = 'reset'"
                            class="shrink-0 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-100">
                        Clear check-ins
                    </button>
                </div>

                <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium">Delete all participants</p>
                        <p class="text-xs text-gray-500">Removes everyone and all check-ins. Use it before re-importing a fresh list.</p>
                    </div>
                    <button type="button" @click="confirmAction = 'delete'"
                            class="shrink-0 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                        Delete all
                    </button>
                </div>
            </div>

            @error('confirmation')
                <p class="field-msg">{{ $message }}</p>
            @enderror
        </section>
    </main>

    {{-- ===== Confirmation popup ===== --}}
    <div x-show="confirmAction" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
         @keydown.escape.window="confirmAction = null">
        <div class="absolute inset-0 bg-black/50" @click="confirmAction = null"></div>

        <div role="alertdialog" aria-modal="true"
             class="relative w-full rounded-t-2xl bg-white p-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] shadow-2xl sm:max-w-md sm:rounded-2xl">

            {{-- clear check-ins --}}
            <template x-if="confirmAction === 'reset'">
                <form method="POST" action="{{ route('data.reset-checkins') }}">
                    @csrf
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.1 15A7 7 0 0 0 18.4 17M18.9 9A7 7 0 0 0 5.6 7"/></svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold">Clear all check-ins?</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        The {{ $checkedIn }} {{ \Illuminate\Support\Str::plural('person', $checkedIn) }} checked in will go back to
                        “not arrived”. No one is deleted.
                    </p>
                    <div class="mt-6 flex gap-3">
                        <button type="button" @click="confirmAction = null"
                                class="flex-1 rounded-xl border border-gray-300 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 rounded-xl bg-amber-500 py-3 text-sm font-semibold text-white transition hover:bg-amber-600">
                            Yes, clear check-ins
                        </button>
                    </div>
                </form>
            </template>

            {{-- delete all --}}
            <template x-if="confirmAction === 'delete'">
                <form method="POST" action="{{ route('data.participants.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="confirmation" value="DELETE">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold">Delete all {{ $total }} participants?</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        This removes everyone and all check-ins from the database. <strong>It cannot be undone.</strong>
                        Download the Excel report first if you need a copy.
                    </p>
                    <div class="mt-6 flex gap-3">
                        <button type="button" @click="confirmAction = null"
                                class="flex-1 rounded-xl border border-gray-300 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 rounded-xl bg-red-600 py-3 text-sm font-semibold text-white transition hover:bg-red-700">
                            Yes, delete everything
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>
@endsection