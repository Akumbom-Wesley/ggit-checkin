{{-- Add participant: bottom sheet on phones, centred dialog on larger screens --}}
<div x-show="add.open" x-cloak x-transition.opacity
     class="fixed inset-0 z-40 flex items-end justify-center sm:items-center sm:p-4"
     @keydown.escape.window="add.open && closeAdd()">

    {{-- backdrop --}}
    <div class="absolute inset-0 bg-black/50" @click="closeAdd()"></div>

    {{-- dialog --}}
    <form @submit.prevent="saveAdd()" novalidate role="dialog" aria-modal="true" aria-labelledby="add-title"
          class="relative flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:max-w-lg sm:rounded-3xl">

        {{-- ===== header ===== --}}
        <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-5 py-4">
            <h2 id="add-title" class="text-lg font-semibold text-ggit-purple">Add participant</h2>
            <button type="button" @click="closeAdd()" aria-label="Close"
                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- ===== body (scrolls) ===== --}}
        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4" x-ref="addBody">

            {{-- possible duplicate: shown at the top so it can't be missed --}}
            <div x-show="add.matches.length" x-cloak role="alert"
                 class="rounded-2xl bg-amber-50 p-4 ring-2 ring-amber-300">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-amber-900">This person may already be on the list</p>
                        <p class="mt-0.5 text-xs text-amber-800">Check before adding a new record.</p>
                    </div>
                </div>

                <ul class="mt-3 space-y-2">
                    <template x-for="m in duplicateMatches()" :key="m.id">
                        <li class="flex items-center gap-3 rounded-xl bg-white p-3 ring-1 ring-amber-200">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold" x-text="m.name"></p>
                                <p class="mt-0.5 text-xs font-medium text-amber-700" x-text="matchReason(m)"></p>
                                <p class="truncate text-xs text-gray-500"
                                   x-text="[cats[m.cat]?.label, m.phone, m.email].filter(Boolean).join(' · ')"></p>
                            </div>
                            <button type="button" @click="checkInExisting(m)"
                                    class="shrink-0 rounded-lg px-3 py-2 text-xs font-semibold transition"
                                    :class="m.in ? 'bg-emerald-100 text-emerald-800' : 'bg-ggit-purple text-white hover:bg-ggit-purple-dark'"
                                    x-text="m.in ? 'Checked in ' + time(m.in) : 'Check in instead'"></button>
                        </li>
                    </template>
                </ul>

                <div class="mt-3 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" @click="dismissDuplicates()"
                            class="rounded-lg border border-amber-300 bg-white px-4 py-2 text-sm font-medium text-amber-900 transition hover:bg-amber-100">
                        Edit details
                    </button>
                    <button type="button" @click="saveAdd(true)" :disabled="add.saving"
                            class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700 disabled:opacity-60"
                            x-text="add.saving ? 'Saving…' : 'Different person, add anyway'"></button>
                </div>
            </div>

            {{-- category --}}
            <div>
                <p class="field-label mb-2">Who are they?</p>
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="c in addCats" :key="c.key">
                        <button type="button" @click="setCategory(c.key)" x-text="c.label"
                                class="rounded-xl border-2 px-3 py-2.5 text-sm font-semibold transition"
                                :class="add.form.category === c.key
                                    ? 'border-ggit-purple bg-ggit-purple/5 text-ggit-purple'
                                    : 'border-gray-200 text-gray-600 hover:border-gray-300'"></button>
                    </template>
                </div>
            </div>

            {{-- partner name (only for partner/sponsor guests) --}}
            <div x-show="add.form.category === 'partner'" x-cloak>
                <label for="add-partner" class="field-label">Partner / Sponsor <span class="text-red-500">*</span></label>
                <input id="add-partner" x-model="add.form.partner_name" list="partner-list" autocomplete="off"
                       placeholder="Which organisation sent them?"
                       @input="delete add.errors.partner_name"
                       class="field" :class="add.errors.partner_name && 'field-invalid'">
                <datalist id="partner-list">
                    <template x-for="n in partnerNames()" :key="n"><option :value="n"></option></template>
                </datalist>
                <p x-show="add.errors.partner_name" class="field-msg" x-text="add.errors.partner_name"></p>
            </div>

            {{-- name --}}
            <div>
                <label for="add-name" class="field-label">Full name <span class="text-red-500">*</span></label>
                <input id="add-name" x-model="add.form.full_name" autocomplete="off" autocapitalize="words"
                       placeholder="e.g. Ngum Grace Ayuk"
                       @input="delete add.errors.full_name"
                       class="field" :class="add.errors.full_name && 'field-invalid'">
                <p x-show="add.errors.full_name" class="field-msg" x-text="add.errors.full_name"></p>
            </div>

            {{-- phone + email --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="add-phone" class="field-label">Phone <span class="text-red-500">*</span></label>
                    <input id="add-phone" x-model="add.form.phone" type="tel" inputmode="tel" autocomplete="off"
                           placeholder="6XX XXX XXX"
                           @input="delete add.errors.phone"
                           class="field" :class="add.errors.phone && 'field-invalid'">
                    <p x-show="add.errors.phone" class="field-msg" x-text="add.errors.phone"></p>
                </div>
                <div>
                    <label for="add-email" class="field-label">Email <span class="text-red-500">*</span></label>
                    <input id="add-email" x-model="add.form.email" type="email" inputmode="email"
                           autocomplete="off" autocapitalize="off" spellcheck="false"
                           placeholder="name@example.com"
                           @input="delete add.errors.email"
                           class="field" :class="add.errors.email && 'field-invalid'">
                    <p x-show="add.errors.email" class="field-msg" x-text="add.errors.email"></p>
                </div>
            </div>

            {{-- notes --}}
            <div>
                <label for="add-notes" class="field-label">Notes <span class="font-normal text-gray-400">(optional)</span></label>
                <textarea id="add-notes" x-model="add.form.notes" rows="2"
                          placeholder="Dietary needs, volunteer, anything staff should know"
                          class="field resize-none"></textarea>
            </div>

            {{-- amount paid --}}
            <div>
                <label for="add-amount" class="field-label">Amount paid</label>
                <div class="relative">
                    <input id="add-amount" x-model="add.form.amount" @input="add.amountTouched = true; delete add.errors.amount"
                           type="number" inputmode="numeric" min="0" step="500"
                           class="field pr-16" :class="add.errors.amount && 'field-invalid'">
                    <span class="pointer-events-none absolute right-4 top-1/2 mt-0.5 -translate-y-1/2 text-sm text-gray-400" x-text="pricing.currency"></span>
                </div>
                <p x-show="add.errors.amount" class="field-msg" x-text="add.errors.amount"></p>
                <p x-show="!add.errors.amount" class="mt-1 text-xs text-gray-500">
                    Door price: <span x-text="money(pricing.ticket)"></span>. Use 0 for free entry (speakers, invited guests, already paid).
                </p>
            </div>

            {{-- check in now --}}
            <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-gray-50 px-4 py-3">
                <input type="checkbox" x-model="add.form.check_in" class="h-5 w-5 accent-ggit-purple">
                <span class="text-sm font-medium">Check them in now</span>
            </label>
        </div>
        {{-- ===== end body ===== --}}

        {{-- ===== footer (always visible) ===== --}}
        <div class="flex shrink-0 gap-3 border-t border-gray-100 bg-white px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <button type="button" @click="closeAdd()"
                    class="flex-1 rounded-xl border-2 border-gray-200 py-3 font-semibold text-gray-700 transition hover:bg-gray-50">
                Cancel
            </button>
            <button type="submit" :disabled="add.saving"
                    class="flex-[2] rounded-xl bg-ggit-purple py-3 font-semibold text-white transition hover:bg-ggit-purple-dark disabled:opacity-60"
                    x-text="add.saving ? 'Saving…' : (add.form.check_in ? 'Add & check in' : 'Add participant')"></button>
        </div>
    </form>
</div>