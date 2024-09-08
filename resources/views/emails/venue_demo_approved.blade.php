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

    <p>We are pleased to inform you that your application for a VenueBoost demo has been approved! Congratulations! We are excited to help you elevate your venue with our powerful platform.</p>

    <p>To proceed with setting up your demo account, we kindly request the following additional information from you:</p>

    <ol>
        <li>
            <strong>Venue Type:</strong> Please provide us with details about the type of venue you operate, such as a restaurant, bar, cafe, or any other relevant information.
        </li>
        <li>
            <strong>Address Details:</strong> Please provide the complete address of your venue, including street name, city, state/province, and zip/postal code.
        </li>
    </ol>

    <p>Once we receive this information, our team will create your demo account and provide you with the necessary login credentials. You will then have the opportunity to explore the features and benefits of VenueBoost firsthand.</p>

    <p>Should you have any questions or require further assistance, please feel free to reach out to us at <a href="mailto:contact@venueboost.io">contact@venueboost.io</a>. We are here to support you throughout the process.</p>

    <p>Thank you for choosing VenueBoost. We look forward to helping you optimize your venue operations and enhance the customer experience.</p>

    <div class="footer">
        <p>Best regards,<br>The VenueBoost Team</p>
    </div>
</div>
</body>
</html>
