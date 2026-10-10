<x-layouts.app title="Profile">
    <div class="mx-auto flex max-w-2xl flex-col gap-6">
        <h1 class="text-2xl font-bold">Profile</h1>

        <section class="rounded-2xl bg-white p-6 shadow-xs sm:p-8" aria-labelledby="details-heading">
            <h2 id="details-heading" class="text-lg font-bold">Your details</h2>

            <form method="POST" action="{{ route('profile.update') }}" class="mt-4 flex flex-col gap-4">
                @csrf
                @method('PUT')

                <x-form.input name="name" label="Name" :value="$user->name" required maxlength="255" autocomplete="name" />
                <x-form.input name="email" label="Email" type="email" :value="$user->email" required maxlength="255" autocomplete="email" />

                <div>
                    <x-form.button>Save details</x-form.button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-xs sm:p-8" aria-labelledby="password-heading">
            <h2 id="password-heading" class="text-lg font-bold">Change password</h2>

            <form method="POST" action="{{ route('profile.password.update') }}" class="mt-4 flex flex-col gap-4">
                @csrf
                @method('PUT')

                <x-form.input name="current_password" label="Current password" type="password" required autocomplete="current-password" />
                <x-form.input name="password" label="New password" type="password" required autocomplete="new-password" hint="At least 8 characters." />
                <x-form.input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />

                <div>
                    <x-form.button>Change password</x-form.button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.app>
