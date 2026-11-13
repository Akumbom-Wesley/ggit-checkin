@extends('layouts.app')
@section('title', 'Staff sign-in')

@section('body')
<main class="flex min-h-dvh items-center justify-center bg-white p-4">
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl shadow-gray-300/50 ring-1 ring-gray-200">
        <img src="{{ asset('img/ggit-banner.png') }}"
             alt="{{ config('checkin.event_name') }}: {{ config('checkin.event_theme') }}"
             class="block w-full">

        <form method="POST" action="{{ url('/login') }}" class="p-6 sm:p-8">
            @csrf
            <h1 class="text-2xl font-semibold tracking-tight text-ggit-purple">Check-in desk</h1>
            <p class="mt-1 text-sm text-gray-500">Enter the staff PIN to start checking participants in.</p>

            <label for="pin" class="mt-6 block text-sm font-medium text-ggit-ink">Staff PIN</label>
            <input id="pin" name="pin" type="password" inputmode="numeric" autocomplete="off" autofocus required
                   class="mt-2 w-full rounded-xl border bg-white px-4 py-3.5 text-center text-2xl tracking-[0.5em] outline-none transition
                          focus:border-ggit-purple focus:ring-4 focus:ring-ggit-purple/15
                          @error('pin') border-red-500 @else border-gray-300 @enderror">
            @error('pin')
                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
            @enderror

            <button class="mt-6 w-full rounded-xl bg-ggit-purple py-3.5 text-base font-semibold text-white shadow-sm transition hover:bg-ggit-purple-dark active:scale-[.99]">
                Unlock
            </button>

            <p class="mt-6 text-center text-xs text-gray-400">{{ config('checkin.event_theme') }}</p>
        </form>
    </div>
</main>
@endsection