<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&display=swap');

        /* CSS styles for the email template */
        body {
            font-family: 'Manrope', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f7fc;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333333;
            font-size: 26px;
            font-weight: 600;
            line-height: 32px;
            margin: 0;
            padding-bottom: 15px;
            text-align: center;
        }

        p {
            color: #666666;
            font-size: 16px;
            line-height: 24px;
            margin: 0 0 20px;
            padding: 0;
            text-align: center;
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .button {
            display: inline-block;
            background-color: #1a1a1a;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 16px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s ease;
        }

        .button:hover {
            background-color: #2980b9;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 14px;
            color: #999999;
            padding-top: 20px;
            border-top: 1px solid #e1e1e1;
        }

        .footer p {
            margin: 0;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Logo Section -->
    <table style="font-family:'Raleway',sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
        <tbody>
        <tr>
            <td class="v-container-padding-padding" style="padding: 30px; text-align: center;">
                <div class="logo">
                    <img src="{{$venue_logo}}" alt="{{$venue_logo}} Logo" style="width: 25%; max-width: 150px;" />
                </div>
            </td>
        </tr>
        </tbody>
    </table>
    <tr>
        <td align="left" class="txt middle" style="
																		font-size: 0px;
																		word-break: break-word;
																		border-collapse: collapse;
																		mso-table-lspace: 0pt;
																		mso-table-rspace: 0pt;
																		padding: 0px 0px 20px;
																	">
            <div style="
																			font-family: Manrope, Arial, sans-serif;
																			font-size: 16px;
																			line-height: 30px;
																			color: #000000;
																		" align="left">
            <span style="
																				mso-line-height-rule: exactly;
																				font-size: 16px;
																				font-family: Manrope, Arial, sans-serif;
																				text-transform: none;
																				font-style: none;
																				font-weight: 400;
																				color: #666666;
																				line-height: 30px;
																				letter-spacing: 0px;
																				text-decoration: initial;
																			"> You have changed your password successfully. If this is not done by your please contact {{$venue_name}} Team. </span>
            </div>
        </td>
    </tr>
    <!-- Footer Section -->
    <div class="footer">
        <p>&copy; {{$venue_name}} on VenueBoost. All Rights Reserved.</p>
    </div>
</div>
</body>
</html>
