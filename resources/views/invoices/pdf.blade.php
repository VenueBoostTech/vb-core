<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice->number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .invoice-info {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        .total {
            text-align: right;
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>Invoice #{{ $invoice->number }}</h1>
    <p>{{ $invoice->issue_date->format('M d, Y') }}</p>
</div>

<div class="invoice-info">
    <div>
        <strong>To:</strong><br>
        {{ $invoice->client->name }}<br>
        {{ $invoice->client->address?->full_address }}<br>
        {{ $invoice->client->phone }}<br>
        {{ $invoice->client->email }}
    </div>
</div>

<table>
    <thead>
    <tr>
        <th>Description</th>
        <th>Quantity</th>
        <th>Rate</th>
        <th>Amount</th>
    </tr>
    </thead>
    <tbody>
    @foreach($invoice->items as $item)
        <tr>
            <td>{{ $item->description }}</td>
            <td>{{ $item->quantity }}</td>
            <td>${{ number_format($item->rate, 2) }}</td>
            <td>${{ number_format($item->amount, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="total">
    <p>
        <strong>Subtotal:</strong> ${{ number_format($invoice->amount, 2) }}<br>
        <strong>Tax:</strong> ${{ number_format($invoice->tax_amount, 2) }}<br>
        <strong>Total:</strong> ${{ number_format($invoice->total_amount, 2) }}
    </p>
</div>
</body>
</html>
