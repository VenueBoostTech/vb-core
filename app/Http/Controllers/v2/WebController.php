<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Mail\CampaignEmail;
use App\Mail\OnboardingVerifyEmail;
use App\Mail\WaitlistEmail;
use App\Mail\WaitlistVerifyLinkEmail;
use App\Models\ApiApp;
use App\Models\ContactFormSubmission;
use App\Models\Country;
use App\Models\MarketingWaitlist;
use App\Models\PromoCodeType;
use App\Models\PromotionalCode;
use App\Models\PromotionalCodePhoto;
use App\Models\SubscribedEmail;
use App\Models\WcIntegration;
use App\Models\WebsiteStatistic;
use App\Services\MondayAutomationsService;
use App\Services\VenueService;
use Carbon\Carbon;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebController extends Controller
{

    protected $mondayAutomationService;

    public function __construct(MondayAutomationsService $mondayAutomationService)
    {
        $this->mondayAutomationService = $mondayAutomationService;
    }

    public function contact(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'message' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $contact = ContactFormSubmission::create($request->all());

        // check if it has subscribed email

        // check if this email is already on contact form submission
        $canSubscribe = false;
        $subscribedEmail = SubscribedEmail::where('email', $request->input('email'))->first();
        if (!$subscribedEmail) {
            $canSubscribe = true;
        }


        $storeAutomationSubscribed = false;

        if ($request->has('subscribe') ) {
          $wantsToSubscribe = $request->input('subscribe');

            if ($wantsToSubscribe && $canSubscribe) {
                $storeAutomationSubscribed = true;
                SubscribedEmail::create(
                    [
                        'email' => $request->input('email'),
                        'contact_form_id' => $contact->id,

                    ]
                );

            }

        }

        try {
            $this->mondayAutomationService->contactFormSubmission($contact, $storeAutomationSubscribed);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            // do nothing
        }

        // Make the TikTok API call in a separate try-catch block
        try {
            $client = new Client();
            $token = 'e9d43fa991d07c689b23bd92b79f9f89a443fd57';

            $hashedEmail = hash('sha256', $request->input('email'));
            $hashedEventId = hash('sha256', uniqid('event_', true));
            $hashedExternalId = hash('sha256', $contact->id);

            $payload = [
                "event_source" => "web",
                "event_source_id" => "CPMC3ABC77U5K3OPHTC0",
                "data" => [
                    [
                        "event" => "VenueBoost Contact Form Submission",
                        "event_id" => $hashedEventId,
                        "event_time" => time(),
                        "user" => [
                            "email" => $hashedEmail,
                            "external_id" => $hashedExternalId
                        ]
                    ]
                ]
            ];

            $response = $client->post('https://business-api.tiktok.com/open_api/v1.3/event/track/', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Access-Token' => $token
                ],
                'json' => $payload
            ]);

            if ($response->getStatusCode() != 200) {
                $body = $response->getBody();
                Log::error('TikTok API error: ' . $body);
            }

        } catch (\Exception $e) {
            Log::error('Error sending event to TikTok API: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Contact form submitted successfully']);

    }

    public function verifyEmailLink(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $token = $request->input('token');

            $id = null;
            try {
                $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
                $id = $decoded->id;
            } catch (ExpiredException|\Exception $expiredException) {
                return response()->json(['message' => 'Invalid waitlist link'], 400);
            }

            $waitlister = MarketingWaitlist::where('id', $id)->first();
            if (!$waitlister) {
                return response()->json(['message' => 'Invalid waitlist email link'], 404);
            }

            // if waitlister first_email_sent is false send email
            if ($waitlister->first_email_sent === 0) {
                Mail::to( $waitlister->email)->send(new WaitlistEmail($request->full_name ?? null));
                $waitlister->first_email_sent = 1;
                $waitlister->save();
            }

            return response()->json(['message' => 'Valid link', 'waitlister' => $waitlister->email], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function generateEmailLink(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        try {
            $email = $request->input('email');


            $waitlister = DB::table('marketing_waitlists')->where('email', $email)->first();
            if (!$waitlister) {
                return response()->json(['message' => "No waitlist entry found for the provided email. Please ensure you've entered the correct email address or sign up if you haven't joined the waitlist yet."], 404);
            }

            $created_at = Carbon::now();
            $expired_at = $created_at->addMinutes(15); // Add 15mins
            $serverName = 'VenueBoost';

            $data = [
                // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
                // 'nbf' => $created_at->timestamp, // Not before
                'iss' => $serverName, // Issuer
                'exp' => $expired_at->timestamp, // Expire,
                'id' => $waitlister->id,
            ];

            $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
            $email_verify_link = 'https://venueboost.io' . "/waitlist/$jwt_token";

            Mail::to($email)->send(new WaitlistVerifyLinkEmail($waitlister->full_name ?? null, $email_verify_link, true));

            return response()->json(['message' => 'Waitlist link generated successfully']);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function listForAutomation(): \Illuminate\Http\JsonResponse
    {
        $waitlists = MarketingWaitlist::orderBy('created_at', 'desc')->get();


        return response()->json(['waitlists' => $waitlists]);
    }

    public function listForSuperadmin(): \Illuminate\Http\JsonResponse
    {
        // sort by created_at desc
        $waitlists = MarketingWaitlist::orderBy('created_at', 'desc')->get();

        // format created_at for each waitlist
        $formattedWaitlists = $waitlists->map(function ($waitlist) {
            $waitlist->created_at_formatted = $waitlist->created_at->format('Y-m-d H:i:s');
            return $waitlist;
        });


        return response()->json(['waitlists' => $formattedWaitlists]);
    }

    public function updateMarketingStatistics(Request $request): \Illuminate\Http\JsonResponse
    {
        $type = $request->input('type');

        if ($type === 'faqs_screen') {
            $statistic = WebsiteStatistic::firstOrCreate([], ['faqs_screen_count' => 0]);
            $statistic->increment('faqs_screen_count');

            return response()->json(null, 200);

        }

        return response()->json(['message' => 'No action taken'], 400);
    }


    // country city state config
    public function getCSCConfig(): \Illuminate\Http\JsonResponse
    {
        $countryTranslations = [
            'Albania' => 'Shqipëria',
            'Kosovo' => 'Kosova',
            'North Macedonia' => 'Maqedonia e Veriut',
            'Unspecified' => 'E papërcaktuar'
        ];

        $stateTranslations = [
            // Albania
            'Tirana' => 'Tiranë',
            'Durres' => 'Durrës',
            'Vlore' => 'Vlorë',
            'Elbasan' => 'Elbasan',
            'Fier' => 'Fier',
            'Korce' => 'Korçë',
            'Shkoder' => 'Shkodër',
            'Berat' => 'Berat',
            'Lezhe' => 'Lezhë',
            'Diber' => 'Dibër',
            'Kukes' => 'Kukës',
            'Gjirokaster' => 'Gjirokastër',
            // Kosovo
            'Pristina' => 'Prishtinë',
            'Prizren' => 'Prizren',
            'Peja' => 'Pejë',
            'Gjakova' => 'Gjakovë',
            'Mitrovica' => 'Mitrovicë',
            'Gjilan' => 'Gjilan',
            'Ferizaj' => 'Ferizaj',
            // North Macedonia
            'Skopje' => 'Shkup',
            'Vardar' => 'Vardari',
            'East' => 'Lindja',
            'Southwest' => 'Jugperëndimi',
            'Southeast' => 'Juglindje',
            'Pelagonia' => 'Pellagonia',
            'Polog' => 'Pollogu',
            'Northeast' => 'Verilindja',
            'Unspecified' => 'E papërcaktuar'
        ];

        $cityTranslations = [
            // Albania - Tirana Region
            'Tirana' => 'Tiranë',
            'Kavaja' => 'Kavajë',
            'Vore' => 'Vorë',
            'Kamez' => 'Kamëz',
            'Rrogozhine' => 'Rrogozhinë',

            // Albania - Durres Region
            'Durres' => 'Durrës',
            'Shijak' => 'Shijak',
            'Kruje' => 'Krujë',
            'Manez' => 'Manëz',

            // Albania - Vlore Region
            'Vlore' => 'Vlorë',
            'Himare' => 'Himarë',
            'Selenice' => 'Selenicë',
            'Orikum' => 'Orikum',
            'Dhermi' => 'Dhërmi',

            // Albania - Elbasan Region
            'Elbasan' => 'Elbasan',
            'Cerrik' => 'Cërrik',
            'Belsh' => 'Belsh',
            'Peqin' => 'Peqin',
            'Gramsh' => 'Gramsh',
            'Librazhd' => 'Librazhd',

            // Albania - Fier Region
            'Fier' => 'Fier',
            'Patos' => 'Patos',
            'Roskovec' => 'Roskovec',
            'Mallakaster' => 'Mallakastër',
            'Lushnje' => 'Lushnjë',
            'Divjake' => 'Divjakë',

            // Albania - Other Regions
            'Shkoder' => 'Shkodër',
            'Lezhe' => 'Lezhë',
            'Korce' => 'Korçë',
            'Berat' => 'Berat',
            'Kucove' => 'Kuçovë',
            'Gjirokaster' => 'Gjirokastër',
            'Permet' => 'Përmet',
            'Tepelene' => 'Tepelenë',
            'Sarande' => 'Sarandë',
            'Delvine' => 'Delvinë',
            'Konispol' => 'Konispol',
            'Kukes' => 'Kukës',
            'Has' => 'Has',
            'Tropoje' => 'Tropojë',
            'Peshkopi' => 'Peshkopi',
            'Bulqize' => 'Bulqizë',
            'Mat' => 'Mat',

            // Kosovo - Pristina Region
            'Pristina' => 'Prishtinë',
            'Podujevo' => 'Podujevë',
            'Obilic' => 'Obiliq',
            'Lipjan' => 'Lipjan',
            'Gllogovc' => 'Gllogoc',
            'Gracanice' => 'Graçanicë',
            'Fushe Kosove' => 'Fushë Kosovë',

            // Kosovo - Prizren Region
            'Prizren' => 'Prizren',
            'Dragash' => 'Dragash',
            'Suva Reka' => 'Suharekë',
            'Mamusha' => 'Mamushë',

            // Kosovo - Peja Region
            'Peja' => 'Pejë',
            'Decan' => 'Deçan',
            'Klina' => 'Klinë',
            'Istog' => 'Istog',
            'Junik' => 'Junik',

            // Kosovo - Mitrovica Region
            'Mitrovica' => 'Mitrovicë',
            'Skenderaj' => 'Skënderaj',
            'Vushtrri' => 'Vushtrri',
            'Zubin Potok' => 'Zubin Potok',
            'Zvecan' => 'Zveçan',

            // Kosovo - Gjakova Region
            'Gjakova' => 'Gjakovë',
            'Rahovec' => 'Rahovec',

            // Kosovo - Gjilan Region
            'Gjilan' => 'Gjilan',
            'Kamenica' => 'Kamenicë',
            'Vitia' => 'Viti',
            'Novoberdo' => 'Novobërdë',
            'Ranillug' => 'Ranillug',
            'Partesh' => 'Partesh',
            'Kllokot' => 'Kllokot',

            // Kosovo - Ferizaj Region
            'Ferizaj' => 'Ferizaj',
            'Kacanik' => 'Kaçanik',
            'Shtime' => 'Shtime',
            'Shterpce' => 'Shtërpcë',
            'Elez Han' => 'Hani i Elezit',

            // North Macedonia - Skopje Region
            'Skopje' => 'Shkup',
            'Aerodrom' => 'Aerodrom',
            'Butel' => 'Butel',
            'Cair' => 'Çair',
            'Centar' => 'Qendër',
            'Gazi Baba' => 'Gazi Babë',
            'Gjorce Petrov' => 'Gjorçe Petrov',
            'Karpos' => 'Karposh',
            'Kisela Voda' => 'Kisella Vodë',
            'Saraj' => 'Saraj',
            'Suto Orizari' => 'Shuto Orizarë',

            // North Macedonia - Polog Region
            'Tetovo' => 'Tetovë',
            'Gostivar' => 'Gostivar',
            'Brvenica' => 'Bërvenicë',
            'Bogovinje' => 'Bogovinë',
            'Zelino' => 'Zhelinë',
            'Jegunovce' => 'Jegunovcë',
            'Mavrovo' => 'Mavrovë',
            'Tearce' => 'Tearcë',
            'Vrapciste' => 'Vrapçishtë',

            // North Macedonia - Northeast Region
            'Kumanovo' => 'Kumanovë',
            'Kriva Palanka' => 'Kriva Pallankë',
            'Kratovo' => 'Kratovë',
            'Lipkovo' => 'Lipkovë',
            'Rankovce' => 'Rankovcë',
            'Staro Nagoricane' => 'Nagoriçan i Vjetër',

            // North Macedonia - Southwest Region
            'Ohrid' => 'Ohër',
            'Struga' => 'Strugë',
            'Debar' => 'Dibër',
            'Vevcani' => 'Vevçani',
            'Plasnica' => 'Pllasnicë',
            'Centar Zupa' => 'Qendra Zhupë',

            // Default
            'Unspecified' => 'E papërcaktuar'
        ];

        $countries = Country::whereIn('code', ['AL', 'XK', 'MK', 'XX'])
            ->with(['states' => function ($query) {
                $query->with('cities');
            }])
            ->get()
            ->map(function ($country) use ($countryTranslations, $stateTranslations, $cityTranslations) {
                return [
                    'id' => $country->id,
                    'code' => $country->code,
                    'names' => [
                        'en' => $country->name,
                        'sq' => $countryTranslations[$country->name] ?? $country->name
                    ],
                    'states' => $country->states->map(function ($state) use ($stateTranslations, $cityTranslations) {
                        return [
                            'id' => $state->id,
                            'names' => [
                                'en' => $state->name,
                                'sq' => $stateTranslations[$state->name] ?? $state->name
                            ],
                            'cities' => $state->cities->map(function ($city) use ($cityTranslations) {
                                return [
                                    'id' => $city->id,
                                    'names' => [
                                        'en' => $city->name,
                                        'sq' => $cityTranslations[$city->name] ?? $city->name
                                    ]
                                ];
                            })
                        ];
                    })
                ];
            });




        return response()->json(['countries' => $countries]);
    }

}
