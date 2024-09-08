<?php

namespace App\Enums;

class InventoryActivityCategory
{
    const ORDER_SALE = 'order_sale';
    const INVENTORY_ITEM_UPDATE = 'inventory_item_update';
    const INVENTORY_ITEM_SYNC_FROM_THIRD_PARTY = 'inventory_item_sync_from_third_party';
}
