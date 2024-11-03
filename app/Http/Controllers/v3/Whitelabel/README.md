# part of doSyncStock
```php
// Update  total stock (Sum of the stock on each warehouse) on each product
// foreach ($updated_article_codes as $product_code) {

//     $product_calculated_data = ProductStock::select('article_no', DB::raw('SUM(stock_quantity) as quantity'), 'alpha_warehouse')
//         ->where('article_no', $product_code)
//         ->whereIn('alpha_warehouse', ['MQ', '01', '02', '03', 'MPB'])
//         ->first();

//     DB::table('store_products_variants')->where('article_no', '=', $product_code)->update([
//         'stock_quantity' => intval($product_calculated_data->quantity) === -1 ? 0 : intval($product_calculated_data->quantity),
//         'warehouse_alpha' => $product_calculated_data->alpha_warehouse,
//         'syncronize_at' => now()
//     ]);

//     DB::table('store_products')->where('article_no', '=', $product_code)->update([
//         'stock_quantity' => intval($product_calculated_data->quantity) === -1 ? 0 : intval($product_calculated_data->quantity),
//         'warehouse_alpha' => $product_calculated_data->alpha_warehouse,
//         'syncronize_at' => now()
//     ]);
// }

// $extractedVariants = DB::table('store_products_variants')
//     ->select('product_id', 'warehouse_alpha', DB::raw('SUM(stock_quantity) as total'))
//     ->whereNotNull('stock_quantity')
//     ->groupBy('product_id', 'warehouse_alpha')
//     ->get();

// foreach ($extractedVariants as $variant) {
//     DB::table('store_products')->where('id', '=', $variant->product_id)->update([
//         'stock_quantity' => intval($variant->total) === -1 ? 0 : intval($variant->total),
//         'warehouse_alpha' => $variant->warehouse_alpha,
//         'syncronize_at' => now()
//     ]);
// }
```
