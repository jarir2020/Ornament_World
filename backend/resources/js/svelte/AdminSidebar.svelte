<script>
    export let active = 'dashboard';

    const links = [
        { key: 'dashboard', label: 'Dashboard', href: '/admin', description: 'Overview and alerts' },
        { key: 'orders', label: 'Orders', href: '/admin#orders', description: 'Search and manage orders' },
        { key: 'products', label: 'Products', href: '/admin#products', description: 'Catalog and inventory' },
        { key: 'categories', label: 'Categories', href: '/admin#categories', description: 'Organize the catalog' },
        { key: 'shipments', label: 'Shipments', href: '/admin#shipment-queue', description: 'Courier queue and recovery' },
        { key: 'fraud', label: 'Fraud review', href: '/admin#fraud-review', description: 'Review order signals' },
    ];

    function logout() {
        window.dispatchEvent(new CustomEvent('ornaments:logout'));
    }
</script>

<aside class="h-fit rounded-2xl border border-white/10 bg-[#111111] p-4 lg:sticky lg:top-6" aria-label="Admin sidebar">
    <div class="flex items-center gap-3 border-b border-white/10 px-2 pb-5">
        <span class="grid size-10 place-items-center rounded-full border border-amber-300/50 text-lg text-amber-300" aria-hidden="true">O</span>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-200">Ornaments</p>
            <p class="mt-1 text-xs text-stone-500">Admin workspace</p>
        </div>
    </div>

    <nav class="mt-4 space-y-1" aria-label="Admin navigation">
        {#each links as link}
            <a
                href={link.href}
                aria-current={link.key === active ? 'page' : undefined}
                class={`group flex items-center justify-between rounded-xl px-3 py-3 transition ${link.key === active ? 'bg-amber-300 text-black' : 'text-stone-300 hover:bg-white/[0.06] hover:text-amber-100'}`}
            >
                <span>
                    <span class="block text-sm font-medium">{link.label}</span>
                    <span class={`mt-1 block text-[11px] ${link.key === active ? 'text-black/60' : 'text-stone-600 group-hover:text-stone-400'}`}>{link.description}</span>
                </span>
                <span class="text-lg" aria-hidden="true">→</span>
            </a>
        {/each}
    </nav>

    <div class="mt-5 border-t border-white/10 pt-4">
        <a class="flex items-center justify-between rounded-xl px-3 py-3 text-sm text-stone-300 transition hover:bg-white/[0.06] hover:text-amber-100" href="/storefront">
            <span>View storefront</span><span aria-hidden="true">↗</span>
        </a>
        <button class="mt-1 flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-sm text-stone-500 transition hover:bg-rose-300/10 hover:text-rose-200" type="button" on:click={logout}>
            <span>Sign out</span><span aria-hidden="true">↪</span>
        </button>
    </div>
</aside>
