<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Demo Account Successfully Created - VenueBoost</title>
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
    <p>Hello {{$venue_name}},</p>

    <p>Your demo account has been successfully created on VenueBoost! We're thrilled to have you on board. Below are your login details:</p>
    <p><strong>Email:</strong> {{$user_email}}</p>
    <p><strong>Password:</strong> {{$user_password}}</p>

    <p>You can now log in to VenueBoost and start exploring all the features and benefits we have to offer. Simply click on the button below to access your account:</p>
    <a class="button" style="margin-bottom: 20px; color: white; background: #2e273b;" href="https://admin.venueboost.io">Login to VenueBoost</a>

    <p>If you have any questions or need assistance, please don't hesitate to contact our support team at <a href="mailto:contact@venueboost.io">contact@venueboost.io</a>. We're here to help you make the most out of your VenueBoost experience.</p>

    <p>Thank you for choosing VenueBoost. We're excited to support your venue's success.</p>

    <div class="footer">
        <p>Best regards,<br>The VenueBoost Team</p>
    </div>
</div>
</body>
</html>
