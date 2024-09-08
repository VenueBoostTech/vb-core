<?php

namespace App\Console\Commands;

use App\Enums\InventoryActivityCategory;
use App\Models\Inventory;
use App\Models\InventoryActivity;
use App\Models\Product;
use Illuminate\Console\Command;

class ThirdPartySyncInventories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventories:third-party-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync inventories from third party sources';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Assume $receivedProducts is an array of products and quantities received
        // from the 3rd party module when integrating with it
        $receivedProducts = [
            [
                'title' => 'Product 1',
                'quantity' => 10
            ],
            [
                'title' => 'Product 2',
                'quantity' => 20
            ],
            [
                'title' => 'Product 3',
                'quantity' => 30
            ],
        ];

        // TODO: after v1 testing replace this with the actual restaurant ID
        $restaurantId = 1;

        $inventory = Inventory::where('restaurant_id', $restaurantId)
            ->find(1);

        foreach ($receivedProducts as $receivedProduct) {
            $productName = $receivedProduct['title'];
            $newQuantity = $receivedProduct['quantity'];

            // Find the product in your inventory by title
            $product = Product::where('title', $productName)->first();

            if ($product) {
                // Update the quantity of the product in your inventory
                $inventoryProduct = $inventory->products()->where('product_id', $product->id)->first();
                if ($inventoryProduct) {
                    $oldQuantity = $inventoryProduct->pivot->quantity;
                    $inventoryProduct->pivot->quantity = $newQuantity;
                    $inventoryProduct->pivot->save();

                    // Create an InventoryActivity record for the update
                    $activity = new InventoryActivity();
                    $activity->product_id = $product->id;
                    $activity->quantity = $newQuantity - $oldQuantity;
                    $activity->activity_category = InventoryActivityCategory::INVENTORY_ITEM_SYNC_FROM_THIRD_PARTY;
                    $activity->activity_type = ($newQuantity > $oldQuantity) ? 'add' : 'deduct';
                    $activity->inventory_id = $inventory->id;
                    $activity->order_id = null;
                    $activity->save();
                }
            }
        }
    }
}
