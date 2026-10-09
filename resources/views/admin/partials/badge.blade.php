@php
    $colors = [
        'pending' => 'bg-amber-100 text-amber-700', 'awaiting_payment' => 'bg-yellow-100 text-yellow-800', 'confirmed' => 'bg-blue-100 text-blue-700',
        'preparing' => 'bg-orange-100 text-orange-700', 'ready' => 'bg-teal-100 text-teal-700',
        'rider_assigned' => 'bg-indigo-100 text-indigo-700', 'picked_up' => 'bg-indigo-100 text-indigo-700',
        'on_the_way' => 'bg-purple-100 text-purple-700', 'delivered' => 'bg-success/10 text-success',
        'completed' => 'bg-success/10 text-success', 'rejected' => 'bg-red-100 text-red-700',
        'cancelled' => 'bg-red-100 text-red-700', 'paid' => 'bg-success/10 text-success',
        'unpaid' => 'bg-amber-100 text-amber-700', 'refunded' => 'bg-gray-100 text-gray-600',
        'active' => 'bg-success/10 text-success', 'blocked' => 'bg-red-100 text-red-700',
        'inactive' => 'bg-gray-100 text-gray-600',
    ];
@endphp
<span class="px-2 py-1 rounded-full text-xs whitespace-nowrap {{ $colors[$status] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
