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
        margin-top: 42px;">Αγαπητέ/ή {{firstName}},</h2>
        <div style="flex-direction: column; gap: 24px;">
            <div class="description" style="margin-bottom: 1rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">Επιθυμείτε να μειώσετε την πολυπλοκότητα στη διαχείριση των χώρων και να αυξήσετε την ικανοποίηση των πελατών σας; Στο VenueBoost, ειδικευόμαστε στην απλούστευση των λειτουργιών, ακριβώς όπως τις δικές σας, χωρίς να διαταράσσουμε το τρέχον σύστημά σας. </div>
            <div class="description" style="
        margin-bottom: 1rem;
        font-size: 16px;
        line-height: 24px;
        font-weight: 600;
        ">Γιατί να επιλέξετε το VenueBoost?</div>
            <div class="description" style="margin-bottom: 1rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">Αυτό είναι το τι προσφέρουμε: </div>
            <div class="check-desc" style="color: #384860;
        font-size: 16px;
        font-weight: 400;
        line-height: 150%;
        /*display: flex;*/
        margin-bottom: 10px;
        align-items: center;
        gap: 4px;">
                <img style="vertical-align: middle;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/accommodation-checkmark.png">
                <span style="vertical-align: middle;">
              <b>Μία Πλήρως Προσαρμοσμένη Λύση: </b>
            </span> Σχεδιασμένη ειδικά για να ανταποκρίνεται στις μοναδικές προκλήσεις της επιχείρησής σας.
            </div>
            <div class="check-desc" style="color: #384860;
        font-size: 16px;
        font-weight: 400;
        line-height: 150%;
        margin-bottom: 10px;
        /*display: flex;*/
        align-items: center;
        gap: 4px;">
                <img style="vertical-align: middle;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/accommodation-checkmark.png">
                <span style="vertical-align: middle;">
              <b>Ολοκληρωμένη Υποστήριξη: </b>
            </span> Δωρεάν ενεργοποίηση, εγκατάσταση και συνεχής υποστήριξη καθ' όλη τη διάρκεια της δοκιμαστικής σας περιόδου.
            </div>
            <div class="check-desc" style="color: #384860;
        font-size: 16px;
        font-weight: 400;
        line-height: 150%;
        margin-bottom: 10px;
        /*display: flex;*/
        align-items: center;
        gap: 4px;">
                <img style="vertical-align: middle;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/accommodation-checkmark.png">
                <span style="vertical-align: middle;">
              <b>Χωρίς Δεσμεύσεις:</b>Απολαύστε όλες τις προνομιακές δυνατότητες της πλατφόρμας μας για 90 ημέρες, εντελώς δωρεάν. </span>
            </div>
            <div class="description" style="margin-top: 1rem;margin-bottom: 1rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">Παρακολουθήστε την γρήγορη επίδειξή μας για να δείτε το VenueBoost σε δράση και φανταστείτε τις δυνατότητες για τον χώρο σας: </div>
            <div class="description" style="margin-bottom: 1rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                <a href="https://app.heygen.com/share/4a108d5bc7fc4ba6b83cd851b7822b77" target="_blank">
                    <b>Παρακολουθήστε την Δείγμα Τώρα </b>
                </a>
            </div>
            <div>
                <div class="description" style="margin-bottom: 1rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">Για να προσαρμόσουμε την πλατφόρμα στις ανάγκες σας, μπορείτε να μας πείτε περισσότερα για τις απαιτήσεις σας? Συγκεκριμένα, επιθυμούμε να βελτιώσουμε: </div>
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
                        <span style="vertical-align: middle">Διαχείριση κρατήσεων και υποδοχών </span>
                    </div>
                </a>
                <a style="text-decoration: none;
    color: initial" href="https://wyvh254xflv.typeform.com/to/io5WJkTk" target="_blank">
                    <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                        <img style="vertical-align: middle;margin-right: 5px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_checkbox.png">
                        <span style="vertical-align: middle">Παρακολούθηση και διαχείριση αποθεμάτων</span>
                    </div>
                </a>
                <a style="text-decoration: none;
    color: initial" href="https://wyvh254xflv.typeform.com/to/io5WJkTk" target="_blank">
                    <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                        <img style="vertical-align: middle;margin-right: 5px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_checkbox.png">
                        <span style="vertical-align: middle">Ενσωμάτωση εργαλείων μάρκετινγκ για προωθήσεις και πιστότητα πελατών </span>
                    </div>
                </a>
                <a style="text-decoration: none;
    color: initial" href="https://wyvh254xflv.typeform.com/to/io5WJkTk" target="_blank">
                    <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                        <img style="vertical-align: middle;margin-right: 5px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_checkbox.png">
                        <span style="vertical-align: middle">Λεπτομερής ανάλυση και αναφορές </span>
                    </div>
                </a>
                <a style="text-decoration: none;
    color: initial" href="https://wyvh254xflv.typeform.com/to/io5WJkTk" target="_blank">
                    <div class="description" style="margin-bottom: 1rem;cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                        <img style="vertical-align: middle;margin-right: 6px;" class="link fr-fic fr-dii" src="https://core.venueboost.io/storage/email_checkbox.png">
                        <span style="vertical-align: middle">Άλλο: _ _ _ _ _ _ _ _</span>
                    </div>
                </a>
            </div>
            <div class="description" style="margin-bottom: 1rem;font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">Ενημερώστε μας για τις ανάγκες σας συμπληρώνοντας αυτήν την γρήγορη φόρμα: <a href="https://wyvh254xflv.typeform.com/to/io5WJkTk" target="_blank">
                    <b>Προσαρμόστε την Πλατφόρμα Μου</b>
                </a>
            </div>
        </div>
        <hr style="border-top: 1px solid #E0E0E0; margin-top: 42px; margin-bottom: 32px;">
        <div style="flex-direction: column; gap: 24px;">
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;"> Εναλλακτικά, απαντήστε σε αυτό το email με τις προτιμήσεις σας, ή προγραμματίστε μια γρήγορη συνομιλία μαζί μου για να συζητήσουμε πώς το VenueBoost μπορεί να κάνει τη διαφορά: </div>
        </div>
        <div style="flex-direction: column; gap: 20px; margin-top: 10px;margin-bottom: 30px;">
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        color: #384860;">
                <a href="https://calendly.com/contact-n1kt/15min" target="_blank">
                    <b>Κλείστε Κλήση</b>
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
        color: #384860;"> ίμαστε ενθουσιασμένοι να σας βοηθήσουμε να <b>Απλοποιήσετε. Βελτιστοποιήσετε. Αναπτυχθείτε. </b>τη διαχείριση του χώρου σας. Ως ευχαριστώ για τη δοκιμή του VenueBoost και την παροχή των σχολίων σας, προσφέρουμε ένα γενναιόδωρο πρόγραμμα παραπομπής και ελκυστικές ευκαιρίες συνεργασίας, εάν αποφασίσετε να συνεργαστείτε πιο στενά μαζί μας. </div>
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 10px;
        color: #384860;">
                <b>Οι προοπτικές σας έχουν σημασία</b>, ακόμη και αν χρησιμοποιείτε ήδη άλλη πλατφόρμα ή αν νιώθετε ότι τώρα δεν είναι η κατάλληλη στιγμή για αλλαγή. Παρακαλούμε σκεφτείτε να μας ενημερώσετε:
            </div>
            <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 5px;
        color: #384860;">1. Πόσο σημαντική είναι η διαχείριση χώρων για την επιχείρησή σας αυτή τη στιγμή? </div>
            <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 2px;
        color: #384860;">2. Ποια πλατφόρμα χρησιμοποιείτε αυτή τη στιγμή, αν χρησιμοποιείτε κάποια? </div>
            <div class="description" style="cursor: pointer; font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 2px;
        color: #384860;">3. Ποιες δυνατότητες ή υπηρεσίες θα έκαναν το VenueBoost την ιδανική επιλογή για εσάς? </div>
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 10px;
        color: #384860;">
                <b>Είμαστε εδώ για να ακούσουμε και να προσαρμοστούμε</b> για να σας εξυπηρετήσουμε καλύτερα.
            </div>
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 10px;
        color: #384860;"> Ευχαριστούμε που λαμβάνετε υπόψη το VenueBoost. Ανυπομονούμε για τα σχόλιά σας! </div>
            <div class="description" style="font-size: 16px;
        line-height: 24px;
        font-weight: 400;
        margin-top: 1rem;
        color: #384860;">
                <div>Με εκτίμηση, </div>
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
            <div style="font-size: 15px; line-height: 28px; color: #240B3B; text-align: center;">Για να μην λαμβάνετε πλέον αυτά τα email, <strong>
                    <a href="#" target="_blank">πατήστε εδώ για να κάνετε απεγγραφή</a>
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
</body>
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
</html>
