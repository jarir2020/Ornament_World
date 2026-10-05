<script>
    const categories = ['Bracelets', 'Chains', 'Rings', 'Lockets'];
</script>

<section class="min-h-[70vh] bg-black px-6 py-16 text-white sm:px-10 lg:px-16">
    <div class="mx-auto max-w-6xl">
        <p class="mb-4 text-xs font-semibold uppercase tracking-[0.35em] text-amber-400">
            Integrated Svelte foundation
        </p>
        <h1 class="max-w-3xl text-4xl font-semibold tracking-tight sm:text-6xl">
            Ornaments World
        </h1>
        <p class="mt-6 max-w-2xl text-base leading-8 text-zinc-300 sm:text-lg">
            Luxury men's jewellery with a simple shopping experience. The storefront
            will grow inside the Nemesis application using Svelte components and
            server-rendered view props.
        </p>

        <div class="mt-10 flex flex-wrap gap-3">
            {#each categories as category}
                <span class="rounded-full border border-amber-400/40 px-4 py-2 text-sm text-amber-100">
                    {category}
                </span>
            {/each}
        </div>
    </div>
</section>
