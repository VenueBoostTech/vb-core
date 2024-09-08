<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Regjistrimi i Anëtarit të Ri</title>
</head>
<body
    style="
         font-family: 'Manrope', sans-serif;
         margin: 0;
         padding: 0;
         background-color: #f5f5f5;
         padding: 20px 10px;
      "
>
<img
    src="https://bybest.shop/assets/img/bybest-logo.png"
    alt="ByBest Shop"
    style="display: block; margin: 24px auto; width: 150px"
/>
<div
    style="
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 24px;
            border-radius: 16px;
            background-color: #fff;
            text-align: left;
         "
>
    <div style="width: 100%; text-align: center"></div>

    <div
        style="
               color: #000;
               font-size: 32px;
               text-align: center;
               line-height: 46px;
               font-weight: bold;
               font-family: 'Manrope', Arial, sans-serif;
               margin: 24px 0;
            "
    >
        <p style="margin: 0">Regjistrimi i një anëtari të ri</p>
    </div>

    <p style="font-size: 16px; line-height: 24px; color: #333">
        Një anëtar i ri është regjistruar nga
        <span style="color: #ed1c24; font-weight: bold"
        >{{ $source == 'landing_page' ? 'Faqja Kryesore' : 'Klubi Im' }}</span
        >.
    </p>

    <h2 style="font-size: 20px; font-weight: bold">Detajet e Anëtarit</h2>
    <table
        style="
               width: 100%;
               border-collapse: collapse;
               border-radius: 8px;
               overflow: hidden;
               box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
               margin: 24px 0;
            "
    >
        <tr>
            <th
                style="
                     text-align: left;
                     padding: 10px 20px;
                     background-color: #f9f1f1;
                  "
            >
                Emri
            </th>
            <td
                style="
                     text-align: left;
                     padding: 10px 20px;
                     background-color: #f9f1f1;
                  "
            >
                {{ $member->first_name }} {{ $member->last_name }}
            </td>
        </tr>
        <tr>
            <th style="text-align: left; padding: 10px 20px">Email</th>
            <td style="text-align: left; padding: 10px 20px">
                {{ $member->email }}
            </td>
        </tr>
        @if($member->phone_number)
            <tr>
                <th
                    style="
                     text-align: left;
                     padding: 10px 20px;
                     background-color: #f9f1f1;
                  "
                >
                    Numri i Telefonit
                </th>
                <td
                    style="
                     text-align: left;
                     padding: 10px 20px;
                     background-color: #f9f1f1;
                  "
                >
                    {{ $member->phone_number }}
                </td>
            </tr>
        @endif @if($member->preferred_brand_id)
            <tr>
                <th style="text-align: left; padding: 10px 20px">
                    Brendi i Preferuar
                </th>
                <td style="text-align: left; padding: 10px 20px">
                    {{ $preferredBrand}}
                </td>
            </tr>
        @endif @if($source == 'from_my_club') @if($member->city)
            <tr>
                <th
                    style="
                     text-align: left;
                     padding: 10px 20px;
                     background-color: #f9f1f1;
                  "
                >
                    Qyteti
                </th>
                <td
                    style="
                     text-align: left;
                     padding: 10px 20px;
                     background-color: #f9f1f1;
                  "
                >
                    {{ $member->city }}
                </td>
            </tr>
        @endif @if($member->address)
            <tr>
                <th style="text-align: left; padding: 10px 20px">Adresa</th>
                <td style="text-align: left; padding: 10px 20px">
                    {{ $member->address }}
                </td>
            </tr>
        @endif @if($member->birthday)
            <tr>
                <th
                    style="
                     text-align: left;
                     padding: 10px 20px;
                     background-color: #f9f1f1;
                  "
                >
                    Ditëlindja
                </th>
                <td
                    style="
                     text-align: left;
                     padding: 10px 20px;
                     background-color: #f9f1f1;
                  "
                >
                    {{ \Carbon\Carbon::parse($member->birthday)->format('Y-m-d') }}
                </td>
            </tr>
        @endif @endif
    </table>

    <p style="font-size: 16px; line-height: 1.5; color: #333; margin: 24px 0">
        Faleminderit,<br />VenueBoost Team
    </p>

    <div style="text-align: center">
        <a
            href="https://admin.venueboost.io/"
            style="
                  display: inline-block;
                  padding: 12px 20px;
                  text-decoration: none;
                  color: #fff;
                  background-color: #cb0000;
                  border-radius: 6px;
                  font-size: 16px;
                  font-weight: bold;
               "
        >Paneli i Adminit</a
        >
    </div>
</div>
<div
    style="text-align: center; margin-top: 20px; font-size: 12px; color: #666"
>
    &copy; {{ date('Y') }} VenueBoost. Të gjitha të drejtat të
    rezervuara.
</div>
</body>
</html>
