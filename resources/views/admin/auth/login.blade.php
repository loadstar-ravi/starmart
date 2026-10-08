<x-layouts.admin title="Login">
    <div class="mx-auto max-w-md rounded-2xl bg-white p-6 shadow-xs sm:p-8">
        <h1 class="text-2xl font-bold">Admin login</h1>

        <form method="POST" action="{{ route('admin.login.store') }}" class="mt-6 flex flex-col gap-4">
            @csrf

            <x-form.input name="email" label="Email" type="email" required autofocus autocomplete="email" />
            <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />

            <x-form.button>Login</x-form.button>
        </form>
    </div>
</x-layouts.admin>
