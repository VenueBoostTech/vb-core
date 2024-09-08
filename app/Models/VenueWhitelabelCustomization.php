<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueWhitelabelCustomization extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'v_wl_information_id',
        'facebook_link',
        'twitter_link',
        'instagram_link',
        'booking_sites',
        'show_logo_header',
        'show_logo_footer',
        'header_links',
        'linkedin_link',
        'pinterest_link',
        'linkedin_link',
        'call_us_text',
        'support_phone',
        'show_newsletter',
        'contact_page_main_title_string',
        'contact_page_toplabel_string',
        'contact_page_address_string',
        'contact_page_phone_string',
        'contact_page_email_string',
        'contact_page_open_hours_string',
        'contact_page_form_subtitle_string',
        'contact_page_form_submit_btn_txt',
        'contact_page_fullname_label_string',
        'contact_page_phone_number_label_string',
        'contact_page_phone_number_show',
        'contact_page_email_label_string',
        'contact_page_subject_label_string',
        'contact_page_subject_show',
        'contact_page_content_label_string',
        'contact_page_content_show',
        'contact_page_enable',
        'contact_page_opening_hours_enable',
        'contact_page_address_value',
        'contact_page_email_value',
        'contact_page_phone_value',
        'contact_page_opening_hours_value',
        'vt_link'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function whitelabelInformation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VenueWhitelabelInformation::class, 'v_wl_information_id');
    }
}
