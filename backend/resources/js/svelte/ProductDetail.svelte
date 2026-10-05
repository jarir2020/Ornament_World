<script>
    export let product;

    let selectedVariant = product?.variants?.[0] ?? null;

    $: selectedPrice = selectedVariant?.price ?? product?.price ?? 0;
    $: selectedStock = selectedVariant?.stockQty ?? product?.stockQty ?? 0;

    function formatPrice(value) {
        return new Intl.NumberFormat('en-BD', {
            style: 'currency',
            currency: 'BDT',
            maximumFractionDigits: 0,
        }).format(Number(value || 0));
    }
</script>

<main class="min-h-screen bg-[#080808] px-6 py-12 text-stone-100 sm:px-10 sm:py-16 lg:px-12">
    <div class="mx-auto max-w-7xl">
        <a class="text-sm text-amber-300 transition hover:text-amber-100" href="/storefront">← Back to collections</a>

        <div class="mt-10 grid gap-12 lg:grid-cols-[1fr_0.9fr] lg:items-start">
            <div class="grid gap-4 sm:grid-cols-2">
                {#if product.images?.length}
                    {#each product.images as image}
                        <img class="aspect-square w-full rounded-3xl border border-white/10 object-cover" src={image.image_url} alt={image.alt_text || product.name} />
                    {/each}
                {:else}
                    <div class="flex aspect-square items-center justify-center rounded-3xl border border-amber-200/20 bg-gradient-to-br from-stone-700 via-stone-950 to-black text-8xl text-amber-200/70 sm:col-span-2" aria-label="Product image placeholder">
                        ✦
                    </div>
                {/if}
            </div>

            <div class="lg:sticky lg:top-8">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{product.category.name}</p>
                <h1 class="mt-4 text-4xl font-semibold tracking-tight text-white sm:text-5xl">{product.name}</h1>
                <p class="mt-5 text-base leading-8 text-stone-300">{product.description || product.shortDescription}</p>

                <div class="mt-8 flex items-baseline gap-3">
                    <span class="text-3xl font-semibold text-amber-200">{formatPrice(selectedPrice)}</span>
                    {#if product.compareAtPrice && product.compareAtPrice > selectedPrice}
                        <span class="text-sm text-stone-500 line-through">{formatPrice(product.compareAtPrice)}</span>
                    {/if}
                </div>

                {#if product.variants?.length > 1}
                    <fieldset class="mt-8">
                        <legend class="text-sm font-medium text-stone-200">Choose an option</legend>
                        <div class="mt-3 flex flex-wrap gap-3">
                            {#each product.variants as variant}
                                <button
                                    class={`rounded-full border px-4 py-2 text-sm text-stone-200 transition hover:border-amber-200 ${selectedVariant?.id === variant.id ? 'border-amber-300' : 'border-white/20'}`}
                                    type="button"
                                    on:click={() => selectedVariant = variant}
                                    disabled={!variant.inStock}
                                >
                                    {variant.name}
                                </button>
                            {/each}
                        </div>
                    </fieldset>
                {/if}

                <div class="mt-8 flex items-center gap-3 text-sm">
                    <span class={`size-2 rounded-full ${selectedStock > 0 ? 'bg-emerald-400' : 'bg-stone-600'}`}></span>
                    <span class="text-stone-300">{selectedStock > 0 ? `${selectedStock} available` : 'Currently out of stock'}</span>
                </div>

                <button class="mt-8 w-full rounded-full bg-amber-300 px-6 py-4 text-sm font-semibold text-black transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:bg-stone-700 disabled:text-stone-400" type="button" disabled={selectedStock < 1}>
                    {selectedStock > 0 ? 'Add to bag' : 'Notify me when available'}
                </button>
                <p class="mt-4 text-center text-xs leading-6 text-stone-500">Checkout and stock revalidation will be added in Phase 3.</p>
            </div>
        </div>
    </div>
</main>
