<section class="rounded-2xl bg-white p-5 ring-1 ring-gray-200">
    <h3 class="text-sm font-medium text-gray-500">Arrivals</h3>
    <p class="mt-1 text-3xl font-semibold tabular-nums text-ggit-ink">
        <span x-text="stats.in">0</span><span class="text-lg font-normal text-gray-400"> of <span x-text="stats.total">0</span></span>
    </p>
    <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
        <div class="h-full rounded-full bg-ggit-purple transition-all duration-500" :style="`width:${stats.pct}%`"></div>
    </div>
    <p class="mt-2 text-xs text-gray-500">
        <span x-text="stats.pct"></span>% arrived · <span x-text="stats.total - stats.in"></span> still expected
    </p>

    <ul class="mt-5 space-y-3 border-t border-gray-100 pt-4">
        <template x-for="(c, key) in cats" :key="key">
            <li>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-700" x-text="c.label"></span>
                    <span class="tabular-nums text-gray-500"><span class="font-medium text-ggit-ink" x-text="stats.cats[key].in"></span> / <span x-text="stats.cats[key].total"></span></span>
                </div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full transition-all duration-500" :class="c.bar" :style="`width:${pct(stats.cats[key].in, stats.cats[key].total)}%`"></div>
                </div>
            </li>
        </template>
    </ul>
</section>