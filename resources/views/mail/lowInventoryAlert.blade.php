<!DOCTYPE html>
<html>
<head>
    <title>Low Inventory Alert</title>
</head>
<body>
<h1>Low Inventory Alert</h1>
<p>The following items have reached low inventory levels:</p>
<table>
    <thead>
    <tr>
        
        <th>Item Name</th>
        <th>Current Quantity</th>
        <th>Reorder Point</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($lowInventoryItems as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ $item->reorder_point }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<p>Please take action to reorder these items as soon as possible to avoid stock-outs.</p>
</body>
</html>
