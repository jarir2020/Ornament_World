<script>
    import { onMount } from 'svelte';
    import { trackEvent } from './analytics.js';

    export let checkout = {};

    let items = [];
    let name = '';
    let phone = '';
    let email = '';
    let address = '';
    let district = '';
    let subdistrict = '';
    let postoffice = '';
    let error = '';
    let submitting = false;
    let idempotencyKey = '';

    $: districtData = checkout.locations?.find((item) => item.name === district) ?? null;
    $: subdistrictData = districtData?.subdistricts?.find((item) => item.name === subdistrict) ?? null;
    $: deliveryRule = checkout.deliveryRules?.find((rule) => rule.districts?.includes(district)) ?? null;
    $: subtotal = items.reduce((sum, item) => sum + (Number(item.price || 0) * Number(item.quantity || 0)), 0);
    $: deliveryCharge = deliveryRule?.charge ?? 0;
    $: total = subtotal + deliveryCharge;

    onMount(() => {
        try {
            items = JSON.parse(sessionStorage.getItem('ornaments_checkout_items') || '[]');
            idempotencyKey = sessionStorage.getItem('ornaments_checkout_key') || createRetryKey();
            sessionStorage.setItem('ornaments_checkout_key', idempotencyKey);
            if (items.length) {
                trackEvent('begin_checkout', {
                    currency: 'BDT',
                    value: items.reduce((sum, item) => sum + (Number(item.price || 0) * Number(item.quantity || 0)), 0),
                    items: items.map((item) => ({
                        item_id: String(item.sku || item.variantId || item.productId),
                        item_name: item.name,
                        price: Number(item.price || 0),
                        quantity: Number(item.quantity || 0),
                    })),
                });
            }
        } catch (storageError) {
            items = [];
        }
    });

    function formatPrice(value) {
        return new Intl.NumberFormat('en-BD', {
            style: 'currency',
            currency: 'BDT',
            maximumFractionDigits: 0,
        }).format(Number(value || 0));
    }

    function changeDistrict(event) {
        district = event.currentTarget.value;
        subdistrict = '';
        postoffice = '';
    }

    function changeSubdistrict(event) {
        subdistrict = event.currentTarget.value;
        postoffice = '';
    }

    function updateQuantity(index, delta) {
        items = items.map((item, itemIndex) => itemIndex === index
            ? { ...item, quantity: Math.max(1, Math.min(10, item.quantity + delta)) }
            : item);
        sessionStorage.setItem('ornaments_checkout_items', JSON.stringify(items));
        resetRetryKey();
    }

    function removeItem(index) {
        items = items.filter((_, itemIndex) => itemIndex !== index);
        sessionStorage.setItem('ornaments_checkout_items', JSON.stringify(items));
        resetRetryKey();
    }

    function createRetryKey() {
        if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
        const bytes = new Uint8Array(16);
        globalThis.crypto?.getRandomValues?.(bytes);
        return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('') || `${Date.now()}-${Math.random()}`;
    }

    function resetRetryKey() {
        idempotencyKey = createRetryKey();
        sessionStorage.setItem('ornaments_checkout_key', idempotencyKey);
    }

    async function submitOrder() {
        if (submitting || items.length === 0) return;
        submitting = true;
        error = '';

        try {
            const response = await fetch('/checkout/orders', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': checkout.csrfToken || '',
                },
                body: JSON.stringify({
                    name,
                    phone,
                    email,
                    address,
                    district,
                    subdistrict,
                    postoffice,
                    idempotency_key: idempotencyKey,
                    items: items.map((item) => ({
                        product_id: item.productId,
                        variant_id: item.variantId || null,
                        quantity: item.quantity,
                    })),
                }),
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Please review your checkout details.');
            }

            sessionStorage.removeItem('ornaments_checkout_items');
            sessionStorage.removeItem('ornaments_checkout_key');
            window.location.assign(`/checkout/success/${encodeURIComponent(payload.data.reference)}`);
        } catch (submitError) {
            error = submitError.message || 'We could not place the order right now.';
        } finally {
            submitting = false;
        }
    }
</script>

<svelte:head>
    <title>Checkout | Ornaments World</title>
    <meta name="description" content="Complete your Ornaments World guest order." />
</svelte:head>

<main class="min-h-screen bg-[#080808] px-6 py-10 text-stone-100 sm:px-10 sm:py-16 lg:px-12">
    <div class="mx-auto max-w-6xl">
        <a class="text-sm text-amber-300 transition hover:text-amber-100" href="/storefront">← Continue shopping</a>
        <div class="mt-8 grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
            <section>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Guest checkout</p>
                <h1 class="mt-3 text-4xl font-semibold tracking-tight text-white sm:text-5xl">Where should we deliver?</h1>
                <p class="mt-4 max-w-2xl leading-7 text-stone-400">Share the essentials only. We will call to confirm your order before shipment.</p>

                {#if error}
                    <div class="mt-7 rounded-2xl border border-rose-300/30 bg-rose-300/10 px-5 py-4 text-sm text-rose-100" role="alert">{error}</div>
                {/if}

                {#if items.length}
                    <form class="mt-8 space-y-6" on:submit|preventDefault={submitOrder}>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="text-sm text-stone-300">Full name
                                <input bind:value={name} required autocomplete="name" class="mt-2 w-full rounded-xl border border-white/15 bg-white/[0.05] px-4 py-3 text-white outline-none focus:border-amber-200" />
                            </label>
                            <label class="text-sm text-stone-300">Bangladesh mobile number
                                <input bind:value={phone} required inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" class="mt-2 w-full rounded-xl border border-white/15 bg-white/[0.05] px-4 py-3 text-white outline-none focus:border-amber-200" />
                            </label>
                        </div>

                        <label class="block text-sm text-stone-300">Email <span class="text-stone-500">(optional)</span>
                            <input bind:value={email} type="email" autocomplete="email" class="mt-2 w-full rounded-xl border border-white/15 bg-white/[0.05] px-4 py-3 text-white outline-none focus:border-amber-200" />
                        </label>

                        <label class="block text-sm text-stone-300">Complete delivery address
                            <textarea bind:value={address} required rows="4" placeholder="House/building, road/village, landmark" class="mt-2 w-full rounded-xl border border-white/15 bg-white/[0.05] px-4 py-3 text-white outline-none focus:border-amber-200"></textarea>
                        </label>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="text-sm text-stone-300">District
                                <select value={district} on:change={changeDistrict} required class="mt-2 w-full rounded-xl border border-white/15 bg-[#151515] px-4 py-3 text-white outline-none focus:border-amber-200">
                                    <option value="">Select district</option>
                                    {#each checkout.locations ?? [] as location}
                                        <option value={location.name}>{location.name}</option>
                                    {/each}
                                </select>
                            </label>
                            <label class="text-sm text-stone-300">Area / thana / upazila
                                <select value={subdistrict} on:change={changeSubdistrict} required disabled={!districtData} class="mt-2 w-full rounded-xl border border-white/15 bg-[#151515] px-4 py-3 text-white outline-none focus:border-amber-200 disabled:opacity-50">
                                    <option value="">Select area</option>
                                    {#each districtData?.subdistricts ?? [] as area}
                                        <option value={area.name}>{area.name}</option>
                                    {/each}
                                </select>
                            </label>
                        </div>

                        <label class="block text-sm text-stone-300">Post office <span class="text-stone-500">(optional)</span>
                            <select value={postoffice} on:change={(event) => postoffice = event.currentTarget.value} disabled={!subdistrictData} class="mt-2 w-full rounded-xl border border-white/15 bg-[#151515] px-4 py-3 text-white outline-none focus:border-amber-200 disabled:opacity-50">
                                <option value="">Select post office if known</option>
                                {#each subdistrictData?.postoffices ?? [] as office}
                                    <option value={office.name}>{office.name} {office.postcode ? `(${office.postcode})` : ''}</option>
                                {/each}
                            </select>
                        </label>

                        <button class="w-full rounded-full bg-amber-300 px-6 py-4 text-sm font-semibold text-black transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:bg-stone-700 disabled:text-stone-400" type="submit" disabled={submitting}>
                            {submitting ? 'Placing order…' : 'Place order'}
                        </button>
                    </form>
                {:else}
                    <div class="mt-8 rounded-2xl border border-dashed border-white/15 px-6 py-12 text-center text-stone-400">
                        <p>Your bag is empty.</p>
                        <a class="mt-5 inline-block rounded-full bg-amber-300 px-5 py-3 text-sm font-semibold text-black" href="/storefront">Browse the collection</a>
                    </div>
                {/if}
            </section>

            <aside class="h-fit rounded-3xl border border-white/10 bg-white/[0.04] p-6 lg:sticky lg:top-8">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-amber-300">Your order</p>
                <div class="mt-6 space-y-4">
                    {#each items as item, index}
                        <div class="flex gap-4 border-b border-white/10 pb-4">
                            <div class="grid size-14 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-stone-700 via-stone-950 to-black text-xl text-amber-200/70" aria-hidden="true">✦</div>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-white">{item.name}</p>
                                {#if item.variantName}<p class="mt-1 text-xs text-stone-500">{item.variantName}</p>{/if}
                                <div class="mt-2 flex items-center justify-between gap-3 text-sm">
                                    <span class="text-stone-400">{formatPrice(item.price)}</span>
                                    <span class="flex items-center gap-2">
                                        <button type="button" class="rounded-full border border-white/15 px-2 text-stone-300" on:click={() => updateQuantity(index, -1)} aria-label={`Decrease ${item.name}`}>−</button>
                                        <span>{item.quantity}</span>
                                        <button type="button" class="rounded-full border border-white/15 px-2 text-stone-300" on:click={() => updateQuantity(index, 1)} aria-label={`Increase ${item.name}`}>+</button>
                                        <button type="button" class="ml-1 text-xs text-rose-200" on:click={() => removeItem(index)}>Remove</button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    {/each}
                </div>
                <div class="mt-6 space-y-3 text-sm">
                    <div class="flex justify-between text-stone-400"><span>Subtotal</span><span>{formatPrice(subtotal)}</span></div>
                    <div class="flex justify-between text-stone-400"><span>Delivery {deliveryRule ? `(${deliveryRule.name})` : ''}</span><span>{formatPrice(deliveryCharge)}</span></div>
                    <div class="flex justify-between border-t border-white/10 pt-4 text-lg font-semibold text-amber-200"><span>Total</span><span>{formatPrice(total)}</span></div>
                </div>
            </aside>
        </div>
    </div>
</main>
