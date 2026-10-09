@use('App\Enums\PaymentStatus')

@props(['status'])

<span @class([
    'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
    'bg-amber-100 text-amber-800' => $status === PaymentStatus::Pending,
    'bg-green-100 text-green-800' => $status === PaymentStatus::Success,
    'bg-red-100 text-red-800' => $status === PaymentStatus::Failed,
    'bg-slate-200 text-slate-600' => $status === PaymentStatus::Refunded,
])>{{ $status->label() }}</span>
