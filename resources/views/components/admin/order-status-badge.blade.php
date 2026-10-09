@use('App\Enums\OrderStatus')

@props(['status'])

<span @class([
    'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
    'bg-amber-100 text-amber-800' => $status === OrderStatus::Placed,
    'bg-blue-100 text-blue-800' => in_array($status, [OrderStatus::Confirmed, OrderStatus::Processing], true),
    'bg-indigo-100 text-indigo-800' => $status === OrderStatus::Shipped,
    'bg-green-100 text-green-800' => $status === OrderStatus::Delivered,
    'bg-red-100 text-red-800' => $status === OrderStatus::Cancelled,
])>{{ $status->label() }}</span>
