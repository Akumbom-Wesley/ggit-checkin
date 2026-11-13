// "Add participant" form: walk-ins, speakers, partner/sponsor guests, invited guests.
// Mixed into the check-in component (see app.js), so it can use this.people, this.apply(), this.toast(), this.pricing…

export const ADD_CATEGORIES = [
    { key: 'walkin', label: 'Walk-in' },
    { key: 'speaker', label: 'Speaker' },
    { key: 'partner', label: 'Partner / Sponsor' },
    { key: 'invited', label: 'Invited guest' },
    { key: 'registered', label: 'Registered (missing)' },
];

const phoneKey = (phone) => {
    const digits = (phone || '').replace(/\D/g, '');
    return digits ? digits.slice(-9) : '';
};

export default function addParticipant() {
    return {
        addCats: ADD_CATEGORIES,
        add: { open: false, saving: false, form: {}, errors: {}, matches: [], amountTouched: false },
        addSticky: {}, // remembers category + partner between adds (partners often send groups)

        /** Ticket price for paying categories (walk-ins), 0 for everyone else. */
        defaultAmount(category) {
            return this.pricing.paying.includes(category) ? this.pricing.ticket : 0;
        },

        blankForm() {
            const category = this.addSticky.category || 'walkin';
            return {
                category,
                partner_name: this.addSticky.partner_name || '',
                full_name: '',
                phone: '',
                email: '',
                notes: '',
                amount: this.defaultAmount(category),
                check_in: true,
            };
        },

        openAdd(prefill = '') {
            const form = this.blankForm();
            const text = typeof prefill === 'string' ? prefill.trim() : '';
            if (/@/.test(text)) form.email = text;
            else if (/^[\d\s+()\-]{3,}$/.test(text)) form.phone = text;
            else form.full_name = text;

            let focus = 'add-name';
            if (form.category === 'partner' && !form.partner_name) focus = 'add-partner';
            else if (form.full_name) focus = 'add-phone';

            this.add = { open: true, saving: false, form, errors: {}, matches: [], amountTouched: false };
            this.$nextTick(() => document.getElementById(focus)?.focus());
        },

        closeAdd() {
            this.add.open = false;
            this.focusSearch();
        },

        setCategory(key) {
            this.add.form.category = key;
            delete this.add.errors.partner_name;
            // Follow the category's price unless staff typed their own amount.
            if (!this.add.amountTouched) this.add.form.amount = this.defaultAmount(key);
            if (key === 'partner') this.$nextTick(() => document.getElementById('add-partner')?.focus());
        },

        partnerNames() {
            const names = new Set();
            for (const p of this.people) if (p.partner) names.add(p.partner);
            return [...names].sort((a, b) => a.localeCompare(b));
        },

        validateAdd() {
            const f = this.add.form;
            const e = {};
            if (f.full_name.trim().length < 2) e.full_name = 'Enter their full name.';
            if (!f.phone.trim()) e.phone = 'Phone number is required.';
            else if (!/^[\d\s+()\-]{8,}$/.test(f.phone.trim())) e.phone = 'Enter a valid phone number.';
            if (!f.email.trim()) e.email = 'Email is required.';
            else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(f.email.trim())) e.email = 'Enter a valid email address.';
            if (f.category === 'partner' && !f.partner_name.trim()) e.partner_name = 'Which partner or sponsor sent them?';
            if (f.amount !== '' && f.amount !== null && (!Number.isInteger(Number(f.amount)) || Number(f.amount) < 0)) {
                e.amount = 'Enter the amount as a whole number (0 if free).';
            }
            this.add.errors = e;
            return Object.keys(e).length === 0;
        },

        // ---------- duplicates ----------

        /** Instant check against the list already on this device. */
        findDuplicates(form) {
            const key = phoneKey(form.phone);
            const email = form.email.trim().toLowerCase();
            const found = [];
            for (const p of this.people) {
                const matchedOn = [];
                if (key && phoneKey(p.phone) === key) matchedOn.push('phone');
                if (email && (p.email || '').toLowerCase() === email) matchedOn.push('email');
                if (matchedOn.length) found.push({ id: p.id, matched_on: matchedOn });
                if (found.length === 5) break;
            }
            return found;
        },

        /** Show the warning at the top of the form and mark the clashing fields. */
        showDuplicates(list) {
            this.add.matches = list;
            const first = this.duplicateMatches()[0];
            const who = first ? first.name : 'someone on the list';
            if (list.some((m) => m.matched_on.includes('phone'))) this.add.errors.phone = `Already used by ${who}`;
            if (list.some((m) => m.matched_on.includes('email'))) this.add.errors.email = `Already used by ${who}`;
            this.$nextTick(() => this.$refs.addBody?.scrollTo({ top: 0, behavior: 'smooth' }));
        },

        dismissDuplicates() {
            this.add.matches = [];
            delete this.add.errors.phone;
            delete this.add.errors.email;
            this.$nextTick(() => document.getElementById('add-phone')?.focus());
        },

        /** Existing records (live, so their check-in status is current) + what matched. */
        duplicateMatches() {
            return this.add.matches
                .map((m) => {
                    const person = this.people[this.byId.get(m.id)];
                    return person ? { ...person, matched_on: m.matched_on || [] } : null;
                })
                .filter(Boolean);
        },

        matchReason(m) {
            const phone = m.matched_on.includes('phone');
            const email = m.matched_on.includes('email');
            if (phone && email) return 'Same phone number and email';
            if (phone) return 'Same phone number';
            if (email) return 'Same email address';
            return 'Possible match';
        },

        checkInExisting(m) {
            const p = this.people[this.byId.get(m.id)];
            this.add.open = false;
            if (!p) return;
            if (p.in) {
                this.toast('warn', `${p.name} is already checked in (${this.time(p.in)})`);
                this.focusSearch();
            } else {
                this.checkIn(p);
            }
        },

        // ---------- save ----------

        async saveAdd(force = false) {
            if (this.add.saving || !this.validateAdd()) return;
            const f = this.add.form;

            // 1. Instant check on this device
            if (!force) {
                const local = this.findDuplicates(f);
                if (local.length) {
                    this.showDuplicates(local);
                    return;
                }
            }

            // 2. Save (the server checks again, in case another desk just added them)
            this.add.saving = true;
            this.add.matches = [];
            const payload = { ...f, amount: f.amount === '' || f.amount === null ? null : Number(f.amount), force };

            try {
                const r = await this.request('POST', '/api/participants', payload);

                if (r.status === 201) {
                    const p = r.data.participant;
                    this.apply([p]);
                    this.addSticky = { category: f.category, partner_name: f.category === 'partner' ? f.partner_name.trim() : '' };
                    this.add.open = false;
                    this.q = '';
                    this.saveCache();
                    const paid = p.amount > 0 ? ` · ${this.money(p.amount)}` : '';
                    this.toast('ok', (p.in ? `${p.name} added and checked in` : `${p.name} added`) + paid, p.in ? p.id : null);
                    this.focusSearch();
                } else if (r.status === 409 && r.data.message === 'possible_duplicate') {
                    this.apply(r.data.matches.map(({ matched_on, ...m }) => m)); // latest data for those people
                    this.showDuplicates(r.data.matches.map((m) => ({ id: m.id, matched_on: m.matched_on || [] })));
                } else if (r.status === 422) {
                    const e = {};
                    for (const [k, v] of Object.entries(r.data.errors || {})) e[k] = v[0];
                    this.add.errors = e;
                    this.toast('error', 'Please fix the highlighted fields.');
                } else {
                    this.toast('error', r.data.message || 'Could not add this person. Please try again.');
                }
            } catch (e) {
                this.toast('error', "Couldn't reach the server. Check the connection and try again.");
            } finally {
                this.add.saving = false;
            }
        },
    };
}