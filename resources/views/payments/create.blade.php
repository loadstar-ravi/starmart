<x-layouts.app title="Pay for your order">
    <div class="mx-auto max-w-md rounded-2xl bg-white p-6 shadow-xs sm:p-8">
        <h1 class="text-2xl font-bold">Pay for your order</h1>
        <p class="mt-2 text-slate-600">Order number <span class="font-semibold text-slate-900">{{ $order->order_number }}</span></p>

        @if ($lastAttemptFailed)
            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Your last payment attempt failed. Nothing was charged, and you can try again.</p>
        @endif

        @if ($errors->any())
            <div class="mt-4 flex flex-col gap-1 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <dl class="mt-6 flex items-center justify-between gap-4 border-y border-slate-200 py-4">
            <dt class="font-semibold">Amount to pay</dt>
            <dd class="text-lg font-bold"><x-money :amount="$order->total_amount" /></dd>
        </dl>

        <form method="POST" action="{{ route('payments.store') }}" data-submit-once class="mt-6 flex flex-col gap-6">
            @csrf
            <input type="hidden" name="order_id" value="{{ $order->id }}">
            <input type="hidden" name="amount" value="{{ $order->total_amount }}">

            <fieldset class="flex flex-col gap-3">
                <legend class="text-sm font-medium text-slate-700">Test outcome</legend>
                <p class="text-sm text-slate-500">This is a simulated payment, so no money is taken. Choose how it should turn out.</p>

                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-300 px-4 py-3 text-sm font-medium has-checked:border-indigo-600 has-checked:bg-indigo-50">
                    <input type="radio" name="simulate" value="success" @checked(old('simulate', 'success') !== 'failed') class="accent-indigo-600">
                    Successful payment
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-300 px-4 py-3 text-sm font-medium has-checked:border-indigo-600 has-checked:bg-indigo-50">
                    <input type="radio" name="simulate" value="failed" @checked(old('simulate') === 'failed') class="accent-indigo-600">
                    Failed payment
                </label>
            </fieldset>

            <x-form.button data-busy-label="Paying…" class="w-full disabled:cursor-not-allowed disabled:opacity-60">Pay <x-money :amount="$order->total_amount" /></x-form.button>
        </form>

        <a href="{{ route('orders.success', $order->order_number) }}" class="mt-4 block text-center text-sm font-medium text-indigo-600 hover:underline">Pay later</a>
    </div>
</x-layouts.app>
