<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stripe\Plan;

class Restaurant extends Model
{
    use HasFactory;

    protected $table = 'restaurants';

    protected $fillable = [
        'name',  'phone_number', 'email', 'website', 'user_id', 'logo', 'cover', 'pricing', 'capacity',
        'plan_type', 'plan_id', 'subscription_id', 'contact_id', 'stripe_customer_id', 'status', 'active_plan',
        'last_payment_date', 'referral_code', 'used_referral_id', 'venue_type', 'paused', 'currency', 'can_process_transactions',
        'years_in_business', 'use_referrals_for', 'qr_code_path', 'full_whitelabel',
        'inventory_warehouses',
        'has_ecommerce',
        'physical_stores',
        'reservation_start_time',
        'reservation_end_time',
        'timezone'
    ];

    public function blogs()
    {
        return $this->hasMany(Blog::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

   public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function tables()
    {
        return $this->hasMany(Table::class);
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function diningSpaceLocations()
    {
        return $this->hasMany(DiningSpaceLocation::class);
    }

    public function seatingArrangements()
    {
        return $this->hasMany(SeatingArrangement::class);
    }

    public function waitlists()
    {
        return $this->hasMany(Waitlist::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function performances()
    {
        return $this->hasMany(Performance::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function digitalMenu(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DigitalMenu::class);
    }

    public function promotions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    public function discounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function coupons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function venueType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VenueType::class, 'venue_type');
    }

    public function venueIndustry(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VenueIndustry::class, 'venue_industry');
    }

    public function photos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function loyaltyPrograms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LoyaltyProgram::class, 'venue_id');
    }

    public function gallery(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    public function golfAvailability(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GolfAvailability::class);
    }

    public function hotelEventsHalls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HotelEventsHall::class);
    }

    public function earn_points_histories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EarnPointsHistory::class);
    }

    public function use_points_histories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UsePointsHistory::class);
    }

    public function wallets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function paymentLinks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentLink::class);
    }

    public function venuePauseHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VenuePauseHistory::class, 'venue_id');
    }

    public function featureFeedbacks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FeatureFeedback::class, 'venue_id');
    }

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'plan_id');
    }

    public function addresses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Address::class, 'restaurant_addresses', 'restaurants_id', 'address_id');
    }

    public function cuisineTypes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(CuisineType::class, 'restaurant_cuisine_types', 'restaurants_id', 'cuisine_types_id');
    }

    public function templates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function automaticReplies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AutomaticReply::class);
    }

    public function storeSettings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StoreSetting::class, 'venue_id');
    }

    public function customers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Customer::class, 'venue_id');
    }

    public function suppliers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Supplier::class, 'venue_id');
    }

    public function brands(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Brand::class, 'venue_id');
    }

    public function shippingZones(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShippingZone::class, 'venue_id');
    }

    public function inventoryRetails(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryRetail::class, 'venue_id');
    }

    public function activityRetails(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ActivityRetail::class, 'venue_id');
    }

    public function variations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Variation::class, 'venue_id');
    }

    public function rentalUnits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalUnit::class, 'venue_id');
    }

    public function accommodationDetails(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccommodationDetail::class, 'venue_id');
    }

    public function breakfastDetails(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BreakfastDetail::class, 'venue_id');
    }

    public function parkingDetails(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ParkingDetail::class, 'venue_id');
    }

    public function accommodationRules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccommodationRule::class, 'venue_id');
    }

    public function accommodationHostProfiles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccommodationHostProfile::class, 'venue_id');
    }

    public function accommodationPaymentCapabilities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccommodationPaymentCapability::class, 'venue_id');
    }

    public function cardPreferences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CardPreference::class, 'venue_id');
    }

    public function pricingAndCalendar(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PricingAndCalendar::class, 'venue_id');
    }

    public function rooms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Room::class, 'venue_id');
    }

    public function restaurantConfiguration(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RestaurantConfiguration::class, 'venue_id');
    }

    public function endUserCards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EndUserCard::class, 'venue_id');
    }

    public function pricePerNights(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PricePerNight::class, 'venue_id');
    }


    public function additionalFeeAndCharges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdditionalFeeAndCharge::class, 'venue_id');
    }

    public function rentalCustomRules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalCustomRule::class, 'venue_id');
    }

    public function emailConfigurations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(EmailConfiguration::class, 'email_configuration_venue', 'venue_id');
    }

    public function surveys(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VenueSurvey::class, 'venue_id');
    }

    public function wcIntegration(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WcIntegration::class, 'venue_id');
    }

    public function receipts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Receipt::class, 'venue_id');
    }

    public function priceBreakdowns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PriceBreakdown::class, 'venue_id');
    }

    public function promptResponses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PromptsResponses::class, 'venue_id');
    }

    public function fineTuningJobs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FineTuningJob::class, 'venue_id');
    }

    public function affiliate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    public function venueWallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VenueWallet::class, 'venue_id');
    }

    public function referrals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RestaurantReferral::class, 'restaurant_id');
    }

    public function marketingLinks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MarketingLink::class, 'restaurant_id');
    }

    public function featureUsageCredit(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FeatureUsageCredit::class, 'venue_id');
    }

    public function gymAvailability(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GymAvailability::class);
    }

    public function bowlingAvailability(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BowlingAvailability::class);
    }

    public function venueConfiguration(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VenueConfiguration::class, 'venue_id');
    }

    public function potentialVenueLead(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PotentialVenueLead::class, 'venue_id');
    }

    public function venueCustomizedExperience(): \Illuminate\Database\Eloquent\Relations\HasOne {
        return $this->hasOne(VenueCustomizedExperience::class, 'venue_id');
    }

    public function venueLeadInfo(): \Illuminate\Database\Eloquent\Relations\HasOne {
        return $this->hasOne(VenueLeadInfo::class, 'venue_id');
    }

    public function apiUsages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApiUsageHistory::class, 'venue_id');
    }

    public function hygieneInspections(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HygieneInspection::class, 'venue_id');
    }

    public function hygieneChecks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HygieneCheck::class, 'venue_id');
    }

    public function hygieneStandardVendors(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HygieneStandardVendor::class, 'venue_id');
    }

    public function venueBrandProfileCustomization(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VenueBrandProfileCustomization::class, 'venue_id');
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class, 'venue_id');
    }

    public function venueCustomPricingContacts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VenueCustomPricingContact::class, 'venue_id');
    }

    public function referredPotentialVenueLeads(): \Illuminate\Database\Eloquent\Relations\hasMany
    {
        return $this->hasMany(PotentialVenueLead::class, 'referer_id');
    }

    public function affiliateWalletHistory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        // has many because if affiliate type is more than 1 month, each month will have a record of affiliate amount
        return $this->hasMany(AffiliateWalletHistory::class, 'registered_venue_id');
    }

    public function whitelabelCustomizations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VenueWhitelabelCustomization::class, 'venue_id');
    }

    public function venueSubscribers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VenueSubscriber::class, 'venue_id');
    }

    public function venueContactForms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VenueContactForm::class, 'venue_id');
    }

    public function whiteLabelOpeningHours(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OpeningHour::class)->where('used_in_white_label', true);
    }

    public function venueBeachBarConfiguration(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VenueBeachBarConfiguration::class, 'venue_id');
    }

    public function venueBeachAreas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VenueBeachArea::class, 'venue_id');
    }

    public function umbrellas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Umbrella::class, 'venue_id');
    }

    public function beachBarTickets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BeachBarTicket::class, 'venue_id');
    }

    public function beachBarBookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BeachBarBooking::class, 'venue_id');
    }

    // has many imported sales
    public function importedSales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ImportedSale::class, 'venue_id');
    }

    public function chats(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chat::class, 'venue_id');
    }

    public function visionTrackConfigurations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VisionTrackConfiguration::class, 'venue_id');
    }

    public function physicalStores(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PhysicalStore::class, 'venue_id');
    }


    public function inventoryWarehouses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryWarehouse::class, 'venue_id');
    }

    public function ecommercePlatforms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EcommercePlatform::class, 'venue_id');
    }

    public function devices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VtDevice::class, 'venue_id');
    }

    public function scanActivities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ScanActivity::class);
    }

    public function members(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Member::class, 'venue_id');
    }

    public function postals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Postal::class, 'venue_id');
    }

    public function whitelabelBanners(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WhitelabelBanner::class, 'venue_id');
    }

    public function dailyOverviewReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DailyOverviewReport::class, 'venue_id');
    }

    public function dailyOverviewSalesLc(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DailySalesLcReport::class, 'venue_id');
    }

}
