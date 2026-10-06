<script>
    export let profile = null;
    export let csrfToken = '';

    let name = profile?.user?.name || '';
    let phone = profile?.user?.phone || '';
    let saving = false;
    let message = '';
    let error = '';

    function formatPrice(value) {
        return new Intl.NumberFormat('en-BD', {
            style: 'currency',
            currency: 'BDT',
            maximumFractionDigits: 0,
        }).format(Number(value || 0));
    }

    async function saveProfile() {
        if (saving) return;
        saving = true;
        message = '';
        error = '';
        try {
            const response = await fetch('/profile', {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ name, phone }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Profile could not be updated.');
            profile = payload.data;
            name = profile.user.name;
            phone = profile.user.phone || '';
            message = 'Profile updated.';
        } catch (saveError) {
            error = saveError.message || 'Profile could not be updated.';
        } finally {
            saving = false;
        }
    }
</script>

<svelte:head>
    <title>My profile | Ornaments World</title>
    <meta name="description" content="Manage your Ornaments World customer profile and account-linked orders." />
</svelte:head>

<main class="min-h-screen bg-[#080808] px-6 py-10 text-stone-100 sm:px-10 lg:px-12">
    <div class="mx-auto max-w-5xl">
        <div class="flex flex-col justify-between gap-4 border-b border-white/10 pb-8 sm:flex-row sm:items-end">
            <div><a class="text-sm text-amber-300 hover:text-amber-100" href="/storefront">← Storefront</a><p class="mt-8 text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Customer account</p><h1 class="mt-3 text-4xl font-semibold text-white">Your profile</h1><p class="mt-3 text-sm leading-7 text-stone-400">Keep your details ready for faster future checkouts. Guest checkout remains available.</p></div>
            <button class="rounded-full border border-white/20 px-5 py-2 text-sm text-stone-300 hover:border-amber-200 hover:text-amber-100" type="button" on:click={() => window.dispatchEvent(new CustomEvent('ornaments:logout'))}>Sign out</button>
        </div>

        {#if profile}
            {#if message}<div class="mt-6 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200" role="status">{message}</div>{/if}
            {#if error}<div class="mt-6 rounded-xl border border-rose-300/30 bg-rose-300/10 px-4 py-3 text-sm text-rose-100" role="alert">{error}</div>{/if}
            <div class="mt-8 grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
                <section class="rounded-2xl border border-white/10 bg-white/[0.04] p-6">
                    <h2 class="font-medium text-white">Personal details</h2>
                    <form class="mt-5 space-y-5" on:submit|preventDefault={saveProfile}>
                        <label class="block text-sm text-stone-300">Full name<input bind:value={name} required minlength="2" maxlength="140" class="mt-2 w-full rounded-xl border border-white/15 bg-black/30 px-4 py-3 text-white outline-none focus:border-amber-200" /></label>
                        <div class="block text-sm text-stone-300">Email<p class="mt-2 rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-stone-400">{profile.user.email}</p></div>
                        <label class="block text-sm text-stone-300">Bangladesh mobile <span class="text-stone-500">(optional)</span><input bind:value={phone} inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" class="mt-2 w-full rounded-xl border border-white/15 bg-black/30 px-4 py-3 text-white outline-none focus:border-amber-200" /></label>
                        <button class="w-full rounded-full bg-amber-300 px-5 py-3 text-sm font-semibold text-black hover:bg-amber-200 disabled:opacity-50" type="submit" disabled={saving}>{saving ? 'Saving…' : 'Save profile'}</button>
                    </form>
                </section>
                <section class="rounded-2xl border border-white/10 bg-white/[0.04] p-6">
                    <h2 class="font-medium text-white">Your orders</h2>
                    {#if profile.orders?.length}
                        <div class="mt-5 divide-y divide-white/10">{#each profile.orders as order}<div class="flex items-center justify-between gap-4 py-4 text-sm"><div><p class="font-medium text-amber-200">{order.reference}</p><p class="mt-1 text-stone-500">{order.status.replaceAll('_', ' ')} · {order.createdAt}</p></div><span class="text-white">{formatPrice(order.total)}</span></div>{/each}</div>
                    {:else}
                        <div class="mt-5 rounded-xl border border-dashed border-white/15 px-5 py-10 text-center text-sm text-stone-500">No account-linked orders yet. <a class="text-amber-200 hover:text-amber-100" href="/storefront">Explore the collection</a></div>
                    {/if}
                </section>
            </div>
        {:else}
            <div class="mt-8 rounded-2xl border border-rose-300/20 bg-rose-300/5 p-6 text-rose-100">This account could not be loaded.</div>
        {/if}
    </div>
</main>
