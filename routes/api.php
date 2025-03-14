<?php

use App\Http\Controllers\AppSuite\AppConfigurationController;
use App\Http\Controllers\AppSuite\AppWhitelabelController;
use App\Http\Controllers\AppSuite\ClientPortal\ClientDashboardController;
use App\Http\Controllers\AppSuite\ClientPortal\ClientInvoiceController;
use App\Http\Controllers\AppSuite\ClientPortal\ClientServiceRequestController;
use App\Http\Controllers\AppSuite\ClientPortal\ClientServicesController;
use App\Http\Controllers\AppSuite\ClientPortal\ClientTicketController;
use App\Http\Controllers\AppSuite\ClientPortal\WebhookController;
use App\Http\Controllers\AppSuite\Inventory\VBAppProductsController;
use App\Http\Controllers\AppSuite\NotificationsController;
use App\Http\Controllers\AppSuite\Staff\AdminAnalyticsController;
use App\Http\Controllers\AppSuite\Staff\AdminInvoiceController;
use App\Http\Controllers\AppSuite\Staff\AdminProjectController;
use App\Http\Controllers\AppSuite\Staff\AdminStaffController;
use App\Http\Controllers\AppSuite\Staff\AdminTaskController;
use App\Http\Controllers\AppSuite\Staff\AdminTicketController;
use App\Http\Controllers\AppSuite\Staff\AdminTimesheetController;
use App\Http\Controllers\AppSuite\Staff\AppClientController;
use App\Http\Controllers\AppSuite\Staff\AppFeedbackController;
use App\Http\Controllers\AppSuite\Staff\AttendanceController;
use App\Http\Controllers\AppSuite\Staff\BusinessController;
use App\Http\Controllers\AppSuite\Staff\ClientProjectsController;
use App\Http\Controllers\AppSuite\Staff\CommentController;
use App\Http\Controllers\AppSuite\Staff\CompanySetupController;
use App\Http\Controllers\AppSuite\Staff\EmployeeProjectController;
use App\Http\Controllers\AppSuite\Staff\EmployeeReportController;
use App\Http\Controllers\AppSuite\Staff\EmployeeTaskController;
use App\Http\Controllers\AppSuite\Staff\EmployeeTimesheetController;
use App\Http\Controllers\AppSuite\Staff\L2EmployeeTimesheetController;
use App\Http\Controllers\AppSuite\Staff\ServiceManagementController;
use App\Http\Controllers\AppSuite\Staff\ServiceRequestAdminController;
use App\Http\Controllers\AppSuite\Staff\ShiftController;
use App\Http\Controllers\AppSuite\Staff\StaffReportController;
use App\Http\Controllers\AppSuite\Staff\TeamController;
use App\Http\Controllers\AppSuite\Staff\TimeEntryController;
use App\Http\Controllers\AppSuite\Staff\EmployeeCheckListController;
use App\Http\Controllers\AppSuite\Staff\EmployeeServiceTicketController;
use App\Http\Controllers\AppSuite\Staff\EmployeeEquipmentController;
use App\Http\Controllers\AppSuite\Staff\EmployeeEquipmentCheckInCheckOutController;
use App\Http\Controllers\AppSuite\Staff\QualityInspectionsConstructionController;
use App\Http\Controllers\AppSuite\Staff\ConstructionSiteController;
use App\Http\Controllers\AppSuite\Staff\OshaComplianceEquipmentController;
use App\Http\Controllers\AppSuite\Staff\SafetyAuditController;
use App\Http\Controllers\AppSuite\Staff\ReportIncidentController;
use App\Http\Controllers\AppSuite\VBAppCustomersController;
use App\Http\Controllers\AppSuite\Staff\ConstructionSiteIssueController;
use App\Http\Controllers\AppSuite\Staff\ConstructionSiteRequirementController;
use App\Http\Controllers\AppSuite\Staff\ConstructionSiteNoticeController;
use App\Http\Controllers\AppSuite\Staff\ConstructionSiteSafetyChecklistController;
use App\Http\Controllers\AppSuite\Staff\ConstructionSiteGalleryController;
use App\Http\Controllers\AppSuite\Staff\EquipmentAssignmentController;
use App\Http\Controllers\TrackMaster\OnboardingAnalyticsController;
use App\Http\Controllers\v1\ProductsController;
use App\Http\Controllers\AppSuite\Staff\StaffChatController;
use App\Http\Controllers\v2\InventoryConfigurationController;
use App\Http\Controllers\v3\Accommodation\BookingsController;
use App\Http\Controllers\v3\Accommodation\CalendarConnectionController;
use App\Http\Controllers\v3\EndUserController;
use App\Http\Controllers\v3\GeneralSyncController;
use App\Http\Controllers\v3\Synchronization\AlphaSyncController;
use App\Http\Controllers\v3\Synchronization\OmniGatewaySyncController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\BbBrandsController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\BbCategoriesController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\BbCollectionsController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\BbGroupsController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\BbProductsController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\BbSearchController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\BBCheckoutController;
use App\Http\Controllers\VisionTrack\ActivityAnalyticsController;
use App\Http\Controllers\VisionTrack\AnalyticsController;
use App\Http\Controllers\VisionTrack\DevicesController;
use App\Http\Controllers\VisionTrack\VenueDetectionActivityController;
use App\Http\Controllers\VisionTrack\VtClientsController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\BBMenusController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\BBSliderController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\BbSimilarProductsController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\BbCartSuggestionProductsController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\CurrencyController;
use App\Http\Controllers\v3\Whitelabel\ByBestShop\PaymentMethodsController;
use App\Http\Controllers\v1\VbStoreAttributeController;
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
        'middleware' => ['jwt', 'api'],
        'prefix' => 'auth',
    ], function ($router) {
        Route::post('/resend-verify-email', 'App\Http\Controllers\v1\AuthController@resendVerifyEmail');

    });


    Route::prefix('faqs')->group(function () {
        Route::get('/search', 'App\Http\Controllers\v1\FaqController@search');
        Route::get('/by-category', 'App\Http\Controllers\v1\FaqController@searchByCategory');
    });

    Route::prefix('bb-shop-web')->group(function () {
        Route::get('/app-service-provider', 'App\Http\Controllers\v1\FaqController@search');
        Route::get('/home-page-brands', 'App\Http\Controllers\v1\FaqController@search');

        Route::get('/home', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\HomePageController@get');
        Route::get('/menus', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\HomePageController@getMenus');

        Route::prefix('brands')->group(function () {
            Route::get('/list', [BbBrandsController::class, 'showAllBrands']);
            Route::get('/{brand_url}', [BbBrandsController::class, 'brandProducts']);
            Route::get('/products/search', [BbBrandsController::class, 'searchProducts']);
        });

        Route::prefix('collections')->group(function () {
            Route::get('/list', [BbCollectionsController::class, 'showAllCollections']);
            Route::get('/{collection_url}', [BbCollectionsController::class, 'collectionProducts']);
            Route::get('/products/search', [BbCollectionsController::class, 'searchProducts']);
        });

        Route::post('/search', [BbSearchController::class, 'searchPage']);
        Route::get('/products/searchpreview', [BbSearchController::class, 'searchProductPreview']);

        Route::prefix('category')->group(function () {
            Route::get('/list', [BbCategoriesController::class, 'showAllCategories']);
            Route::get('/{category_url}', [BbCategoriesController::class, 'categoryProducts']);
            Route::get('/products/search', [BbCategoriesController::class, 'searchProducts']);
        });

        Route::prefix('group')->group(function () {
            Route::get('/{group_id}', [BbGroupsController::class, 'groupProducts']);
        });

        Route::prefix('products')->group(function () {
            Route::get('/{product_id}/{product_url}', [BbProductsController::class, 'singleProduct']);
            Route::get('/variant', [BbProductsController::class, 'changeProductVariant']);
        });


       Route::post('similar-products', [BbSimilarProductsController::class, 'getSimilarProducts']);

       Route::post('cart-suggestions', [BbCartSuggestionProductsController::class, 'getCartSuggestionProducts']);

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

        Route::group(['prefix' => 'postals'], function () {
            Route::get('/', [BBCheckoutController::class, 'index']);
            Route::get('/pricing', [BBCheckoutController::class, 'pricing']);
        });

        // Route::post('/checkout/bybest', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\TwoCheckoutController@quickCheckout');
        // Define the success and cancel routes
        Route::get('/payment/success', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\PaymentController@success')->name('payment.success');
        Route::get('/payment/cancel', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\PaymentController@cancel')->name('payment.cancel');
        Route::get('/payment/callback', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\PaymentController@callback')->name('payment.callback');
        Route::post('/quick-checkout/bybest', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\BBCheckoutController@quickCheckout');
        Route::post('/checkout/bybest', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\BBCheckoutController@checkout');
        Route::post('/initiate-bkt-payment', 'App\Http\Controllers\v3\Whitelabel\BktPaymentController@initiatePayment');

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
        Route::get('/country-city-state', 'App\Http\Controllers\v2\WebController@getCSCConfig');
        Route::post('/contact', 'App\Http\Controllers\v2\WebController@contact');
        Route::post('/update-statistics', 'App\Http\Controllers\v2\WebController@updateMarketingStatistics');

    });

    Route::group(['prefix' => 'ai'], function () {
        Route::post('/suggest-quiz', 'App\Http\Controllers\v1\AI\Web\QuizzesController@suggestQuiz');
        Route::post('/store-quiz-answers', 'App\Http\Controllers\v1\AI\Web\QuizzesController@storeQuizAnswers');
        Route::get('/blogs-list', 'App\Http\Controllers\v1\BlogsController@blogsList');
        Route::get('/featured-blog', 'App\Http\Controllers\v1\BlogsController@featuredBlog');
        // Route to get a single blog by slug
        Route::get('/blogs/{slug}', 'App\Http\Controllers\v1\BlogsController@getOneBlog');
        Route::post('/chat', 'App\Http\Controllers\v1\AI\ChatbotController@sendChat');
        Route::get('/search-blogs', 'App\Http\Controllers\v1\BlogsController@searchBlogs');

        Route::get('/blogs-list-metroshop', 'App\Http\Controllers\v1\BlogsController@blogsListMetroshop');
        Route::get('/blogs-list-metroshop/{slug}', 'App\Http\Controllers\v1\BlogsController@getBlogMetroshop');
        Route::post('/suggest-quiz-metroshop', 'App\Http\Controllers\v1\AI\Web\QuizzesController@suggestQuizMetroshop');
        Route::post('/store-quiz-answers-metroshop', 'App\Http\Controllers\v1\AI\Web\QuizzesController@storeQuizAnswersMetroshop');

    });

    Route::group(['prefix' => 'retail'], function () {
        Route::get('/product-details/{id}', 'App\Http\Controllers\v1\OrdersController@webProductDetails');
        Route::get('/shipping-methods', 'App\Http\Controllers\v1\OrdersController@shippingMethods');
        Route::post('/checkout', 'App\Http\Controllers\v1\OrdersController@retailOrder');
        Route::post('/validate-coupon', 'App\Http\Controllers\v1\OrdersController@validateCoupon');
        Route::post('/validate-mm-coupon', 'App\Http\Controllers\v1\OrdersController@validateMMCoupon');
        Route::post('/validate-discount', 'App\Http\Controllers\v1\OrdersController@validateDiscount');
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
            Route::post('/accept', 'App\Http\Controllers\v3\Whitelabel\MemberController@acceptMember');;
            Route::post('/reject', 'App\Http\Controllers\v3\Whitelabel\MemberController@rejectMember');
        });

        Route::group(['prefix' => 'postals'], function () {
            Route::get('/', 'App\Http\Controllers\v3\WhiteLabel\PostalController@index');
            Route::get('/pricing', 'App\Http\Controllers\v3\WhiteLabel\PostalController@pricing');
        });


        Route::group(['prefix' => 'banners'], function () {
            Route::get('/', 'App\Http\Controllers\v3\WhiteLabel\ByBestShop\BannersController@index');
            Route::get('/types', 'App\Http\Controllers\v3\WhiteLabel\ByBestShop\BannersController@types');
            Route::post('/bb-sliders/upload-photos', 'App\Http\Controllers\v3\WhiteLabel\ByBestShop\BannersController@uploadPhotos');
        });

        Route::group(['prefix' => 'inventory-reports'], function () {
           Route::get('/{brandId}/daily-overview', 'App\Http\Controllers\v3\ReportController@getDailyOverviewReport');
            Route::get('/{brandId}/daily-sales-lc', 'App\Http\Controllers\v3\ReportController@getDailySalesInLCReport');
            Route::get('/{brandId}/inventory', 'App\Http\Controllers\v3\ReportController@getInventory');
            Route::get('/{brandId}/inventory-by-store', 'App\Http\Controllers\v3\ReportController@getInventoryByStore');
            Route::get('/{brandId}/inventory-turnover', 'App\Http\Controllers\v3\ReportController@getInventoryTurnoverReport');

            Route::get('/orders-by-brand', 'App\Http\Controllers\v3\InventoryReportController@ordersByBrand');
            Route::get('/orders-by-brand-and-country', 'App\Http\Controllers\v3\InventoryReportController@ordersByBrandAndCountry');
            Route::get('/orders-by-brand-and-city', 'App\Http\Controllers\v3\InventoryReportController@ordersByBrandAndCity');
            Route::get('/inventory-data', 'App\Http\Controllers\v3\InventoryReportController@getInventoryData');
            Route::get('/locations', 'App\Http\Controllers\v3\InventoryReportController@getLocationsSummary');
            Route::get('/upcoming-launches', 'App\Http\Controllers\v3\InventoryReportController@getUpcomingLaunches');
            Route::get('/inventory-distribution', 'App\Http\Controllers\v3\InventoryReportController@getInventoryDistribution');
            Route::get('/channel-performance', 'App\Http\Controllers\v3\InventoryReportController@getChannelPerformance');
            Route::get('/data-quality', 'App\Http\Controllers\v3\InventoryReportController@getDataQualityScore');
            Route::get('/all-report-data', 'App\Http\Controllers\v3\InventoryReportController@getAllReportData');
        });

        Route::group(['prefix' => 'synchronizations'], function () {

            Route::get('/', 'App\Http\Controllers\v3\InventoryReportController@getSyncronizations');  // api documented
            Route::get('/health', 'App\Http\Controllers\v3\InventoryReportController@getSyncHealth'); // api documented
            Route::get('/{sync}/errors', 'App\Http\Controllers\v3\InventoryReportController@getSyncErrors'); // api documented

            Route::prefix('do-sync')->group(function() {
                Route::post('/price', [AlphaSyncController::class, 'syncPriceAlpha']); // api documented
                Route::post('/sku', [AlphaSyncController::class, 'syncSkuAlpha']);
                Route::post('/stocks', [AlphaSyncController::class, 'syncStockAlpha']);
                Route::post('/calculate-stock', [AlphaSyncController::class, 'calculateStock']);
            });

            Route::prefix('omni-gateway-sync')->group(function() {
                Route::post('/price', [OmniGatewaySyncController::class, 'syncPrice']);
                Route::post('/stock', [OmniGatewaySyncController::class, 'syncStock']);
            });
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
            // inventory-master
            Route::get('/history', 'App\Http\Controllers\v3\InventorySyncController@syncHistory');
            Route::get('/queue/check', [GeneralSyncController::class, 'countJobs']);
            Route::get('/list-groups', [GeneralSyncController::class, 'listGroups']);
            Route::post('/bybest-collections/', 'App\Http\Controllers\v3\InventorySyncController@collectionSync'); // ok
            Route::post('/bybest-brands/', 'App\Http\Controllers\v3\InventorySyncController@brandSync'); // ok
            Route::post('/bybest-groups/', 'App\Http\Controllers\v3\InventorySyncController@groupsSync'); // ok
            Route::post('/bybest-categories/', 'App\Http\Controllers\v3\InventorySyncController@categoriesSync'); // ok
            Route::post('/bybest-attributes/', 'App\Http\Controllers\v3\InventorySyncController@attributesSync'); // ok
            Route::post('/bybest-attroptions/', 'App\Http\Controllers\v3\InventorySyncController@attributesOptionsSync'); // ok
            Route::post('/bybest-products/', 'App\Http\Controllers\v3\InventorySyncController@productSync'); // ok

            Route::post('/bybest-productvariants/', 'App\Http\Controllers\v3\InventorySyncController@productVariantsSync'); // ok
            Route::post('/bybest-productattrs/', 'App\Http\Controllers\v3\InventorySyncController@productAttributesSync'); // ok
            Route::post('/bybest-productvariantattrs/', 'App\Http\Controllers\v3\InventorySyncController@productVariantAttributesSync'); // ok
            Route::post('/bybest-productgroups/', 'App\Http\Controllers\v3\InventorySyncController@productGroupsSync'); // ok
            Route::post('/bybest-productcategories/', 'App\Http\Controllers\v3\InventorySyncController@productCategorySync'); //ok
            Route::post('/bybest-productcollections/', 'App\Http\Controllers\v3\InventorySyncController@productCollectionSync'); // ok
            Route::post('/bybest-productgallery/', 'App\Http\Controllers\v3\InventorySyncController@productGallerySync'); // ok
            Route::post('/bybest-productstock/', 'App\Http\Controllers\v3\InventorySyncController@productStockSync'); // ok
            Route::post('/bybest-articlecats/', 'App\Http\Controllers\v3\ArticleSyncController@articleCategoriesSync');
            Route::post('/bybest-articles/', 'App\Http\Controllers\v3\ArticleSyncController@articlesSync');
            Route::post('/bybest-users', 'App\Http\Controllers\v3\GeneralSyncController@syncUsersFromBB');
            Route::post('/bybest-coupons/', 'App\Http\Controllers\v3\OrdersSyncController@couponsSync');
            Route::post('/bybest-orders/', 'App\Http\Controllers\v3\OrdersSyncController@ordersSync');
            Route::post('/bybest-orderproducts/', 'App\Http\Controllers\v3\OrdersSyncController@orderProductsSync');

            Route::post('/bybest-sliders/', 'App\Http\Controllers\v3\BbWebSyncController@slidersSync');
            Route::post('/bybest-mainmenus/', 'App\Http\Controllers\v3\BbWebSyncController@mainMenuSync');

            Route::post('/parallel-product-sync','App\Http\Controllers\v3\InventorySyncController@parallelProductSync');
            Route::get('/sync-status', 'App\Http\Controllers\v3\InventorySyncController@checkSyncStatus');

            Route::post('/parallel-sync/products', 'App\Http\Controllers\v3\InventorySyncController@parallelProductSync');
            Route::post('/parallel-sync/variants', 'App\Http\Controllers\v3\InventorySyncController@parallelProductVariantsSync');
            Route::post('/parallel-sync/attributes', 'App\Http\Controllers\v3\InventorySyncController@parallelProductAttributesSync');
            Route::post('/parallel-sync/variant-attributes', 'App\Http\Controllers\v3\InventorySyncController@parallelProductVariantAttributesSync');
            Route::post('/parallel-sync/groups', 'App\Http\Controllers\v3\InventorySyncController@parallelProductGroupsSync');
            Route::post('/parallel-sync/categories', 'App\Http\Controllers\v3\InventorySyncController@parallelProductCategoriesSync');
            Route::post('/parallel-sync/collections', 'App\Http\Controllers\v3\InventorySyncController@parallelProductCollectionsSync');
            Route::post('/parallel-sync/gallery', 'App\Http\Controllers\v3\InventorySyncController@parallelProductGallerySync');
            Route::post('/parallel-sync/stock', 'App\Http\Controllers\v3\InventorySyncController@parallelProductStockSync');

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


            Route::prefix('rental-units/{rentalUnitId}/calendar-connections')->group(function () {
                Route::get('/', [CalendarConnectionController::class, 'index']);
                Route::get('/logs', [CalendarConnectionController::class, 'logs']);
                Route::post('/', [CalendarConnectionController::class, 'store']);
                Route::put('/{connectionId}', [CalendarConnectionController::class, 'update']);
                Route::post('/{connectionId}/refresh', [CalendarConnectionController::class, 'refresh']);
                Route::post('/{connectionId}/disconnect', [CalendarConnectionController::class, 'disconnect']);
            });

            // booking api route with ics file
            Route::group(['prefix' => 'third-party-booking'], function () {
                Route::post('{id}/store', 'App\Http\Controllers\v1\BookingController@storeThirdPartyBooking');
                Route::post('{id}/show', 'App\Http\Controllers\v1\BookingController@showThirdPartyBooking');
            });

            // booking api route
            Route::group(['prefix' => 'booking'], function () {
                Route::get('/', 'App\Http\Controllers\v1\BookingController@index');
                Route::get('/{id}', 'App\Http\Controllers\v1\BookingController@getBookingDetails');
                Route::patch('/change-status', 'App\Http\Controllers\v1\BookingController@changeStatus');
                Route::patch('/paid', 'App\Http\Controllers\v1\BookingController@paid');
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
            Route::get('/customers-search', 'App\Http\Controllers\v1\RetailController@getSearchCustomers');
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
            Route::get('scan-activities', 'App\Http\Controllers\v1\RetailController@scanHistory');

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

            Route::prefix('vt-clients')->group(function () {
                Route::get('/', [VtClientsController::class, 'index']);
                Route::get('/stats', [VtClientsController::class, 'getSubscriptionStats']);
                Route::get('/expiring-soon', [VtClientsController::class, 'getExpiringSoon']);
                Route::get('/plans', [VtClientsController::class, 'getPlans']);  // New route for retrieving plans
                Route::get('/{id}', [VtClientsController::class, 'show']);
                Route::post('/subscribe', [VtClientsController::class, 'subscribe']); // New subscription endpoint

            });


            Route::prefix('analytics')->group(function () {
                Route::get('/heatmap', [ActivityAnalyticsController::class, 'getActivityHeatmap']);
                Route::get('/distribution', [ActivityAnalyticsController::class, 'getActivityDistribution']);
                Route::get('/recent', [ActivityAnalyticsController::class, 'getRecentActivities']);
                Route::get('/trends', [ActivityAnalyticsController::class, 'getActivityTrends']);
            });

            Route::prefix('detection-activities')->group(function () {
                // Global list of all detection activities
                Route::get('/global', [VenueDetectionActivityController::class, 'listGlobal']);
                // List available activities for venue
                Route::get('/', [VenueDetectionActivityController::class, 'listAvailable']);

                // Enable/Configure for venue
                Route::post('/', [VenueDetectionActivityController::class, 'store']);

                // Device assignments (device_id in request body)
                Route::get('/by-device', [VenueDetectionActivityController::class, 'listByDevice']);
                Route::post('/assign', [VenueDetectionActivityController::class, 'assignToDevice']);
                Route::put('/{id}', [VenueDetectionActivityController::class, 'updateDeviceActivity']);
                Route::delete('/{id}', [VenueDetectionActivityController::class, 'deleteFromDevice']);




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
            Route::patch('/delivery/{id}/leave-note', 'App\Http\Controllers\v1\OrdersController@leaveOrderNote');
            Route::patch('/delivery/{id}/billing', 'App\Http\Controllers\v1\OrdersController@updateBilling');
            Route::get('/pickup', 'App\Http\Controllers\v1\OrdersController@getPickupOrders');
            Route::get('/{id}', 'App\Http\Controllers\v1\OrdersController@show');
            Route::delete('/destroy/{id}', 'App\Http\Controllers\v1\OrdersController@destroy');
            Route::get('/tracking/details/{id}', 'App\Http\Controllers\v1\OrdersController@getTracking')->withoutMiddleware(['admin_api_key', 'jwt']);
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



        Route::group(['prefix' => 'similar-products'], function () {
           Route::get('/{bybest_id}', 'App\Http\Controllers\v1\SimilarProductController@getSimilarProducts');
           Route::post('/', 'App\Http\Controllers\v1\SimilarProductController@updateOrcreatesimilarProducts');
          });


          Route::group(['prefix' => 'cart-sugesstion'], function () {
            Route::get('/{bybest_id}', 'App\Http\Controllers\v1\CartSuggestionController@getCartSuggestion');
            Route::post('/', 'App\Http\Controllers\v1\CartSuggestionController@upateOrcreateCartSuggestion');
           });


            Route::get('/collections', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\BbCollectionsController@getCollections');
            Route::get('/categories', 'App\Http\Controllers\v1\CategoriesController@get');
            Route::post('/categories', 'App\Http\Controllers\v1\CategoriesController@store');
            Route::delete('/categories/{id}', 'App\Http\Controllers\v1\CategoriesController@delete');

            Route::get('/products-search', 'App\Http\Controllers\v1\ProductsController@getSearch');

            Route::get('/products', 'App\Http\Controllers\v1\ProductsController@get');
            Route::get('/products/{id}', 'App\Http\Controllers\v1\ProductsController@getOne');
            Route::patch('/products/{id}', 'App\Http\Controllers\v1\ProductsController@updateProduct');
            Route::get('/products/sku/{sku}', 'App\Http\Controllers\v1\ProductsController@getOneBySku');
            Route::post('/products', 'App\Http\Controllers\v1\ProductsController@store');
            Route::post('/products/store-after-scanning', 'App\Http\Controllers\v1\ProductsController@storeAfterScanning');
            Route::delete('/products/{id}', 'App\Http\Controllers\v1\ProductsController@delete');
            Route::get('/products/filters/{attribute}', 'App\Http\Controllers\v1\ProductsController@getProductAttributes');
            Route::post('/upload-photo', 'App\Http\Controllers\v1\ProductsController@uploadPhoto');
            Route::delete('/product-gallery/{id}', 'App\Http\Controllers\v1\ProductsController@deletePhoto');
            Route::post('/retail-inventory', 'App\Http\Controllers\v1\ProductsController@createOrUpdateRetailProductInventory');
            Route::get('/inventories', 'App\Http\Controllers\v1\ProductsController@getRetailProductInventories');
            Route::get('/inventory-activity/{id}', 'App\Http\Controllers\v1\ProductsController@getRetailProductInventoryActivity');
            Route::get('/product-attributes/list', 'App\Http\Controllers\v1\ProductsController@getProductAttributesList');
            Route::post('/product-attributes', 'App\Http\Controllers\v1\ProductsController@storeAttributesOptions');
            Route::put('/product-attributes/{id}', 'App\Http\Controllers\v1\ProductsController@updateProductAttribute');
            Route::delete('/product-attributes/{product_id}/{id}', 'App\Http\Controllers\v1\ProductsController@deleteAttributesOptions');
            Route::get('/product-att-variations/{id}', 'App\Http\Controllers\v1\ProductsController@getProductAttributesForVariations');
            Route::post('/product-variations/{product_id}/{option_id}', 'App\Http\Controllers\v1\ProductsController@addProductAttributeVariation');
            Route::delete('/product-variations/{id}', 'App\Http\Controllers\v1\ProductsController@deleteProductVariation');
            Route::post('/products/bulk-import', 'App\Http\Controllers\v1\ProductsController@bulkImportProducts');
            Route::post('/products/try-home-product', 'App\Http\Controllers\v1\ProductsController@tryHomeProduct');

            Route::apiResource('vb-store-attributes', VbStoreAttributeController::class);

            Route::get('/attributes-options/{id}', 'App\Http\Controllers\v1\VbStoreAttributeController@getAttributesOptions');
            Route::patch('/update-attributes-options', 'App\Http\Controllers\v1\VbStoreAttributeController@updateAttributeOptions');
            Route::delete('/delete-attributes-options/{id}', 'App\Http\Controllers\v1\VbStoreAttributeController@deleteAttributeOption');


            Route::group(['prefix' => 'inventory-management'], function () {
                Route::get('/summery', 'App\Http\Controllers\v1\ProductsController@getProductInventoriesSummery');
                Route::get('/cross-location-inventory-balance', 'App\Http\Controllers\v1\ProductsController@getCrossLocationInventoryBalance');

                Route::group(['prefix' => 'reports'], function (){
                    Route::get('/{id}', 'App\Http\Controllers\v1\InventoryReportController@show');
                    Route::get('/', 'App\Http\Controllers\v1\InventoryReportController@index');
                    Route::post('/generate', 'App\Http\Controllers\v1\InventoryReportController@create');
                    Route::put('/update/{id}', 'App\Http\Controllers\v1\InventoryReportController@update');
                    Route::delete('/{id}', 'App\Http\Controllers\v1\InventoryReportController@destroy');
                });
            });

            Route::group(['prefix' => 'sales-metrics'], function () {
                Route::get('/by-brands', 'App\Http\Controllers\v1\ProductsController@getSalesByBrands');
                Route::get('/by-ecomstore', 'App\Http\Controllers\v1\ProductsController@getSalesByEcomStore');
            });


            Route::resource('bb-menu', BBMenusController::class);
            Route::post('bb-menu/{id}', [BBMenusController::class , 'update']);

            Route::resource('bb-slider', BBSliderController::class);
            Route::post('bb-slider/{id}', [BBSliderController::class , 'update']);

            Route::resource('currencies', CurrencyController::class);

            Route::resource('payment-methods', PaymentMethodsController::class);

        });

        Route::group(['prefix' => 'staff'], function () {
            Route::get('employees', 'App\Http\Controllers\v1\EmployeeController@index');
            Route::get('employees-staff', 'App\Http\Controllers\v1\EmployeeController@getHousekeepingStaff');
            Route::post('employees', 'App\Http\Controllers\v1\EmployeeController@store');
            Route::put('/employees/{id}', 'App\Http\Controllers\v1\EmployeeController@update');
            Route::get('/employees/{id}', 'App\Http\Controllers\v1\EmployeeController@show');
            Route::delete('/employees/destroy/{id}', 'App\Http\Controllers\v1\EmployeeController@destroy');
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
            Route::get('quizzes', 'App\Http\Controllers\v1\AI\Web\QuizzesController@quizListMetroshop');
            Route::put('/update-quiz-config', 'App\Http\Controllers\v1\AI\Web\QuizzesController@updateQuizMetroshop');
        });

        Route::group(['prefix' => 'ai'], function () {
            Route::post('/post-chat', 'App\Http\Controllers\v1\AI\Admin\ChatbotController@sendChat');
            Route::post('/vb-assistant-ask', 'App\Http\Controllers\v1\AI\Admin\VBAssistantController@ask');
        });


        // VB Apps
        Route::group(['prefix' => 'vb-apps'], function () {
            Route::prefix('staff/admin')->group(function () {
                // Business Management
                Route::put('business/update-geofence-and-qr', [BusinessController::class, 'updateGeofenceAndQR']);

                // TODO: manage these + dynamic data
                Route::get('dashboard', [\App\Http\Controllers\v3\DashboardController::class, 'index']);
                Route::get('dashboard/export', [\App\Http\Controllers\v3\DashboardController::class, 'export']);
                Route::get('productivity', [\App\Http\Controllers\v3\ProductivityController::class, 'index']);
                Route::get('productivity/export', [\App\Http\Controllers\v3\ProductivityController::class, 'export']);
                Route::get('attendance', [\App\Http\Controllers\AppSuite\Staff\AttendanceController::class, 'index']);
                Route::get('attendance/export', [\App\Http\Controllers\AppSuite\Staff\AttendanceController::class, 'export']);
//                Route::get('status-overview', [\App\Http\Controllers\v3\DashboardController::class, 'project_status_overview']);
//                Route::get('employees/{id}/performance-history', [\App\Http\Controllers\v3\DashboardController::class, 'staff_performance_history']);
//                Route::get('top-performers', [\App\Http\Controllers\v3\DashboardController::class, 'top_performers']);
//                Route::get('completion-status', [\App\Http\Controllers\v3\DashboardController::class, 'task_completion_status']);
//                Route::get('completion-by-department', [\App\Http\Controllers\v3\DashboardController::class, 'task_completion_by_department']);
//                Route::get('recent-activities', [\App\Http\Controllers\v3\DashboardController::class, 'recent_activities']);
//                // Miscellaneous
//                Route::get('overview', [\App\Http\Controllers\v3\DashboardController::class, 'overview']);
//                Route::get('search', [\App\Http\Controllers\v3\DashboardController::class, 'search']);
//                Route::get('export', [\App\Http\Controllers\v3\DashboardController::class, 'export']);
                // Reports and Analytics
                Route::prefix('reports')->group(function () {
                    Route::get('time-tracking', [StaffReportController::class, 'timeTracking']);
                    Route::get('task-completion', [StaffReportController::class, 'taskCompletion']);
                    Route::get('productivity-trend', [\App\Http\Controllers\v3\DashboardController::class, 'productivity_trend']);
                    Route::get('department-averages', [\App\Http\Controllers\v3\DashboardController::class, 'department_averages']);
                    Route::get('staffing-forecast', [\App\Http\Controllers\v3\DashboardController::class, 'staffing_forecast']);
                });


                // Department Management
                Route::get('departments', [CompanySetupController::class, 'listDepartments']);
                Route::get('departments/{id}', [CompanySetupController::class, 'getDepartment']);
                Route::post('departments', [CompanySetupController::class, 'createDepartment']);
                Route::put('departments/{id}', [CompanySetupController::class, 'updateDepartment']);
                Route::delete('/departments/{id}', [CompanySetupController::class, 'deleteDepartment']);

                // Role Management
                Route::get('roles', [CompanySetupController::class, 'listRoles']);
                Route::post('roles/custom', [CompanySetupController::class, 'createCustomRole']);
                Route::get('roles/custom', [CompanySetupController::class, 'listCustomRoles']);
                Route::put('roles/custom/{id}', [CompanySetupController::class, 'updateCustomRole']);
                Route::delete('roles/custom/{id}', [CompanySetupController::class, 'deleteCustomRole']);
                Route::post('roles/attach', [CompanySetupController::class, 'attachRole']);
                Route::post('roles/detach', [CompanySetupController::class, 'detachRole']);

                // Employee Management
                Route::get('employees', [CompanySetupController::class, 'listEmployees']);
                Route::get('employees/managers', [CompanySetupController::class, 'listProjectManagers']);
                Route::get('employees/team-leaders', [CompanySetupController::class, 'listTeamLeaders']);
                Route::get('employees/operations-managers', [CompanySetupController::class, 'listOperationsManagers']);
                // todo: make this better -- activities
                Route::get('employees/{id}', [CompanySetupController::class, 'getEmployee']);
                // todo: add what requires the admin side on full
                Route::get('employees/full/{id}', [CompanySetupController::class, 'getEmployeeFullProfile']);
                Route::get('employee/{employeeId}/time-data', [AdminStaffController::class, 'getEmployeeTimeData']);
                Route::post('employees/{id}/status', [AdminStaffController::class, 'updateEmployeeStatus']);
                Route::post('employees', [CompanySetupController::class, 'createEmployee']);
                Route::post('employees-update/{id}', [CompanySetupController::class, 'updateEmployee']);
                Route::delete('employees/{id}', [CompanySetupController::class, 'deleteEmployee']);


                // Compliance Overview Screen
                Route::get('compliance/status', [AdminStaffController::class, 'getCompanyComplianceStatus']);

                // Compliance Report/Details
                Route::get('compliance/report', [AdminStaffController::class, 'generateCompanyComplianceReport']);

                Route::prefix('teams')->group(function () {
                    Route::get('/', [TeamController::class, 'listTeams']);
                    Route::get('/{id}', [TeamController::class, 'getTeam']);
                    Route::post('/', [TeamController::class, 'createTeam']);
                    Route::put('/{id}', [TeamController::class, 'updateTeam']);
                    Route::delete('/{id}', [TeamController::class, 'deleteTeam']);

                    Route::get('/{id}/departments', [TeamController::class, 'getTeamDepartments']);
                    Route::put('/{id}/departments', [TeamController::class, 'updateTeamDepartments']);
                    // todo: send notification
                    Route::post('{teamId}/assign-employees', [TeamController::class, 'assignEmployeesToTeam']);
                    // todo: send notification
                    Route::post('{teamId}/remove-employees', [TeamController::class, 'removeEmployeesFromTeam']);
                    Route::get('{teamId}/employees', [TeamController::class, 'getTeamEmployees']);
                    Route::post('/{id}/assign-team-leader', [TeamController::class, 'assignTeamLeader']);
                    Route::post('assign-operations-manager', [TeamController::class, 'assignOperationsManager']);
                });

                Route::prefix('activity')->group(function () {
                    Route::get('/', [AdminStaffController::class, 'activity']);
                    Route::get('/performance-metrics', [AdminStaffController::class, 'getPerformanceMetrics']);
                    Route::get('{id}/activity', [AdminStaffController::class, 'getEmployeeActivities']);

                });

                Route::prefix('logs')->group(function () {
                    Route::get('/audit', [\App\Http\Controllers\AppSuite\ClientPortal\AuditLogsController::class, 'index']);
                    Route::get('/audit/export', [\App\Http\Controllers\AppSuite\ClientPortal\AuditLogsController::class, 'index']);

                });

                // Project Management
                Route::prefix('projects')->group(function () {
                    Route::get('/', [AdminProjectController::class, 'index']);
                    Route::get('{id}/team', [AdminProjectController::class, 'getProjectTeam']);
                    Route::post('/', [AdminProjectController::class, 'store']);
                    Route::put('{id}', [AdminProjectController::class, 'update']);
                    Route::delete('{id}', [AdminProjectController::class, 'destroy']);
                    Route::post('{id}/assign-employee', [AdminProjectController::class, 'assignEmployee']);
                    Route::post('{id}/unassign-employee', [AdminProjectController::class, 'unassignEmployee']);
                    Route::post('{id}/assign-team', [AdminProjectController::class, 'assignTeam']);
                    Route::post('{id}/assign-project-manager', [AdminProjectController::class, 'assignProjectManager']);
                    Route::get('/statuses', [AdminProjectController::class, 'getProjectStatuses']);
                    Route::get('/time-entries', [AdminProjectController::class, 'getAllTimeEntries']);
                    Route::get('{id}', [AdminProjectController::class, 'show']); // New route for project details
                    Route::post('/{id}/assign-team-leaders', [AdminProjectController::class, 'assignTeamLeaders']);
                    Route::post('/{id}/assign-operations-managers', [AdminProjectController::class, 'assignOperationsManagers']);
                    Route::post('{id}/time-entries', [AdminProjectController::class, 'storeTimeEntry']);
                    Route::post('{id}/unassign-team-leader', [AdminProjectController::class, 'unassignTeamLeader']);
                    Route::post('{id}/unassign-project-manager', [AdminProjectController::class, 'unassignProjectManager']);
                    Route::post('{id}/unassign-operations-manager', [AdminProjectController::class, 'unassignOperationsManager']);


                    // todo: add comment section at project
                    // Note: also admin


                    Route::get('{id}/app-galleries', [AdminProjectController::class, 'getAppGalleriesByProjectId']);
                    Route::post('{id}/app-galleries', [AdminProjectController::class, 'addAppGallery']);
                    Route::delete('app-galleries/{galleryId}', [AdminProjectController::class, 'removeAppGallery']);

                });

                Route::prefix('client-projects')->group(function () {
                    Route::get('/', [ClientProjectsController::class, 'getClientProjects']);
                });

                Route::prefix('app-clients')->group(function () {
                    Route::get('/', [AppClientController::class, 'listClients']);
                    Route::get('/{id}', [AppClientController::class, 'getClient']);
                    Route::post('/', [AppClientController::class, 'createClient']);
                    Route::put('/{id}', [AppClientController::class, 'updateClient']);
                    Route::delete('/{id}', [AppClientController::class, 'deleteClient']);
                    Route::post('create-user', [AppClientController::class, 'createClientUser']);
                    Route::post('connect-user', [AppClientController::class, 'connectExistingUser']);
                });


                Route::prefix('feedback')->group(function () {
                    Route::get('/stats', [AppFeedbackController::class, 'getFeedbackStats']);
                    Route::get('/', [AppFeedbackController::class, 'getFeedbackList']);
                    Route::post('/{id}/respond', [AppFeedbackController::class, 'respond']);
                });


                Route::prefix('invoices')->group(function () {
                    Route::get('/', [AdminInvoiceController::class, 'index']);
                    Route::post('/generate', [AdminInvoiceController::class, 'generateInvoice']);
                    Route::get('/{id}', [AdminInvoiceController::class, 'show']);
                    Route::post('/{id}/mark-as-paid', [AdminInvoiceController::class, 'markAsPaid']);
                    Route::get('/{id}/download', [AdminInvoiceController::class, 'downloadPdf']);
                });

                // Webhook Routes (no auth middleware)
                Route::post('webhooks/stripe', [WebhookController::class, 'handleStripeWebhook']);
                Route::post('webhooks/banking', [WebhookController::class, 'handleBankingWebhook']);




                Route::prefix('service-categories')->group(function () {
                    Route::get('/', [ServiceManagementController::class, 'listCategories']);
                    Route::post('/', [ServiceManagementController::class, 'createCategory']);
                    Route::put('/{id}', [ServiceManagementController::class, 'updateCategory']);
                    Route::delete('/{id}', [ServiceManagementController::class, 'deleteCategory']);
                });

                Route::group(['prefix' => 'analytics'], function () {
                    Route::get('services', [AdminAnalyticsController::class, 'services']);
                    Route::get('clients', [AdminAnalyticsController::class, 'clients']);
                    Route::get('revenue', [AdminAnalyticsController::class, 'revenue']);
                    Route::get('services/export', [AdminAnalyticsController::class, 'exportServices']);
                    Route::get('clients/export', [AdminAnalyticsController::class, 'exportClients']);
                    Route::get('revenue/export', [AdminAnalyticsController::class, 'exportRevenue']);
                });

                Route::prefix('tickets')->group(function () {
                    Route::get('/', [AdminTicketController::class, 'index']);
                    Route::get('/{id}', [AdminTicketController::class, 'show']);
                    Route::post('/{id}/reply', [AdminTicketController::class, 'reply']);
                    Route::post('/{id}/assign', [AdminTicketController::class, 'assign']);
                    Route::post('/{id}/status', [AdminTicketController::class, 'updateStatus']);
                    Route::post('/{id}/priority', [AdminTicketController::class, 'updatePriority']);
                });

                Route::prefix('services')->group(function () {
                    Route::get('/', [ServiceManagementController::class, 'listServices']);
                    Route::get('/{id}', [ServiceManagementController::class, 'show']);
                    Route::post('/', [ServiceManagementController::class, 'createService']);
                    Route::put('/{id}', [ServiceManagementController::class, 'updateService']);
                    Route::delete('/{id}', [ServiceManagementController::class, 'deleteService']);
                });

                Route::prefix('service-requests')->group(function () {
                    Route::get('/', [ServiceRequestAdminController::class, 'index']);
                    Route::get('{id}', [ServiceRequestAdminController::class, 'show']);
                    Route::post('{id}/approve', [ServiceRequestAdminController::class, 'approve']);
                    Route::post('{id}/decline', [ServiceRequestAdminController::class, 'decline']);
                    Route::post('{id}/connect-project', [ServiceRequestAdminController::class, 'connectWithProject']);

                });

                Route::get('countries', [CompanySetupController::class, 'listCountries']);
                Route::get('states/{countryId}', [CompanySetupController::class, 'listStatesByCountry']);
                Route::get('cities/{stateId}', [CompanySetupController::class, 'listCitiesByState']);

                // Task Management
                Route::prefix('tasks')->group(function () {
                    Route::get('/', [AdminTaskController::class, 'index']); // List all tasks
                    Route::get('/statuses', [AdminTaskController::class, 'getTaskStatuses']);
                    Route::get('/{id}', [AdminTaskController::class, 'show']); // Get task details
                    Route::post('/', [AdminTaskController::class, 'store']); // Create new task
                    Route::put('{id}', [AdminTaskController::class, 'update']); // Update task
                    Route::delete('{id}', [AdminTaskController::class, 'destroy']); // Delete task
                    Route::post('{id}/assign', [AdminTaskController::class, 'assignEmployee']); // Assign task to an employee
                    Route::post('{id}/unassign', [AdminTaskController::class, 'unassignEmployee']); // Unassign task from employee

                    // todo: add comment section at task details

                });

                Route::group(['prefix' => 'shifts'], function () {
                    Route::get('/calendar', [ShiftController::class, 'getCalendarEvents']);
                    Route::post('/schedule', [ShiftController::class, 'createSchedule']);
                });

                Route::group(['prefix' => 'construction-site'], function () {
                    Route::post('/', [ConstructionSiteController::class, 'store']);
                    Route::get('/', [ConstructionSiteController::class, 'index']);
                    Route::get('/{id}', [ConstructionSiteController::class, 'show']);

                    Route::group(['prefix' => 'issues'], function () {
                        Route::get('/{constructionSiteId}', [ConstructionSiteIssueController::class, 'index']);
                        Route::post('/{constructionSiteId}', [ConstructionSiteIssueController::class, 'create']);
                        Route::put('/{id}', [ConstructionSiteIssueController::class, 'update']);
                        Route::delete('/{id}', [ConstructionSiteIssueController::class, 'destroy']);
                    });

                    Route::group(['prefix' => 'requirements'], function () {
                        Route::get('/{constructionSiteId}', [ConstructionSiteRequirementController::class, 'index']);
                        Route::post('/{constructionSiteId}', [ConstructionSiteRequirementController::class, 'create']);
                        Route::put('/{id}', [ConstructionSiteRequirementController::class, 'update']);
                        Route::delete('/{id}', [ConstructionSiteRequirementController::class, 'destroy']);
                    });

                    Route::group(['prefix' => 'notices'], function () {
                        Route::get('/{constructionSiteId}', [ConstructionSiteNoticeController::class, 'index']);
                        Route::post('/{constructionSiteId}', [ConstructionSiteNoticeController::class, 'create']);
                        Route::post('/update/{id}', [ConstructionSiteNoticeController::class, 'update']);
                        Route::delete('/{id}', [ConstructionSiteNoticeController::class, 'destroy']);
                    });

                    Route::group(['prefix' => 'checklists'], function () {
                        Route::get('/{constructionSiteId}', [ConstructionSiteSafetyChecklistController::class, 'index']);
                        Route::post('/{constructionSiteId}', [ConstructionSiteSafetyChecklistController::class, 'create']);
                        Route::put('/{id}', [ConstructionSiteSafetyChecklistController::class, 'update']);
                        Route::delete('/{id}', [ConstructionSiteSafetyChecklistController::class, 'destroy']);
                    });

                    Route::group(['prefix' => 'checklist-items'], function () {
                        Route::put('/{checkListId}/{id}', [ConstructionSiteSafetyChecklistController::class, 'updateItemStatus']);
                    });

                });

                Route::group(['prefix' => 'equipment'], function () {
                    Route::get('/', [EmployeeEquipmentController::class, 'index']);
                    Route::post('/', [EmployeeEquipmentController::class, 'store']);
                    Route::put('/{id}', [EmployeeEquipmentController::class, 'update']);
                });

                Route::group(['prefix' => 'equipment-assignments'], function () {
                    Route::get('/', [EquipmentAssignmentController::class, 'index']);
                    Route::post('/', [EquipmentAssignmentController::class, 'store']);
                    Route::put('/{id}', [EquipmentAssignmentController::class, 'update']);
                });

                Route::group(['prefix' => 'osha-compliance'], function () {
                    Route::get('/', [OshaComplianceEquipmentController::class, 'index']);
                    Route::post('/', [OshaComplianceEquipmentController::class, 'store']);
                });

                Route::group(['prefix' => 'safety-audit'], function () {
                    Route::get('/', [SafetyAuditController::class, 'getReportByVenue']);
                });
            });
        });
    });
});

Route::middleware(['omni_stack_gateway_api_key', 'api'])->prefix('v1')->group(function () {

    Route::group(['prefix' => 'feedback-os'], function () {
        Route::get('/', [VBAppCustomersController::class, 'listFeedbackOS']);
        Route::get('/stats', [VBAppCustomersController::class, 'getFeedbackStatsOS']);
        Route::get('/{id}', [VBAppCustomersController::class, 'getFeedbackByIdOS']);
    });

    Route::group(['prefix' => 'members-os'], function () {
        Route::get('/', 'App\Http\Controllers\v3\Whitelabel\MemberController@listMembersOS');
        Route::post('/accept', 'App\Http\Controllers\v3\Whitelabel\MemberController@acceptMemberOS');;
        Route::post('/reject', 'App\Http\Controllers\v3\Whitelabel\MemberController@rejectMemberOS');
        Route::get('/export', 'App\Http\Controllers\v3\Whitelabel\MemberController@exportMembersOS');
    });

    Route::group(['prefix' => 'stores-os'], function () {

        Route::get('/', [InventoryConfigurationController::class, 'listStoresOS']);
        Route::post('connect-disconnect', [InventoryConfigurationController::class, 'connectDisconnectStoreOS']);
    });


    Route::group(['prefix' => 'auth-os'], function () {
        Route::post('/get-connection', [App\Http\Controllers\AppSuite\Staff\AuthenticationController::class, 'getConnection']);
        Route::post('/create-venue-user-for-staffluent', [App\Http\Controllers\AppSuite\Staff\AuthenticationController::class, 'createVenueAndUserForStaffluent']);
        Route::post('/verify-user-email', [App\Http\Controllers\AppSuite\Staff\AuthenticationController::class, 'verifyUserEmail']);
        Route::post('/change-password', [App\Http\Controllers\AppSuite\Staff\AuthenticationController::class, 'changePassword']);

        // New route for staff connections
        Route::post('/get-staff-connection', [App\Http\Controllers\AppSuite\Staff\AuthenticationController::class, 'getStaffConnection']);
        Route::post('/get-mobile-staff-connection', [App\Http\Controllers\v1\AuthController::class, 'authenticate']);
    });

    Route::group(['prefix' => 'accommodation-os'], function () {
        Route::get('/', [App\Http\Controllers\v1\AccommodationController::class, 'listRentalUnitsForOmnistack']);
        Route::post('/{id}/external-id', [App\Http\Controllers\v1\AccommodationController::class, 'updateRentalUnitExternalId']);
        Route::get('/bookings', [App\Http\Controllers\v1\BookingController::class, 'listBookingsForOmnistack']);
        Route::post('/bookings/{id}/external-id', [App\Http\Controllers\v1\BookingController::class, 'updateBookingExternalId']);
        Route::delete('/guests/{id}', [App\Http\Controllers\v1\GuestsController::class, 'destroyForOmnistack']);
        Route::delete('/bookings/{id}', [App\Http\Controllers\v1\BookingController::class, 'destroyForOmnistack']);
    });

    Route::group(['prefix' => 'chats-os'], function () {
        Route::get('/', [App\Http\Controllers\v1\ChatController::class, 'listChatsForOmnistack']);
        Route::post('/{id}/external-id', [App\Http\Controllers\v1\ChatController::class, 'updateChatExternalId']);
        Route::delete('/{id}', [App\Http\Controllers\v1\ChatController::class, 'destroyForOmnistack']);
    });


    Route::group(['prefix' => 'campaigns-os'], function () {
        Route::get('/', [App\Http\Controllers\v1\CampaignController::class, 'listCampaignsForOmnistack']);
        Route::post('/{id}/external-id', [App\Http\Controllers\v1\CampaignController::class, 'updateCampaignExternalId']);
        Route::delete('/{id}', [App\Http\Controllers\v1\CampaignController::class, 'destroyForOmnistack']);
    });

    Route::group(['prefix' => 'promotions-os'], function () {
        Route::get('/', [App\Http\Controllers\v1\PromotionsController::class, 'listPromotionsForOmnistack']);
        Route::post('/{id}/external-id', [App\Http\Controllers\v1\PromotionsController::class, 'updatePromotionExternalId']);
        Route::delete('/{id}', [App\Http\Controllers\v1\PromotionsController::class, 'destroyPromotionForOmnistack']);
    });

    Route::group(['prefix' => 'discounts-os'], function () {
        Route::get('/', [App\Http\Controllers\v1\PromotionsController::class, 'listDiscountsForOmnistack']);
        Route::post('/{id}/external-id', [App\Http\Controllers\v1\PromotionsController::class, 'updateDiscountExternalId']);
        Route::delete('/{id}', [App\Http\Controllers\v1\PromotionsController::class, 'destroyDiscountForOmnistack']);
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
        Route::post('/blogs/{id}', 'App\Http\Controllers\v1\BlogsController@updateBlogNew');
        Route::get('/blogs/{id}', 'App\Http\Controllers\v1\BlogsController@getOneBlogNew');
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

    // VB Apps
    Route::group(['prefix' => 'vb-apps'], function () {
        Route::prefix('/app-configurations')->group(function () {
            Route::post('/configurations', [AppConfigurationController::class, 'store']);
            Route::get('/configurations/{app_id}', [AppConfigurationController::class, 'show']);
            Route::put('/configurations/{app_id}', [AppConfigurationController::class, 'update']);
            Route::delete('/configurations/{app_id}', [AppConfigurationController::class, 'destroy']);
        });
    });
});

// API Call for Client Portal
Route::middleware(['client_portal_api_key'])->prefix('v1')->group(function () {

    Route::middleware(['jwt'])->group(function () {

        Route::prefix('client-portal')->group(function () {

            // Service Requests
            Route::prefix('cp-service-requests')->group(function () {
                Route::post('request', [ClientServiceRequestController::class, 'requestService']);
                Route::get('my-requests', [ClientServiceRequestController::class, 'listMyRequests']);
                Route::get('my-requests/{id}', [ClientServiceRequestController::class, 'getRequestDetails']);
            });

            Route::prefix('cp-tickets')->group(function () {
                Route::get('/', [ClientTicketController::class, 'index']);
                Route::post('/', [ClientTicketController::class, 'store']);
                Route::get('/{id}', [ClientTicketController::class, 'show']);
                Route::post('/{id}/reply', [ClientTicketController::class, 'reply']);
            });

            Route::get('/cp-dashboard', [ClientDashboardController::class, 'getDashboardData']);

            // Service Requests
            Route::prefix('cp-services')->group(function () {
                Route::get('/', [ClientServicesController::class, 'index']);
                Route::get('/available', [ClientServicesController::class, 'available']);
                Route::get('/{id}', [ClientServicesController::class, 'show']);
                Route::post('/{id}/feedback', [ClientServiceRequestController::class, 'submitFeedback']);
                Route::get('/{id}/feedback', [ClientServiceRequestController::class, 'getFeedback']);
            });

            // Invoices
            Route::prefix('cp-invoices')->group(function () {
                Route::get('/', [ClientInvoiceController::class, 'index']);
                Route::get('/{id}', [ClientInvoiceController::class, 'show']);
                Route::post('/{id}/pay', [ClientInvoiceController::class, 'initiatePayment']);
                Route::get('/{id}/download', [ClientInvoiceController::class, 'downloadPdf']);
            });
        });
    });
});
// API Calls for EndUser
Route::middleware(['enduser_api_key'])->prefix('v1')->group(function () {

    Route::middleware(['jwt'])->group(function () {

        Route::prefix('end-user')->group(function () {
            // booking api route
            Route::group(['prefix' => 'bookings'], function () {
                Route::get('/', [BookingsController::class, 'index']);
                Route::get('/details/{bookingId}', [BookingsController::class, 'bookingDetails']);
            });

            Route::get('countries', 'App\Http\Controllers\v3\EndUserController@listcountries');
            Route::get('states/{countryId}', 'App\Http\Controllers\v3\EndUserController@listStatesByCountry');
            Route::get('cities/{stateId}', 'App\Http\Controllers\v3\EndUserController@listCitiesByState');

            Route::get('/orders', 'App\Http\Controllers\v3\EndUserController@getOrders');
            Route::get('/orders/{id}', 'App\Http\Controllers\v3\EndUserController@getOrderDetails');
            Route::get('/activities', 'App\Http\Controllers\v3\EndUserController@getActivities');
            Route::get('/wallet/info', 'App\Http\Controllers\v3\EndUserController@walletInfo');
            Route::get('/wallet/payment-methods', 'App\Http\Controllers\v3\EndUserController@getPaymentMethods');
            Route::get('/promotions', 'App\Http\Controllers\v3\EndUserController@getPromotions');
            Route::get('/promotions-guest', 'App\Http\Controllers\v3\EndUserController@getPromotionsGuest');
            Route::get('/wishlist', [EndUserController::class, 'getWishlist']);
            Route::post('/wishlist/add', [EndUserController::class, 'addToWishlist']);
            Route::delete('/wishlist/{productId}', [EndUserController::class, 'removeFromWishlist']);
            Route::get('/{id}', 'App\Http\Controllers\v3\EndUserController@getOne');

            Route::get('guest/payments', [EndUserController::class, 'getGuestPayments']);


            Route::group(['prefix' => 'chat'], function () {
                Route::get('/', 'App\Http\Controllers\v1\EndUserChatController@index');
                Route::post('/start-conversation', 'App\Http\Controllers\v1\EndUserChatController@startConversation');
                Route::post('/messages', 'App\Http\Controllers\v1\EndUserChatController@storeMessage');
                Route::get('/messages/{chatId}', 'App\Http\Controllers\v1\EndUserChatController@getMessages');
            });

            Route::group(['prefix' => 'setting'], function () {
                Route::post('/reset-password', 'App\Http\Controllers\v3\EndUserController@resetPassword');
                Route::get('/user-security-activities', 'App\Http\Controllers\v3\EndUserController@getUserActivities');
                Route::get('/marketing-settings', 'App\Http\Controllers\v3\EndUserController@getMarketingSettings');
                Route::post('/marketing-settings', 'App\Http\Controllers\v3\EndUserController@updateMarketingSettings');
                Route::get('/profile', 'App\Http\Controllers\v3\EndUserController@getGuestProfile');
                Route::put('/profile', 'App\Http\Controllers\v3\EndUserController@updateProfile');
                Route::put('/customer-profile', 'App\Http\Controllers\v3\EndUserController@updateCustomerProfile');
                Route::get('/customer-profile', 'App\Http\Controllers\v3\EndUserController@getCustomerProfile');
            });
        });
    });
});

// API Calls for VB Apps
Route::middleware(['vb_apps_api_key'])->prefix('v1')->group(function () {

    Route::group(['prefix' => 'vb-apps'], function () {
        Route::get('/web-app-config', [AppWhitelabelController::class, 'webAppConfig']);
        Route::post('/verify-supabase', [\App\Http\Controllers\AppSuite\Staff\AuthenticationController::class, 'getConnection']);
    });

    Route::middleware(['jwt'])->group(function () {

        Route::group(['prefix' => 'vb-apps'], function () {
            Route::group(['prefix' => 'products'], function () {
                Route::get('/', [VBAppProductsController::class, 'index']);
                Route::get('/gift-suggestions', [VBAppProductsController::class, 'giftSuggestions']);
            });
            Route::group(['prefix' => 'customers'], function () {
                Route::get('/', [VBAppCustomersController::class, 'index']);
                Route::get('/{id}', [VBAppCustomersController::class, 'show']);
                Route::post('/{id}/feedback', [VBAppCustomersController::class, 'storeFeedback']);
            });

            Route::prefix('staff')->group(function () {


                Route::group(['prefix' => 'reports'], function () {
                    Route::get('/data', [EmployeeReportController::class, 'getReportData']);
                });

                Route::group(['prefix' => 'dashboard'], function () {
                    Route::get('/', [\App\Http\Controllers\AppSuite\Staff\EmployeeDashboardController::class, 'getDashboardData']);
                    Route::get('/export', [\App\Http\Controllers\AppSuite\Staff\EmployeeDashboardController::class, 'exportData']);
                });


                Route::prefix('internal-chat')->group(function () {
                    // List/Index routes should come first
                    Route::get('/', [StaffChatController::class, 'index']);
                    Route::get('/search', [StaffChatController::class, 'searchChats']);
                    Route::get('/employees', [StaffChatController::class, 'listEmployees']);
                    Route::get('/clients', [StaffChatController::class, 'listClients']);

                    // Start chat route
                    Route::post('/start', [StaffChatController::class, 'startChat']);

                    // Dynamic parameter routes should come last
                    Route::get('/{chatId}', [StaffChatController::class, 'getMessages']);
                    Route::post('/{chatId}/messages', [StaffChatController::class, 'sendMessage']);
                });

                // Employee routes
                Route::prefix('employee')->middleware(['auth:api'])->group(function () {
                    Route::get('current-session', [EmployeeTimesheetController::class, 'getCurrentSession']);
                    Route::get('projects', [EmployeeProjectController::class, 'index']);
                    Route::get('projects/{id}', [EmployeeProjectController::class, 'show']);


                    Route::get('/service-list', [EmployeeServiceTicketController::class, 'getServiceList'])->name('service-list.index');

                    Route::prefix('projects/{projectId}')->group(function () {
                        Route::get('/checklists', [EmployeeCheckListController::class, 'index'])->name('checklists.index');
                        Route::post('/checklists', [EmployeeCheckListController::class, 'store'])->name('checklists.store');
                        Route::put('/checklists/{id}', [EmployeeCheckListController::class, 'update'])->name('checklists.update');
                        Route::delete('/checklists/{id}', [EmployeeCheckListController::class, 'destroy'])->name('checklists.destroy');

                        Route::post('/checklists-item/{checklistId}', [EmployeeCheckListController::class, 'addCheckListItem'])->name('checklists-item.store');
                        Route::put('/checklists-item/{checklistId}/{itemId}/mark-as-completed-uncompleted', [EmployeeCheckListController::class, 'markAsCompletedUnCompleted'])->name('checklists-item.markAsCompletedUnCompleted');


                        Route::get('/service-ticket', [EmployeeServiceTicketController::class, 'index'])->name('service-ticket.index');
                        Route::post('/service-ticket', [EmployeeServiceTicketController::class, 'store'])->name('service-ticket.store');

                        Route::get('/equipment', [EmployeeEquipmentController::class, 'index'])->name('equipment.index');
                        Route::post('/equipment', [EmployeeEquipmentController::class, 'store'])->name('equipment.store');

                        Route::post('/equipment-check-in-check-out/{equipmentId}', [EmployeeEquipmentCheckInCheckOutController::class, 'store'])->name('equipment-check-in-check-out.store');

                        Route::post('/quality-inspections-construction', [QualityInspectionsConstructionController::class, 'store'])->name('quality-inspections-construction.store');
                        Route::get('/quality-inspections-construction', [QualityInspectionsConstructionController::class, 'index'])->name('quality-inspections-construction.index');
                    });

                    Route::prefix('construction-site')->group(function () {

                        Route::group(['prefix' => 'osha-compliance'], function () {
                            Route::get('/', [OshaComplianceEquipmentController::class, 'index']);
                        });

                        Route::get('/', [ConstructionSiteController::class, 'index'])->name('construction-site.index');
                        Route::get('/{id}', [ConstructionSiteController::class, 'show'])->name('construction-site.show');
                        Route::get('{constructionSiteId}/check-in-exists', [ConstructionSiteController::class, 'checkInExists'])->name('construction-site.check-in-exists');
                        Route::post('{constructionSiteId}/check-in', [ConstructionSiteController::class, 'checkIn'])->name('construction-site.check-in');
                        Route::post('{constructionSiteId}/check-out/{checkInId}', [ConstructionSiteController::class, 'checkOut'])->name('construction-site.check-out');

                        Route::get('/report-incident', [ReportIncidentController::class, 'index'])->name('report-incident.index');

                        Route::prefix('/{constructionSiteId}/report-incident')->group(function () {
                            Route::post('/', [ReportIncidentController::class, 'store'])->name('report-incident.store');
                        });

                        Route::prefix('/safety-audit/{constructionSiteId}')->group(function () {
                            Route::get('/', [SafetyAuditController::class, 'index'])->name('safety-audit.index');
                            Route::post('/', [SafetyAuditController::class, 'store'])->name('safety-audit.store');
                        });

                        Route::prefix('issues')->group(function () {
                            Route::post('/{constructionSiteId}/', [ConstructionSiteIssueController::class, 'create']);
                            Route::get('/{constructionSiteId}/', [ConstructionSiteIssueController::class, 'index']);

                            Route::prefix('/{issueId}/comments')->group(function () {
                                Route::get('/', [CommentController::class, 'getCommentsForIssue']);
                                Route::post('/', [CommentController::class, 'addCommentToIssue']);
                                Route::delete('/{id}', [CommentController::class, 'deleteCommentFromIssue']);
                            });
                        });

                        Route::group(['prefix' => 'requirements'], function () {
                            Route::get('/{constructionSiteId}', [ConstructionSiteRequirementController::class, 'index']);
                        });

                        Route::group(['prefix' => 'notices'], function () {
                            Route::get('/{constructionSiteId}', [ConstructionSiteNoticeController::class, 'index']);
                        });

                        Route::group(['prefix' => 'checklists'], function () {
                            Route::get('/{constructionSiteId}', [ConstructionSiteSafetyChecklistController::class, 'index']);
                            Route::put('/{checkListId}/{id}', [ConstructionSiteSafetyChecklistController::class, 'updateItemStatus']);
                        });

                        Route::group(['prefix' => 'galleries'], function () {
                            Route::get('/{constructionSiteId}', [ConstructionSiteGalleryController::class, 'index']);
                            Route::post('/{constructionSiteId}', [ConstructionSiteGalleryController::class, 'create']);
                            Route::delete('/{id}', [ConstructionSiteGalleryController::class, 'destroy']);
                        });
                    });


                    Route::get('tasks', [EmployeeTaskController::class, 'index']);
                    Route::get('tasks/{id}', [EmployeeTaskController::class, 'show']);
                    Route::put('tasks/{id}/status', [EmployeeTaskController::class, 'updateStatus']);

                    Route::get('reports', [StaffReportController::class, 'employeeReport']);

                    Route::get('time-entries', [TimeEntryController::class, 'index']);
                    Route::post('time-entries', [TimeEntryController::class, 'store']);
                    Route::put('time-entries/{id}', [TimeEntryController::class, 'update']);
                    Route::delete('time-entries/{id}', [TimeEntryController::class, 'destroy']);
                    Route::post('time-entries/start', [TimeEntryController::class, 'startTimer']);
                    Route::post('time-entries/stop', [TimeEntryController::class, 'stopTimer']);

                    // Route::get('/tasks', [\App\Http\Controllers\v1\EmployeeProfileController::class, 'tasks']); // Not needed, maybe remove
                    // Route::get('/projects', [\App\Http\Controllers\v1\EmployeeProfileController::class, 'assigned_projects']); // Not needed, maybe remove
                    Route::get('/time-entries', [\App\Http\Controllers\v1\EmployeeProfileController::class, 'time_entries']);
                    Route::post('/update-profile', [\App\Http\Controllers\v1\EmployeeProfileController::class, 'update_profile']);
                    Route::post('/update-profile-picture', [\App\Http\Controllers\v1\EmployeeProfileController::class, 'update_profile_picture']);
                    Route::post('/save-firebase-token', [\App\Http\Controllers\v1\EmployeeProfileController::class, 'save_firebase_token']);
                    Route::post('/update-communication-preferences', [\App\Http\Controllers\v1\EmployeeProfileController::class, 'update_communication_preferences']);
                    Route::post('/update-tracking-preferences', [\App\Http\Controllers\v1\EmployeeProfileController::class, 'update_tracking_preferences']);
                    Route::post('/update-location', [\App\Http\Controllers\v1\EmployeeProfileController::class, 'update_location']);
                    Route::get('/tracking-status', [\App\Http\Controllers\v1\EmployeeProfileController::class, 'get_tracking_status']);
                    Route::get('/profile', [\App\Http\Controllers\v1\EmployeeProfileController::class, 'get_profile']);
                    Route::post('/reset-password', [\App\Http\Controllers\v1\EmployeeProfileController::class, 'reset_password']);


                    Route::prefix('projects')->group(function () {
                        Route::get('/{id}/app-galleries', [App\Http\Controllers\AppSuite\Staff\StaffController::class, 'getAppGalleriesByProjectId']);
                        Route::post('/{id}/app-galleries', [\App\Http\Controllers\AppSuite\Staff\StaffController::class, 'addAppGallery']);
                        Route::delete('/app-galleries/{galleryId}', [\App\Http\Controllers\AppSuite\Staff\StaffController::class, 'removeAppGallery']);

                        Route::get('/{id}/supplies-requests', [App\Http\Controllers\AppSuite\Staff\StaffController::class, 'getSuppliesRequests']);
                        Route::post('/{id}/supplies-requests', [App\Http\Controllers\AppSuite\Staff\StaffController::class, 'addSuppliesRequest']);
                        Route::get('/{id}/quality-inspections', [App\Http\Controllers\AppSuite\Staff\StaffController::class, 'getQualityInspections']);
                        Route::post('/{id}/quality-inspections', [App\Http\Controllers\AppSuite\Staff\StaffController::class, 'addQualityInspection']);
                        Route::get('/{id}/work-orders', [App\Http\Controllers\AppSuite\Staff\StaffController::class, 'getWorkOrders']);
                        Route::post('/{id}/work-orders', [App\Http\Controllers\AppSuite\Staff\StaffController::class, 'addWorkOrder']);
                        Route::get('/{id}/project-issues', [App\Http\Controllers\AppSuite\Staff\StaffController::class, 'getProjectIssues']);
                        Route::post('/{id}/project-issues', [App\Http\Controllers\AppSuite\Staff\StaffController::class, 'addProjectIssue']);

                        // Employee timesheet routes
                        Route::post('/{id}/clock-in', [EmployeeTimesheetController::class, 'clockIn']);
                        Route::post('/{id}/clock-out', [EmployeeTimesheetController::class, 'clockOut']);
                        Route::get('/{id}/my-timesheets', [EmployeeTimesheetController::class, 'getMyTimesheets']);

                        Route::get('/{id}/breaks', [EmployeeTimesheetController::class, 'getMyBreaks']);
                        Route::post('/{id}/breaks/start', [EmployeeTimesheetController::class, 'startBreak']);
                        Route::put('/{id}/breaks/{break_id}/end', [EmployeeTimesheetController::class, 'endBreak']);
                        // View own timesheet details
                        Route::get('/{id}/timesheet-details/{timesheetId}/details', [EmployeeTimesheetController::class, 'getTimesheetDetails']);

                        // comments routes
                        Route::get('/{projectId}/comments', [CommentController::class, 'getComments']);
                        Route::post('/{projectId}/comments', [CommentController::class, 'addComment']);
                        Route::delete('/comments/{id}', [CommentController::class, 'deleteComment']);

                        // L2 Employee timesheet routes
                        Route::get('l2/{id}/timesheets', [L2EmployeeTimesheetController::class, 'getProjectTimesheets']);
                        Route::get('l2/{id}/active-employees', [L2EmployeeTimesheetController::class, 'getActiveEmployees']);
                        Route::put('l2/timesheets/{id}', [L2EmployeeTimesheetController::class, 'updateTimesheet']);
                        Route::get('l2/{id}/timesheet-report', [L2EmployeeTimesheetController::class, 'generateReport']);

                        Route::get('l2/{id}/breaks', [L2EmployeeTimesheetController::class, 'getAllBreaks']);
                        Route::put('l2/{id}/breaks/{break_id}', [L2EmployeeTimesheetController::class, 'updateBreak']);

                        // Overtime Management
                        Route::get('l2/{id}/overtime', [L2EmployeeTimesheetController::class, 'getOvertimeSummary']);
                        Route::put('l2/{id}/overtime/approve', [L2EmployeeTimesheetController::class, 'approveOvertime']);

                        // Compliance
                        Route::get('l2/{id}/compliance', [L2EmployeeTimesheetController::class, 'getComplianceStatus']);
                        Route::get('l2/{id}/compliance/report', [L2EmployeeTimesheetController::class, 'generateComplianceReport']);

                    });
                });

                Route::group(['prefix' => 'shifts'], function () {
                    Route::get('/', [ShiftController::class, 'getShiftsData']);
                    Route::get('/leave-types', [ShiftController::class, 'getLeaveTypes']);
                    Route::get('/leave-balance', [ShiftController::class, 'getLeaveBalance']);
                    Route::post('/time-off', [ShiftController::class, 'requestTimeOff']);
                    Route::get('/time-off/list', [ShiftController::class, 'getShiftList']);
                });

                Route::group(['prefix' => 'attendance'], function () {
                    Route::get('/list', [AttendanceController::class, 'getAttendanceList']);
                    Route::get('/', [AttendanceController::class, 'getAttendanceData']);
                    Route::post('/check-in', [AttendanceController::class, 'checkIn']);
                    Route::post('/check-out', [AttendanceController::class, 'checkOut']);
                });
            });

            Route::group(['prefix' => 'notifications'], function () {
                Route::get('/', [NotificationsController::class, 'index']);
                Route::get('/counts', [NotificationsController::class, 'getCounts']);  // New route
                Route::put('/{id}/mark-as-read', [NotificationsController::class, 'markAsRead']);
                Route::put('/mark-all-as-read', [NotificationsController::class, 'markAllAsRead']);
                Route::delete('/{id}', [NotificationsController::class, 'destroy']);

                Route::get('/settings', [NotificationsController::class, 'getSettings']);
                Route::put('/settings', [NotificationsController::class, 'updateSettings']);
            });

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
    Route::post('/checkout/test', 'App\Http\Controllers\v3\Whitelabel\ByBestShop\CheckoutController@testCheckout');

    Route::prefix('/onboarding-analytics')->group(function () {
        Route::get('/overview', [OnboardingAnalyticsController::class, 'getOverview']);
        Route::get('/step-analysis', [OnboardingAnalyticsController::class, 'getStepAnalysis']);
        Route::get('/acquisition-analysis', [OnboardingAnalyticsController::class, 'getAcquisitionAnalysis']);
        Route::get('/onboarding-steps', [OnboardingAnalyticsController::class, 'getOnboardingSteps']);
        Route::get('/industry-analysis', [OnboardingAnalyticsController::class, 'getIndustryAnalysis']);
        Route::get('/conversion-timeline', [OnboardingAnalyticsController::class, 'getConversionTimeline']);
        Route::get('/subscription-analysis', [OnboardingAnalyticsController::class, 'getSubscriptionAnalysis']);
    });

    Route::prefix('/logs')->group(function () {
        Route::get('/onboarding-error-analysis', [OnboardingAnalyticsController::class, 'getErrorAnalysis']);
    });


    Route::prefix('/post-onboarding-analytics')->group(function () {
        Route::get('/user-engagement', [OnboardingAnalyticsController::class, 'getUserEngagementRate']);
        Route::get('/feature-adoption', [OnboardingAnalyticsController::class, 'getFeatureAdoptionRate']);
        Route::get('/revenue-growth', [OnboardingAnalyticsController::class, 'getRevenueGrowth']);
        Route::get('/churn-prediction', [OnboardingAnalyticsController::class, 'getChurnPrediction']);
        Route::get('/industry-comparison', [OnboardingAnalyticsController::class, 'getIndustryComparison']);
        Route::get('/venue-performance', [OnboardingAnalyticsController::class, 'getVenuePerformance']);
    });


});

Route::post('api/v1/white-label/bb/bkt-webhook', 'App\Http\Controllers\v3\Whitelabel\BktPaymentController@webhook')->name('webhook.bkt');


Route::get('api/v1/calendar/{obfuscatedId}/{token}.ics', [CalendarConnectionController::class, 'generateIcs'])
    ->name('rental-unit.ics');

Route::get('api/v1/calendar-complete/{obfuscatedId}/{token}.ics', [CalendarConnectionController::class, 'generateIcsComplete'])
    ->name('rental-unit.ics-complete');




Route::get('api/v1/end-user/messages/{chatId}', 'App\Http\Controllers\v1\EndUserChatController@getMessages');





// OLD Routes -- Keep here, maybe delete on the future
//Route::prefix('checkin-methods')->group(function () {
//    Route::post('create', 'App\Http\Controllers\v3\CheckinController@create');
//    Route::get('get/{id}', 'App\Http\Controllers\v3\CheckinController@get');
//    Route::get('get-all', 'App\Http\Controllers\v3\CheckinController@getAll');
//    Route::put('update/{id}', 'App\Http\Controllers\v3\CheckinController@update');
//    Route::delete('delete/{id}', 'App\Http\Controllers\v3\CheckinController@delete');
//    //
//    Route::post('user-check-in-out-methods/create', 'App\Http\Controllers\v3\UserCheckInOutMethodsController@create');
//    Route::get('user-check-in-out-methods/get/{id}', 'App\Http\Controllers\v3\UserCheckInOutMethodsController@get');
//    Route::get('user-check-in-out-methods/get-all', 'App\Http\Controllers\v3\UserCheckInOutMethodsController@getAll');
//
//    //updateToken
//    Route::post('update-token', 'App\Http\Controllers\v1\FCMController@updateToken');
//});
