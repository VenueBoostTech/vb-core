<?php
namespace App\Services;

use App\Models\Product;
use App\Models\InventoryRetail;
use App\Models\InventoryWarehouse;
use App\Models\InventoryWarehouseProduct;
use App\Models\ProductStock;
use App\Models\InventoryActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class InventoryService
{
    private $alphaWarehouseMappings = [
        'MQ' => 'MAIN',
        '01' => 'WAREHOUSE_1',
        '02' => 'WAREHOUSE_2',
        '03' => 'WAREHOUSE_3',
        'MPB' => 'MPB'
    ];

    public function checkStockAvailability(Product $product, int $requestedQuantity): bool
    {
        // Check inventory_retail first (source of truth)
        $retailStock = InventoryRetail::where('product_id', $product->id)->first();
        if (!$retailStock || !$retailStock->manage_stock) {
            return false;
        }

        return $retailStock->stock_quantity >= $requestedQuantity;
    }

    public function decreaseStock(Product $product, int $quantity, int $orderId): bool
    {
        try {
            DB::beginTransaction();

            // 1. Create Alpha invoice first
            $alphaInvoice = $this->createAlphaInvoice($product, $quantity, $orderId);
            if (!$alphaInvoice['success']) {
                throw new \Exception('Failed to create Alpha invoice: ' . $alphaInvoice['message']);
            }

            // 2. Get and update retail stock
            $retailStock = InventoryRetail::where('product_id', $product->id)->first();
            if (!$retailStock || $retailStock->stock_quantity < $quantity) {
                throw new \Exception('Insufficient stock');
            }

            $previousRetailStock = $retailStock->stock_quantity;
            $retailStock->stock_quantity -= $quantity;
            $retailStock->save();

            // 3. Update product total stock
            $previousTotalStock = $product->stock_quantity;
            $product->stock_quantity -= $quantity;
            $product->save();

            // 4. Update product_stock for all warehouses
            $productStocks = ProductStock::where('article_no', $product->article_no)
                ->get();

            $warehouseStocksBefore = $productStocks->map(function($stock) {
                return [
                    'warehouse' => $stock->alpha_warehouse,
                    'quantity' => $stock->stock_quantity
                ];
            })->toArray();

            // Update the primary warehouse stock
            if ($retailStock->warehouse_id) {
                $primaryWarehouseStock = ProductStock::where([
                    'article_no' => $product->article_no,
                    'alpha_warehouse' => $this->getAlphaWarehouseCode($retailStock->warehouse_id)
                ])->first();

                if ($primaryWarehouseStock) {
                    $primaryWarehouseStock->stock_quantity -= $quantity;
                    $primaryWarehouseStock->save();
                }

                // Update our warehouse stock
                $warehouseProduct = InventoryWarehouseProduct::where([
                    'inventory_warehouse_id' => $retailStock->warehouse_id,
                    'product_id' => $product->id
                ])->first();

                if ($warehouseProduct) {
                    $warehouseProduct->quantity -= $quantity;
                    $warehouseProduct->save();
                }
            }

            // 5. Record detailed activity
            InventoryActivity::create([
                'product_id' => $product->id,
                'order_id' => $orderId,
                'quantity' => $quantity,
                'inventory_retail_id' => $retailStock->id,
                'activity_type' => 'deduct',
                'activity_category' => 'ORDER_SALE',
                'metadata' => [
                    'alpha_invoice_number' => $alphaInvoice['invoice_number'],
                    'previous_retail_stock' => $previousRetailStock,
                    'new_retail_stock' => $retailStock->stock_quantity,
                    'previous_total_stock' => $previousTotalStock,
                    'new_total_stock' => $product->stock_quantity,
                    'warehouse_stocks_before' => $warehouseStocksBefore,
                    'warehouse_id' => $retailStock->warehouse_id,
                    'sync_pending' => true
                ]
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function syncWarehouseStocks(Product $product): void
    {
        try {
            DB::beginTransaction();

            // Get Alpha stocks
            $alphaStocks = ProductStock::where('article_no', $product->article_no)
                ->get()
                ->groupBy('alpha_warehouse');

            $previousStocks = [];

            // Update our warehouse stocks
            foreach ($alphaStocks as $alphaCode => $stocks) {
                $totalStock = $stocks->sum('stock_quantity');
                $warehouse = $this->getWarehouseFromAlphaCode($alphaCode);

                if (!$warehouse) continue;

                $previousStock = InventoryWarehouseProduct::where([
                    'inventory_warehouse_id' => $warehouse->id,
                    'product_id' => $product->id
                ])->value('quantity');

                $previousStocks[$alphaCode] = $previousStock;

                InventoryWarehouseProduct::updateOrCreate(
                    [
                        'inventory_warehouse_id' => $warehouse->id,
                        'product_id' => $product->id
                    ],
                    ['quantity' => $totalStock]
                );
            }

            // Update retail stock if necessary
            $retailStock = InventoryRetail::where('product_id', $product->id)->first();
            if ($retailStock && $retailStock->warehouse_id) {
                $alphaCode = $this->getAlphaWarehouseCode($retailStock->warehouse_id);
                if (isset($alphaStocks[$alphaCode])) {
                    $retailStock->stock_quantity = $alphaStocks[$alphaCode]->sum('stock_quantity');
                    $retailStock->save();
                }
            }

            // Update total product stock
            $totalStock = $alphaStocks->sum(function($stocks) {
                return $stocks->sum('stock_quantity');
            });

            $product->stock_quantity = $totalStock;
            $product->save();

            // Record sync activity
            InventoryActivity::create([
                'product_id' => $product->id,
                'activity_type' => 'sync',
                'activity_category' => 'ALPHA_SYNC',
                'metadata' => [
                    'previous_stocks' => $previousStocks,
                    'new_stocks' => $alphaStocks->toArray(),
                    'sync_time' => now()
                ]
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function createAlphaInvoice(Product $product, int $quantity, int $orderId): array
    {
        try {
            $response = Http::withHeaders([
                "Content-Type" => "application/json",
                "ndermarrjaserver" => "Alpha Web",
                "connectionstringname" => "by-best-duty-free",
                "authorization" => "Basic ZWNvbTo4MzQ4ODE2ZjI0YTk2ZDdlMTRjMjIwYzFjYzQxOTJlYWNiNTFhMGM3YjE1YzI5NTkyNzQyODViNDdlOTM0YjYz"
            ])->post('http://node.alpha.al/createInvoice', [
                'product_code' => $product->article_no,
                'quantity' => $quantity,
                'order_reference' => $orderId,
                'warehouse_code' => $this->getAlphaWarehouseCode($product->inventoryRetail?->warehouse_id)
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'invoice_number' => $data['invoice_number'] ?? null
                ];
            }

            return [
                'success' => false,
                'message' => $response->body()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function getWarehouseFromAlphaCode(string $alphaCode): ?InventoryWarehouse
    {
        $ourCode = $this->alphaWarehouseMappings[$alphaCode] ?? null;
        if (!$ourCode) return null;

        return InventoryWarehouse::where('code', $ourCode)->first();
    }

    private function getAlphaWarehouseCode(?int $warehouseId): ?string
    {
        if (!$warehouseId) return null;

        $warehouse = InventoryWarehouse::find($warehouseId);
        if (!$warehouse) return null;

        return array_search($warehouse->code, $this->alphaWarehouseMappings) ?: null;
    }
}
