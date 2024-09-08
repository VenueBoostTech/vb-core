<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to VenueBoost! Verify your Email Address</title>
    <style>

        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&display=swap');

        /* CSS styles for the email template */
        body {
            font-family: 'Manrope', sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            color: #333333;
            font-size: 24px;
            line-height: 30px;
            margin: 0;
            padding: 0;
        }

        p {
            color: #666666;
            font-size: 16px;
            line-height: 24px;
            margin: 0 0 10px;
            padding: 0;
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .button {
            display: inline-block;
            background-color: #3498db;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">

    {{--    <div class="logo">--}}
    {{--        <img src="https://venueboost.io/static/media/logo.e8f564e786e99e6a3610e53d5f04df0a.svg" alt="VenueBoost Logo" width="150">--}}
    {{--    </div>--}}
    <p>Hello {{$name}},</p>

    <p>Thank you for registering with VenueBoost! We're excited to have you on board. Before you can access your VenueBoost account, we need to verify your email address.
        To complete the verification process, please click on Verify Email below:</p>
    <a class="button" style="margin-bottom: 20px;color: white; background: #2e273b;" href="{{$link}}">Verify Email</a>

    <p>Once your email address is verified, you'll be able to log in to VenueBoost and start exploring all the features and benefits we have to offer.</p>
    <p>If you encounter any issues during the verification of email or have any questions, please don't hesitate to reach out to our support team at
        <a href="mailto:contact@venueboost.io">contact@venueboost.io</a>. We are here to support you throughout the process.</p>

    <p>Thank you again for choosing VenueBoost. We look forward to supporting your venue's success.
    </p>

    <div class="footer">
        <p>Best regards,<br>The VenueBoost Team</p>
    </div>
</div>
</body>
</html>
