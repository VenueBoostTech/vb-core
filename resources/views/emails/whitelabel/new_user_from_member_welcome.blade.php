<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>Mirë se vini në Platformën Tonë!</title>
</head>
<body
    style="
         font-family: Arial, sans-serif;
         color: #333;
         background-color: #f5f5f5;
         padding: 0 10px;

      "
>
<div style="max-width: 600px; margin: 0 auto; text-align: center">
    <img
        src="https://bybest.shop/assets/img/bybest-logo.png"
        alt="ByBest Shop"
        style="margin: 24px auto"
    />

    <div
        style="
               font-size: 16px;
               line-height: 1.5;
               border: 1px solid #ddd;
               border-radius: 16px;
               background-color: #fff;
               padding: 20px;
               text-align: left;
            "
    >
        <div
            style="
                  color: #000;
                  font-size: 32px;
                  text-align: center;
                  line-height: 46px;
                  font-weight: bold;
                  font-family: Arial, sans-serif;
               "
        >
            <p style="margin: 0">Mirë se vjen, {{ $userName }}!</p>
        </div>
        <p style="margin: 24px auto">
            Ne jemi të lumtur që ju kemi në platformën tonë. Llogaria juaj është
            krijuar me sukses.
        </p>
        <p style="margin: 24px auto">
            Detajet tuaja të hyrjes janë si më poshtë:
        </p>
        <ul style="margin: 24px auto">
            <li><strong>Email:</strong> {{ $userEmail }}</li>
            <li><strong>Fjalëkalimi:</strong> {{ $password }}</li>
        </ul>
        <p style="margin: 24px auto">
            Ju lutemi, hyni dhe ndryshoni fjalëkalimin tuaj sa më parë që të
            siguroni llogarinë tuaj.
        </p>
        <div style="width: 100%; text-align: center; margin: 0 auto">
            <a
                style="
                     margin: 24px auto;
                     padding: 12px 20px;
                     text-decoration: none;
                     color: #fff;
                     background-color: #cb0000;
                     width: fit-content;
                     border-radius: 6px;
                  "
                href="https://bybest.shop/login"
            >
                Hyni tani
            </a>
        </div>
        <div style="margin: 24px auto">
            <p style="margin: 0">Faleminderit që u bashkuat me ne!</p>
            <p style="color: #ed1c24; font-weight: bold; margin: 0">
                Ekipi ByBest Shop
            </p>
        </div>
    </div>
    <div
        style="
               margin: 24px auto;
               text-align: center;
               margin-top: 20px;
               font-size: 12px;
               color: #666;
            "
    >
        &copy; {{ date('Y') }} ByBest Shop.
    </div>
</div>
</body>
</html>
