<script>
    import AdminSidebar from './AdminSidebar.svelte';

    export let order = null;
    export let csrfToken = '';

    let selectedStatus = '';
    let note = '';
    let reviewNote = '';
    let manualReference = '';
    let manualNote = '';
    let message = '';
    let error = '';
    let saving = false;

    $: selectedStatus = selectedStatus || order?.allowedTransitions?.[0] || '';

    function formatPrice(value) {
        return new Intl.NumberFormat('en-BD', {
            style: 'currency',
            currency: 'BDT',
            maximumFractionDigits: 0,
        }).format(Number(value || 0));
    }

    function clearFeedback() {
        message = '';
        error = '';
    }

    async function request(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'The order action could not be completed.');
        }
        return payload.data;
    }

    async function transition() {
        if (!order || !selectedStatus || saving) return;
        clearFeedback();
        saving = true;
        try {
            await request(`/admin/orders/${encodeURIComponent(order.reference)}/transition`, { status: selectedStatus, note });
            message = 'Order status updated.';
            window.location.reload();
        } catch (actionError) {
            error = actionError.message;
        } finally {
            saving = false;
        }
    }

    async function addNote() {
        if (!order || !note.trim() || saving) return;
        clearFeedback();
        saving = true;
        try {
            await request(`/admin/orders/${encodeURIComponent(order.reference)}/notes`, { note });
            message = 'Note added.';
            window.location.reload();
        } catch (actionError) {
            error = actionError.message;
        } finally {
            saving = false;
        }
    }

    async function reviewFlag(flagId, resolution) {
        if (!order || saving) return;
        clearFeedback();
        saving = true;
        try {
            await request(`/admin/fraud-flags/${flagId}/review`, { resolution, note: reviewNote });
            message = 'Review outcome saved.';
            window.location.reload();
        } catch (actionError) {
            error = actionError.message;
        } finally {
            saving = false;
        }
    }

    async function createShipment() {
        if (!order || saving) return;
        clearFeedback();
        saving = true;
        try {
            await request(`/admin/orders/${encodeURIComponent(order.reference)}/shipment`, {});
            message = 'Shipment action saved.';
            window.location.reload();
        } catch (actionError) {
            error = actionError.message;
        } finally {
            saving = false;
        }
    }

    async function recordManualShipment() {
        if (!order || saving || !manualReference.trim() || !manualNote.trim()) return;
        clearFeedback();
        saving = true;
        try {
            await request(`/admin/orders/${encodeURIComponent(order.reference)}/manual-shipment`, {
                manual_reference: manualReference,
                note: manualNote,
            });
            message = 'Manual shipment recorded.';
            window.location.reload();
        } catch (actionError) {
            error = actionError.message;
        } finally {
            saving = false;
        }
    }
</script>

<svelte:head><title>{order ? `${order.reference} | Order admin` : 'Order not found | Ornaments World'}</title></svelte:head>

{#if order}
    <main class="min-h-screen bg-[#080808] px-6 py-10 text-stone-100 sm:px-10 lg:px-12">
        <div class="mx-auto max-w-[90rem]">
            <div class="grid gap-8 lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-start">
                <AdminSidebar active="orders" />
                <div class="min-w-0">
            <a class="text-sm text-amber-300 transition hover:text-amber-100" href="/admin">← Back to order queue</a>
            <div class="mt-7 flex flex-col justify-between gap-5 border-b border-white/10 pb-7 sm:flex-row sm:items-end">
                <div><p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Protected order detail</p><h1 class="mt-3 text-4xl font-semibold text-white">{order.reference}</h1><p class="mt-2 text-sm text-stone-500">Placed {order.createdAt}</p></div>
                <span class="rounded-full bg-amber-200/10 px-4 py-2 text-sm text-amber-200">{order.status.replaceAll('_', ' ')}</span>
            </div>

            {#if message}<div class="mt-6 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">{message}</div>{/if}
            {#if error}<div class="mt-6 rounded-xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200">{error}</div>{/if}

            <div class="mt-8 grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                <div class="space-y-6">
                    <section class="rounded-2xl border border-white/10 bg-white/[0.04] p-6">
                        <h2 class="font-medium text-white">Customer and delivery</h2>
                        <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                            <div><dt class="text-stone-500">Customer</dt><dd class="mt-1 text-white">{order.customerName}</dd></div>
                            <div><dt class="text-stone-500">Phone</dt><dd class="mt-1 text-white">{order.customerPhone}</dd></div>
                            {#if order.email}<div><dt class="text-stone-500">Email</dt><dd class="mt-1 break-words text-white">{order.email}</dd></div>{/if}
                            <div><dt class="text-stone-500">Location</dt><dd class="mt-1 text-white">{order.subdistrict}, {order.district}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-stone-500">Address</dt><dd class="mt-1 leading-7 text-white">{order.address}{order.postoffice ? `, ${order.postoffice}` : ''}{order.postcode ? ` ${order.postcode}` : ''}</dd></div>
                        </dl>
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04]">
                        <div class="border-b border-white/10 px-6 py-5"><h2 class="font-medium text-white">Order items</h2></div>
                        <div class="divide-y divide-white/10">
                            {#each order.items as item}
                                <div class="flex items-center justify-between gap-5 px-6 py-5 text-sm"><div><p class="font-medium text-white">{item.productName}</p><p class="mt-1 text-stone-500">{item.variantName} · {item.sku} · Qty {item.quantity}</p></div><span class="text-amber-200">{formatPrice(item.lineTotal)}</span></div>
                            {/each}
                        </div>
                        <div class="space-y-2 border-t border-white/10 px-6 py-5 text-sm"><div class="flex justify-between text-stone-400"><span>Subtotal</span><span>{formatPrice(order.subtotal)}</span></div><div class="flex justify-between text-stone-400"><span>Delivery</span><span>{formatPrice(order.deliveryCharge)}</span></div><div class="flex justify-between pt-2 text-lg font-semibold text-amber-200"><span>Total</span><span>{formatPrice(order.total)}</span></div></div>
                    </section>
                </div>

                <div class="space-y-6">
                    <section class="rounded-2xl border border-amber-200/20 bg-amber-200/[0.04] p-6">
                        <h2 class="font-medium text-white">Order action</h2>
                        {#if order.allowedTransitions?.length}
                            <div class="mt-5 grid gap-3"><label class="text-sm text-stone-300">Move to<select bind:value={selectedStatus} class="mt-2 w-full rounded-xl border border-white/10 bg-[#151515] px-4 py-3 text-sm text-white outline-none focus:border-amber-300">{#each order.allowedTransitions as status}<option value={status}>{status.replaceAll('_', ' ')}</option>{/each}</select></label><textarea bind:value={note} rows="3" placeholder="Confirmation note or reason" class="rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-amber-300"></textarea><button class="rounded-full bg-amber-300 px-5 py-3 text-sm font-semibold text-black hover:bg-amber-200 disabled:opacity-50" type="button" disabled={saving} on:click={transition}>Save status</button></div>
                        {:else}<p class="mt-4 text-sm text-stone-500">No further status transitions are available.</p>{/if}
                        <div class="mt-6 border-t border-white/10 pt-5"><label class="text-sm text-stone-300">Add internal note<textarea bind:value={note} rows="3" placeholder="Call outcome or operational note" class="mt-2 w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-amber-300"></textarea></label><button class="mt-3 rounded-full border border-amber-300/50 px-5 py-3 text-sm font-semibold text-amber-100 hover:bg-amber-200/10 disabled:opacity-50" type="button" disabled={saving || !note.trim()} on:click={addNote}>Add note</button></div>
                    </section>

                    <section class="rounded-2xl border border-sky-200/20 bg-sky-200/[0.04] p-6">
                        <div class="flex items-start justify-between gap-4"><div><h2 class="font-medium text-white">Shipment</h2><p class="mt-1 text-xs leading-5 text-stone-400">Order status stays separate from courier status. Provider requests are server-side and idempotent.</p></div>{#if order.shipment}<span class="rounded-full bg-sky-200/10 px-3 py-1 text-xs text-sky-200">{order.shipment.status.replaceAll('_', ' ')}</span>{/if}</div>
                        {#if order.shipment}
                            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                                <div><dt class="text-stone-500">Attempts</dt><dd class="mt-1 text-white">{order.shipment.attemptCount}</dd></div>
                                {#if order.shipment.externalId}<div><dt class="text-stone-500">Provider reference</dt><dd class="mt-1 break-all text-white">{order.shipment.externalId}</dd></div>{/if}
                                {#if order.shipment.manualReference}<div><dt class="text-stone-500">Manual reference</dt><dd class="mt-1 break-all text-white">{order.shipment.manualReference}</dd></div>{/if}
                                {#if order.shipment.lastHttpStatus}<div><dt class="text-stone-500">Last response</dt><dd class="mt-1 text-white">HTTP {order.shipment.lastHttpStatus}</dd></div>{/if}
                            </dl>
                            {#if order.shipment.lastError}<p class="mt-4 rounded-xl border border-rose-300/20 bg-rose-300/[0.05] px-4 py-3 text-sm leading-6 text-rose-100">{order.shipment.lastError}</p>{/if}
                            {#if order.shipment.manualNote}<p class="mt-4 text-sm leading-6 text-stone-400">{order.shipment.manualNote}</p>{/if}
                            {#if order.shipment.attempts?.length}<div class="mt-5 border-t border-white/10 pt-4"><p class="text-xs uppercase tracking-[0.2em] text-stone-500">Attempt history</p><div class="mt-3 space-y-3">{#each order.shipment.attempts as attempt}<div class="flex justify-between gap-3 text-xs"><span class="text-stone-300">#{attempt.attemptNo} · {attempt.outcome}{attempt.httpStatus ? ` · HTTP ${attempt.httpStatus}` : ''}</span><span class="text-stone-600">{attempt.createdAt}</span></div>{/each}</div></div>{/if}
                        {:else}<p class="mt-5 text-sm leading-6 text-stone-400">No shipment has been created yet.</p>{/if}

                        {#if order.shipmentEligible || order.shipmentCanRetry}
                            <button class="mt-5 w-full rounded-full bg-sky-200 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-sky-100 disabled:opacity-50" type="button" disabled={saving} on:click={createShipment}>{order.shipmentCanRetry ? 'Retry Pathao shipment' : 'Send to Pathao / create shipment'}</button>
                        {:else if order.status === 'confirmed' && order.shipment?.status === 'manual_required'}
                            <p class="mt-5 text-sm leading-6 text-amber-100">Automatic retry is not recommended for this provider response. Record the courier reference below after arranging shipment manually.</p>
                        {:else if order.status !== 'confirmed' && !['processing', 'shipped'].includes(order.status)}
                            <p class="mt-5 text-sm leading-6 text-stone-500">Confirm this order before creating a courier shipment.</p>
                        {/if}

                        {#if ['confirmed', 'processing', 'shipped'].includes(order.status) && order.shipment?.status !== 'created'}
                            <div class="mt-6 border-t border-white/10 pt-5"><p class="text-sm font-medium text-white">Manual shipment fallback</p><div class="mt-3 grid gap-3"><input bind:value={manualReference} placeholder="Courier reference" class="rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-sky-200" /><textarea bind:value={manualNote} rows="2" placeholder="Where and when was it created?" class="rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-sky-200"></textarea><button class="rounded-full border border-sky-200/50 px-5 py-3 text-sm font-semibold text-sky-100 hover:bg-sky-200/10 disabled:opacity-50" type="button" disabled={saving || !manualReference.trim() || !manualNote.trim()} on:click={recordManualShipment}>Record manual shipment</button></div></div>
                        {/if}
                    </section>

                    <section class="rounded-2xl border border-white/10 bg-white/[0.04] p-6"><h2 class="font-medium text-white">Fraud review</h2>{#if order.fraudFlags?.length}<textarea bind:value={reviewNote} rows="2" placeholder="Review note" class="mt-4 w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white outline-none focus:border-amber-300"></textarea><div class="mt-4 space-y-4">{#each order.fraudFlags as flag}<div class="rounded-xl border border-rose-300/20 bg-rose-300/[0.05] p-4"><div class="flex justify-between gap-3"><p class="text-sm font-medium text-rose-100">{flag.code.replaceAll('_', ' ')}</p><span class="text-xs text-rose-200">{flag.resolution}</span></div><p class="mt-2 text-xs leading-6 text-stone-400">{flag.reason}</p><div class="mt-3 flex flex-wrap gap-2"><button type="button" class="rounded-full border border-emerald-300/40 px-3 py-1.5 text-xs text-emerald-200" on:click={() => reviewFlag(flag.id, 'confirmed')}>Confirm legitimate</button><button type="button" class="rounded-full border border-stone-400/40 px-3 py-1.5 text-xs text-stone-300" on:click={() => reviewFlag(flag.id, 'dismissed')}>Dismiss</button><button type="button" class="rounded-full border border-rose-300/40 px-3 py-1.5 text-xs text-rose-200" on:click={() => reviewFlag(flag.id, 'blocked')}>Block</button></div></div>{/each}</div>{:else}<p class="mt-4 text-sm text-emerald-300">No fraud flags on this order.</p>{/if}</section>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <section class="rounded-2xl border border-white/10 bg-white/[0.04] p-6"><h2 class="font-medium text-white">Status history</h2><div class="mt-5 space-y-4">{#each order.history as entry}<div class="border-l border-amber-200/40 pl-4"><p class="text-sm text-white">{entry.toStatus.replaceAll('_', ' ')}</p>{#if entry.note}<p class="mt-1 text-sm leading-6 text-stone-400">{entry.note}</p>{/if}<p class="mt-1 text-xs text-stone-600">{entry.createdAt}</p></div>{/each}</div></section>
                <section class="rounded-2xl border border-white/10 bg-white/[0.04] p-6"><h2 class="font-medium text-white">Previous orders for this phone</h2>{#if order.previousOrders?.length}<div class="mt-5 space-y-3">{#each order.previousOrders as previous}<a class="flex items-center justify-between rounded-xl border border-white/10 px-4 py-3 text-sm hover:border-amber-200/50" href={`/admin/orders/${previous.reference}`}><span class="text-amber-200">{previous.reference}</span><span class="text-stone-400">{previous.status.replaceAll('_', ' ')} · {formatPrice(previous.total)}</span></a>{/each}</div>{:else}<p class="mt-4 text-sm text-stone-500">No previous orders found for this phone.</p>{/if}</section>
            </div>
                </div>
            </div>
        </div>
    </main>
{:else}
    <main class="grid min-h-screen place-items-center bg-[#080808] px-6 text-center text-stone-100"><div><p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">404</p><h1 class="mt-4 text-4xl font-semibold text-white">Order not found.</h1><a class="mt-8 inline-block rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black" href="/admin">Return to admin</a></div></main>
{/if}
