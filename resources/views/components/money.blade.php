@props(['amount'])

<span {{ $attributes }}>&#8377;{{ number_format((float) $amount, 2) }}</span>
