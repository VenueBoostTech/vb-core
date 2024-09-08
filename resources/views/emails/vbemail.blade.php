<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>VenueBoost</title>
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
            padding: 24px;
        }

        .logo {
            text-align: center;
            padding: 25px 0px;
        }

        .title {
            font-size: 28px;
            line-height: 36px;
            font-weight: 600;
            color: #240B3B;
            margin-top: 42px;
        }

        .description {
            font-size: 15px;
            line-height: 24px;
            font-weight: 500;
            color: #667085;
        }

        .footer {
            width: 100%;
            padding: 10px 10px;
            background-color: #F4EBFF;
        }

        .button {
            background-color: #240B3B;
            border-radius: 6px;
            width: 140px;
            height: 35px;
            color: #F3f3f3;
            font-size: 14px;
            line-height: 23px;
            font-weight: 500;
            border: 0px;
            cursor: pointer;
        }

        .adventures,
        .social {
            width: 100%;
            display: flex;
        }

        .adventure-item,
        .social-panel {
            width: 50%;
            margin: 25px 0px;
        }

        .social-panel .link {
            width: 24px;
            height: 27px;
            margin-right: 5px;
        }

        @media only screen and (max-width: 600px) {

            .adventures,
            .social {
                width: 100%;
                display: inherit;
            }

            .adventure-item,
            .social-panel {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">
            <img src="https://venueboost.io/static/media/logo-png-header.939ae58925d2f2c11f7a.png" alt="VenueBoost Logo"
                width="225" height="38">
        </div>
        <hr style="border-top: 1px solid #E0E0E0; margin-top: 18px; margin-bottom: 18px;" />
        <h2 class="title">Hello Gerco Gerco,</h2>
        <p class="description">A warm welcome to you from the VenueBoost team! We're absolutely
            delighted to have you join us. Your journey to streamline and elevate your business with VenueBoost begins
            today, and we're here to make every step a success.</p>
        <h2 class="title">What's Next on Your VenueBoost Adventure:</h2>
        <div class="adventures">
            <div class="adventure-item">
                <img src="{{ asset('storage/email_icon_1.svg') }}" style="width: 48px; height: 48px;"/>
                <h4>Your Command Center</h4>
                <p class="description" style="margin-top: 8px;">Dive into your personalized VenueBoost Admin Panel –
                    your new hub for managing and transforming your business operations. You will be request to set a
                    new password for the first time Login.</p>
                <a href="https://admin.venueboost.io/" ><button class="button">Login</button></a>
            </div>
            <div style="width: 28px;"></div>
            <div class="adventure-item">
                <img src="{{ asset('storage/email_icon_2.svg') }}" style="width: 48px; height: 48px;"/>
                <h4>Kickstart Your Experience</h4>
                <p class="description" style="margin-top: 8px;">To ensure a smooth start, we've crafted a comprehensive
                    guide that highlights VenueBoost's essential features and how to leverage them for maximum impact.
                </p>
                <a href="https://venueboost.io/"><button class="button">Get Started Guides</button></a>
            </div>
        </div>
        <div class="adventures">
            <div class="adventure-item">
                <img src="{{ asset('storage/email_icon_3.svg') }}" style="width: 48px; height: 48px;"/>
                <h4>Dedicated Support Just a Click Away</h4>
                <p class="description" style="margin-top: 8px;">Our Customer Success Team is passionate about your
                    experience and success. Should you have any queries or need a helping hand, we're just a message
                    away.</p>
                <a href="https://venueboost.io/contact-us"><button class="button">Contact Us</button></a>
            </div>
            <div style="width: 28px;"></div>
            <div class="adventure-item">
                <img src="{{ asset('storage/email_icon_4.svg') }}" style="width: 48px; height: 48px;"/>
                <h4>Insights and Updates Direct to You</h4>
                <p class="description" style="margin-top: 8px;">Stay tuned for our regular emails filled with valuable
                    tips, updates, and insights tailored to enhance your VenueBoost journey.</p>
            </div>
        </div>
        <div style="width: 100%; margin-top: 40px;">
            <img src="{{ asset('storage/email_icon_3.svg') }}" style="width: 48px; height: 48px;"/>
            <h4>Be Part of Our Vibrant Community</h4>
            <p class="description" style="margin-top: 8px;">Engage with fellow VenueBoost users, exchange ideas, and
                gain new perspectives. Join us on our social platforms!</p>
        </div>
        <h2 class="title">We Value Your Voice</h2>
        <p class="description" style="margin-top: 8px;">
            Your feedback is a cornerstone of our continuous improvement. In the coming days, we'll invite you to share
            your thoughts on the onboarding process. Your insights are crucial in shaping an exceptional VenueBoost
            experience.
            <br /><br />
            Thank you for choosing VenueBoost. We're not just a service; we're your partner in growth and success.
            <br /><br />
            Warmest regards,
            <br />
            <b>VenueBoost</b>
            <br />
            Head of Customer Experience, VenueBoost
        </p>
        <hr style="border-top: 1px solid #E0E0E0; margin-top: 42px; margin-bottom: 32px;" />
        <div class="social">
            <div class="social-panel">
                <img src="https://venueboost.io/static/media/logo-png-header.939ae58925d2f2c11f7a.png"
                    alt="VenueBoost Logo" width="225" height="38">
                <p class="description" style="margin-top: 12px;">Copyright © 2024 VenueBoost Inc.</p>
            </div>
            <div style="width: 28px;"></div>
            <div class="social-panel">
                <div style="display: flex; flex-wrap: wrap;">

                    <a href="https://www.reddit.com/user/venueboost/" target="_blank">
                        <img class="link" style="margin-right: 10px;" src="https://core.venueboost.io/storage/email_link_1.png" />
                    </a>
                    <a href="https://www.linkedin.com/company/venueboostinc/" target="_blank">
                        <img class="link" style="margin-right: 10px;" src="https://core.venueboost.io/storage/email_link_2.png" />
                    </a>
                    <a href="https://www.facebook.com/venueboost" target="_blank">
                        <img class="link" style="margin-right: 10px;" src="https://core.venueboost.io/storage/email_link_3.png" />
                    </a>

                    <a href="https://www.youtube.com/channel/UCVKZgwUfFTL1IaxGrvc-I1A" target="_blank">
                        <img class="link" style="margin-right: 10px;" src="https://core.venueboost.io/storage/email_link_4.png" />
                    </a>

                    <a href="https://twitter.com/venueboostinc" target="_blank">
                        <img class="link" style="margin-right: 10px;" src="https://core.venueboost.io/storage/email_link_5.png" />
                    </a>
                    <a href="https://www.instagram.com/venueboost.io" target="_blank">
                        <img class="link" style="margin-right: 10px;" src="https://core.venueboost.io/storage/email_link_6.png" />
                    </a>
                    <a href="https://www.tiktok.com/@venueboost" target="_blank">
                        <img class="link" style="margin-right: 10px;" src="https://core.venueboost.io/storage/email_link_7.png" />
                    </a>
                </div>
                <p class="description" style="margin-top: 12px;">222 East 44th Street<br />New York, NY 10017 United States</p>
            </div>
        </div>
        <div class="footer">
            <p style="font-size: 15px; line-height: 28px; color: #240B3B; text-align: center;">The email was sent to
                <a href="mailto:development@venueboost.io" style="color: #240B3B;">development@venueboost.io.</a><br />To no longer
                receive these emails, <b><a>unsubscribe.</a></b></p>
        </div>
    </div>
</body>

</html>
