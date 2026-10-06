<script>
    import { onMount } from 'svelte';
    import Checkout from './Checkout.svelte';
    import AdminOrderDetail from './AdminOrderDetail.svelte';
    import ProductDetail from './ProductDetail.svelte';
    import AdminCatalog from './AdminCatalog.svelte';
    import OrderSuccess from './OrderSuccess.svelte';
    import ContentPage from './ContentPage.svelte';
    import { trackEvent } from './analytics.js';

    export let page = {};

    let cart = [];
    let showCart = false;

    $: brand = page.brand ?? 'Ornaments World';
    $: categories = page.categories ?? [];
    $: products = page.products ?? [];
    $: filters = page.filters ?? { category: '', search: '' };

    $: cartCount = cart.reduce((count, item) => count + Number(item.quantity || 0), 0);

    onMount(() => {
        try {
            cart = JSON.parse(sessionStorage.getItem('ornaments_cart') || '[]');
        } catch (storageError) {
            cart = [];
        }
    });

    function persistCart() {
        sessionStorage.setItem('ornaments_cart', JSON.stringify(cart));
    }

    function itemKey(item) {
        return `${item.productId}:${item.variantId || 'default'}`;
    }

    function addToCart(item) {
        const key = itemKey(item);
        const existing = cart.find((cartItem) => itemKey(cartItem) === key);
        cart = existing
            ? cart.map((cartItem) => cartItem === existing ? { ...cartItem, quantity: Math.min(10, cartItem.quantity + 1) } : cartItem)
            : [...cart, item];
        persistCart();
        trackEvent('add_to_cart', {
            currency: 'BDT',
            value: Number(item.price || 0),
            items: [{
                item_id: String(item.sku || item.variantId || item.productId),
                item_name: item.name,
                item_variant: item.variantName || undefined,
                price: Number(item.price || 0),
                quantity: 1,
            }],
        });
        showCart = true;
    }

    function addProductToCart(product) {
        addToCart({
            productId: product.id,
            variantId: product.defaultVariant?.id ?? null,
            sku: product.defaultVariant?.sku ?? '',
            name: product.name,
            variantName: product.defaultVariant?.name ?? '',
            price: product.price,
            quantity: 1,
            imageUrl: product.imageUrl ?? null,
        });
    }

    function orderNow(item) {
        sessionStorage.setItem('ornaments_checkout_items', JSON.stringify([item]));
        sessionStorage.removeItem('ornaments_checkout_key');
        window.location.assign('/checkout');
    }

    function checkoutCart() {
        sessionStorage.setItem('ornaments_checkout_items', JSON.stringify(cart));
        sessionStorage.removeItem('ornaments_checkout_key');
        window.location.assign('/checkout');
    }

    function updateCart(index, delta) {
        cart = cart.map((item, itemIndex) => itemIndex === index
            ? { ...item, quantity: Math.max(1, Math.min(10, item.quantity + delta)) }
            : item);
        persistCart();
    }

    function removeFromCart(index) {
        cart = cart.filter((_, itemIndex) => itemIndex !== index);
        persistCart();
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
    <title>{page.notFound || page.notFoundPage ? `Page not found | ${brand}` : page.product ? `${page.product.name} | ${brand}` : page.help ? `${page.help.title} | ${brand}` : `${brand} | Men's jewellery`}</title>
    <meta name="description" content={page.product?.shortDescription ?? 'Refined men\'s jewellery from Ornaments World.'} />
</svelte:head>

{#if page.notFound}
    <main class="grid min-h-screen place-items-center bg-[#080808] px-6 text-center text-stone-100">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">404</p>
            <h1 class="mt-4 text-4xl font-semibold text-white">That piece is not available.</h1>
            <a class="mt-8 inline-block rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black" href="/storefront">Return to collections</a>
        </div>
    </main>
{:else if page.notFoundPage}
    <main class="grid min-h-screen place-items-center bg-[#080808] px-6 text-center text-stone-100">
        <div><p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">404</p><h1 class="mt-4 text-4xl font-semibold text-white">That page has moved on.</h1><p class="mt-4 max-w-md leading-7 text-stone-400">The page you requested is not part of the current collection.</p><a class="mt-8 inline-block rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black" href="/storefront">Return to collections</a></div>
    </main>
{:else if page.product}
    <ProductDetail product={page.product} onAddToCart={addToCart} onOrderNow={orderNow} />
{:else if page.checkout}
    <Checkout checkout={page.checkout} />
{:else if page.orderSuccessPage}
    <OrderSuccess order={page.orderSuccess} />
{:else if page.help}
    <ContentPage content={page.help} />
{:else if page.adminOrderPage}
    <AdminOrderDetail order={page.adminOrder} csrfToken={page.adminOrderCsrfToken} />
{:else if page.admin}
    <AdminCatalog admin={page.admin} />
{:else}
<div class="min-h-screen bg-[#080808] text-stone-100">
    <header class="border-b border-white/10 bg-black/70 backdrop-blur">
        <nav class="mx-auto flex max-w-7xl items-center gap-6 px-6 py-5 sm:px-10 lg:px-12" aria-label="Main navigation">
            <a href="/storefront" class="mr-auto flex items-center gap-3 text-sm font-semibold uppercase tracking-[0.24em] text-amber-200">
                <span class="grid size-9 place-items-center rounded-full border border-amber-300/50 text-base text-amber-300" aria-hidden="true">O</span>
                <span>{brand}</span>
            </a>
            <a class="hidden text-sm text-stone-300 transition hover:text-amber-200 sm:inline" href="#collections">Collections</a>
            <a class="hidden text-sm text-stone-300 transition hover:text-amber-200 sm:inline" href="#story">Our story</a>
            <button class="relative rounded-full border border-amber-300/40 px-4 py-2 text-sm text-amber-100 transition hover:border-amber-200 hover:bg-amber-200/10" type="button" on:click={() => showCart = true} aria-label={`Shopping bag with ${cartCount} items`}>
                Bag
                {#if cartCount > 0}
                    <span class="ml-2 rounded-full bg-amber-300 px-2 py-0.5 text-xs font-bold text-black">{cartCount}</span>
                {/if}
            </button>
        </nav>
    </header>

    <main>
        <section class="relative overflow-hidden border-b border-white/10 px-6 py-20 sm:px-10 sm:py-28 lg:px-12">
            <div class="absolute -right-24 -top-32 size-96 rounded-full bg-amber-300/10 blur-3xl" aria-hidden="true"></div>
            <div class="relative mx-auto grid max-w-7xl gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                <div>
                    <p class="mb-5 text-xs font-semibold uppercase tracking-[0.35em] text-amber-300">{page.eyebrow ?? 'Quiet luxury, made for every day'}</p>
                    <h1 class="max-w-4xl text-5xl font-semibold leading-[1.05] tracking-tight text-white sm:text-7xl">
                        {page.headline ?? 'Details that make the moment.'}
                    </h1>
                    <p class="mt-7 max-w-2xl text-base leading-8 text-stone-300 sm:text-lg">
                        {page.intro ?? 'Discover considered men’s jewellery designed to feel personal, lasting, and unmistakably yours.'}
                    </p>
                    <div class="mt-9 flex flex-wrap gap-4">
                        <a class="rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black transition hover:bg-amber-200" href="#collections">Explore collections</a>
                        <a class="rounded-full border border-white/20 px-6 py-3 text-sm font-semibold text-stone-100 transition hover:border-amber-200/70 hover:text-amber-200" href="#story">Why Ornaments World</a>
                    </div>
                </div>

                <div class="relative mx-auto aspect-square w-full max-w-md overflow-hidden rounded-[2rem] border border-amber-200/20 bg-gradient-to-br from-stone-700 via-stone-950 to-black p-8 shadow-2xl shadow-amber-200/10">
                    <div class="absolute inset-6 rounded-[1.5rem] border border-amber-200/20"></div>
                    <div class="relative flex h-full flex-col items-center justify-center text-center">
                        <span class="text-7xl font-light text-amber-200/80" aria-hidden="true">✦</span>
                        <p class="mt-6 text-xs uppercase tracking-[0.4em] text-amber-200/80">The signature edit</p>
                        <p class="mt-3 max-w-xs text-2xl font-medium text-white">Wear what stays with you.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="collections" class="mx-auto max-w-7xl px-6 py-16 sm:px-10 lg:px-12">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Start exploring</p>
                    <h2 class="mt-3 text-3xl font-semibold text-white sm:text-4xl">Find your everyday signature</h2>
                </div>
                <span class="text-sm text-stone-400">Curated collections for him</span>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {#each categories as category, index}
                    <a href={`/storefront?category=${encodeURIComponent(category.slug)}`} class="group rounded-2xl border border-white/10 bg-white/[0.04] p-5 transition hover:-translate-y-1 hover:border-amber-200/50 hover:bg-amber-200/[0.06]">
                        <span class="text-sm text-amber-300">0{index + 1}</span>
                        <h3 class="mt-12 text-xl font-medium text-white group-hover:text-amber-100">{category.name}</h3>
                        <span class="mt-3 block text-sm text-stone-400">Explore edit <span aria-hidden="true">→</span></span>
                    </a>
                {/each}
            </div>
        </section>

        <section id="catalog" class="mx-auto max-w-7xl px-6 pb-16 sm:px-10 lg:px-12">
            <div class="flex flex-col justify-between gap-5 border-b border-white/10 pb-6 sm:flex-row sm:items-end">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">The edit</p>
                    <h2 class="mt-3 text-3xl font-semibold text-white sm:text-4xl">Pieces to make your own</h2>
                </div>
                <form class="flex flex-col gap-3 sm:flex-row" method="GET" action="/storefront">
                    <label class="sr-only" for="catalog-search">Search catalog</label>
                    <input id="catalog-search" name="search" value={filters.search} placeholder="Search pieces" class="rounded-full border border-white/15 bg-white/[0.05] px-4 py-2 text-sm text-white outline-none placeholder:text-stone-500 focus:border-amber-200" />
                    <label class="sr-only" for="catalog-category">Filter category</label>
                    <select id="catalog-category" name="category" class="rounded-full border border-white/15 bg-[#151515] px-4 py-2 text-sm text-stone-200 outline-none focus:border-amber-200">
                        <option value="">All collections</option>
                        {#each categories as category}
                            <option value={category.slug} selected={filters.category === category.slug}>{category.name}</option>
                        {/each}
                    </select>
                    <button class="rounded-full bg-amber-300 px-5 py-2 text-sm font-semibold text-black transition hover:bg-amber-200" type="submit">Filter</button>
                </form>
            </div>

            {#if products.length}
                <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {#each products as product}
                        <article class="group overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04]">
                            <a href={`/storefront/product/${product.slug}`} class="block">
                                {#if product.imageUrl}
                                    <img class="aspect-square w-full object-cover transition duration-500 group-hover:scale-105" src={product.imageUrl} alt={product.name} loading="lazy" />
                                {:else}
                                    <div class="flex aspect-square items-center justify-center bg-gradient-to-br from-stone-700 via-stone-950 to-black text-6xl text-amber-200/60" aria-label="Product image placeholder">✦</div>
                                {/if}
                            </a>
                            <div class="p-5">
                                <p class="text-xs uppercase tracking-[0.2em] text-amber-300">{product.category.name}</p>
                                <h3 class="mt-2 text-lg font-medium text-white"><a href={`/storefront/product/${product.slug}`}>{product.name}</a></h3>
                                <p class="mt-2 min-h-12 text-sm leading-6 text-stone-400">{product.shortDescription}</p>
                                <div class="mt-5 flex items-center justify-between gap-3">
                                    <span class="font-semibold text-amber-200">{formatPrice(product.price)}</span>
                                    <span class="text-xs {product.inStock ? 'text-emerald-300' : 'text-stone-500'}">{product.inStock ? 'In stock' : 'Out of stock'}</span>
                                </div>
                                <button class="mt-4 w-full rounded-full border border-amber-200/40 px-4 py-2 text-sm font-medium text-amber-100 transition hover:border-amber-200 hover:bg-amber-200/10 disabled:cursor-not-allowed disabled:border-white/10 disabled:text-stone-500" type="button" disabled={!product.inStock} on:click={() => addProductToCart(product)}>
                                    {product.inStock ? 'Add to bag' : 'Out of stock'}
                                </button>
                            </div>
                        </article>
                    {/each}
                </div>
            {:else}
                <div class="mt-8 rounded-2xl border border-dashed border-white/15 px-6 py-12 text-center text-stone-400">No pieces match this filter yet.</div>
            {/if}
        </section>

        <section id="story" class="border-y border-white/10 bg-white/[0.03] px-6 py-16 sm:px-10 lg:px-12">
            <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Made to matter</p>
                    <h2 class="mt-3 text-3xl font-semibold text-white">A considered way to shop.</h2>
                </div>
                <div class="grid gap-8 sm:grid-cols-3 lg:col-span-2">
                    <div>
                        <p class="text-2xl text-amber-200">01</p>
                        <h3 class="mt-3 font-medium text-white">Personal by design</h3>
                        <p class="mt-2 text-sm leading-7 text-stone-400">Pieces chosen for the details that become part of your story.</p>
                    </div>
                    <div>
                        <p class="text-2xl text-amber-200">02</p>
                        <h3 class="mt-3 font-medium text-white">Clear and simple</h3>
                        <p class="mt-2 text-sm leading-7 text-stone-400">A focused catalogue and a checkout experience without clutter.</p>
                    </div>
                    <div>
                        <p class="text-2xl text-amber-200">03</p>
                        <h3 class="mt-3 font-medium text-white">Ready for yours</h3>
                        <p class="mt-2 text-sm leading-7 text-stone-400">Thoughtful service from first browse to final delivery.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-8 text-sm text-stone-500 sm:flex-row sm:items-center sm:justify-between sm:px-10 lg:px-12">
        <span>© {new Date().getFullYear()} {brand}</span>
        <span class="flex flex-wrap gap-4"><a class="hover:text-amber-200" href="/help/delivery">Delivery</a><a class="hover:text-amber-200" href="/help/contact">Contact</a><a class="hover:text-amber-200" href="/help/privacy">Privacy</a><span>Built with care in Bangladesh</span></span>
    </footer>

    {#if showCart}
        <div class="fixed inset-0 z-20 bg-black/70" role="presentation" on:click={() => showCart = false}></div>
        <aside class="fixed inset-y-0 right-0 z-30 flex w-full max-w-md flex-col border-l border-white/10 bg-[#101010] p-6 text-stone-100 shadow-2xl" aria-label="Shopping bag">
            <div class="flex items-center justify-between border-b border-white/10 pb-5">
                <div><p class="text-xs font-semibold uppercase tracking-[0.25em] text-amber-300">Your bag</p><h2 class="mt-2 text-2xl font-semibold text-white">{cartCount} {cartCount === 1 ? 'piece' : 'pieces'}</h2></div>
                <button type="button" class="rounded-full border border-white/15 px-3 py-2 text-sm text-stone-300" on:click={() => showCart = false} aria-label="Close shopping bag">Close</button>
            </div>
            {#if cart.length}
                <div class="flex-1 space-y-4 overflow-y-auto py-6">
                    {#each cart as item, index}
                        <div class="flex gap-4 border-b border-white/10 pb-4">
                            <div class="grid size-14 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-stone-700 via-stone-950 to-black text-xl text-amber-200/70" aria-hidden="true">✦</div>
                            <div class="min-w-0 flex-1"><p class="font-medium text-white">{item.name}</p><p class="mt-1 text-xs text-stone-500">{item.variantName || 'Standard'}</p><p class="mt-2 text-sm text-amber-200">{formatPrice(item.price)}</p><div class="mt-3 flex items-center gap-2 text-sm"><button type="button" class="rounded-full border border-white/15 px-2" on:click={() => updateCart(index, -1)} aria-label={`Decrease ${item.name}`}>−</button><span>{item.quantity}</span><button type="button" class="rounded-full border border-white/15 px-2" on:click={() => updateCart(index, 1)} aria-label={`Increase ${item.name}`}>+</button><button type="button" class="ml-2 text-xs text-rose-200" on:click={() => removeFromCart(index)}>Remove</button></div></div>
                        </div>
                    {/each}
                </div>
                <button class="rounded-full bg-amber-300 px-5 py-4 text-sm font-semibold text-black transition hover:bg-amber-200" type="button" on:click={checkoutCart}>Continue to checkout</button>
            {:else}
                <div class="grid flex-1 place-items-center text-center text-stone-400"><div><p>Your bag is empty.</p><a class="mt-5 inline-block rounded-full bg-amber-300 px-5 py-3 text-sm font-semibold text-black" href="#collections" on:click={() => showCart = false}>Browse collections</a></div></div>
            {/if}
        </aside>
    {/if}
</div>
{/if}
