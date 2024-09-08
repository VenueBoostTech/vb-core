<html>
<head>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&display=swap');

        /* CSS styles for the email template */
        body {
            font-family: 'Manrope', sans-serif;
            margin: 0;
            padding: 0;
        }

        .header {
            height: 10px;
        }

        .container {
            max-width: 640px;
            margin-top: 15px;
            margin: 0 auto;
            padding: 40px 0px 24px 0px;
        }

        .logo {
            padding: 25px 0px;
        }

        .footer {
            width: 100%;
            padding: 10px 10px;
        }

        @media only screen and (max-width: 600px) {

            .adventures,
            .social {
                width: 100%;
                gap: 24px;
                flex-direction: column;
                margin-top: 50px;
            }

            .adventure-item,
            .social-panel {
                width: 100%;
                text-align: center;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
        }
    </style>
    <style>
        @font-face {
            font-family: 'Inter var';
            font-weight: 100 900;
            font-display: swap;
            font-style: normal;
            font-named-instance: 'Regular';
            src: url("chrome-extension://neabdmkliomokekhgnogbeonopbjmajc/content/Inter-roman.var.woff2?v=3.19") format("woff2");
        }

        @font-face {
            font-family: 'Inter var';
            font-weight: 100 900;
            font-display: swap;
            font-style: italic;
            font-named-instance: 'Italic';
            src: url("chrome-extension://neabdmkliomokekhgnogbeonopbjmajc/content/Inter-italic.var.woff2?v=3.19") format("woff2");
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800;900&amp;display=swap" rel="stylesheet">
    <style type="text/css">
        @font-face {
            font-weight: 400;
            font-style: normal;
            font-family: circular;
            src: url('chrome-extension://liecbddmkiiihnedobmlmillhodjkdmb/fonts/CircularXXWeb-Book.woff2') format('woff2');
        }

        @font-face {
            font-weight: 700;
            font-style: normal;
            font-family: circular;
            src: url('chrome-extension://liecbddmkiiihnedobmlmillhodjkdmb/fonts/CircularXXWeb-Bold.woff2') format('woff2');
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header"></div>
    <div style="padding: 0px 0px">
        <div class="logo" style="text-align: center;">
            <img src="https://venueboost.io/static/media/logo-png-header.7c3ed4ed1731d48567be.png" alt="VenueBoost Logo" width="225" height="38" class="fr-fic fr-dii">
        </div>
        <hr style="border-top: 1px solid #E0E0E0; margin-top: 18px; margin-bottom: 18px;">
        <h2 class="title" style=" font-size: 20px;
        line-height: 36px;
        font-weight: bold;
        color: #121A26;
        margin-top: 42px;">Estimado/a {{firstName}},</h2>
        <div style="flex-direction: column; gap: 24px;">
            <div class="description" style="margin-bottom: 1rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">¿Está buscando reducir la complejidad en la gestión de sus espacios y mejorar la satisfacción de sus clientes? En VenueBoost, nos especializamos en simplificar operaciones como las suyas, sin interrupciones para su sistema actual.</div>
            <div class="description" style="
        margin-bottom: 1rem;
        font-size: 16px;
        line-height: 24px;
        font-weight: 600;
        ">¿Por qué elegir VenueBoost?</div>
            <div class="description" style="margin-bottom: 1rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">Esto es lo que ofrecemos: </div>
            <div class="check-desc" style="color: #384860;
        font-size: 16px;
        font-weight: 400;
        line-height: 150%;
        /*display: flex;*/
        margin-bottom: 10px;
        align-items: center;
        gap: 4px;">
                <img style="vertical-align: middle;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/rm-checkmark.png">
                <span style="vertical-align: middle;">
              <b>Una Solución Totalmente Personalizada: </b>
            </span>Adaptada específicamente para enfrentar los desafíos únicos de su negocio.
            </div>
            <div class="check-desc" style="color: #384860;
        font-size: 16px;
        font-weight: 400;
        line-height: 150%;
        margin-bottom: 10px;
        /*display: flex;*/
        align-items: center;
        gap: 4px;">
                <img style="vertical-align: middle;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/rm-checkmark.png">
                <span style="vertical-align: middle;">
              <b>Soporte Integral:</b>
            </span>Configuración, puesta en marcha y soporte continuo gratuitos durante todo su período de prueba.
            </div>
            <div class="check-desc" style="color: #384860;
        font-size: 16px;
        font-weight: 400;
        line-height: 150%;
        margin-bottom: 10px;
        /*display: flex;*/
        align-items: center;
        gap: 4px;">
                <img style="vertical-align: middle;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/rm-checkmark.png">
                <span style="vertical-align: middle;">
              <b>Sin Compromisos: </b>Disfrute de todas las características premium de nuestra plataforma durante 90 días, completamente gratis. </span>
            </div>
            <div class="description" style="margin-top: 1rem;margin-bottom: 1rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">Vea nuestra demostración breve para descubrir VenueBoost en acción e imagine las posibilidades para su lugar:</div>
            <div class="description" style="margin-bottom: 1rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                <a href="https://app.heygen.com/share/4a108d5bc7fc4ba6b83cd851b7822b77" target="_blank">
                    <b>Ver Demo Ahora</b>
                </a>
            </div>
            <div>
                <div class="description" style="margin-bottom: 1rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">Para adaptar la plataforma a sus necesidades, ¿podría indicarnos más sobre sus requisitos? Específicamente, buscamos mejorar: </div>
                <div class="description" style="margin-bottom: 0.5rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;"></div>
                <a style="text-decoration: none;
    color: initial" href="https://wyvh254xflv.typeform.com/to/io5WJkTk" target="_blank">
                    <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                        <img style="vertical-align: middle;margin-right: 5px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_checkbox.png">
                        <span style="vertical-align: middle">La gestión de reservas y eventos </span>
                    </div>
                </a>
                <a style="text-decoration: none;
    color: initial" href="https://wyvh254xflv.typeform.com/to/io5WJkTk" target="_blank">
                    <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                        <img style="vertical-align: middle;margin-right: 5px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_checkbox.png">
                        <span style="vertical-align: middle">El seguimiento y gestión de inventarios </span>
                    </div>
                </a>
                <a style="text-decoration: none;
    color: initial" href="https://wyvh254xflv.typeform.com/to/io5WJkTk" target="_blank">
                    <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                        <img style="vertical-align: middle;margin-right: 5px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_checkbox.png">
                        <span style="vertical-align: middle">La integración de herramientas de marketing para promociones y lealtad del cliente </span>
                    </div>
                </a>
                <a style="text-decoration: none;
    color: initial" href="https://wyvh254xflv.typeform.com/to/io5WJkTk" target="_blank">
                    <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                        <img style="vertical-align: middle;margin-right: 5px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_checkbox.png">
                        <span style="vertical-align: middle">Análisis y reportes detallados</span>
                    </div>
                </a>
                <a style="text-decoration: none;
    color: initial" href="https://wyvh254xflv.typeform.com/to/io5WJkTk" target="_blank">
                    <div class="description" style="margin-bottom: 1rem;cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                        <img style="vertical-align: middle;margin-right: 6px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_checkbox.png">
                        <span style="vertical-align: middle">Otros: _ _ _ _ _ _ _ _</span>
                    </div>
                </a>
            </div>
            <div class="description" style="margin-bottom: 1rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">Díganos sus necesidades completando este formulario rápido: <a href="https://wyvh254xflv.typeform.com/to/io5WJkTk" target="_blank">
                    <b>Personalizar Mi Plataforma</b>
                </a>
            </div>
        </div>
        <hr style="border-top: 1px solid #E0E0E0; margin-top: 42px; margin-bottom: 32px;">
        <div style="flex-direction: column; gap: 24px;">
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">Alternativamente, responda a este correo electrónico con sus preferencias, o programe una llamada rápida conmigo para discutir cómo VenueBoost puede hacer la diferencia: </div>
        </div>
        <div style="flex-direction: column; gap: 20px; margin-top: 10px;margin-bottom: 30px;">
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                <a href="https://calendly.com/contact-n1kt/15min" target="_blank">
                    <b>Reservar una Llamada</b>
                </a>
            </div>
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 10px;
        color: #384860;"></div>
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 10px;
        color: #384860;">Estamos emocionados de ayudarle a <b>Simplificar. Optimizar. Crecer. </b> la gestión de sus espacios. Como agradecimiento por probar VenueBoost y proporcionar sus comentarios, ofrecemos un programa de referidos generoso y oportunidades de afiliación atractivas si decide asociarse más estrechamente con nosotros. </div>
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 10px;
        color: #384860;">
                <b>Sus perspectivas son importantes, </b> incluso si actualmente está utilizando otra plataforma o si siente que ahora no es el momento adecuado para cambiar. Por favor considere hacernos saber:
            </div>
            <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 5px;
        color: #384860;">1. ¿Qué importancia tiene la gestión de espacios para su operación en este momento? </div>
            <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 2px;
        color: #384860;">2. ¿Qué plataforma está utilizando actualmente, si es que usa alguna?</div>
            <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 2px;
        color: #384860;">3. ¿Qué características o servicios harían de VenueBoost la opción ideal para usted? </div>
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 10px;
        color: #384860;">
                <b>Estamos aquí para escuchar y adaptarnos</b>para servirle mejor.
            </div>
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 10px;
        color: #384860;">Gracias por considerar VenueBoost. ¡Esperamos con interés sus comentarios! </div>
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 1rem;
        color: #384860;">
                <div>Cordiales saludos, </div>
                <div>Griseld</div>
                <div>CTO, VenueBoost</div>
                <div>contact@venueboost.io | +1 (844) 248-1465 </div>
            </div>
        </div>
        <div class="social" style=" width: 100%;

        text-align: center;
        margin-top: 50px;
        justify-content: space-between;">
            <div class="social-panel" style="
        flex-direction: column;
        align-items: center;
        gap: 12px;">
                <img src="https://venueboost.io/static/media/logo-png-header.7c3ed4ed1731d48567be.png" alt="VenueBoost Logo" width="225" height="38" class="fr-fic fr-dii">
                <div class="description" style="margin-top: 12px; color: #667085;
        font-size: 15px;
        font-weight: 400;">Copyright © 2024 VenueBoost Inc.</div>
            </div>
            <div style="width: 18px;">
                <br>
            </div>
            <div class="social-panel" style="text-align: center;
        flex-direction: column;
        gap: 0px;">
                <div>
                    <a href="https://www.reddit.com/user/venueboost/" target="_blank" style="text-decoration: none">
                        <img style="width: 24px;
        height: 27px;
        margin-right: 10px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_1.png">
                    </a>
                    <a href="https://www.linkedin.com/company/venueboostinc/" target="_blank" style="text-decoration: none">
                        <img style="width: 24px;
        height: 27px;
        margin-right: 10px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_2.png">
                    </a>
                    <a href="https://www.facebook.com/venueboost" target="_blank" style="text-decoration: none">
                        <img style="width: 24px;
        height: 27px;
        margin-right: 10px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_3.png">
                    </a>
                    <a href="https://www.youtube.com/channel/UCVKZgwUfFTL1IaxGrvc-I1A" target="_blank" style="text-decoration: none">
                        <img style="width: 24px;
        height: 27px;
        margin-right: 10px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_4.png">
                    </a>
                    <a href="https://twitter.com/venueboostinc" target="_blank" style="text-decoration: none">
                        <img style="width: 24px;
        height: 27px;
        margin-right: 10px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_5.png">
                    </a>
                    <a href="https://www.instagram.com/venueboost.io" target="_blank" style="text-decoration: none">
                        <img style="width: 24px;
        height: 27px;
        margin-right: 10px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_6.png">
                    </a>
                    <a href="https://www.tiktok.com/@venueboost" target="_blank" style="text-decoration: none">
                        <img style="width: 24px;
        height: 27px;
        margin-right: 10px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_link_7.png">
                    </a>
                </div>
                <div class="description" style="margin-top: 15px; margin-bottom: 15px;  color: #667085;
        font-size: 15px;
        font-weight: 400;">222 East 44th Street <br>New York, NY 10017 United States </div>
            </div>
        </div>
        <div class="footer" style=" background-color: #F4EBFF;">
            <div style="font-size: 15px; line-height: 28px; color: #240B3B; text-align: center;">Pour ne plus recevoir ces emails, <strong>
                    <a href="#" target="_blank">cliquez ici pour vous désabonner</a>
                </strong>
            </div>
        </div>
    </div>
</div>
<ug-extension></ug-extension>
<div id="loom-companion-mv3" ext-id="liecbddmkiiihnedobmlmillhodjkdmb">
    <section id="shadow-host-companion"></section>
</div>
<scribe-shadow id="crxjs-ext" style="position: fixed; width: 0px; height: 0px; top: 0px; left: 0px; z-index: 2147483647; overflow: visible;"></scribe-shadow>
<div id="folio-outer">
    <style>
        @font-face {
            font-family: CircularXXWeb;
            src: url(chrome-extension://ckcmjfgpicpgmnlfjjnogcjemkcofaal/CircularXXWeb-Medium.woff2) format("truetype");
            font-weight: 500
        }

        @font-face {
            font-family: CircularXXWeb;
            src: url(chrome-extension://ckcmjfgpicpgmnlfjjnogcjemkcofaal/CircularXXWeb-Regular.woff2) format("truetype");
        }

        @font-face {
            font-family: CircularXXWeb;
            src: url(chrome-extension://ckcmjfgpicpgmnlfjjnogcjemkcofaal/CircularXXWeb-Bold.woff2) format("truetype");
            font-weight: 700
        }

        @font-face {
            font-family: CircularXXWeb;
            src: url(chrome-extension://ckcmjfgpicpgmnlfjjnogcjemkcofaal/CircularXXWeb-Bold.woff2) format("truetype");
            font-weight: bold
        }
    </style>
    <div class="app___yDxHh"></div>
</div>
<tolstoy-container id="tolstoy-extension"></tolstoy-container>
</body>
</html>
