<script>
    export let admin = { categories: [], products: [], orders: [], csrfToken: '' };

    let message = '';
    let error = '';
    let product = {
        name: '',
        slug: '',
        category_id: admin.categories?.[0]?.id ?? '',
        short_description: '',
        description: '',
        base_price: '',
        compare_at_price: '',
        stock_qty: 0,
        is_active: true,
        is_featured: false,
        variant_name: 'Standard',
        sku: '',
    };
    let category = { name: '', slug: '', description: '' };

    function clearFeedback() {
        message = '';
        error = '';
    }

    async function request(url, method, body) {
        clearFeedback();
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': admin.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'The admin action could not be completed.');
        }
        return payload;
    }

    async function createProduct() {
        try {
            await request('/admin/catalog/products', 'POST', product);
            message = 'Product created.';
            window.location.reload();
        } catch (actionError) {
            error = actionError.message;
        }
    }

    async function createCategory() {
        try {
            await request('/admin/catalog/categories', 'POST', category);
            message = 'Category created.';
            window.location.reload();
        } catch (actionError) {
            error = actionError.message;
        }
    }

    async function toggleProduct(item) {
        try {
            await request(`/admin/catalog/products/${item.id}/active`, 'POST', { active: !Boolean(item.is_active) });
            window.location.reload();
        } catch (actionError) {
            error = actionError.message;
        }
    }

    function formatPrice(value) {
        return new Intl.NumberFormat('en-BD', {
            style: 'currency',
            currency: 'BDT',
            maximumFractionDigits: 0,
        }).format(Number(value || 0));
    }
</script>

<svelte:head>
    <title>Catalog admin | Ornaments World</title>
</svelte:head>

<main class="min-h-screen bg-[#080808] px-6 py-10 text-stone-100 sm:px-10 lg:px-12">
    <div class="mx-auto max-w-7xl">
        <div class="flex flex-col justify-between gap-4 border-b border-white/10 pb-8 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Protected workspace</p>
                <h1 class="mt-3 text-4xl font-semibold text-white">Catalog management</h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-stone-400">Manage active catalog records, default variants, prices, and stock. Product deactivation is reversible.</p>
            </div>
            <a class="rounded-full border border-amber-300/40 px-5 py-2 text-sm text-amber-100 hover:bg-amber-200/10" href="/storefront">View storefront</a>
        </div>

        {#if message}<div class="mt-6 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">{message}</div>{/if}
        {#if error}<div class="mt-6 rounded-xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200">{error}</div>{/if}

        <section class="mt-8 overflow-hidden rounded-2xl border border-amber-200/20 bg-amber-200/[0.04]">
            <div class="flex flex-col justify-between gap-2 border-b border-white/10 px-5 py-4 sm:flex-row sm:items-center">
                <div><h2 class="font-medium text-white">New order queue</h2><p class="mt-1 text-xs text-stone-500">Review customer details and call before confirmation or shipment.</p></div>
                <span class="text-sm text-amber-200">{admin.orders?.length ?? 0} waiting</span>
            </div>
            {#if admin.orders?.length}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.15em] text-stone-500"><tr><th class="px-5 py-3">Order</th><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Delivery</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Review</th></tr></thead>
                        <tbody class="divide-y divide-white/10">
                            {#each admin.orders as order}
                                <tr>
                                    <td class="px-5 py-4"><div class="font-medium text-amber-200">{order.reference}</div><div class="mt-1 text-xs text-stone-500">{order.item_count} {order.item_count === 1 ? 'item' : 'items'} · {order.created_at}</div></td>
                                    <td class="px-5 py-4"><div class="font-medium text-white">{order.customer_name}</div><div class="mt-1 text-xs text-stone-400">{order.customer_phone}</div></td>
                                    <td class="px-5 py-4 text-stone-300">{order.subdistrict}, {order.district}</td>
                                    <td class="px-5 py-4 text-amber-200">{formatPrice(order.total)}</td>
                                    <td class="px-5 py-4">{#if order.is_suspicious}<span class="rounded-full bg-rose-300/15 px-2 py-1 text-xs text-rose-200">Flagged · {order.risk_score}</span>{:else}<span class="text-xs text-emerald-300">No flags</span>{/if}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {:else}
                <p class="px-5 py-7 text-sm text-stone-500">No new guest orders are waiting for review.</p>
            {/if}
        </section>

        <section class="mt-8 grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
            <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04]">
                <div class="border-b border-white/10 px-5 py-4">
                    <h2 class="font-medium text-white">Products</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.15em] text-stone-500">
                            <tr><th class="px-5 py-3">Product</th><th class="px-5 py-3">Category</th><th class="px-5 py-3">Price</th><th class="px-5 py-3">Stock</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            {#each admin.products as item}
                                <tr>
                                    <td class="px-5 py-4"><div class="font-medium text-white">{item.name}</div><div class="mt-1 text-xs text-stone-500">{item.slug}</div></td>
                                    <td class="px-5 py-4 text-stone-300">{item.category_name || 'Uncategorized'}</td>
                                    <td class="px-5 py-4 text-amber-200">{formatPrice(item.base_price)}</td>
                                    <td class="px-5 py-4 text-stone-300">{item.stock_qty}</td>
                                    <td class="px-5 py-4"><span class={`rounded-full px-2 py-1 text-xs ${item.is_active ? 'bg-emerald-400/15 text-emerald-300' : 'bg-stone-700 text-stone-400'}`}>{item.is_active ? 'Active' : 'Inactive'}</span></td>
                                    <td class="px-5 py-4 text-right"><button class="text-xs text-amber-300 hover:text-amber-100" type="button" on:click={() => toggleProduct(item)}>{item.is_active ? 'Deactivate' : 'Activate'}</button></td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <form class="rounded-2xl border border-white/10 bg-white/[0.04] p-5" on:submit|preventDefault={createProduct}>
                    <h2 class="font-medium text-white">Add product</h2>
                    <div class="mt-4 grid gap-3">
                        <input bind:value={product.name} required placeholder="Product name" class="rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-amber-300" />
                        <input bind:value={product.slug} placeholder="Slug (optional)" class="rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-amber-300" />
                        <select bind:value={product.category_id} required class="rounded-xl border border-white/10 bg-[#151515] px-4 py-3 text-sm text-white outline-none focus:border-amber-300">
                            {#each admin.categories as item}<option value={item.id}>{item.name}</option>{/each}
                        </select>
                        <div class="grid grid-cols-2 gap-3"><input bind:value={product.base_price} required type="number" min="0" step="0.01" placeholder="Price (BDT)" class="rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-amber-300" /><input bind:value={product.stock_qty} required type="number" min="0" step="1" placeholder="Stock" class="rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-amber-300" /></div>
                        <input bind:value={product.sku} placeholder="SKU (optional)" class="rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-amber-300" />
                        <textarea bind:value={product.short_description} rows="2" placeholder="Short description" class="rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-amber-300"></textarea>
                        <button class="rounded-full bg-amber-300 px-5 py-3 text-sm font-semibold text-black hover:bg-amber-200" type="submit">Create product</button>
                    </div>
                </form>

                <form class="rounded-2xl border border-white/10 bg-white/[0.04] p-5" on:submit|preventDefault={createCategory}>
                    <h2 class="font-medium text-white">Add category</h2>
                    <div class="mt-4 grid gap-3">
                        <input bind:value={category.name} required placeholder="Category name" class="rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-amber-300" />
                        <input bind:value={category.slug} placeholder="Slug (optional)" class="rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-amber-300" />
                        <button class="rounded-full border border-amber-300/50 px-5 py-3 text-sm font-semibold text-amber-100 hover:bg-amber-200/10" type="submit">Create category</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>
