<script>
    export let order = null;

    function formatPrice(value) {
        return new Intl.NumberFormat('en-BD', {
            style: 'currency',
            currency: 'BDT',
            maximumFractionDigits: 0,
        }).format(Number(value || 0));
    }
</script>

<svelte:head>
    <title>{order ? `Order ${order.reference} | Ornaments World` : 'Order not found | Ornaments World'}</title>
</svelte:head>

{#if order}
    <main class="grid min-h-screen place-items-center bg-[#080808] px-6 py-16 text-stone-100">
        <section class="w-full max-w-2xl rounded-3xl border border-amber-200/20 bg-white/[0.04] p-7 text-center sm:p-10">
            <div class="mx-auto grid size-16 place-items-center rounded-full border border-emerald-300/40 bg-emerald-300/10 text-2xl text-emerald-200" aria-hidden="true">✓</div>
            <p class="mt-7 text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Order received</p>
            <h1 class="mt-3 text-4xl font-semibold text-white">Thank you for choosing your piece.</h1>
            <p class="mt-5 leading-7 text-stone-300">We will call to confirm the order before shipment.</p>
            <p class="mt-5 text-sm text-stone-400">Order reference <strong class="text-amber-200">{order.reference}</strong></p>

            <div class="mt-8 divide-y divide-white/10 rounded-2xl border border-white/10 text-left">
                {#each order.items as item}
                    <div class="flex items-center justify-between gap-4 px-5 py-4 text-sm">
                        <div><p class="font-medium text-white">{item.name}</p><p class="mt-1 text-stone-500">{item.variant} × {item.quantity}</p></div>
                        <span class="text-amber-200">{formatPrice(item.total)}</span>
                    </div>
                {/each}
                <div class="space-y-2 px-5 py-4 text-sm">
                    <div class="flex justify-between text-stone-400"><span>Subtotal</span><span>{formatPrice(order.subtotal)}</span></div>
                    {#if order.discountTotal > 0}<div class="flex justify-between text-emerald-200"><span>Discount</span><span>−{formatPrice(order.discountTotal)}</span></div>{/if}
                    <div class="flex justify-between text-stone-400"><span>Delivery</span><span>{formatPrice(order.deliveryCharge)}</span></div>
                    <div class="flex justify-between border-t border-white/10 pt-3 text-lg font-semibold text-amber-200"><span>Total</span><span>{formatPrice(order.total)}</span></div>
                </div>
            </div>

            <a class="mt-8 inline-block rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black transition hover:bg-amber-200" href="/storefront">Continue shopping</a>
        </section>
    </main>
{:else}
    <main class="grid min-h-screen place-items-center bg-[#080808] px-6 text-center text-stone-100">
        <div><p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">404</p><h1 class="mt-4 text-4xl font-semibold text-white">Order not found.</h1><a class="mt-8 inline-block rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black" href="/storefront">Return to collections</a></div>
    </main>
{/if}
