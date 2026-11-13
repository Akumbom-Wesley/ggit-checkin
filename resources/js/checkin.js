// GGIT check-in screen (Alpine.js component)
// The whole guest list lives in the browser, so search never waits on the network.
// Other desks' check-ins arrive through a light poll every few seconds.

export const CATEGORIES = {
    registered: { label: 'Registered', badge: 'bg-ggit-blue/10 text-ggit-blue', bar: 'bg-ggit-blue' },
    partner: { label: 'Partner / Sponsor', badge: 'bg-ggit-purple/10 text-ggit-purple', bar: 'bg-ggit-purple' },
    invited: { label: 'Invited guest', badge: 'bg-amber-100 text-amber-800', bar: 'bg-amber-500' },
    walkin: { label: 'Walk-in', badge: 'bg-emerald-100 text-emerald-800', bar: 'bg-emerald-500' },
};

const PER_PAGE_OPTIONS = [25, 50, 100];
const PER_PAGE_KEY = 'ggit-checkin-per-page';

// Each device remembers its own page size.
function savedPerPage() {
    try {
        const n = parseInt(localStorage.getItem(PER_PAGE_KEY), 10);
        return PER_PAGE_OPTIONS.includes(n) ? n : 25;
    } catch (e) {
        return 25;
    }
}
const CACHE_KEY = 'ggit-checkin-cache-v1';
const IMPORTANT_NOTE = /(eat|food|allerg|chicken|meat|diet|vegetar|halal|volunteer|disab|wheelchair|medic|pregnan)/i;

// ---------- text helpers ----------
const fold = (s) => (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const words = (s) => fold(s).replace(/[^a-z0-9]+/g, ' ').trim();
const digits = (s) => (s || '').replace(/\D/g, '');
const esc = (s) => (s || '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

function lev(a, b, max) {
    if (Math.abs(a.length - b.length) > max) return max + 1;
    let prev = Array.from({ length: b.length + 1 }, (_, i) => i);
    for (let i = 1; i <= a.length; i++) {
        const cur = [i];
        let rowMin = i;
        for (let j = 1; j <= b.length; j++) {
            cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
            rowMin = Math.min(rowMin, cur[j]);
        }
        if (rowMin > max) return max + 1;
        prev = cur;
    }
    return prev[b.length];
}

// typo-tolerant: "mireile" ~ "mireille", "shila" ~ "shilla"
function closeToken(q, tokens) {
    if (q.length < 3) return tokens.some((t) => t.startsWith(q));
    const max = q.length >= 6 ? 2 : 1;
    return tokens.some((t) => lev(q, t.slice(0, q.length), max) <= max || lev(q, t, max) <= max);
}

function indexPerson(p) {
    p._name = words(p.name);
    p._tokens = p._name.split(' ');
    p._phone = digits(p.phone);
    p._email = fold(p.email);
    p._partner = words(p.partner);
    p._busy = false;
    p._confirmUndo = false;
    p._localAt = p._localAt || 0; // when this device last changed this person
    return p;
}

export default function checkin(config = {}) {
    let memo = { key: null };

    return {
        people: [],
        version: 0, // bumped whenever the list changes, so search re-runs
        byId: new Map(),
        q: '',
        filter: 'all', // all | waiting | in
        active: 0,
        page: 1,
        perPage: savedPerPage(),
        perPageOptions: PER_PAGE_OPTIONS,
        loading: true,
        online: true,
        lastSync: null,
        since: null,
        dataset: null,
        menuOpen: false,
        syncing: false,
        toastMsg: null,
        toastTimer: null,
        syncTimer: null,
        cats: CATEGORIES,
        tz: config.tz || 'Africa/Douala',
        syncMs: config.syncMs || 3000,

        async init() {
            // Show the last known list instantly (if any), then refresh from the server.
            try {
                const cached = JSON.parse(localStorage.getItem(CACHE_KEY) || 'null');
                if (cached?.participants) this.setAll(cached.participants);
            } catch (e) { /* storage unavailable */ }

            await this.loadAll();
            this.syncTimer = setInterval(() => this.sync(), this.syncMs);
            document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && this.sync());
            window.addEventListener('online', () => this.sync());
            window.addEventListener('keydown', (e) => {
                if (e.key === '/' && document.activeElement !== this.$refs.search && !e.target.closest('input,textarea,select')) {
                    e.preventDefault();
                    this.focusSearch();
                }
            });
                        this.$watch('q', () => { this.active = 0; this.page = 1; });
            this.$watch('filter', () => { this.active = 0; this.page = 1; });
            this.$watch('perPage', (value) => {
                this.page = 1;
                this.active = 0;
                try { localStorage.setItem(PER_PAGE_KEY, value); } catch (e) { /* ignore */ }
            });
        },

        // ---------- data ----------
        setAll(list) {
            this.byId = new Map();
            this.people = list.map((p) => indexPerson({ ...p }));
            this.people.forEach((p, i) => this.byId.set(p.id, i));
            this.version++;
        },

        // `startedAt`: when the sync request was sent. Data fetched before a local change is stale for that person.
        apply(list, startedAt = Infinity) {
            for (const raw of list) {
                const i = this.byId.get(raw.id);
                if (i === undefined) {
                    this.people.push(indexPerson({ ...raw }));
                    this.byId.set(raw.id, this.people.length - 1);
                } else {
                    const p = this.people[i];
                    if (p._busy || p._localAt > startedAt) continue; // our own newer change wins
                    if (p.name !== raw.name || p.phone !== raw.phone || p.email !== raw.email || p.partner !== raw.partner) {
                        Object.assign(p, raw);
                        indexPerson(p);
                    } else {
                        p.in = raw.in;
                        p.cat = raw.cat;
                        p.notes = raw.notes;
                    }
                }
            }
            if (list.length) this.version++;
        },

        async loadAll() {
            try {
                const r = await this.request('GET', '/api/participants');
                this.setAll(r.data.participants);
                this.since = r.data.now;
                this.dataset = r.data.dataset;
                this.markOnline();
                this.saveCache();
            } catch (e) {
                this.online = false;
                if (!this.people.length) this.toast('error', "Couldn't load the guest list. Check the connection; retrying…");
                setTimeout(() => this.loadAll(), 4000);
                return;
            } finally {
                this.loading = false;
            }
        },

        async sync() {
            if (!this.since || document.visibilityState === 'hidden' || this.syncing) return;
            this.syncing = true;
            const startedAt = Date.now();
            try {
                const r = await this.request('GET', '/api/participants/changes?since=' + encodeURIComponent(this.since));
                if (r.data.dataset && this.dataset && r.data.dataset !== this.dataset) {
                    await this.loadAll(); // list was replaced on Data & Reports: reload everything
                    return;
                }
                this.apply(r.data.participants, startedAt);
                this.since = r.data.now;
                this.markOnline();
                if (r.data.participants.length) this.saveCache();
            } catch (e) {
                this.online = false;
            } finally {
                this.syncing = false;
            }
        },

        markOnline() {
            this.online = true;
            this.lastSync = new Date();
        },

        saveCache() {
            try {
                const slim = this.people.map(({ id, name, email, phone, cat, partner, notes, in: inn }) => ({ id, name, email, phone, cat, partner, notes, in: inn }));
                localStorage.setItem(CACHE_KEY, JSON.stringify({ participants: slim }));
            } catch (e) { /* ignore */ }
        },

        async request(method, url, body) {
            const res = await fetch(url, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: body ? JSON.stringify(body) : undefined,
            });
            if (res.status === 401 || res.status === 419) {
                window.location.href = '/login';
                throw new Error('signed out');
            }
            const data = await res.json().catch(() => ({}));
            if (res.status >= 500) throw new Error('server error');
            return { status: res.status, data };
        },

        // ---------- search ----------
        // Memoised per (query, filter, data version) so the template can read it many times per render.
        get search() {
            const key = this.q + '\u0000' + this.filter + '\u0000' + this.version;
            if (memo.key !== key) memo = { key, ...this.runSearch() };
            return memo;
        },
        get matches() { return this.search.list; },
        get fuzzy() { return this.search.fuzzy; },
        
                // ---------- pagination ----------
        get pageCount() { return Math.max(1, Math.ceil(this.matches.length / this.perPage)); },
        get currentPage() { return Math.min(Math.max(1, this.page), this.pageCount); },
        get pageStart() { return this.matches.length ? (this.currentPage - 1) * this.perPage + 1 : 0; },
        get pageEnd() { return Math.min(this.currentPage * this.perPage, this.matches.length); },
        get results() {
            const from = (this.currentPage - 1) * this.perPage;
            return this.matches.slice(from, from + this.perPage);
        },

        runSearch() {
            const raw = this.q.trim();
            const qn = words(raw);
            let list;
            let fuzzy = false;

            if (!qn) {
                list = [...this.people].sort((a, b) => (!!a.in - !!b.in) || a._name.localeCompare(b._name));
            } else {
                const qd = digits(raw);
                const isPhone = /^[\d\s+()\-]+$/.test(raw) && qd.length >= 3;
                const qLocal = qd.replace(/^(00)?237/, ''); // "+237 675..." also finds "675..."
                const qt = qn.split(' ');
                const qEmail = fold(raw);
                const scored = [];

                for (const p of this.people) {
                    let s = 0;
                    if (isPhone) {
                        if (p._phone.includes(qd) || (qLocal.length >= 3 && p._phone.includes(qLocal))) s = 80;
                    } else if (p._name.startsWith(qn)) s = 100;
                    else if (qt.every((t) => p._tokens.some((x) => x.startsWith(t)))) s = p._tokens[0].startsWith(qt[0]) ? 92 : 90;
                    else if (p._name.includes(qn)) s = 60;
                    else if (qEmail.length >= 3 && p._email.includes(qEmail)) s = 50;
                    else if (qn.length >= 3 && p._partner.includes(qn)) s = 40;
                    if (s) scored.push([s, p]);
                }

                // Nothing exact: fall back to typo-tolerant matching.
                if (!scored.length && !isPhone) {
                    for (const p of this.people) {
                        if (qt.every((t) => closeToken(t, p._tokens))) scored.push([10, p]);
                    }
                    fuzzy = scored.length > 0;
                }

                scored.sort((a, b) => b[0] - a[0] || (!!a[1].in - !!b[1].in) || a[1]._name.localeCompare(b[1]._name));
                list = scored.map((x) => x[1]);
            }

            if (this.filter === 'waiting') list = list.filter((p) => !p.in);
            if (this.filter === 'in') list = list.filter((p) => p.in);
            return { list, fuzzy };
        },

        highlight(name) {
            const qt = words(this.q).split(' ').filter(Boolean);
            if (!qt.length || this.fuzzy) return esc(name);
            return name
                .split(/([^\p{L}\p{N}]+)/u)
                .map((part) => {
                    const w = words(part);
                    const t = qt.filter((t) => w.startsWith(t)).sort((a, b) => b.length - a.length)[0];
                    if (!t || w.length !== part.length) return esc(part);
                    return `<mark class="rounded bg-ggit-purple/15 px-0.5 text-ggit-purple">${esc(part.slice(0, t.length))}</mark>${esc(part.slice(t.length))}`;
                })
                .join('');
        },

        // ---------- stats ----------
        get stats() {
            const s = { total: 0, in: 0, cats: {} };
            for (const k of Object.keys(CATEGORIES)) s.cats[k] = { total: 0, in: 0 };
            for (const p of this.people) {
                s.total++;
                const c = (s.cats[p.cat] ||= { total: 0, in: 0 });
                c.total++;
                if (p.in) { s.in++; c.in++; }
            }
            s.pct = s.total ? Math.round((s.in / s.total) * 100) : 0;
            return s;
        },

        pct(n, d) {
            return d ? Math.round((n / d) * 100) : 0;
        },

        // ---------- actions ----------
        async checkIn(p) {
            if (!p || p.in || p._busy) return;
            p._busy = true;
            p._localAt = Date.now();
            this.dismissToast(); // never leave the previous person's Undo on screen
            p.in = new Date().toISOString(); // optimistic: the button turns green instantly
            this.version++;
            try {
                const r = await this.request('POST', `/api/participants/${p.id}/checkin`);
                p.in = r.data.participant.in;
                this.version++;
                if (r.status === 200) {
                    this.toast('ok', `${p.name} checked in`, p.id);
                    this.clearSearch();
                } else {
                    this.toast('warn', `${p.name} was already checked in at ${this.time(p.in)}`);
                }
                this.saveCache();
            } catch (e) {
                p.in = null;
                this.version++;
                this.toast('error', `Couldn't check in ${p.name}. Check the connection and tap again.`);
            } finally {
                p._busy = false;
                p._localAt = Date.now();
            }
        },

        async undo(id) {
            const p = this.people[this.byId.get(id)];
            if (!p || p._busy) return;
            p._busy = true;
            p._localAt = Date.now();
            const prev = p.in;
            p.in = null;
            this.version++;
            try {
                await this.request('POST', `/api/participants/${p.id}/undo`);
                this.toast('info', `Check-in undone for ${p.name}`);
                this.saveCache();
            } catch (e) {
                p.in = prev;
                this.version++;
                this.toast('error', `Couldn't undo. Check the connection.`);
            } finally {
                p._busy = false;
                p._confirmUndo = false;
                p._localAt = Date.now();
            }
        },

        askUndo(p) {
            if (p._confirmUndo) return this.undo(p.id);
            p._confirmUndo = true;
            setTimeout(() => (p._confirmUndo = false), 3000);
        },

                goToPage(n) {
            const target = Math.min(Math.max(1, n), this.pageCount);
            if (target === this.currentPage) return;
            this.page = target;
            this.active = 0;
            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        },

        // e.g. [1, '…', 4, 5, 6, '…', 12]
        pageNumbers() {
            const total = this.pageCount;
            const current = this.currentPage;
            if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);

            const pages = [1];
            const start = Math.max(2, current - 1);
            const end = Math.min(total - 1, current + 1);
            if (start > 2) pages.push('…');
            for (let i = start; i <= end; i++) pages.push(i);
            if (end < total - 1) pages.push('…');
            pages.push(total);
            return pages;
        },
        
        // ---------- keyboard ----------
        move(d) {
            const n = this.results.length;
            if (!n) return;
            this.active = (this.active + d + n) % n;
            this.$nextTick(() => document.getElementById('row-' + this.results[this.active]?.id)?.scrollIntoView({ block: 'nearest' }));
        },

        enter() {
            const p = this.results[this.active];
            if (p && !p.in) this.checkIn(p);
            else if (!this.results.length && this.q.trim()) this.openAdd(this.q.trim());
        },

        clearSearch() {
            this.q = '';
            this.focusSearch();
        },

        focusSearch() {
            this.$nextTick(() => this.$refs.search?.focus());
        },

        // ---------- ui helpers ----------
        time(iso) {
            if (!iso) return '';
            return new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: this.tz });
        },

        initials(name) {
            const w = (name || '').trim().split(/\s+/);
            return ((w[0]?.[0] || '') + (w.length > 1 ? w[w.length - 1][0] : '')).toUpperCase();
        },

        importantNote(p) {
            return p.notes && IMPORTANT_NOTE.test(p.notes);
        },

        dismissToast() {
            clearTimeout(this.toastTimer);
            this.toastMsg = null;
        },

        toast(type, text, undoId = null) {
            clearTimeout(this.toastTimer);
            this.toastMsg = { type, text, undoId, key: Date.now() };
            this.toastTimer = setTimeout(() => (this.toastMsg = null), undoId ? 6000 : 4000);
        },

        get syncLabel() {
            if (!this.online) return 'Offline. Retrying…';
            return 'Live';
        },
    };
}