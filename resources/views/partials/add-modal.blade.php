{{-- Add participant: bottom sheet on phones, centred dialog on larger screens --}}
<div x-show="add.open" x-cloak x-transition.opacity
     class="fixed inset-0 z-40 flex items-end justify-center sm:items-center sm:p-4"
     @keydown.escape.window="add.open && closeAdd()">

    <div class="absolute inset-0 bg-black/50" @click="closeAdd()"></div>

    <form @submit.prevent="saveAdd()" novalidate role="dialog" aria-modal="true" aria-labelledby="add-title"
          class="relative flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:max-w-lg sm:rounded-3xl">

        {{-- header --}}
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <h2 id="add-title" class="text-lg font-bold text-ggit-purple">Add participant</h2>
            <button type="button" @click="closeAdd()" aria-label="Close"
                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- body --}}
        <div class="space-y-4 overflow-y-auto px-5 py-4">

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
            <div x-show="add.form.category === 'partner'">
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
                    Ticket price: <span x-text="money(pricing.ticket)"></span>. Use 0 for free entry (speakers, invited guests, already paid).
                </p>
            </div>

            {{-- check in now --}}
            <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-gray-50 px-4 py-3">
                <input type="checkbox" x-model="add.form.check_in" class="h-5 w-5 accent-ggit-purple">
                <span class="text-sm font-medium">Check them in now</span>
            </label>

            {{-- duplicate warning --}}
            <div x-show="add.matches.length" class="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200">
                <p class="text-sm font-semibold text-amber-900">This phone or email is already on the list:</p>
                <ul class="mt-3 space-y-2">
                    <template x-for="m in duplicateMatches()" :key="m.id">
                        <li class="flex items-center gap-3 rounded-xl bg-white p-3 ring-1 ring-amber-200">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold" x-text="m.name"></p>
                                <p class="truncate text-xs text-gray-500"
                                   x-text="[cats[m.cat]?.label, m.phone, m.email].filter(Boolean).join(' · ')"></p>
                            </div>
                            <button type="button" @click="checkInExisting(m)"
                                    class="shrink-0 rounded-lg px-3 py-2 text-sm font-bold"
                                    :class="m.in ? 'bg-emerald-100 text-emerald-800' : 'bg-ggit-purple text-white'"
                                    x-text="m.in ? 'In · ' + time(m.in) : 'Check in'"></button>
                        </li>
                    </template>
                </ul>
                <button type="button" @click="saveAdd(true)" class="mt-3 text-sm font-semibold text-amber-900 underline">
                    No, this is a different person. Add anyway.
                </button>
            </div>
        </div>

        {{-- footer --}}
        <div class="flex gap-3 border-t border-gray-100 px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <button type="button" @click="closeAdd()"
                    class="flex-1 rounded-xl border-2 border-gray-200 py-3 font-semibold text-gray-700 transition hover:bg-gray-50">
                Cancel
            </button>
            <button type="submit" :disabled="add.saving"
                    class="flex-[2] rounded-xl bg-ggit-purple py-3 font-bold text-white transition hover:bg-ggit-purple-dark disabled:opacity-60"
                    x-text="add.saving ? 'Saving…' : (add.form.check_in ? 'Add & check in' : 'Add participant')"></button>
        </div>
    </form>
</div>