<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Welcome to Staffluent!</title>
    <style>
        body {
            font-family: 'Manrope', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            padding: 20px 10px;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 24px;
            border-radius: 16px;
            background-color: #fff;
            text-align: left;
        }
        .title {
            color: #000;
            font-size: 32px;
            text-align: center;
            line-height: 46px;
            font-weight: bold;
            margin: 24px 0;
        }
        .content {
            font-size: 16px;
            line-height: 24px;
            color: #333;
            margin: 24px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 20px;
            text-decoration: none;
            color: #fff;
            background-color: #1964b7; /* Your specified color */
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
            text-align: center;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
<img src="https://venueboost.io/staffluent.png" alt="Staffluent" style="display: block; margin: 24px auto; width: 150px" />
<div class="container">
    <div class="title">Welcome to Staffluent!</div>
    <p class="content">
        You have been successfully added as a staff member at {{ $venue->name }}. We're excited to have you on board!
    </p>
    <p class="content">Thank you for being part of our team!</p>
    <p class="content">
        Your email and password credentials will be provided by your manager. If you have any questions or need assistance, please reach out to him.
    </p>
    <div style="text-align: center; margin: 20px 0">
        <a href="https://apps.apple.com/app/id123456789" class="button">
            <img src="https://venueboost.io/appstore-btn.png" alt="Download on iOS" style="height: 40px;"/>
        </a>
        <a href="https://play.google.com/store/apps/details?id=com.staffluent" class="button">
            <img src="https://venueboost.io/googleplay-btn.png" alt="Get it on Android" style="height: 40px;"/>
        </a>
    </div>
    <p class="content">Regards,<br />The Staffluent Team</p>
</div>
<div class="footer">&copy; {{ date('Y') }} Staffluent. All rights reserved.</div>
</body>
</html>
