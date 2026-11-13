// "Add participant" form: walk-ins, partner/sponsor guests, invited guests.
// Mixed into the check-in component (see app.js), so it can use this.people, this.apply(), this.toast()…

const blankForm = (sticky = {}) => ({
    category: sticky.category || 'walkin',
    partner_name: sticky.partner_name || '',
    full_name: '',
    phone: '',
    email: '',
    notes: '',
    check_in: true,
});

export const ADD_CATEGORIES = [
    { key: 'walkin', label: 'Walk-in' },
    { key: 'partner', label: 'Partner / Sponsor' },
    { key: 'invited', label: 'Invited guest' },
    { key: 'registered', label: 'Registered (missing)' },
];

export default function addParticipant() {
    return {
        addCats: ADD_CATEGORIES,
        add: { open: false, saving: false, form: blankForm(), errors: {}, matches: [] },
        addSticky: {}, // remembers category + partner between adds (partners often send groups)

        openAdd(prefill = '') {
            const form = blankForm(this.addSticky);
            const text = typeof prefill === 'string' ? prefill.trim() : '';
            if (/@/.test(text)) form.email = text;
            else if (/^[\d\s+()\-]{3,}$/.test(text)) form.phone = text;
            else form.full_name = text;

            let focus = 'add-name';
            if (form.category === 'partner' && !form.partner_name) focus = 'add-partner';
            else if (form.full_name) focus = 'add-phone';

            this.add = { open: true, saving: false, form, errors: {}, matches: [] };
            this.$nextTick(() => document.getElementById(focus)?.focus());
        },

        closeAdd() {
            this.add.open = false;
            this.focusSearch();
        },

        setCategory(key) {
            this.add.form.category = key;
            delete this.add.errors.partner_name;
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
            this.add.errors = e;
            return Object.keys(e).length === 0;
        },

        async saveAdd(force = false) {
            if (this.add.saving || !this.validateAdd()) return;
            this.add.saving = true;
            this.add.matches = [];
            const f = this.add.form;
            try {
                const r = await this.request('POST', '/api/participants', { ...f, force });

                if (r.status === 201) {
                    const p = r.data.participant;
                    this.apply([p]);
                    this.addSticky = { category: f.category, partner_name: f.category === 'partner' ? f.partner_name.trim() : '' };
                    this.add.open = false;
                    this.q = '';
                    this.saveCache();
                    this.toast('ok', p.in ? `${p.name} added and checked in` : `${p.name} added`, p.in ? p.id : null);
                    this.focusSearch();
                } else if (r.status === 409 && r.data.message === 'possible_duplicate') {
                    this.apply(r.data.matches); // get their latest check-in status
                    this.add.matches = r.data.matches.map((m) => m.id);
                } else if (r.status === 422) {
                    const e = {};
                    for (const [k, v] of Object.entries(r.data.errors || {})) e[k] = v[0];
                    this.add.errors = e;
                } else {
                    this.toast('error', r.data.message || 'Could not add this person.');
                }
            } catch (e) {
                this.toast('error', "Couldn't reach the server. Check the connection and try again.");
            } finally {
                this.add.saving = false;
            }
        },

        duplicateMatches() {
            return this.add.matches.map((id) => this.people[this.byId.get(id)]).filter(Boolean);
        },

        checkInExisting(p) {
            this.add.open = false;
            if (p.in) {
                this.toast('warn', `${p.name} is already checked in (${this.time(p.in)})`);
                this.focusSearch();
            } else {
                this.checkIn(p);
            }
        },
    };
}