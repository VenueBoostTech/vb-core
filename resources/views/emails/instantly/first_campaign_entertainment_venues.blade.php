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
        margin-top: 15px;
        margin: 0 auto;
        padding: 40px 0px 24px 0px;
    ;
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
        margin-right: 10px;
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
<div class="container">
    <div class="logo">
        <img src="https://venueboost.io/static/media/logo-png-header.939ae58925d2f2c11f7a.png" alt="VenueBoost Logo" width="225" height="38" class="fr-fic fr-dii">
    </div>
    <hr style="border-top: 1px solid #E0E0E0; margin-top: 18px; margin-bottom: 18px;">
    <h2 class="title">Dear {{firstName}},</h2>
    <div class="description">I hope you&#39;re having a fantastic day. My name is Griseld, and I&#39;m reaching out from VenueBoost, a leading software provider for the entertainment industry. I recently came across {{companyName}} and was impressed by your reputation for delivering outstanding events and entertainment packages.</div>
    <div class="adventures">
        <div class="adventure-item">
            <div class="description" style="margin-top: 8px;">At VenueBoost, we&#39;re passionate about empowering venues like yours to create unforgettable experiences for your guests. Our platform is designed to simplify your operations, from ticketing and scheduling to customer engagement and analytics. By streamlining your processes, you can focus on what you do best: delivering world-class entertainment and creating lasting memories for your guests.</div>
        </div>
        <div style="width: 18px;">
            <br>
        </div>
        <div class="adventure-item">
            <div class="description" style="margin-top: 8px;">I believe VenueBoost can be a game-changer for {{companyName}}, enabling you to optimize your resources, boost efficiency, and drive customer loyalty. Our software is intuitive, customizable, and scalable, ensuring that it grows with your business.</div>
        </div>
    </div>
    <div style="width: 100%; margin-top: 0px;">
        <div class="description" style="margin-top: 8px;">I would be delighted to schedule a call with you to discuss how VenueBoost can help you elevate the experiences you offer at {{companyName}} and contribute to your success. Please let me know if you have any availability this week for a brief conversation.</div>
    </div>
    <div class="description" style="margin-top: 0px;">
        <br>Best wishes, <br>
        <strong>Griseld</strong>
    </div>
    <hr style="border-top: 1px solid #E0E0E0; margin-top: 42px; margin-bottom: 32px;">
    <div class="social">
        <div class="social-panel">
            <img src="https://venueboost.io/static/media/logo-png-header.939ae58925d2f2c11f7a.png" alt="VenueBoost Logo" width="225" height="38" class="fr-fic fr-dii">
            <div class="description" style="margin-top: 12px;">Copyright &copy; 2024 VenueBoost Inc.</div>
        </div>
        <div style="width: 18px;">
            <br>
        </div>
        <div class="social-panel">
            <div>
                <a href="https://www.reddit.com/user/venueboost/" target="_blank">
                    <img class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_1.png">
                </a>
                <a href="https://www.linkedin.com/company/venueboostinc/" target="_blank">
                    <img class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_2.png">
                </a>
                <a href="https://www.facebook.com/venueboost" target="_blank">
                    <img class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_3.png">
                </a>
                <a href="https://www.youtube.com/channel/UCVKZgwUfFTL1IaxGrvc-I1A" target="_blank">
                    <img class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_4.png">
                </a>
                <a href="https://twitter.com/venueboostinc" target="_blank">
                    <img class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_5.png">
                </a>
                <a href="https://www.instagram.com/venueboost.io" target="_blank">
                    <img class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_6.png">
                </a>
                <a href="https://www.tiktok.com/@venueboost" target="_blank">
                    <img class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_7.png">
                </a>
            </div>
            <div class="description" style="margin-top: 15px; margin-bottom: 15px;">222 East 44th Street <br>New York, NY 10017 United States </div>
        </div>
    </div>
    <div class="footer">
        <div style="font-size: 15px; line-height: 28px; color: #240B3B; text-align: centehr;">To no longer receive these emails, <strong>
                <a href="https://UNSUBSCRIBE_INSTANTLY.ai" target="_blank">click here to unsubscribe</a>
            </strong>
        </div>
    </div>
</div>
