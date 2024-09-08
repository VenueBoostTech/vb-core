<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>VenueBoost Demo Approval and Request for Additional Information</title>
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
    <p>Hello {{$venue_name}},</p>

    <p>Congratulations! Your VenueBoost registration application has been approved.
        We're excited to have you on board! To proceed with the registration process, please click the on Complete Registration below:</p>
    <a class="button" style="margin-bottom: 20px;color: white; background: #2e273b;" href="{{$link}}">Complete Registration</a>

    <p>If you encounter any issues during the registration process or have any questions, please don't hesitate to reach out to our support team at
        <a href="mailto:contact@venueboost.io">contact@venueboost.io</a>. We are here to support you throughout the process.</p>

    <p>Thank you for choosing VenueBoost. We're confident that our platform will help elevate your venue operations and provide a seamless customer experience. We look forward to partnering with you.
    </p>

    <div class="footer">
        <p>Best regards,<br>The VenueBoost Team</p>
    </div>
</div>
</body>
</html>
