<script>
    export let content = null;
</script>

<svelte:head>
    <title>{content?.title ?? 'Help | Ornaments World'}</title>
    <meta name="description" content={content?.description ?? 'Information from Ornaments World.'} />
</svelte:head>

{#if content}
    <main class="min-h-screen bg-[#080808] px-6 py-14 text-stone-100 sm:px-10 sm:py-20 lg:px-12">
        <div class="mx-auto max-w-4xl">
            <a class="text-sm text-amber-300 transition hover:text-amber-100" href="/storefront">← Back to collections</a>
            <p class="mt-10 text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{content.eyebrow}</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-tight text-white sm:text-6xl">{content.title}</h1>
            <p class="mt-6 max-w-3xl text-base leading-8 text-stone-300">{content.intro}</p>

            <div class="mt-12 space-y-8">
                {#each content.sections ?? [] as section}
                    <section class="rounded-2xl border border-white/10 bg-white/[0.04] p-6 sm:p-8">
                        <h2 class="text-xl font-medium text-white">{section.heading}</h2>
                        <p class="mt-3 leading-8 text-stone-400">{section.body}</p>
                    </section>
                {/each}
            </div>
        </div>
    </main>
{:else}
    <main class="grid min-h-screen place-items-center bg-[#080808] px-6 text-center text-stone-100"><div><p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">404</p><h1 class="mt-4 text-4xl font-semibold text-white">Help page not found.</h1><a class="mt-8 inline-block rounded-full bg-amber-300 px-6 py-3 text-sm font-semibold text-black" href="/storefront">Return to collections</a></div></main>
{/if}
