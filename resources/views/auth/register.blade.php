<x-layouts.app title="Register">
    <div class="mx-auto max-w-md rounded-2xl bg-white p-6 shadow-xs sm:p-8">
        <h1 class="text-2xl font-bold">Create an account</h1>

        <form method="POST" action="{{ route('register.store') }}" class="mt-6 flex flex-col gap-4">
            @csrf

            <x-form.input name="name" label="Name" required autofocus autocomplete="name" />
            <x-form.input name="email" label="Email" type="email" required autocomplete="email" />
            <x-form.input name="password" label="Password" type="password" required autocomplete="new-password" />
            <x-form.input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />

            <x-form.button>Register</x-form.button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            Already registered? <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:underline">Login</a>
        </p>
    </div>
</x-layouts.app>
