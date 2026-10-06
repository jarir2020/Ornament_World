<script>
    export let mode = 'login';
    export let csrfToken = '';
    export let errorCode = '';

    $: isRegister = mode === 'register';
    $: errorMessage = errorCode === 'invalid_credentials'
        ? 'The email or password was not recognized.'
        : errorCode === 'registration_failed'
            ? 'We could not create the account. Check the details and try again.'
            : '';
</script>

<svelte:head>
    <title>{isRegister ? 'Create account' : 'Sign in'} | Ornaments World</title>
    <meta name="description" content={isRegister ? 'Create an optional Ornaments World customer account.' : 'Sign in to your optional Ornaments World customer account.'} />
</svelte:head>

<main class="grid min-h-screen place-items-center bg-[#080808] px-6 py-12 text-stone-100 sm:px-10">
    <section class="w-full max-w-lg rounded-3xl border border-amber-200/20 bg-white/[0.04] p-7 shadow-2xl shadow-amber-200/5 sm:p-10">
        <a class="text-sm text-amber-300 hover:text-amber-100" href="/storefront">← Back to storefront</a>
        <p class="mt-10 text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Your Ornaments World</p>
        <h1 class="mt-3 text-4xl font-semibold text-white">{isRegister ? 'Create your account.' : 'Welcome back.'}</h1>
        <p class="mt-4 leading-7 text-stone-400">{isRegister ? 'Save your details for faster future orders. Guest checkout is always available.' : 'Sign in to view your profile and account-linked order history.'}</p>

        {#if errorMessage}
            <div class="mt-6 rounded-xl border border-rose-300/30 bg-rose-300/10 px-4 py-3 text-sm text-rose-100" role="alert">{errorMessage}</div>
        {/if}

        <form class="mt-8 space-y-5" method="POST" action={isRegister ? '/register' : '/login'}>
            <input type="hidden" name="_token" value={csrfToken} />
            {#if isRegister}
                <label class="block text-sm text-stone-300">Full name
                    <input name="name" required autocomplete="name" minlength="2" maxlength="140" class="mt-2 w-full rounded-xl border border-white/15 bg-black/30 px-4 py-3 text-white outline-none focus:border-amber-200" />
                </label>
            {/if}
            <label class="block text-sm text-stone-300">Email
                <input name="email" type="email" required autocomplete="email" class="mt-2 w-full rounded-xl border border-white/15 bg-black/30 px-4 py-3 text-white outline-none focus:border-amber-200" />
            </label>
            {#if isRegister}
                <label class="block text-sm text-stone-300">Bangladesh mobile <span class="text-stone-500">(optional)</span>
                    <input name="phone" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" class="mt-2 w-full rounded-xl border border-white/15 bg-black/30 px-4 py-3 text-white outline-none focus:border-amber-200" />
                </label>
            {/if}
            <label class="block text-sm text-stone-300">Password
                <input name="password" type="password" required minlength={isRegister ? 12 : 1} autocomplete={isRegister ? 'new-password' : 'current-password'} class="mt-2 w-full rounded-xl border border-white/15 bg-black/30 px-4 py-3 text-white outline-none focus:border-amber-200" />
            </label>
            {#if isRegister}
                <label class="block text-sm text-stone-300">Confirm password
                    <input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" class="mt-2 w-full rounded-xl border border-white/15 bg-black/30 px-4 py-3 text-white outline-none focus:border-amber-200" />
                </label>
            {/if}
            <button class="w-full rounded-full bg-amber-300 px-6 py-4 text-sm font-semibold text-black hover:bg-amber-200" type="submit">{isRegister ? 'Create account' : 'Sign in'}</button>
        </form>

        <p class="mt-7 text-center text-sm text-stone-400">
            {#if isRegister}Already have an account? <a class="text-amber-200 hover:text-amber-100" href="/login">Sign in</a>{:else}New here? <a class="text-amber-200 hover:text-amber-100" href="/register">Create an account</a>{/if}
        </p>
        <p class="mt-4 text-center text-xs text-stone-600"><a href="/checkout">Continue as guest at checkout</a></p>
    </section>
</main>
