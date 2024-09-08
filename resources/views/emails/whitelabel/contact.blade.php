<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Contact Form Submission</title>
    <style type="text/css">
        body {
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            font-family: 'Manrope', Arial, sans-serif;
            background-color: #fafafa;
        }
        img {
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
        }
        .header img {
            max-width: 100%;
            height: auto;
        }
        .content {
            font-size: 16px;
            color: #323338;
            line-height: 1.5;
        }
        .content h1 {
            font-size: 24px;
            color: #4a4a4a;
            margin-bottom: 16px;
        }
        .content p {
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header" style="text-align: center; padding: 20px 0;">
        <a href="https://venueboost.io"><img src="https://venueboost.io/static/media/logo-png-header.7c3ed4ed1731d48567be.png" alt="VenueBoost Logo"></a>
    </div>
    <div class="content">
        <h1>New Contact Form Submission</h1>
        <p>Hello {{ $venueName }},</p>
        <p>We have received a new contact form submission on your website. Here are the details:</p>
        <table>
            @if(!empty($fullName))
                <tr>
                    <td><strong>Full Name:</strong></td>
                    <td>{{ $fullName }}</td>
                </tr>
            @endif
            @if(!empty($email))
                <tr>
                    <td><strong>Email:</strong></td>
                    <td>{{ $email }}</td>
                </tr>
            @endif
            @if(!empty($phone))
                <tr>
                    <td><strong>Phone:</strong></td>
                    <td>{{ $phone }}</td>
                </tr>
            @endif
            @if(!empty($subject_i))
                <tr>
                    <td><strong>Subject:</strong></td>
                    <td>{{ $subject_i }}</td>
                </tr>
            @endif
            @if(!empty($content))
                <tr>
                    <td><strong>Message:</strong></td>
                    <td>{{ $content }}</td>
                </tr>
            @endif
        </table>
        <p>Please respond to the inquiry at your earliest convenience.</p>
        <p>Best regards,</p>
        <p>The VenueBoost Team</p>
    </div>
</div>
</body>
</html>
