{{-- Phone / tablet menu. Needs `menuOpen` in the page's x-data. --}}
@php
    $items = [
        ['route' => 'checkin', 'label' => 'Check-in', 'hint' => 'Search and check people in',
         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.5 11 14.5 15.5 10M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>'],
        ['route' => 'summary', 'label' => 'Summary', 'hint' => 'Arrivals by category and partner',
         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10m6 10V4m6 16v-7m4 7H2"/>'],
        ['route' => 'data.index', 'label' => 'Reports', 'hint' => 'Download Excel, import, reset data',
         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M3 12h18M3 19h18M9 5v14"/>'],
    ];
@endphp

<div x-show="menuOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" @keydown.escape.window="menuOpen = false">
    <div class="absolute inset-0 bg-black/40" @click="menuOpen = false"></div>

    <aside x-show="menuOpen"
           x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
           class="absolute inset-y-0 left-0 flex w-[86%] max-w-sm flex-col bg-gray-50 shadow-2xl"
           role="dialog" aria-modal="true" aria-label="Menu">

        <div class="flex h-14 items-center justify-between bg-ggit-ink px-4 text-white">
            <div>
                <p class="text-sm font-medium leading-tight">{{ config('checkin.event_name') }}</p>
                <p class="text-xs leading-tight text-white/60">Check-in desk</p>
            </div>
            <button type="button" @click="menuOpen = false" aria-label="Close menu"
                    class="rounded-lg p-2 text-white/70 transition hover:bg-white/10 hover:text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="h-1 bg-ggit-purple"></div>

        <nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="Main menu">
            @foreach ($items as $item)
                @php($current = request()->routeIs($item['route']))
                <a href="{{ $current ? '#' : route($item['route']) }}"
                   @if ($current) aria-current="page" @click.prevent="menuOpen = false" @endif
                   @class([
                       'flex items-center gap-3 rounded-xl px-4 py-3.5 transition',
                       'bg-ggit-purple/10 text-ggit-purple' => $current,
                       'text-ggit-ink hover:bg-white active:bg-white' => ! $current,
                   ])>
                    <svg @class(['h-5 w-5 shrink-0', 'text-gray-500' => ! $current]) fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                    <span class="min-w-0 flex-1">
                        <span class="block text-base font-medium">{{ $item['label'] }}</span>
                        <span @class(['block text-xs', 'text-ggit-purple/70' => $current, 'text-gray-500' => ! $current])>{{ $item['hint'] }}</span>
                    </span>
                    @unless ($current)
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
                    @endunless
                </a>
            @endforeach
        </nav>

        <div class="border-t border-gray-200 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            @include('partials.logout-button', ['block' => true])
        </div>
    </aside>
</div>