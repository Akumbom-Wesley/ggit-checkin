<div class="space-y-4">
    {{-- primary action --}}
    <button type="button" @click="menuOpen = false; openAdd()"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-ggit-blue py-3.5 text-base font-semibold text-white shadow-sm transition hover:bg-ggit-blue-dark active:scale-[.99]">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
        Add participant
    </button>

    {{-- arrivals summary --}}
    @include('partials.stats')



    {{-- connection --}}
    <p class="flex items-center gap-1.5 px-1 text-xs text-gray-500">
        <span class="h-2 w-2 rounded-full" :class="online ? 'bg-emerald-500' : 'animate-pulse bg-red-500'"></span>
        <span x-text="online ? 'Live' : 'Offline. Retrying…'"></span>
        <span x-show="online && lastSync" x-text="'· synced ' + (lastSync ? lastSync.toLocaleTimeString('en-GB') : '')"></span>
    </p>

    <p class="hidden px-1 text-xs leading-relaxed text-gray-400 lg:block">
        Keyboard: <kbd class="rounded bg-white px-1.5 py-0.5 ring-1 ring-gray-300">/</kbd> search ·
        <kbd class="rounded bg-white px-1.5 py-0.5 ring-1 ring-gray-300">↑</kbd><kbd class="rounded bg-white px-1.5 py-0.5 ring-1 ring-gray-300">↓</kbd> choose ·
        <kbd class="rounded bg-white px-1.5 py-0.5 ring-1 ring-gray-300">Enter</kbd> check in
    </p>

</div>