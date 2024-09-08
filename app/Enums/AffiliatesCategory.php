<?php

namespace App\Enums;

class AffiliatesCategory
{

    const INFLUENCERS = 'Influencers';
    const INDUSTRY_BLOGGERS = 'Industry Bloggers';
    const MAGAZINES_MEDIA_OUTLETS = 'Magazines & Media Outlets';
    const CONSULTANTS = 'Consultants';

    //*Influencers
    //**Micro-Influencers**: 1,000-10,000 followers; could focus on niche markets.
    //**Mega-Influencers**: 10,000+ followers; have a broader reach but less engagement.
    //
    //*Industry Bloggers
    //**Niche Bloggers**: Specialize in a single industry like F&B or Retail.
    //**General Tech Bloggers**: Cover broader topics and can provide an overarching view of your platform.
    //
    //*Magazines & Media Outlets
    //**Trade Publications**: Focus on specific industries, giving your brand industry-specific credibility.
    //**Mainstream Media**: Broader reach but less targeted.
    //
    //*Consultants
    //**Industry Consultants**: Experts in specific verticals.
    //**Business Strategy Consultants**: Look at the business from a 360-degree perspective.
}
