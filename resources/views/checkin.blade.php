@extends('layouts.app')
@section('title', 'Check-in')

@section('body')
<div x-data="checkin(@js(['tz' => config('app.timezone'), 'syncMs' => config('checkin.sync_interval')]))"
     x-effect="document.body.classList.toggle('overflow-hidden', menuOpen || add.open)"
     class="min-h-dvh pb-24 lg:pb-0">

    {{-- ===== Header ===== --}}
    <x-app-header>
        <span class="flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium" :title="syncLabel">
            <span class="h-2 w-2 rounded-full" :class="online ? 'bg-emerald-400' : 'animate-pulse bg-red-500'"></span>
            <span x-text="online ? 'Live' : 'Offline'">Live</span>
        </span>

        {{-- phones / tablets: add --}}
        <button type="button" @click="openAdd(q.trim())"
                class="flex items-center gap-1 rounded-lg bg-ggit-blue px-3 py-2 text-sm font-semibold text-white transition hover:bg-ggit-blue-dark active:scale-95 lg:hidden">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            Add
        </button>

        {{-- brand strip doubles as the arrival progress bar --}}
        <x-slot:bar>
            <div class="h-1 bg-ggit-purple/40">
                <div class="h-full bg-ggit-purple transition-all duration-500" :style="`width:${stats.pct}%`"></div>
            </div>
        </x-slot:bar>
    </x-app-header>

    <x-app-drawer />

    <div class="mx-auto grid max-w-6xl grid-cols-1 gap-8 px-4 lg:grid-cols-[minmax(0,1fr)_300px]">
        <main class="min-w-0 lg:pb-10">
            {{-- ===== What to do ===== --}}
            <div class="pt-5">
                <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">Check in participants</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Search by name or phone number, then tap <span class="font-medium text-ggit-purple">Check in</span>.
                    <span class="lg:hidden">Not on the list? Tap <span class="font-medium text-ggit-blue">+ Add</span>.</span>
                </p>
            </div>

            {{-- ===== Search (sticks under the header) ===== --}}
            <div class="sticky top-[3.75rem] z-20 -mx-4 bg-gray-100/95 px-4 pb-3 pt-3 backdrop-blur">
                <label for="search" class="sr-only">Search participants</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/></svg>
                    <input id="search" x-ref="search" x-model="q" type="text" autofocus
                           autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" enterkeyhint="search"
                           placeholder="Type a name or phone number"
                           @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                           @keydown.enter.prevent="enter()" @keydown.escape="q = ''"
                           class="w-full rounded-xl border border-gray-300 bg-white py-3.5 pl-12 pr-12 text-base shadow-sm outline-none transition placeholder:text-gray-400 focus:border-ggit-purple focus:ring-4 focus:ring-ggit-purple/15 sm:text-lg">
                    <button type="button" x-show="q" x-cloak @click="clearSearch()" aria-label="Clear search"
                            class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <div class="inline-flex min-w-0 rounded-lg bg-white p-1 text-sm shadow-sm ring-1 ring-gray-200">
                        <template x-for="f in [['all', 'All'], ['waiting', 'Not arrived'], ['in', 'Checked in']]" :key="f[0]">
                            <button type="button" @click="filter = f[0]"
                                    class="whitespace-nowrap rounded-md px-2.5 py-1.5 font-medium transition sm:px-3"
                                    :class="filter === f[0] ? 'bg-ggit-purple text-white' : 'text-gray-600 hover:text-ggit-ink'">
                                <span x-text="f[1]"></span>
                                <span class="ml-0.5 tabular-nums opacity-70"
                                      x-text="f[0] === 'all' ? stats.total : (f[0] === 'in' ? stats.in : stats.total - stats.in)"></span>
                            </button>
                        </template>
                    </div>
                    <p class="ml-auto hidden text-xs text-gray-500 sm:block" x-show="q.trim()" x-cloak
                       x-text="`${matches.length} match${matches.length === 1 ? '' : 'es'}`"></p>
                </div>
            </div>

            {{-- ===== Results ===== --}}
            <div x-show="loading && !people.length" class="space-y-2">
                @for ($i = 0; $i < 6; $i++)
                    <div class="h-20 animate-pulse rounded-xl bg-white/70"></div>
                @endfor
            </div>

            <p x-show="fuzzy" x-cloak class="mb-2 rounded-lg bg-amber-50 px-4 py-2 text-sm text-amber-800 ring-1 ring-amber-200">
                No exact match. Showing similar spellings.
            </p>

            <ul class="space-y-2" role="list">
                <template x-for="(p, i) in results" :key="p.id">
                    <li :id="'row-' + p.id" @mouseenter="active = i"
                        class="flex items-center gap-3 rounded-xl p-3 ring-1 transition sm:gap-4 sm:p-4"
                        :class="[p.in ? 'bg-emerald-50/70' : 'bg-white shadow-sm', i === active && q.trim() ? 'ring-2 ring-ggit-purple' : 'ring-gray-200']">

                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-medium"
                             :class="p.in ? 'bg-emerald-500 text-white' : 'bg-ggit-purple/10 text-ggit-purple'">
                            <span x-show="!p.in" x-text="initials(p.name)"></span>
                            <svg x-show="p.in" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-base font-medium text-ggit-ink" x-html="highlight(p.name)"></p>
                            <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-sm text-gray-500">
                                <span class="rounded-md px-1.5 py-0.5 text-xs font-medium" :class="cats[p.cat]?.badge"
                                      x-text="cats[p.cat]?.label + (p.partner ? ' · ' + p.partner : '')"></span>
                                <span x-show="p.phone" class="tabular-nums" x-text="p.phone"></span>
                                <span x-show="p.email" class="hidden truncate md:inline" x-text="p.email"></span>
                            </div>
                            <p x-show="p.notes" class="mt-1 line-clamp-2 text-xs"
                               :class="importantNote(p) ? 'font-medium text-amber-700' : 'text-gray-400'"
                               x-text="(importantNote(p) ? '⚠ ' : '') + p.notes"></p>
                        </div>

                        <div class="shrink-0 text-right">
                            <button type="button" x-show="!p.in" @click="checkIn(p)" :disabled="p._busy"
                                    class="rounded-lg bg-ggit-purple px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ggit-purple-dark active:scale-95 disabled:opacity-60 sm:px-5">
                                Check in
                            </button>
                            <div x-show="p.in" class="flex flex-col items-end gap-1">
                                <span class="inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                    Checked in <span class="tabular-nums" x-text="time(p.in)"></span>
                                </span>
                                <button type="button" @click="askUndo(p)" class="rounded px-1 text-xs"
                                        :class="p._confirmUndo ? 'bg-red-50 font-medium text-red-600' : 'text-gray-400 underline-offset-2 hover:text-gray-700 hover:underline'"
                                        x-text="p._confirmUndo ? 'Tap again to undo' : 'Undo'"></button>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>

                        {{-- ===== Pagination ===== --}}
            <nav x-show="matches.length" x-cloak aria-label="Pagination"
                 class="mt-4 flex flex-col items-center gap-3 sm:flex-row sm:justify-between">

                <p class="text-sm text-gray-500">
                    Showing <span class="font-medium tabular-nums text-ggit-ink" x-text="pageStart"></span>–<span class="font-medium tabular-nums text-ggit-ink" x-text="pageEnd"></span>
                    of <span class="font-medium tabular-nums text-ggit-ink" x-text="matches.length"></span>
                </p>

                <div x-show="pageCount > 1" class="flex items-center gap-1">
                    <button type="button" @click="goToPage(currentPage - 1)" :disabled="currentPage === 1"
                            class="flex h-9 items-center gap-1 rounded-lg bg-white px-3 text-sm font-medium text-gray-700 ring-1 ring-gray-200 transition hover:ring-ggit-purple disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:ring-gray-200">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7"/></svg>
                        Prev
                    </button>

                    {{-- page numbers: tablet / desktop --}}
                    <div class="hidden items-center gap-1 sm:flex">
                        <template x-for="(n, idx) in pageNumbers()" :key="idx">
                            <button type="button" x-text="n"
                                    @click="n !== '…' && goToPage(n)" :disabled="n === '…'"
                                    :aria-current="n === currentPage ? 'page' : null"
                                    class="h-9 min-w-9 rounded-lg px-2 text-sm font-medium tabular-nums transition"
                                    :class="n === currentPage
                                        ? 'bg-ggit-purple text-white'
                                        : (n === '…' ? 'cursor-default text-gray-400' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:ring-ggit-purple')"></button>
                        </template>
                    </div>

                    {{-- compact counter: phones --}}
                    <span class="px-3 text-sm font-medium tabular-nums text-gray-600 sm:hidden">
                        <span x-text="currentPage"></span> / <span x-text="pageCount"></span>
                    </span>

                    <button type="button" @click="goToPage(currentPage + 1)" :disabled="currentPage === pageCount"
                            class="flex h-9 items-center gap-1 rounded-lg bg-white px-3 text-sm font-medium text-gray-700 ring-1 ring-gray-200 transition hover:ring-ggit-purple disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:ring-gray-200">
                        Next
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
                    </button>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-500">
                    Per page
                    <select x-model.number="perPage"
                            class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm text-ggit-ink outline-none focus:border-ggit-purple focus:ring-2 focus:ring-ggit-purple/15">
                        <template x-for="n in perPageOptions" :key="n">
                            <option :value="n" x-text="n" :selected="n === perPage"></option>
                        </template>
                    </select>
                </label>
            </nav>

            {{-- no match → offer to add --}}
            <div x-show="!loading && q.trim() && !matches.length && people.length" x-cloak
                 class="rounded-xl border-2 border-dashed border-gray-300 bg-white p-8 text-center">
                <p class="text-base font-medium">No one matches “<span x-text="q.trim()"></span>”</p>
                <p class="mt-1 text-sm text-gray-500">Try the surname or the last digits of their phone, or add them as a new participant.</p>
                <button type="button" @click="openAdd(q.trim())"
                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-ggit-blue px-5 py-3 text-sm font-semibold text-white transition hover:bg-ggit-blue-dark">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    Add “<span x-text="q.trim()"></span>”
                </button>
            </div>

            {{-- empty database --}}
            <div x-show="!loading && !people.length" x-cloak class="rounded-xl bg-white p-8 text-center ring-1 ring-gray-200">
                <p class="text-base font-medium">No participants yet</p>
                <p class="mt-1 text-sm text-gray-500">
                    Import a list from <a href="{{ route('data.index') }}" class="font-medium text-ggit-purple underline">Data &amp; Reports</a>,
                    or add people one by one.
                </p>
            </div>

            <p x-show="!loading && !q.trim() && !matches.length && people.length" x-cloak class="py-10 text-center text-gray-500"
               x-text="filter === 'in' ? 'No one checked in yet.' : 'Everyone has arrived!'"></p>
        </main>

        {{-- ===== Sidebar (desktop): scrolls on its own ===== --}}
        <aside class="hidden lg:block">
            <div class="sticky top-[3.75rem] max-h-[calc(100dvh-3.75rem)] overflow-y-auto pb-8 pr-1 pt-5">
                @include('partials.side-panel')
            </div>
        </aside>
    </div>

    @include('partials.add-modal')

    {{-- ===== Toast ===== --}}
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex justify-center px-4 lg:bottom-6" aria-live="polite">
        <template x-if="toastMsg">
            <div class="pointer-events-auto flex w-full max-w-md items-center gap-3 rounded-xl px-4 py-3 text-white shadow-2xl"
                 :class="{ ok: 'bg-emerald-600', warn: 'bg-amber-600', error: 'bg-red-600', info: 'bg-ggit-ink' }[toastMsg.type]">
                <p class="flex-1 text-sm" x-text="toastMsg.text"></p>
                <button type="button" x-show="toastMsg.undoId" @click="undo(toastMsg.undoId); toastMsg = null"
                        class="rounded-md bg-white/20 px-3 py-1.5 text-sm font-medium transition hover:bg-white/30">Undo</button>
            </div>
        </template>
    </div>
</div>
@endsection