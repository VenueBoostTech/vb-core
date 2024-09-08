<!DOCTYPE html>
<html>
<head>
    <style>
        /* Add your custom CSS styles for the menu here */
        body {
            font-family: Arial, sans-serif;
            background-color: #ffffff;
        }

        h1 {
            color: #000000;
        }

        .category {
            margin-bottom: 20px;
        }

        .category-title {
            font-size: 20px;
            font-weight: bold;
        }

        .product {
            margin-bottom: 10px;
        }

        .product-name {
            font-weight: bold;
        }

        .product-price {
            font-style: italic;
            color: #999999;
        }
    </style>
</head>
<body>
<h1>Restaurant Menu</h1>

@foreach ($categories as $category)
    <div class="category">
        <div class="category-title">{{ $category->name }}</div>
        @foreach ($category->products as $product)
            <div class="product">
                <div class="product-name">{{ $product->name }}</div>
                <div class="product-price">${{ $product->price }}</div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
