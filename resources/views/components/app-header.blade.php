{{--
    Shared top bar. Needs `menuOpen` in the page's x-data (for the drawer button).
    Props:  title = small line under the event name.
    Slots:  default = extra buttons on the right, `bar` = replaces the purple strip under the header.
--}}
@props(['title' => 'Check-in desk'])

@php
    $onCheckin = request()->routeIs('checkin');
    $links = ['checkin' => 'Check-in', 'summary' => 'Summary', 'data.index' => 'Data & Reports'];
@endphp

<header class="sticky top-0 z-30 bg-ggit-ink text-white shadow-md">
    <div class="mx-auto flex h-14 max-w-6xl items-center gap-1 px-3 sm:gap-2 sm:px-4">
        {{-- drawer (phones / tablets) --}}
        <button type="button" @click="menuOpen = true" aria-label="Open menu"
                class="-ml-1 rounded-lg p-2 text-white/90 transition hover:bg-white/10 lg:hidden">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>

        {{-- back (every page except check-in) --}}
        @unless ($onCheckin)
            <a href="{{ route('checkin') }}" aria-label="Back to check-in"
               class="flex items-center gap-1 rounded-lg p-2 text-sm font-medium text-white/90 transition hover:bg-white/10">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7"/></svg>
                <span class="hidden sm:inline">Back</span>
            </a>
        @endunless

        <img src="{{ asset('img/ggi-logo.jpg') }}" alt="Growing Girls Initiative" class="ml-1 h-6 w-auto sm:h-7">
        <div class="ml-2 hidden border-l border-white/20 pl-3 md:block">
            <p class="text-sm font-medium leading-tight">{{ config('checkin.event_name') }}</p>
            <p class="text-xs leading-tight text-white/60">{{ $title }}</p>
        </div>

        <div class="ml-auto flex items-center gap-2">
            {{ $slot }}

            {{-- desktop navigation --}}
            <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
                @foreach ($links as $route => $label)
                    <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif
                       @class([
                           'rounded-lg px-3 py-2 text-sm font-medium transition',
                           'bg-white/15 text-white' => request()->routeIs($route),
                           'text-white/75 hover:bg-white/10 hover:text-white' => ! request()->routeIs($route),
                       ])>{{ $label }}</a>
                @endforeach
            </nav>

            @include('partials.logout-button', ['class' => 'hidden lg:block'])
        </div>
    </div>

    @isset($bar)
        {{ $bar }}
    @else
        <div class="h-1 bg-ggit-purple"></div>
    @endisset
</header>