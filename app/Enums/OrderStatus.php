<?php

namespace App\Enums;

class OrderStatus
{
    const RESERVATION_CONFIRMED = 'reservation_confirmed';
    const RESERVATION_CANCELLED = 'reservation_cancelled';
    const RESERVATION_COMPLETED = 'reservation_completed';
    const NEW_ORDER = 'new';
    const ON_HOLD = 'on_hold';
    const PROCESSING = 'processing';
    const ORDER_CANCELLED = 'order_cancelled';
    const ORDER_COMPLETED = 'order_completed';
    const DELIVERY_CONFIRMED = 'delivery_confirmed';
    const DELIVERY_CANCELLED = 'delivery_cancelled';
    const DELIVERY_COMPLETED = 'delivery_completed';
    const PICKUP_CONFIRMED = 'pickup_confirmed';
    const PICKUP_CANCELLED = 'pickup_cancelled';
    const PICKUP_COMPLETED = 'pickup_completed';
    const ORDER_CONFIRMED = 'order_confirmed';
    const ORDER_ON_DELIVERY = 'order_on_delivery';
    const ORDER_READY_FOR_PICKUP = 'order_ready_for_pickup'; // preparation completed (cooked, packed, etc.)
    const ORDER_PAID = 'Paid';
}
