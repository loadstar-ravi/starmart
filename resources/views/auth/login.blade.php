<x-layouts.app title="Login">
    <div class="mx-auto max-w-md rounded-2xl bg-white p-6 shadow-xs sm:p-8">
        <h1 class="text-2xl font-bold">Login</h1>

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 flex flex-col gap-4">
            @csrf

            <x-form.input name="email" label="Email" type="email" required autofocus autocomplete="email" />
            <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                Remember me
            </label>

            <x-form.button>Login</x-form.button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            New here? <a href="{{ route('register') }}" class="font-semibold text-indigo-600 hover:underline">Create an account</a>
        </p>
    </div>
</x-layouts.app>
