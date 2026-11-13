{{-- Red log-out button. Options: $class (wrapper classes), $block (true = full width) --}}
<form method="POST" action="{{ route('logout') }}" class="{{ $class ?? '' }}">
    @csrf
    <button type="submit"
            class="{{ ($block ?? false) ? 'w-full' : '' }} inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 active:scale-95">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/></svg>
        Log out
    </button>
</form>