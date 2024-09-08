<?php

use App\Http\Controllers\v1\ProductsController;
use App\Http\Controllers\v2\InventoryConfigurationController;
use App\Http\Controllers\v2\VisionTrack\DevicesController;
use App\Http\Controllers\v2\VisionTrack\AnalyticsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API Calls for Boost Web
Route::middleware(['web_api_key'])->prefix('v1')->group(function () {

    // Authentication API Calls
    Route::group([
        'middleware' => 'api',
        'prefix' => 'auth',
    ], function ($router) {
        Route::post('/login-affiliate', 'App\Http\Controllers\v1\AuthController@authenticateAffiliate');
        Route::post('/login-enduser', 'App\Http\Controllers\v1\AuthController@authenticateEndUser');
        Route::post('/register-enduser', 'App\Http\Controllers\v1\AuthController@registerEndUser');
        Route::post('/refresh-affiliate', 'App\Http\Controllers\v1\AuthController@refresh');
        Route::post('/refresh-enduser', 'App\Http\Controllers\v1\AuthController@refresh');
        Route::post('/logout-affiliate', 'App\Http\Controllers\v1\AuthController@logout');
        Route::post('/logout-enduser', 'App\Http\Controllers\v1\AuthController@logout');
        Route::post('/verify-email', 'App\Http\Controllers\v1\AuthController@verifyEmail');
    });

    Route::group([
        'middleware' => ['jwt', 'api'] ,
        'prefix' => 'auth',
    ], function ($router) {
        Route::post('/resend-verify-email', 'App\Http\Controllers\v1\AuthController@resendVerifyEmail');


    });

    Route::middleware(['jwt'])->group(function () {
        Route::prefix('end-user')->group(function () {
            Route::get('/orders', 'App\Http\Controllers\v3\EndUserController@getOrders');
            Route::get('/orders/{id}', 'App\Http\Controllers\v3\EndUserController@getOrderDetails');
            Route::get('/activities', 'App\Http\Controllers\v3\EndUserController@getActivities');
            Route::get('/wallet/info', 'App\Http\Controllers\v3\EndUserController@walletInfo');
            Route::get('/wallet/payment-methods', 'App\Http\Controllers\v3\EndUserController@getPaymentMethods');
            Route::get('/promotions', 'App\Http\Controllers\v3\EndUserController@getPromotions');
            Route::get('/wishlist', 'App\Http\Controllers\v3\EndUserController@getWishlist');
            Route::get('/{id}', 'App\Http\Controllers\v3\EndUserController@getOne');

        });
    });


    Route::prefix('faqs')->group(function () {
        Route::get('/search', 'App\Http\Controllers\v1\FaqController@search');
        Route::get('/by-category', 'App\Http\Controllers\v1\FaqController@searchByCategory');
    });


    Route::prefix('privacy-rights-requests')->group(function () {
        Route::post('/', 'App\Http\Controllers\v1\LegalController@store');
        Route::post('/verify-email-link', 'App\Http\Controllers\v1\LegalController@verifyEmailLink');
    });

    Route::prefix('vendor-contact-applications')->group(function () {
        Route::post('', 'App\Http\Controllers\v1\VendorContactApplications@create');
        Route::post('/verify-register-link', 'App\Http\Controllers\v1\VendorContactApplications@verifyRegisterLink');
    });

    Route::prefix('restaurants')->group(function () {
            Route::post('/register', 'App\Http\Controllers\v1\RestaurantPreOnboardingController@create');
        Route::post('/verify-email', 'App\Http\Controllers\v1\RestaurantPreOnboardingController@verifyEmail');
        Route::post('/resend-verify-email', 'App\Http\Controllers\v1\RestaurantPreOnboardingController@resendVerifyEmail');
        Route::post('/add-card', 'App\Http\Controllers\v1\RestaurantPreOnboardingController@addCard');
        Route::post('/payment-methods', 'App\Http\Controllers\v1\RestaurantPreOnboardingController@getPaymentMethods');
        Route::post('/pay-with-card', 'App\Http\Controllers\v1\RestaurantPreOnboardingController@payWithCard');

        Route::get('/register-config', 'App\Http\Controllers\v1\VendorConfigurationController@getRegisterConfig');
        Route::get('/payment-config', 'App\Http\Controllers\v1\VendorConfigurationController@getPaymentConfig');
    });

    Route::group(['prefix' => 'white-label'], function () {
        Route::get('/web-profile', 'App\Http\Controllers\v1\RestaurantController@getVenueWhiteLabelProfile');
        Route::get('/brand-profile', 'App\Http\Controllers\v1\VenueBrandProfileConfigController@get');
        Route::get('/brand-contact-configurations', 'App\Http\Controllers\v2\VenueWhitelabelCustomizationController@brandContactConfigurations');
        Route::post('/reservation', 'App\Http\Controllers\v1\ReservationController@webReservationCreate');
        Route::get('/reservation-times', 'App\Http\Controllers\v1\ReservationController@webGetBookTimes');
        Route::post('/guest-enroll', 'App\Http\Controllers\v1\ReservationController@enrollGuest');
        Route::post('/email-subscribe', 'App\Http\Controllers\v2\VenueWhitelabelCustomizationController@emailSubscribe');
        Route::post('/submit-whitelabel-contact', 'App\Http\Controllers\v2\VenueWhitelabelCustomizationController@submitWhitelabelContact');
        Route::post('/restaurant/order', 'App\Http\Controllers\v1\OrdersController@restaurantOrder');
        Route::post('/check-availability', 'App\Http\Controllers\v1\BookingController@checkAvailability');
        Route::post('/restaurant/validate-coupon', 'App\Http\Controllers\v1\OrdersController@validateCoupon');
        Route::prefix('payments')->group(function () {
            Route::post('/', 'App\Http\Controllers\v1\Stripe\WhiteLabel\PaymentsController@createPaymentIntent');
            Route::post('/destination-charge', 'App\Http\Controllers\v1\Stripe\WhiteLabel\PaymentsController@createDestinationCharge');
        });

        // Route::post('/checkout/bybest', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\TwoCheckoutController@quickCheckout');
        // Define the success and cancel routes
        Route::get('/payment/success', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\PaymentController@success')->name('payment.success');
        Route::get('/payment/cancel', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\PaymentController@cancel')->name('payment.cancel');
        Route::get('/payment/callback', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\PaymentController@callback')->name('payment.callback');
        Route::post('/checkout/bybest', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\CheckoutController@quickCheckout');
    });

    Route::group(['prefix' => 'resto-tables'], function () {
        Route::post('/available', 'App\Http\Controllers\v1\TableController@webAvailableTables');
    });

    Route::group(['prefix' => 'accommodation'], function () {
        Route::get('/rental-unit/white-label', 'App\Http\Controllers\v1\AccommodationController@rentalUnitWhiteLabel');
        Route::get('/rental-unit/pricing', 'App\Http\Controllers\v1\BookingController@getRentalUnitPrice');
        Route::post('/rental-unit/book', 'App\Http\Controllers\v1\BookingController@store');
    });

    Route::group(['prefix' => 'members'], function () {
        Route::post('/register-landing', 'App\Http\Controllers\v3\Whitelabel\MemberController@registerFromLandingPage');
        Route::post('/register-myclub', 'App\Http\Controllers\v3\Whitelabel\MemberController@registerFromMyClub');
    });

    Route::group(['prefix' => 'affiliate'], function () {
        Route::post('/register', 'App\Http\Controllers\v1\AffiliateController@registerAffiliate');
        Route::get('/web-types', 'App\Http\Controllers\v1\AffiliateController@getAffiliateTypesWithPrograms');
    });

    Route::group(['prefix' => 'local-testing'], function () {
        Route::post('/xlsx-to-json', 'App\Http\Controllers\v2\TestingLocalController@convertJsonToXlsx');

    });

    Route::group(['prefix' => 'web'], function () {
        Route::post('/contact', 'App\Http\Controllers\v2\WebController@contact');
        Route::post('/update-statistics', 'App\Http\Controllers\v2\WebController@updateMarketingStatistics');
    });

    Route::group(['prefix' => 'ai'], function () {
        Route::post('/suggest-quiz', 'App\Http\Controllers\v1\AI\Web\QuizzesController@suggestQuiz');
        Route::post('/store-quiz-answers', 'App\Http\Controllers\v1\AI\Web\QuizzesController@storeQuizAnswers');
        Route::get('/blogs-list', 'App\Http\Controllers\v1\BlogsController@blogsList');
        // Route to get a single blog by ID
        Route::get('/blogs/{id}', 'App\Http\Controllers\v1\BlogsController@getOneBlog');
        Route::post('/chat', 'App\Http\Controllers\v1\AI\ChatbotController@sendChat');
    });

    Route::group(['prefix' => 'retail'], function () {
        Route::get('/product-details/{id}', 'App\Http\Controllers\v1\OrdersController@webProductDetails');
        Route::get('/shipping-methods', 'App\Http\Controllers\v1\OrdersController@shippingMethods');
        Route::post('/checkout', 'App\Http\Controllers\v1\OrdersController@retailOrder');
        Route::post('/validate-coupon', 'App\Http\Controllers\v1\OrdersController@validateCoupon');
    });

    Route::prefix('web-stripe-connected')->group(function () {
        Route::prefix('accounts')->group(function () {
            Route::post('/', 'App\Http\Controllers\v1\Stripe\Connected\AccountsController@create');

        });
    });

    Route::prefix('/marketing-waitlist')->group(function () {
        Route::post('/', 'App\Http\Controllers\v2\MarketingWaitlistsController@create');
        Route::post('/verify-email-link', 'App\Http\Controllers\v2\MarketingWaitlistsController@verifyEmailLink');
        Route::post('/generate-email-link', 'App\Http\Controllers\v2\MarketingWaitlistsController@generateEmailLink');
    });

    Route::prefix('/onboarding')->group(function () {
        Route::get('/get-payment-gateway', 'App\Http\Controllers\v2\OnboardingController@getPaymentGateway');
        Route::post('/', 'App\Http\Controllers\v2\OnboardingController@create');
        Route::post('/verify-email-link', 'App\Http\Controllers\v2\OnboardingController@verifyEmailLink');
        Route::post('/verify-reset-password-link', 'App\Http\Controllers\v2\OnboardingController@verifyPasswordLink');
        Route::post('/reset-password', 'App\Http\Controllers\v2\OnboardingController@resetPassword');
        Route::post('/track-onboarding', 'App\Http\Controllers\v2\OnboardingController@trackOnboarding');
        Route::post('/recommend-plan', 'App\Http\Controllers\v2\OnboardingController@recommendPricingPlan');
        Route::post('/confirm-onboarding', 'App\Http\Controllers\v2\OnboardingController@completeSubscriptionChosenDuringOnboarding');
        Route::post('/create-checkout-session-onboarding', 'App\Http\Controllers\v1\PricingPlansController@createCheckoutSessionForOnboarding');
        Route::post('/confirm-subscription-onboarding', 'App\Http\Controllers\v1\PricingPlansController@confirmSubscriptionForOnboarding');

    });

});

// API Calls for Boost Admin
Route::middleware(['admin_api_key'])->prefix('v1')->group(function () {
    // Authentication API Calls
    Route::group([
        'middleware' => 'api',
        'prefix' => 'auth',
    ], function ($router) {
        Route::post('/login', 'App\Http\Controllers\v1\AuthController@authenticate');
        Route::post('/refresh', 'App\Http\Controllers\v1\AuthController@refresh');
        Route::post('/logout', 'App\Http\Controllers\v1\AuthController@logout');
    });

    Route::middleware(['jwt'])->group(function () {
        Route::group(['prefix' => 'users'], function () {
            Route::get('/profile', 'App\Http\Controllers\v1\AuthController@getUserProfile');
            Route::post('/request-change-email', 'App\Http\Controllers\v1\AuthController@requestChangeEmail');
            Route::post('/verify-change-email', 'App\Http\Controllers\v1\AuthController@verifyChangeEmail');
            Route::post('/change-pass', 'App\Http\Controllers\v1\AuthController@changePassword');
        });

        Route::prefix('admin-stripe-connected')->group(function () {
            Route::prefix('accounts')->group(function () {
                Route::get('/', 'App\Http\Controllers\v1\Stripe\Connected\AccountsController@getOne');
                Route::put('/', 'App\Http\Controllers\v1\Stripe\Connected\AccountsController@update');

            });
        });


    Route::prefix('web-stripe-terminal')->group(function () {
        Route::prefix('connection')->group(function () {
            Route::post('/', 'App\Http\Controllers\v1\Stripe\Terminal\ConnectionController@connect');
            Route::get('/locations', 'App\Http\Controllers\v1\Stripe\Terminal\ConnectionController@locations');
        });

        Route::prefix('readers')->group(function () {
            Route::get('/', 'App\Http\Controllers\v1\Stripe\Terminal\ReadersController@readers');
            Route::post('/create', 'App\Http\Controllers\v1\Stripe\Terminal\ReadersController@createReader');

        });

        Route::prefix('payments')->group(function () {
            Route::post('/', 'App\Http\Controllers\v1\Stripe\Terminal\PaymentsController@createPaymentIntent');

        });

    });

        Route::group(['prefix' => 'members'], function () {
            Route::get('/', 'App\Http\Controllers\v3\Whitelabel\MemberController@listMembers');
            Route::post('/accept','App\Http\Controllers\v3\Whitelabel\MemberController@acceptMember');;
            Route::post('/reject','App\Http\Controllers\v3\Whitelabel\MemberController@rejectMember');
        });

        Route::group(['prefix' => 'postals'], function () {
            Route::get('/', 'App\Http\Controllers\v3\WhiteLabel\PostalController@index');
            Route::get('/pricing', 'App\Http\Controllers\v3\WhiteLabel\PostalController@pricing');
        });



        Route::group(['prefix' => 'banners'], function () {
            Route::get('/', 'App\Http\Controllers\v3\WhiteLabel\ByBestShop\BannersController@index');
            Route::get('/types', 'App\Http\Controllers\v3\WhiteLabel\ByBestShop\BannersController@types');
        });

        Route::group(['prefix' => 'inventory-reports'], function () {
            Route::get('/orders-by-brand', 'App\Http\Controllers\v3\InventoryReportController@ordersByBrand');
            Route::get('/orders-by-brand-and-country', 'App\Http\Controllers\v3\InventoryReportController@ordersByBrandAndCountry');
            Route::get('/orders-by-brand-and-city', 'App\Http\Controllers\v3\InventoryReportController@ordersByBrandAndCity');
            Route::get('/{brandId}/daily-overview', 'App\Http\Controllers\v3\ReportController@getDailyOverviewReport');
            Route::get('/{brandId}/daily-sales-lc','App\Http\Controllers\v3\ReportController@getDailySalesInLCReport');
            Route::get('/{brandId}/inventory','App\Http\Controllers\v3\ReportController@getInventory');
            Route::get('/{brandId}/inventory-by-store', 'App\Http\Controllers\v3\ReportController@getInventoryByStore');
            Route::get('/{brandId}/inventory-turnover', 'App\Http\Controllers\v3\ReportController@getInventoryTurnoverReport');
            Route::get('/inventory-data', 'App\Http\Controllers\v3\InventoryReportController@getInventoryData');
            Route::get('/locations', 'App\Http\Controllers\v3\InventoryReportController@getLocationsSummary');
            Route::get('/sync-status', 'App\Http\Controllers\v3\InventoryReportController@getSyncStatus');
            Route::get('/upcoming-launches', 'App\Http\Controllers\v3\InventoryReportController@getUpcomingLaunches');
            Route::get('/inventory-distribution', 'App\Http\Controllers\v3\InventoryReportController@getInventoryDistribution');
            Route::get('/channel-performance', 'App\Http\Controllers\v3\InventoryReportController@getChannelPerformance');
            Route::get('/sync-health', 'App\Http\Controllers\v3\InventoryReportController@getSyncHealth');
            Route::get('/data-quality', 'App\Http\Controllers\v3\InventoryReportController@getDataQualityScore');
            Route::get('/all-report-data', 'App\Http\Controllers\v3\InventoryReportController@getAllReportData');
        });


        Route::group(['prefix' => 'subscriptions'], function () {
        Route::get('/get-subscription', 'App\Http\Controllers\v2\SubscriptionsController@getSubscription');
        Route::post('/create-checkout-session', 'App\Http\Controllers\v1\PricingPlansController@createCheckoutSession');
        Route::post('/confirm-subscription', 'App\Http\Controllers\v1\PricingPlansController@confirmSubscription');
    });

        Route::group(['prefix' => 'feature-feedback'], function () {
            Route::post('/', 'App\Http\Controllers\v1\FeatureFeedbackController@store');
        });

        Route::group(['prefix' => 'affiliate'], function () {
            Route::post('/apply', 'App\Http\Controllers\v1\AffiliateController@venueAffiliateApply');
        });

        Route::group(['prefix' => 'sync'], function () {
            Route::post('/bybest-inventory/', 'App\Http\Controllers\v3\InventorySyncController@startSync');
            Route::post('/bybest-collections/', 'App\Http\Controllers\v3\InventorySyncController@collectionSync');
            // inventory-master
            Route::get('/history', 'App\Http\Controllers\v3\InventorySyncController@syncHistory');

        });

        Route::group(['prefix' => 'venue'], function () {
            Route::post('/create', 'App\Http\Controllers\v1\RestaurantController@addNewVenue'); // where the venue logic creation is
            Route::get('/all-by-owner', 'App\Http\Controllers\v1\RestaurantController@allVenuesByOwner');
            Route::post('/pause', 'App\Http\Controllers\v1\RestaurantController@pause');
            Route::post('/reactivate', 'App\Http\Controllers\v1\RestaurantController@reactivate');
            Route::post('/calendar-availability', 'App\Http\Controllers\v1\RestaurantController@checkVenueCalendarAvailability');
        });

        Route::group(['prefix' => 'beach-bar'], function () {
            Route::get('/configuration', 'App\Http\Controllers\v2\EVController@getBeachBarConfiguration');
            Route::post('/configuration', 'App\Http\Controllers\v2\EVController@cuBeachBarConfiguration');

            Route::post('/area', 'App\Http\Controllers\v2\EVController@createArea');
            Route::put('/area', 'App\Http\Controllers\v2\EVController@editArea');
            Route::delete('/area/{areaId}', 'App\Http\Controllers\v2\EVController@deleteArea');
            Route::get('/area', 'App\Http\Controllers\v2\EVController@listAreas');

        });

        Route::group(['prefix' => 'whitelabel-customization'], function () {
            Route::get('/', 'App\Http\Controllers\v2\VenueWhitelabelCustomizationController@get');
            Route::put('/', 'App\Http\Controllers\v2\VenueWhitelabelCustomizationController@update');
        });

        Route::group(['prefix' => 'brand-profile-customization'], function () {
            Route::get('/', 'App\Http\Controllers\v2\VenueBrandProfileConfigController@get');
            Route::put('/', 'App\Http\Controllers\v2\VenueBrandProfileConfigController@update');
        });

        Route::group(['prefix' => 'invoices'], function () {
            Route::get('/', 'App\Http\Controllers\v2\InvoiceController@get');
            Route::get('/daily-invoices', 'App\Http\Controllers\v2\InvoiceController@dailyInvoiceSummary');
            Route::post('/create', 'App\Http\Controllers\v2\InvoiceController@store');
        });

        Route::group(['prefix' => 'business-configuration'], function () {
            Route::get('/', 'App\Http\Controllers\v2\InvoiceController@get');
            Route::get('/EOD-settings', 'App\Http\Controllers\v2\BusinessSettingController@showEndOfDay');
            Route::post('/end-of-day', 'App\Http\Controllers\v2\BusinessSettingController@storeEndOfDay');
        });

        Route::group(['prefix' => 'hygiene-standard'], function () {
            Route::get('/', 'App\Http\Controllers\v2\HygieneStandardController@general');
            Route::post('/hygiene-checks', 'App\Http\Controllers\v2\HygieneStandardController@createCheck');
            Route::put('/hygiene-checks', 'App\Http\Controllers\v2\HygieneStandardController@editCheck');
            Route::delete('/hygiene-checks/{checkId}', 'App\Http\Controllers\v2\HygieneStandardController@deleteCheck');
            Route::get('/hygiene-checks', 'App\Http\Controllers\v2\HygieneStandardController@listChecks');

            Route::put('/checklist-items/{itemId}', 'App\Http\Controllers\v2\HygieneStandardController@markChecklistItemCompleted');
            Route::delete('/checklist-items/{itemId}', 'App\Http\Controllers\v2\HygieneStandardController@deleteChecklistItem');

            Route::post('/hygiene-inspections', 'App\Http\Controllers\v2\HygieneStandardController@createInspection');
            Route::put('/hygiene-inspections', 'App\Http\Controllers\v2\HygieneStandardController@editInspection');
            Route::delete('/hygiene-inspections/{inspectionId}', 'App\Http\Controllers\v2\HygieneStandardController@deleteInspection');
            Route::get('/hygiene-inspections', 'App\Http\Controllers\v2\HygieneStandardController@listInspections');

            Route::post('/vendors', 'App\Http\Controllers\v2\HygieneStandardController@createVendor');
            Route::put('/vendors', 'App\Http\Controllers\v2\HygieneStandardController@editVendor');
            Route::delete('/vendors/{vendorId}', 'App\Http\Controllers\v2\HygieneStandardController@deleteVendor');
            Route::get('/vendors', 'App\Http\Controllers\v2\HygieneStandardController@listVendors');
        });

        Route::group(['prefix' => 'templates'], function () {
            Route::get('/', 'App\Http\Controllers\v1\TemplateController@index');
            Route::put('/', 'App\Http\Controllers\v1\TemplateController@update');
            Route::post('/', 'App\Http\Controllers\v1\TemplateController@store');
            Route::delete('/{id}', 'App\Http\Controllers\v1\TemplateController@destroy');
            Route::post('automatic-replies', 'App\Http\Controllers\v1\TemplateController@createAutomaticReply');
            Route::get('automatic-replies', 'App\Http\Controllers\v1\TemplateController@listAutomaticReplies');
            Route::put('automatic-replies', 'App\Http\Controllers\v1\TemplateController@updateAutomaticReplyTemplate');

        });

        Route::group(['prefix' => 'end-user-card'], function () {
            Route::get('/', 'App\Http\Controllers\v1\EndUserCardController@index');
            Route::patch('/', 'App\Http\Controllers\v1\EndUserCardController@update');
            Route::patch('/change-status', 'App\Http\Controllers\v1\EndUserCardController@changeStatus');
            Route::patch('/verify', 'App\Http\Controllers\v1\EndUserCardController@verify');
            Route::post('/', 'App\Http\Controllers\v1\EndUserCardController@store');
            Route::delete('/{id}', 'App\Http\Controllers\v1\EndUserCardController@destroy');
            Route::get('/nocards', 'App\Http\Controllers\v1\EndUserCardController@guestsWithoutCards');
            Route::get('/{id}', 'App\Http\Controllers\v1\EndUserCardController@showByID');
            Route::get('/scanned/{uuid}', 'App\Http\Controllers\v1\EndUserCardController@showByUUID');
            Route::post('/qr-code/{uuid}', 'App\Http\Controllers\v1\EndUserCardController@generateQrCode');
        });

        Route::group(['prefix' => 'surveys'], function () {
            Route::get('/templates', 'App\Http\Controllers\v2\SurveysController@getTemplates');
        });

        Route::group(['prefix' => 'emails'], function () {
            Route::get('/templates', 'App\Http\Controllers\v2\EmailsController@getKlaviyoTemplates');
        });

        Route::group(['prefix' => 'analytics'], function () {
            Route::get('/inventory-usage-over-time', 'App\Http\Controllers\v1\AnalyticsController@inventoryUsageOverTime');
        });

        Route::group(['prefix' => 'payment-links'], function () {
            Route::get('/', 'App\Http\Controllers\v1\PaymentLinksController@index');
            Route::post('/', 'App\Http\Controllers\v1\PaymentLinksController@store');
            Route::get('/{id}', 'App\Http\Controllers\v1\PaymentLinksController@show');
        });

        Route::group(['prefix' => 'orders-and-pay'], function () {
            Route::get('/', 'App\Http\Controllers\v1\OrdersController@getOrderAndPayOrders');
        });

        Route::group(['prefix' => 'dashboard'], function () {
            Route::get('/weekly-reservations', 'App\Http\Controllers\v1\DashboardController@weeklyReservations');
            Route::get('/table-turnaround-time', 'App\Http\Controllers\v1\DashboardController@tableTurnaroundTime');
            Route::get('/average-table-occupancy', 'App\Http\Controllers\v1\DashboardController@averageTableOccupancy');
            Route::get('/reservation-source-analysis', 'App\Http\Controllers\v1\DashboardController@reservationSourceAnalysis');
            Route::get('/analytics/guest-attendance', 'App\Http\Controllers\v1\DashboardController@guestAttendance');
            Route::get('/analytics/popular-items', 'App\Http\Controllers\v1\DashboardController@popularItems');
            Route::get('/customer-insights/top-guests', 'App\Http\Controllers\v1\DashboardController@topGuests');
            Route::get('/customer-insights/guests-overview', 'App\Http\Controllers\v1\DashboardController@guestsOverview');
            Route::get('/food-delivery-revenue', 'App\Http\Controllers\v1\DashboardController@fetchRevenueData');
            Route::get('/food-delivery-dashboard', 'App\Http\Controllers\v1\DashboardController@fetchDashboardData');

            // divide for accommodation, group routes
            Route::get('/accommodation', 'App\Http\Controllers\v1\DashboardController@getDashboardData');
        });

        Route::group(['prefix' => 'loyalty'], function () {
            Route::get('/programs/enrolled-guests/{id}', 'App\Http\Controllers\v1\LoyaltyController@getEnrolledGuests');
            Route::get('/programs/{id}', 'App\Http\Controllers\v1\LoyaltyController@show');
            Route::post('/programs', 'App\Http\Controllers\v1\LoyaltyController@store');
            Route::put('/programs', 'App\Http\Controllers\v1\LoyaltyController@update');
        });


        Route::group(['prefix' => 'accommodation'], function () {
            Route::post('/rental-unit', 'App\Http\Controllers\v1\AccommodationController@createRentalUnit');
            Route::get('/rental-unit/{id}', 'App\Http\Controllers\v1\AccommodationController@showRentalUnit');
            Route::get('/rental-unit', 'App\Http\Controllers\v1\AccommodationController@listRentalUnits');
            Route::delete('/rental-unit/{id}', 'App\Http\Controllers\v1\AccommodationController@destroyRentalUnit');
            Route::put('/rental-unit/{id}/name-location', 'App\Http\Controllers\v1\AccommodationController@updateNameLocation');
            Route::put('/rental-unit/{id}/facilities', 'App\Http\Controllers\v1\AccommodationController@updateFacilities');
            Route::put('/rental-unit/{id}/accommodation-setup', 'App\Http\Controllers\v1\AccommodationController@updateAccommodationSetup');
            Route::put('/rental-unit/{id}/pricing-calendar', 'App\Http\Controllers\v1\AccommodationController@updatePricingCalendar');
            Route::put('/rental-unit/{id}/policies-and-rules', 'App\Http\Controllers\v1\AccommodationController@updatePoliciesAndRules');
            Route::post('/rental-unit/{id}/upload-photo', 'App\Http\Controllers\v1\AccommodationController@rentalUploadPhoto');
            Route::post('/rental-unit/{id}/additional-fee-charge-update', 'App\Http\Controllers\v1\AccommodationController@updateAdditionalFeeAndCharge');
            Route::post('/rental-unit/{id}/additional-fee-charge-create', 'App\Http\Controllers\v1\AccommodationController@addAdditionalFeeCharge');
            Route::post('/rental-unit/{id}/add-room', 'App\Http\Controllers\v1\AccommodationController@addRoom');
            Route::delete('/rental-unit/{id}/delete-room/{roomId}', 'App\Http\Controllers\v1\AccommodationController@deleteRoom');
            Route::post('/rental-unit/{id}/custom-rule', 'App\Http\Controllers\v1\AccommodationController@customHouseRule');
            Route::delete('/rental-unit/{id}/delete-custom-rule/{customRuleId}', 'App\Http\Controllers\v1\AccommodationController@deleteCustomHouseRule');
            Route::delete('/rental-unit/{id}/delete-price-per-night/{pricePerNightId}', 'App\Http\Controllers\v1\AccommodationController@deletePricePerNight');
            Route::put('/rental-unit/{id}/price-per-night', 'App\Http\Controllers\v1\AccommodationController@updatePricePerNight');
            Route::post('/rental-unit/{id}/upload-room-photo/{roomId}', 'App\Http\Controllers\v1\AccommodationController@rentalRoomUploadPhoto');

            // booking api route with ics file
            Route::group(['prefix' => 'third-party-booking'], function () {
                Route::post('{id}/store', 'App\Http\Controllers\v1\BookingController@storeThirdPartyBooking');
                Route::post('{id}/show', 'App\Http\Controllers\v1\BookingController@showThirdPartyBooking');
            });

            // booking api route
            Route::group(['prefix' => 'booking'], function () {
                Route::get('/', 'App\Http\Controllers\v1\BookingController@index');
                Route::patch('/change-status', 'App\Http\Controllers\v1\BookingController@changeStatus');
            });
        });

        Route::group(['prefix' => 'housekeeping-tasks'], function () {
            Route::get('/', 'App\Http\Controllers\v1\HouseKeepingTaskController@getHouseKeepingTasks');
            Route::get('/{id}', 'App\Http\Controllers\v1\HouseKeepingTaskController@getHouseKeepingTasks');
            Route::post('/', 'App\Http\Controllers\v1\HouseKeepingTaskController@createHouseKeepingTask');
            Route::put('/{id}', 'App\Http\Controllers\v1\HouseKeepingTaskController@createHouseKeepingTask');
            Route::delete('/{id}', 'App\Http\Controllers\v1\HouseKeepingTaskController@destroyTask');
        });

        Route::group(['prefix' => 'gym'], function () {
            Route::get('/classes', 'App\Http\Controllers\v1\GymClassController@get');
            Route::get('/classes/{id}', 'App\Http\Controllers\v1\GymClassController@get');
            Route::post('/classes', 'App\Http\Controllers\v1\GymClassController@create');
            Route::put('/classes/{id}', 'App\Http\Controllers\v1\GymClassController@create');
            Route::delete('/classes/{id}', 'App\Http\Controllers\v1\GymClassController@destroy');
        });

        Route::group(['prefix' => 'golf'], function () {
            Route::get('/course-types', 'App\Http\Controllers\v1\GolfCourseTypesController@get');
            Route::get('/course-types/{id}', 'App\Http\Controllers\v1\GolfCourseTypesController@get');
            Route::post('/course-types', 'App\Http\Controllers\v1\GolfCourseTypesController@create');
            Route::put('/course-types/{id}', 'App\Http\Controllers\v1\GolfCourseTypesController@create');
            Route::delete('/course-types/{id}', 'App\Http\Controllers\v1\GolfCourseTypesController@destroy');
        });

        Route::group(['prefix' => 'bowling'], function () {
            Route::get('/lanes', 'App\Http\Controllers\v1\BowlingLaneController@get');
            Route::get('/lanes/{id}', 'App\Http\Controllers\v1\BowlingLaneController@get');
            Route::post('/lanes', 'App\Http\Controllers\v1\BowlingLaneController@create');
            Route::put('/lanes/{id}', 'App\Http\Controllers\v1\BowlingLaneController@create');
            Route::delete('/lanes/{id}', 'App\Http\Controllers\v1\BowlingLaneController@destroy');
        });

        Route::group(['prefix' => 'retail'], function () {
            Route::get('/orders', 'App\Http\Controllers\v1\OrdersController@getRetailOrders');
            Route::get('/store-settings', 'App\Http\Controllers\v1\RetailController@show');
            Route::post('/store-settings', 'App\Http\Controllers\v1\RetailController@cuStoreSettings');
            Route::get('/orders/{id}', 'App\Http\Controllers\v1\OrdersController@retailOrderDetails');
            Route::get('/customers', 'App\Http\Controllers\v1\RetailController@getCustomers');
            Route::patch('/orders/{id}/status', 'App\Http\Controllers\v1\OrdersController@changeOrderStatus');
            Route::get('/revenue', 'App\Http\Controllers\v1\RetailController@fetchRevenueData');
            Route::get('/dashboard', 'App\Http\Controllers\v1\RetailController@fetchDashboardData');
            Route::get('/inventory-analytics', 'App\Http\Controllers\v1\RetailController@fetchInventoryAnalyticsData');
            Route::get('/suppliers', 'App\Http\Controllers\v1\RetailController@listSuppliers');
            Route::post('/suppliers', 'App\Http\Controllers\v1\RetailController@createSupplier');
            Route::put('/suppliers', 'App\Http\Controllers\v1\RetailController@updateSupplier');
            Route::delete('/suppliers/{id}', 'App\Http\Controllers\v1\RetailController@deleteSupplier');
            Route::get('/brands', 'App\Http\Controllers\v1\RetailController@listBrands');
            Route::post('/brands', 'App\Http\Controllers\v1\RetailController@createBrand');
            Route::post('/brands/{id}', 'App\Http\Controllers\v1\RetailController@updateBrand');
            Route::delete('/brands/{id}', 'App\Http\Controllers\v1\RetailController@deleteBrand');
            Route::get('/collections', 'App\Http\Controllers\v1\RetailController@listCollections');
            Route::post('/collections', 'App\Http\Controllers\v1\RetailController@createCollection');
            Route::post('/collections/{id}', 'App\Http\Controllers\v1\RetailController@updateCollection');
            Route::delete('/collections/{id}', 'App\Http\Controllers\v1\RetailController@deleteCollection');
            Route::post('/shipping-zones', 'App\Http\Controllers\v1\RetailController@createShippingZone');
            Route::delete('/shipping-zones/{id}', 'App\Http\Controllers\v1\RetailController@deleteShippingZone');
            // inventory-master
            Route::get('scan-activities','App\Http\Controllers\v1\RetailController@scanHistory');

        });

        Route::group(['prefix' => 'promotions'], function () {

            // Discounts
            Route::get('/discounts', 'App\Http\Controllers\v1\PromotionsController@discounts');
            Route::get('/discounts/{id}', 'App\Http\Controllers\v1\PromotionsController@showDiscount');
            Route::patch('/discounts/{id}/update-status', 'App\Http\Controllers\v1\PromotionsController@updateDiscountStatus');
            Route::get('/active-discounts', 'App\Http\Controllers\v1\PromotionsController@listActiveDiscounts');
            Route::put('/discounts', 'App\Http\Controllers\v1\PromotionsController@updateDiscount');
            Route::post('/discounts', 'App\Http\Controllers\v1\PromotionsController@storeDiscount');

            // Coupons
            Route::get('/coupons', 'App\Http\Controllers\v1\PromotionsController@coupons');
            Route::post('/coupons', 'App\Http\Controllers\v1\PromotionsController@storeCoupon');
            Route::get('/coupons/{id}', 'App\Http\Controllers\v1\PromotionsController@showCoupon');
            Route::patch('/coupons/{id}/update-status', 'App\Http\Controllers\v1\PromotionsController@updateCouponStatus');
            Route::get('/active-coupons', 'App\Http\Controllers\v1\PromotionsController@listActiveCoupons');
            Route::put('/coupons', 'App\Http\Controllers\v1\PromotionsController@updateCoupon');

            // Promotions
            Route::get('/', 'App\Http\Controllers\v1\PromotionsController@index');
            Route::get('/{id}', 'App\Http\Controllers\v1\PromotionsController@show');
            Route::post('/', 'App\Http\Controllers\v1\PromotionsController@store');
            Route::put('/', 'App\Http\Controllers\v1\PromotionsController@update');
            Route::patch('/{id}/update-status', 'App\Http\Controllers\v1\PromotionsController@updateStatus');
            Route::post('/calendar', 'App\Http\Controllers\v1\PromotionsController@calendar');
        });

        // VisionTrack
        Route::group(['prefix' => 'vision-track'], function () {
            // Warehouse routes
            Route::get('devices', [DevicesController::class, 'index']);
            Route::post('devices', [DevicesController::class, 'store']);
            Route::put('devices/{deviceId}', [DevicesController::class, 'update']);
            Route::delete('devices/{deviceId}', [DevicesController::class, 'delete']);

            Route::get('devices/{deviceId}/streams', [DevicesController::class, 'indexStreams']);
            Route::post('devices/{deviceId}/streams', [DevicesController::class, 'storeStreams']);
            Route::put('devices/{deviceId}/streams/{streamId}', [DevicesController::class, 'updateStreams']);

            // get one stream
            Route::get('devices/{deviceId}/streams/{streamId}', [DevicesController::class, 'showStream']);
            Route::delete('devices/{deviceId}/streams/{streamId}', [DevicesController::class, 'deleteStreams']);


            Route::group(['prefix' => 'coreapi'], function () {
                // read / post data data from vision track
                Route::post('analytics/{deviceId}/{streamId}/people-count', [AnalyticsController::class, 'peopleCount']);
                Route::post('analytics/{deviceId}/{streamId}/demographics', [AnalyticsController::class, 'demographics']);
                Route::post('analytics/{deviceId}/traffic-trends', [AnalyticsController::class, 'trafficTrends']);
                Route::post('analytics/{deviceId}/demographic-traffic-overview', [AnalyticsController::class, 'demographicTrafficOverview']);
                Route::post('{deviceId}/entrylog', [AnalyticsController::class, 'setEntryLog']);
                Route::post('{deviceId}/exitlog', [AnalyticsController::class, 'setExitLog']);
            });

        });




        // Inventory Configuration
        Route::group(['prefix' => 'inventory-configurations'], function () {
            // Warehouse routes
            Route::get('warehouses', [InventoryConfigurationController::class, 'listWarehouses']);
            Route::post('warehouses', [InventoryConfigurationController::class, 'createWarehouse']);
            Route::put('warehouses/{warehouseId}', [InventoryConfigurationController::class, 'updateWarehouse']);
            Route::delete('warehouses/{warehouseId}', [InventoryConfigurationController::class, 'deleteWarehouse']);

            // Physical Store routes
            Route::get('physical-stores', [InventoryConfigurationController::class, 'listStores']);
            Route::post('physical-stores', [InventoryConfigurationController::class, 'createStore']);
            Route::put('physical-stores/{storeId}', [InventoryConfigurationController::class, 'updateStore']);
            Route::delete('physical-stores/{storeId}', [InventoryConfigurationController::class, 'deleteStore']);

            // E-commerce Platform routes
            Route::get('ecommerce-platforms', [InventoryConfigurationController::class, 'listEcommercePlatforms']);
            Route::post('ecommerce-platforms', [InventoryConfigurationController::class, 'createEcommercePlatform']);
            Route::put('ecommerce-platforms/{platformId}', [InventoryConfigurationController::class, 'updateEcommercePlatform']);
            Route::delete('ecommerce-platforms/{platformId}', [InventoryConfigurationController::class, 'deleteEcommercePlatform']);
        });

        Route::group(['prefix' => 'campaigns'], function () {
            Route::get('/', 'App\Http\Controllers\v1\CampaignController@index');
            Route::post('/', 'App\Http\Controllers\v1\CampaignController@store');
            Route::put('/', 'App\Http\Controllers\v1\CampaignController@update');
            Route::delete('/{id}', 'App\Http\Controllers\v1\CampaignController@delete');
        });

        Route::group(['prefix' => 'customers'], function () {
            Route::get('/food-delivery', 'App\Http\Controllers\v1\CustomersController@getFoodDeliveryCustomers');
        });

        Route::group(['prefix' => 'orders'], function () {
            Route::get('/by-vendor', 'App\Http\Controllers\v1\OrdersController@getOrdersByVendor');
            Route::post('/', 'App\Http\Controllers\v1\OrdersController@store');
            Route::post('/accept', 'App\Http\Controllers\v1\OrdersController@acceptOrder');
            Route::post('/create-delivery-request', 'App\Http\Controllers\v1\OrdersController@createDeliveryRequest');
            Route::get('/delivery', 'App\Http\Controllers\v1\OrdersController@getDeliveryOrders');
            Route::get('/delivery/{id}', 'App\Http\Controllers\v1\OrdersController@deliveryOrderDetails');
            Route::patch('/delivery/{id}/status', 'App\Http\Controllers\v1\OrdersController@changeOrderDeliveryStatus');
            Route::get('/pickup', 'App\Http\Controllers\v1\OrdersController@getPickupOrders');
            Route::get('/{id}', 'App\Http\Controllers\v1\OrdersController@show');
            Route::post('finalize-order', 'App\Http\Controllers\v1\OrdersController@finalizeOrder');
            Route::post('create-customer', 'App\Http\Controllers\v1\OrdersController@createCustomer');
        });

        Route::group(['prefix' => 'inventory'], function () {
            Route::post('/', 'App\Http\Controllers\v1\InventoryController@store');
            Route::put('/alert', 'App\Http\Controllers\v1\InventoryController@createUpdateInventoryAlert');
            Route::get('/', 'App\Http\Controllers\v1\InventoryController@index');
            Route::delete('/alert/{id}', 'App\Http\Controllers\v1\InventoryController@delete');
            // inventory-master
            Route::get('/{id}/activities', 'App\Http\Controllers\v1\InventoryController@activities');
            Route::get('/stock-level-report', 'App\Http\Controllers\v1\InventoryController@stockLevelReport');
            Route::get('/turnover-report', 'App\Http\Controllers\v1\InventoryController@inventoryTurnoverReport');
            Route::get('/low-stock-alert-report', 'App\Http\Controllers\v1\InventoryController@lowStockAlertReport');
            Route::get('/valuation-report', 'App\Http\Controllers\v1\InventoryController@inventoryValuationReport');
            Route::get('/seasonal-demand-analysis', 'App\Http\Controllers\v1\InventoryController@seasonalDemandAnalysis');
            Route::get('/forecasting', 'App\Http\Controllers\v1\InventoryController@inventoryForecasting');
            Route::get('/{id}', 'App\Http\Controllers\v1\InventoryController@show');
            Route::put('/{id}', 'App\Http\Controllers\v1\InventoryController@update');
            Route::get('/{id}/inventory-usage-history', 'App\Http\Controllers\v1\InventoryUsageHistoryController@index');


        });

        Route::group(['prefix' => 'reports'], function () {
            Route::get('sales-by-product-report', 'App\Http\Controllers\v1\ReportsController@salesByProductReport');
            Route::get('inventory-turnover-report', 'App\Http\Controllers\v1\ReportsController@inventoryTurnoverReport');
            Route::get('stock-aging-report', 'App\Http\Controllers\v1\ReportsController@stockAgingReport');
            Route::get('reorder-point-report', 'App\Http\Controllers\v1\ReportsController@reorderPointReport');
            Route::get('cost-of-goods-sold-report', 'App\Http\Controllers\v1\ReportsController@costOfGoodsSoldReport');
            Route::get('waitlist', 'App\Http\Controllers\v1\ReportsController@generateWaitlistReport');
            Route::get('table-metrics', 'App\Http\Controllers\v1\ReportsController@generateTableMetricsReport');
        });

        Route::group(['prefix' => 'sales'], function () {
            Route::post('/integrations/bulk-import', 'App\Http\Controllers\v1\ProductsController@bulkImportSales');
            Route::get('/imported', 'App\Http\Controllers\v1\ProductsController@getImportedSales');
            Route::post('/sales/single', [ProductsController::class, 'createSingleSale']);
            Route::post('/sales/sync-woocommerce', [ProductsController::class, 'syncWooCommerceSales']);
        });

        Route::group(['prefix' => 'menu'], function () {
            Route::post('create', 'App\Http\Controllers\v1\ProductsController@createMenu');
            Route::post('generate-digital', 'App\Http\Controllers\v1\ProductsController@generateDigitalMenu');

            Route::get('/categories', 'App\Http\Controllers\v1\CategoriesController@get');
            Route::post('/categories', 'App\Http\Controllers\v1\CategoriesController@store');
            Route::delete('/categories/{id}', 'App\Http\Controllers\v1\CategoriesController@delete');

            Route::get('/products', 'App\Http\Controllers\v1\ProductsController@get');
            Route::get('/products/{id}', 'App\Http\Controllers\v1\ProductsController@getOne');
            Route::get('/products/sku/{sku}', 'App\Http\Controllers\v1\ProductsController@getOneBySku');
            Route::post('/products', 'App\Http\Controllers\v1\ProductsController@store');
            Route::post('/products/store-after-scanning', 'App\Http\Controllers\v1\ProductsController@storeAfterScanning');
            Route::delete('/products/{id}', 'App\Http\Controllers\v1\ProductsController@delete');
            Route::post('/upload-photo', 'App\Http\Controllers\v1\ProductsController@uploadPhoto');
            Route::post('/retail-inventory', 'App\Http\Controllers\v1\ProductsController@createOrUpdateRetailProductInventory');
            Route::get('/inventories', 'App\Http\Controllers\v1\ProductsController@getRetailProductInventories');
            Route::get('/inventory-activity/{id}', 'App\Http\Controllers\v1\ProductsController@getRetailProductInventoryActivity');
            Route::post('/product-attributes', 'App\Http\Controllers\v1\ProductsController@createAndAssignToProduct');
            Route::delete('/product-attributes/{id}', 'App\Http\Controllers\v1\ProductsController@deleteProductAttribute');
            Route::get('/product-att-variations/{id}', 'App\Http\Controllers\v1\ProductsController@getProductAttributesForVariations');
            Route::post('/product-variations', 'App\Http\Controllers\v1\ProductsController@createUpdateVariationsForProduct');
            Route::delete('/product-variations/{id}', 'App\Http\Controllers\v1\ProductsController@deleteProductVariation');
            Route::post('/products/bulk-import', 'App\Http\Controllers\v1\ProductsController@bulkImportProducts');
            Route::post('/products/try-home-product', 'App\Http\Controllers\v1\ProductsController@tryHomeProduct');

        });

        Route::group(['prefix' => 'staff'], function () {
            Route::get('employees', 'App\Http\Controllers\v1\EmployeeController@index');
            Route::get('employees-staff', 'App\Http\Controllers\v1\EmployeeController@getHousekeepingStaff');
            Route::post('employees', 'App\Http\Controllers\v1\EmployeeController@store');
            Route::put('/employees/{id}', 'App\Http\Controllers\v1\EmployeeController@update');
            Route::get('/employees/{id}', 'App\Http\Controllers\v1\EmployeeController@show');
            Route::get('roles', 'App\Http\Controllers\v1\RolePermissionController@index');
            Route::post('generate-paycheck', 'App\Http\Controllers\v1\PayrollController@generatePaycheck');
            Route::post('payroll', 'App\Http\Controllers\v1\PayrollController@index');
            Route::post('payroll/calculate', 'App\Http\Controllers\v1\PayrollController@calculatePayroll');
            Route::post('reports/generate', 'App\Http\Controllers\v1\PayrollController@generateReport');
            Route::post('schedules', 'App\Http\Controllers\v1\PayrollController@createSchedule');
            Route::post('view-schedule-conflicts', 'App\Http\Controllers\v1\PayrollController@viewScheduleConflicts');
            Route::post('view-schedule-to-requests', 'App\Http\Controllers\v1\PayrollController@viewScheduleTORequests');
            Route::get('view-schedule-to-requests', 'App\Http\Controllers\v1\PayrollController@getAllScheduleTORequests');
            Route::post('approve-time-off', 'App\Http\Controllers\v1\PayrollController@approveTimeOff');
            Route::post('calculate-overtime', 'App\Http\Controllers\v1\PayrollController@calculateOvertime');
            Route::get('expense/{id}', 'App\Http\Controllers\v1\ExpensesController@getExpenseByEmployee');
            Route::post('expense', 'App\Http\Controllers\v1\ExpensesController@createExpense');
            Route::get('expense', 'App\Http\Controllers\v1\ExpensesController@index');
            Route::post('performance', 'App\Http\Controllers\v1\PerformanceController@create');
            Route::get('performance', 'App\Http\Controllers\v1\PerformanceController@index');
            Route::get('performance/{id}', 'App\Http\Controllers\v1\PerformanceController@getPerformanceByEmployee');
        });

        Route::group(['prefix' => 'reservations'], function () {
            Route::post('/', 'App\Http\Controllers\v1\ReservationController@create');
            Route::get('/', 'App\Http\Controllers\v1\ReservationController@index');
            Route::get('/filter', 'App\Http\Controllers\v1\ReservationController@filter');
            Route::get('/{id}', 'App\Http\Controllers\v1\ReservationController@show');
            Route::get('/{guestId}/guest-valid-promotions', 'App\Http\Controllers\v1\ReservationController@getValidPromotionsForGuest');
            Route::patch('/{id}/confirm', 'App\Http\Controllers\v1\ReservationController@confirm');
            Route::patch('/{id}/choose-table', 'App\Http\Controllers\v1\ReservationController@chooseTable');
            Route::patch('/{id}/apply-promo', 'App\Http\Controllers\v1\ReservationController@applyPromo');
            Route::patch('/{id}/provide-payment-method', 'App\Http\Controllers\v1\ReservationController@providePaymentMethod');
            Route::patch('/{id}/assign-order', 'App\Http\Controllers\v1\ReservationController@assignOrder');

        });

        Route::group(['prefix' => 'tables'], function () {
            Route::get('/', 'App\Http\Controllers\v1\TableController@index');
            Route::post('/', 'App\Http\Controllers\v1\TableController@create');
            Route::get('/shapes', 'App\Http\Controllers\v1\TableController@shapes');
            Route::get('/available', 'App\Http\Controllers\v1\TableController@getAvailableTables');
            Route::get('/details/{id}', 'App\Http\Controllers\v1\TableController@details');
            Route::get('/dining-space-locations', 'App\Http\Controllers\v1\TableController@diningSpaceLocations');
            Route::post('/dining-space-locations', 'App\Http\Controllers\v1\TableController@createDiningSpaceLocations');
            Route::get('/availability', 'App\Http\Controllers\v1\ReservationController@getAvailabilityByTable');
            Route::put('/move', 'App\Http\Controllers\v1\TableController@moveTable');
            Route::put('/merge', 'App\Http\Controllers\v1\TableController@mergeTables');
            Route::put('/split', 'App\Http\Controllers\v1\TableController@splitTable');
            Route::post('/assign-guests', 'App\Http\Controllers\v1\TableController@assignGuests');
            Route::get('/seating-arrangements', 'App\Http\Controllers\v1\SeatingArrangementController@index');
            Route::post('/seating-arrangements', 'App\Http\Controllers\v1\SeatingArrangementController@create');
            Route::put('/seating-arrangements', 'App\Http\Controllers\v1\SeatingArrangementController@update');
            Route::delete('/seating-arrangements', 'App\Http\Controllers\v1\SeatingArrangementController@destroy');
        });

        Route::group(['prefix' => 'waitlists'], function () {
            Route::get('/', 'App\Http\Controllers\v1\WaitlistController@index');
            Route::post('/', 'App\Http\Controllers\v1\WaitlistController@create');
            Route::get('/prioritize', 'App\Http\Controllers\v1\WaitlistController@prioritizeWaitlist');
            Route::put('/update-wait-time/{id}', 'App\Http\Controllers\v1\WaitlistController@updateWaitTime');
            Route::get('/guests/{id}/history', 'App\Http\Controllers\v1\WaitlistController@guestsHistory');
            Route::put('/', 'App\Http\Controllers\v1\WaitlistController@update');
        });

        Route::prefix('pricing-plans')->group(function () {
            Route::get('/', 'App\Http\Controllers\v1\PricingPlansController@index');
            Route::get('/{id}', 'App\Http\Controllers\v1\PricingPlansController@show');
            Route::post('/', 'App\Http\Controllers\v1\PricingPlansController@store');
            Route::put('/{id}', 'App\Http\Controllers\v1\PricingPlansController@update');
            Route::delete('/{id}', 'App\Http\Controllers\v1\PricingPlansController@destroy');
        });

        Route::prefix('blog')->group(function () {
            Route::get('/', 'App\Http\Controllers\v1\BlogsController@index');
            Route::get('/{id}', 'App\Http\Controllers\v1\BlogsController@view');
            Route::post('/', 'App\Http\Controllers\v1\BlogsController@store');
            Route::post('/{id}', 'App\Http\Controllers\v1\BlogsController@update');
            Route::delete('/{id}', 'App\Http\Controllers\v1\BlogsController@destroy');
        });
        Route::prefix('blog-categories')->group(function () {
            Route::get('/', 'App\Http\Controllers\v1\BlogsController@listBlogCategories');
            Route::post('/', 'App\Http\Controllers\v1\BlogsController@createBlogCategory');
            Route::put('/{id}', 'App\Http\Controllers\v1\BlogsController@updateBlogCategory');
            Route::delete('/{id}', 'App\Http\Controllers\v1\BlogsController@deleteBlogCategory');
        });
        Route::group(['prefix' => 'guests'], function () {
            Route::get('/', 'App\Http\Controllers\v1\GuestsController@index');
            Route::get('/no-table-reservations', 'App\Http\Controllers\v1\GuestsController@guestsWihoutTableReservations');
            Route::get('/{id}', 'App\Http\Controllers\v1\GuestsController@show');
            Route::post('/', 'App\Http\Controllers\v1\GuestsController@storeGuest');
            Route::post('/bulk-import', 'App\Http\Controllers\v1\GuestsController@bulkImportProducts');
        });

        Route::group(['prefix' => 'chat'], function () {
            Route::get('/', 'App\Http\Controllers\v1\ChatController@index');
            Route::post('/start-conversation', 'App\Http\Controllers\v1\ChatController@startConversation');
            Route::post('/messages', 'App\Http\Controllers\v1\MessageController@store');
            Route::get('/messages/{chatId}', 'App\Http\Controllers\v1\MessageController@index');
        });

        Route::group(['prefix' => 'third-party-integrations'], function () {
            Route::post('/ubereats/add-integration', 'App\Http\Controllers\v1\ThirdPartyIntegrations\UberEatsIntegrationController@addIntegration');
            Route::delete('/ubereats/delete-integration/{id}', 'App\Http\Controllers\v1\ThirdPartyIntegrations\UberEatsIntegrationController@disconnectIntegration');
            Route::post('/doordash/add-integration', 'App\Http\Controllers\v1\ThirdPartyIntegrations\DoordashDriveIntegrationController@addIntegration');
            Route::delete('/doordash/delete-integration/{id}', 'App\Http\Controllers\v1\ThirdPartyIntegrations\DoordashDriveIntegrationController@disconnectIntegration');
            Route::post('/doordash/create-delivery-request', 'App\Http\Controllers\v1\ThirdPartyIntegrations\DoordashDriveIntegrationController@createDelivery');
            Route::post('/doordash/get-delivery-update', 'App\Http\Controllers\v1\ThirdPartyIntegrations\DoordashDriveIntegrationController@getDeliveryUpdate');
            Route::post('/doordash/cancel-delivery-request', 'App\Http\Controllers\v1\ThirdPartyIntegrations\DoordashDriveIntegrationController@cancelDeliveryRequest');
        });

        Route::group(['prefix' => 'store-integrations'], function () {
            Route::post('/wc-connection', 'App\Http\Controllers\v2\StoreIntegrationsController@wcConnection');
            Route::get('/wc-connection', 'App\Http\Controllers\v2\StoreIntegrationsController@getWcConnection');
            Route::delete('/wc-connection', 'App\Http\Controllers\v2\StoreIntegrationsController@deleteWcConnection');
        });


        Route::group(['prefix' => 'accounts'], function () {
            Route::post('/update-restaurant', 'App\Http\Controllers\v1\RestaurantController@update');
            // venue upload photo
            // Golf - Manage
            // Restaurant - Web Profile
            // Hotel - Web Profile
            Route::post('/upload-photo', 'App\Http\Controllers\v1\RestaurantController@uploadPhoto');

            // Hotel - Gym
            // Hotel - Restaurant
            // Hotel - Events Hall
            Route::post('/facilities/upload-photo', 'App\Http\Controllers\v1\RestaurantController@facilityUploadPhoto');
            Route::delete('/photo/{id}', 'App\Http\Controllers\v1\RestaurantController@deletePhoto');
            Route::put('/facilities/availability/update', 'App\Http\Controllers\v1\RestaurantController@manageUpdateAvailability');
            Route::put('/white-label/availability/update', 'App\Http\Controllers\v1\RestaurantController@manageWhiteLabelUpdateAvailability');
            Route::post('/facilities/availability/calendar', 'App\Http\Controllers\v1\RestaurantController@checkCalendarAvailability');
            Route::put('/facilities/information/update', 'App\Http\Controllers\v1\RestaurantController@manageUpdateInformation');
            Route::put('/white-label/web-profile/update', 'App\Http\Controllers\v1\RestaurantController@updateWebProfile');


            Route::post('/verify-email', 'App\Http\Controllers\v1\RestaurantController@verifyEmail');
            Route::post('/resend-verify-email', 'App\Http\Controllers\v1\RestaurantController@resendVerifyEmail');
            Route::post('/add-card', 'App\Http\Controllers\v1\RestaurantController@addCard');
            Route::post('/payment-methods', 'App\Http\Controllers\v1\RestaurantController@getPaymentMethods');
            Route::post('/pay-with-card', 'App\Http\Controllers\v1\RestaurantController@payWithCard');
            Route::post('/change-subscribe', 'App\Http\Controllers\v1\RestaurantController@changeSubscription');



            Route::get('/usage-credits-history', 'App\Http\Controllers\v1\RestaurantController@usageCreditsHistory');
            Route::get('/wallet-history', 'App\Http\Controllers\v1\RestaurantController@walletHistory');
            Route::post('/custom-pricing-contact-sales', 'App\Http\Controllers\v2\SubscriptionsController@customPricingContactSalesAdminRequest');

            Route::get('/register-config', 'App\Http\Controllers\v1\RestaurantController@getRegisterConfig');
            Route::get('/payment-config', 'App\Http\Controllers\v1\RestaurantController@getPaymentConfig');
            Route::get('/check-qrcode', 'App\Http\Controllers\v1\RestaurantController@generateQRCodeForVenue');

            Route::get('/pricing-plans', 'App\Http\Controllers\v1\RestoConfigController@getPricePlans');
            Route::put('/update-email-preferences', 'App\Http\Controllers\v1\RestaurantController@updateEmailPreferences');

            Route::get('/check-web-profile', 'App\Http\Controllers\v1\RestaurantController@checkWebProfile');
            Route::post('/check-manage-venue', 'App\Http\Controllers\v1\RestaurantController@checkManageVenue');
            Route::get('/venue-configuration', 'App\Http\Controllers\v2\VenueConfigurationController@get');
            Route::post('/venue-configuration', 'App\Http\Controllers\v2\VenueConfigurationController@cuVenueConfiguration');
            Route::post('/venue-create-connect-account', 'App\Http\Controllers\v2\VenueConfigurationController@createConnectedAccount');
        });

        Route::group(['prefix' => 'marketing'], function () {
            Route::get('referrals', 'App\Http\Controllers\v1\ReferralsController@getReferrals');
            Route::patch('/referrals', 'App\Http\Controllers\v1\ReferralsController@updateUserReferralCreditFor');
        });

        Route::group(['prefix' => 'ai'], function () {
            Route::post('/post-chat', 'App\Http\Controllers\v1\AI\Admin\ChatbotController@sendChat');
            Route::post('/vb-assistant-ask', 'App\Http\Controllers\v1\AI\Admin\VBAssistantController@ask');
        });
    });
});

// API Calls for Superadmin
Route::middleware(['superadmin_api_key'])->prefix('v1')->group(function () {

    // Authentication API Calls
    Route::group([
        'middleware' => 'api',
        'prefix' => 'auth',
    ], function ($router) {
        Route::post('/login-superadmin', 'App\Http\Controllers\v1\AuthController@authenticateSuperadmin');
    });

    Route::prefix('faqs')->group(function () {
        Route::get('/', 'App\Http\Controllers\v1\FaqController@index');
        Route::post('/', 'App\Http\Controllers\v1\FaqController@store');
        Route::delete('/', 'App\Http\Controllers\v1\FaqController@destroy');
        Route::get('/category', 'App\Http\Controllers\v1\FaqCatController@index');
        Route::post('/category', 'App\Http\Controllers\v1\FaqCatController@store');
        Route::delete('/category', 'App\Http\Controllers\v1\FaqCatController@destroy');
    });

    Route::prefix('general-sync')->group(function () {
        Route::post('/sync-users-from-bb', 'App\Http\Controllers\v3\GeneralSyncController@syncUsersFromBB');
    });

    Route::prefix('/promotional-codes')->group(function () {
        Route::post('/', 'App\Http\Controllers\v2\PromotionalCodesController@create');
        Route::get('/', 'App\Http\Controllers\v2\PromotionalCodesController@listPromotionalCodes');
        Route::delete('/{id}', 'App\Http\Controllers\v2\PromotionalCodesController@deletePromotionalCode');
        Route::put('/', 'App\Http\Controllers\v2\PromotionalCodesController@update');
    });

    Route::prefix('/features-fill')->group(function () {
        Route::post('/food', 'App\Http\Controllers\v1\PricingPlansController@populateFoodFeatures');
        Route::post('/accommodation', 'App\Http\Controllers\v1\PricingPlansController@populateAccommodationFeatures');
        Route::post('/retail', 'App\Http\Controllers\v1\PricingPlansController@populateRetailFeatures');
        Route::post('/sport-entertainment', 'App\Http\Controllers\v1\PricingPlansController@populateSportEntertainmentFeatures');
    });

    Route::middleware(['jwt'])->group(function () {
        Route::group(['prefix' => 'profile'], function () {
            Route::get('/get-user-data', 'App\Http\Controllers\v1\AuthController@getUserData');
        });

        Route::prefix('/notifications')->group(function () {
            Route::get('/configuration/list', 'App\Http\Controllers\v2\NotificationController@listConfigurations');
            Route::post('/configuration/create', 'App\Http\Controllers\v2\NotificationController@createConfiguration');
            Route::put('/configuration/{id}', 'App\Http\Controllers\v2\NotificationController@updateConfiguration');
            Route::post('/firebase/token', 'App\Http\Controllers\v2\NotificationController@storeFirebaseToken');
            Route::delete('/configuration/{id}', 'App\Http\Controllers\v2\NotificationController@deleteConfiguration');
            Route::post('/send-test-notification', 'App\Http\Controllers\v2\NotificationController@sendTestNotification'); // For testing purposes
            Route::post('/send-token-test', 'App\Http\Controllers\v2\NotificationController@tryTokenDummyNotification'); // For testing purposes
            Route::post('/refresh-token', 'App\Http\Controllers\v2\NotificationController@refreshUserToken');
        });
    });

    Route::group(['prefix' => 'affiliate'], function () {
        Route::get('/programs', 'App\Http\Controllers\v1\AffiliateController@listAffiliatePrograms');
        Route::post('/create-affiliate-program', 'App\Http\Controllers\v1\AffiliateController@createAffiliateProgram');
        Route::post('/connect-affiliate-with-program', 'App\Http\Controllers\v1\AffiliateController@connectAffiliateWithProgram');
        Route::put('/activate-deactivate-affiliate-program/{id}', 'App\Http\Controllers\v1\AffiliateController@toggleAffiliateProgramStatus');
        Route::put('/update-affiliate-program/{id}', 'App\Http\Controllers\v1\AffiliateController@updateAffiliateProgram');
        Route::get('/list-affiliates/{affiliateProgramId}', 'App\Http\Controllers\v1\AffiliateController@listAffiliatesByProgramId');
        Route::get('/list-affiliates-by-type/{affiliateTypeId}', 'App\Http\Controllers\v1\AffiliateController@listAffiliatesByTypeId');
        Route::put('/approve-decline-affiliate/{affiliateId}', 'App\Http\Controllers\v1\AffiliateController@approveOrDeclineAffiliate');
        Route::post('/create-affiliate-plan', 'App\Http\Controllers\v1\AffiliateController@createAffiliatePlan');
        Route::get('/types', 'App\Http\Controllers\v1\AffiliateController@getAffiliateTypesWithPrograms');

    });

    Route::prefix('demo')->group(function () {
        Route::post('/create-account', 'App\Http\Controllers\v1\RestaurantController@create'); // where the venue logic creation is
        Route::post('/create-another-account', 'App\Http\Controllers\v1\RestaurantController@createAnotherDemo'); // where the venue logic creation is
    });

    // data behavioral event tracking
    Route::prefix('dbe-tracking')->group(function () {
        Route::post('/mixpanel-events', 'App\Http\Controllers\v2\DBETrackingController@getMixpanelEvents');
    });

    // public API
    Route::prefix('public-api')->group(function () {
        Route::post('/create-app', 'App\Http\Controllers\v2\ApiAppController@createApp');
    });


    Route::prefix('vendor-contact-applications')->group(function () {
        Route::get('', 'App\Http\Controllers\v1\VendorContactApplications@get');
        Route::get('/{id}', 'App\Http\Controllers\v1\VendorContactApplications@getOne');
        Route::put('/{id}', 'App\Http\Controllers\v1\VendorContactApplications@update');
    });

    Route::prefix('subscriptions-module')->group(function () {
        Route::get('/plans-with-new-feature-relationship', 'App\Http\Controllers\v2\SubscriptionsController@subscriptionPlansWithNewFeatureRelationships');
    });

    Route::prefix('/marketing-waitlist')->group(function () {
        Route::get('/', 'App\Http\Controllers\v2\MarketingWaitlistsController@listForSuperadmin');
    });

    Route::prefix('/analytics')->group(function () {
        Route::get('/venue-top-features/{venueId}', 'App\Http\Controllers\v2\AnalyticsController@topFeaturesByVenue');
        Route::get('/top-features', 'App\Http\Controllers\v2\AnalyticsController@topFeatures');
        Route::get('/top-venues', 'App\Http\Controllers\v2\AnalyticsController@topVenues');
        Route::get('/marketing-analytics', 'App\Http\Controllers\v1\BlogsController@blogsListAndReadCount');
        Route::post('/store-multiple-blogs', 'App\Http\Controllers\v1\BlogsController@storeBlog');
        Route::put('/update-blog', 'App\Http\Controllers\v1\BlogsController@updateBlog');
        Route::put('/update-blog-status', 'App\Http\Controllers\v1\BlogsController@updateStatus');

    });

    Route::prefix('/blogs')->group(function () {
        Route::get('/', 'App\Http\Controllers\v1\BlogsController@blogsListSuperadmin');
    });

    Route::prefix('/quizzes')->group(function () {
        Route::get('/', 'App\Http\Controllers\v1\AI\Web\QuizzesController@quizList');
        // Route::get('/quiz-config-list', 'App\Http\Controllers\v2\QuizConfigurationController@list');
        Route::post('/store-config', 'App\Http\Controllers\v2\QuizConfigurationController@store');
        Route::put('/update-config', 'App\Http\Controllers\v2\QuizConfigurationController@update');
        // Route::delete('/delete-quiz-config', 'App\Http\Controllers\v2\QuizConfigurationController@delete');
    });

    Route::prefix('/onboarding')->group(function () {
        Route::get('/', 'App\Http\Controllers\v2\OnboardingController@index');
        Route::get('/get-payment-gateways', 'App\Http\Controllers\v2\OnboardingController@getPaymentGatewaysForSuperadmin');
        Route::post('/generate-onboarding-link', 'App\Http\Controllers\v2\OnboardingController@generateOnboardingLink');
        Route::post('/generate-magic-link', 'App\Http\Controllers\v2\OnboardingController@generateMagicLink');
        Route::post('/convert-to-discover', 'App\Http\Controllers\v2\OnboardingController@convertToDiscover');
        Route::post('/convert-to-not-discover', 'App\Http\Controllers\v2\OnboardingController@convertToNotDiscover');
        Route::post('/convert-waitlist-to-lead', 'App\Http\Controllers\v2\OnboardingController@convertWaitlistToLead');
    });

    Route::prefix('/venues')->group(function () {
        Route::get('/', 'App\Http\Controllers\v1\RestaurantController@allVenues');
    });

    Route::prefix('restaurant-config')->group(function () {
        Route::get('/cuisinetypes', 'App\Http\Controllers\v1\RestoConfigController@getCusinTypes');
        Route::post('/cuisinetypes', 'App\Http\Controllers\v1\RestoConfigController@postCuisineType');
        Route::delete('/cuisinetypes/{id}', 'App\Http\Controllers\v1\RestoConfigController@deleteCuisineType');

        Route::get('/amenities', 'App\Http\Controllers\v1\RestoConfigController@getAmenities');
        Route::post('/amenities', 'App\Http\Controllers\v1\RestoConfigController@postAmenity');
        Route::delete('/amenities/{id}', 'App\Http\Controllers\v1\RestoConfigController@deleteAmenity');

        Route::get('/addons', 'App\Http\Controllers\v1\RestoConfigController@getAddons');
        Route::post('/addons', 'App\Http\Controllers\v1\RestoConfigController@postAddon');
        Route::delete('/addons/{id}', 'App\Http\Controllers\v1\RestoConfigController@deleteAddon');

        Route::get('/pricing-plans', 'App\Http\Controllers\v1\RestoConfigController@getPricePlans');
        Route::post('/pricing-plans', 'App\Http\Controllers\v1\RestoConfigController@postPricePlan');
        Route::delete('/pricing-plans/{id}', 'App\Http\Controllers\v1\RestoConfigController@deletePricePlan');

        Route::get('/features', 'App\Http\Controllers\v1\RestoConfigController@fetchFeatures');
        Route::post('/features', 'App\Http\Controllers\v1\RestoConfigController@storeFeature');
        Route::delete('/features/{id}', 'App\Http\Controllers\v1\RestoConfigController@destroyFeature');

        Route::get('/sub-features', 'App\Http\Controllers\v1\RestoConfigController@fetchSubFeatures');
        Route::post('/sub-features', 'App\Http\Controllers\v1\RestoConfigController@storeSubFeature');
        Route::delete('/sub-features/{id}', 'App\Http\Controllers\v1\RestoConfigController@destroySubFeature');

    });

    Route::prefix('ml')->group(function () {
        // Time Series Analysis - Model to forecast future occupancy rates
        Route::prefix('tsa-occupancy-rates-forecast')->group(function () {
            Route::get('/model-evaluations', 'App\Http\Controllers\v1\ML\OccupancyRatesForecastController@getModelEvaluations');
            Route::get('/model-summaries', 'App\Http\Controllers\v1\ML\OccupancyRatesForecastController@getModelSummaries');
            Route::get('/occupancy-rate-forecasts', 'App\Http\Controllers\v1\ML\OccupancyRatesForecastController@getOccupancyRateForecasts');
            Route::get('/prepared-occupancy-data', 'App\Http\Controllers\v1\ML\OccupancyRatesForecastController@getPreparedOccupancyData');
        });
    });

    Route::prefix('stripe-connected')->group(function () {
        Route::prefix('accounts')->group(function () {
            Route::get('/', 'App\Http\Controllers\v1\Stripe\Connected\AccountsController@get');
            Route::post('/', 'App\Http\Controllers\v1\Stripe\Connected\AccountsController@create');

        });
    });
});


// Public API Calls
Route::middleware(['public_api_key'])->prefix('v1')->group(function () {
    Route::prefix('vb')->group(function () {
        Route::prefix('/faqs')->group(function () {
            Route::get('/', 'App\Http\Controllers\v1\FaqController@index');
        });

        Route::prefix('/marketing-waitlist')->group(function () {
            Route::get('/', 'App\Http\Controllers\v2\MarketingWaitlistsController@listForAutomation');
        });


    });

    Route::prefix('public-vision-track')->group(function () {
        Route::get('devices/{deviceId}/streams', [DevicesController::class, 'AIPipelineStreams']);
    });
});


// API Calls for SN Platform
Route::middleware(['sn_platform_api_key'])->prefix('v1')->group(function () {
    Route::prefix('sn-platform-connect')->group(function () {
        Route::post('/reservations', 'App\Http\Controllers\v1\SNPlatform\SNReservationsController@create');
    });
});

// Public
Route::prefix('v3/tm-api')->group(function () {
    Route::get('/get-started/leads', [\App\Http\Controllers\v2\OnboardingController::class, 'getStartedLeads']);
    Route::post('/paysera/checkout/test', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\CheckoutController@testCheckout');
});
