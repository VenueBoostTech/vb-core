<?php

namespace App\Http\Controllers\v1\AI\Admin;

use App\Models\FineTuningJob;
use App\Models\PromptsResponses;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ChatbotController extends Controller
{
    public function sendChat(Request $request): \Illuminate\Http\JsonResponse
    {

        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // pre-defined questions and answers based on industry
        if ($venue->venueType->definition === 'accommodation') {
            // Predefined Questions and Answers
            $predefinedQuestions = [
                'Hi' => 'Hello! How can I assist you today?',
                'Hello' => 'Hello! How can I assist you today?',
                'How are you today' => 'I\'m just a chatbot, but I\'m here to help. How can I assist you today?',
                'Can you help me with something' => 'Of course, I\'m here to assist you. What do you need help with?',
                'What features does VenueBoost offer for our venue' => 'VenueBoost offers a wide range of features tailored to accommodation providers. Here are some of the key features you can benefit from:',
                'How does VenueBoost help with bookings?' => 'VenueBoost offers efficient bookings management, allowing you to prevent overbooking and optimize room occupancy.',
                'Can VenueBoost help with staff management?' => 'Yes, VenueBoost simplifies staff scheduling, task assignment, and performance monitoring.',
                'What marketing tools are available in VenueBoost?' => 'VenueBoost provides marketing tools such as email campaigns, referral programs, and promotional discounts to attract and retain guests.',
                'Is there a loyalty program in VenueBoost?' => 'Absolutely, you can create loyalty programs in VenueBoost to reward returning guests and encourage repeat bookings.',
                'How can I access analytics and reports in VenueBoost?' => 'VenueBoost offers comprehensive analytics and reporting for data-driven decisions and performance tracking.',
                'Can guests make online payments through VenueBoost?' => 'Yes, VenueBoost allows guests to make secure online payments, simplifying the reservation process.',
                'Does VenueBoost help with guest management?' => 'VenueBoost includes features to maintain guest profiles and data, providing personalized service.',
                'Is marketing automation available in VenueBoost?' => 'Yes, you can automate marketing campaigns in VenueBoost for time and effort savings.',
                'What are guest cards in VenueBoost?' => 'Guest cards in VenueBoost streamline the check-in process for guests.',
                'Does VenueBoost support iCal integration?' => 'Yes, VenueBoost can synchronize bookings with third-party platforms to avoid double bookings.',
                'Can I collaborate with affiliates using VenueBoost?' => 'Yes, VenueBoost allows you to collaborate with partners to expand your reach and bookings.',
                'How can I collect guest feedback in VenueBoost?' => 'You can collect guest feedback and ratings in VenueBoost to improve your services.',
                'What is advanced behavior data analytics in VenueBoost?' => 'VenueBoost provides advanced behavior data analytics to understand guest behavior, preferences, and trends for informed decisions.',
                'What is iCal Integration in VenueBoost?' => 'iCal Integration in VenueBoost streamlines availability management for accommodations. It allows you to sync booking calendars and prevent overlapping reservations.',
                'How does VenueBoost help with managing availability?' => 'VenueBoost offers seamless iCal Integration, ensuring you can avoid double bookings and maintain a smooth booking process.',
                'Can I sync my availability with third-party booking platforms?' => "Yes, VenueBoost's iCal Integration allows you to sync your availability with iCal-supported booking platforms, ensuring consistent updates across all platforms.",
                'What are the benefits of using iCal Integration in VenueBoost?' => "With VenueBoost\'s iCal Integration, your accommodation bookings will run smoothly, preventing overbookings and ensuring accurate calendar updates.",
                'How can VenueBoost help with automatic updates?' => "VenueBoost's iCal Integration automatically updates changes across all platforms, saving you time and effort.",
                'What is the Accommodation Guest Loyalty Program in VenueBoost?' => 'VenueBoost\'s Accommodation Guest Loyalty Program streamlines reservations and guest interactions to enhance guest experiences.',
                'How does the loyalty program work?' => 'The program rewards guests with points for each stay or booking and grants perks like room upgrades as guests collect points.',
                'What benefits does the program offer to guests?' => 'Guests can enjoy rewards and privileges, making their stays more enjoyable and satisfying.',
                'How can VenueBoost help with optimizing accommodation businesses?' => "VenueBoost's Accommodation Guest Loyalty Program helps you optimize your accommodation business by improving guest experiences and loyalty.",
                'Are there any specific perks for loyal guests?' => "Yes, loyal guests can earn points and receive perks like room upgrades, creating an incentive for repeat bookings.",
                'What is Advanced Guest Behavior Analytics in VenueBoost?' => 'VenueBoost offers Advanced Guest Behavior Analytics to gain deep insights into guest behavior, allowing you to understand your guests better and tailor your services accordingly.',
                'How can I analyze guest preferences, history, and engagement patterns with VenueBoost?' => 'With VenueBoost, you can analyze guest preferences, history, and engagement patterns, helping you make data-driven decisions to improve guest experiences.',
                'What benefits does data-driven decision-making provide?' => 'Data-driven decisions enable you to improve guest experiences and effectively target marketing based on behavior insights.',
                'How does VenueBoost empower businesses with data-driven strategies?' => 'VenueBoost empowers businesses to enhance guest engagement through data-driven strategies, ultimately leading to improved services and guest satisfaction.',
                'Can I use behavior insights for targeted marketing?' => 'Absolutely, VenueBoost allows you to target marketing efforts based on behavior insights, ensuring more effective marketing campaigns.',
                'What is Marketing Automation in VenueBoost?' => 'Marketing Automation in VenueBoost allows you to take your marketing efforts to the next level. It enables you to seamlessly automate and personalize campaigns to drive engagement and sales.',
                'How can I create targeted email campaigns with VenueBoost?' => 'With VenueBoost, you can create targeted email campaigns based on guest behavior, ensuring your messages are relevant and effective.',
                'Can I send personalized offers and recommendations to guests?' => 'Absolutely, VenueBoost lets you send personalized offers and recommendations, enhancing the guest experience and increasing sales.',
                'What tools are available to measure the effectiveness of marketing campaigns?' => 'VenueBoost provides tools to measure the effectiveness of your marketing campaigns, allowing you to optimize your strategies for better results.',
                'How does VenueBoost streamline marketing across industries?' => 'VenueBoost streamlines marketing across various industries with powerful automation, ensuring consistent and effective marketing efforts.',
                'What is Bookings Management in VenueBoost?' => 'Bookings Management in VenueBoost is designed to optimize accommodation reservations. Here are key aspects:',
                'How can I create bookings using VenueBoost?' => 'VenueBoost allows you to easily create bookings, specifying dates, rooms, and guest details for a seamless reservation process.',
                'Is there a dashboard to track and modify reservations?' => 'Yes, VenueBoost provides a reservation dashboard to help you track, modify, and organize bookings for efficiency.',
                'Does VenueBoost offer RSVP features?' => 'Absolutely, VenueBoost includes RSVP features to facilitate smooth communication with guests.',
                'Can I gain insights into occupancy and traffic patterns?' => 'VenueBoost offers insights into occupancy and traffic patterns, helping you make data-driven decisions.',
                'How does VenueBoost streamline reservations management?' => 'VenueBoost provides a complete and tailored reservations management solution, eliminating incohesive systems for a streamlined process.',
                'What is Inventory Management in VenueBoost?' => 'Inventory Management in VenueBoost offers insights to optimize room and amenity availability. Here are key details:',
                'How can I track inventory with VenueBoost?' => 'With VenueBoost, you can easily track your inventory, ensuring rooms and amenities are effectively managed.',
                'Can I update inventory information in VenueBoost?' => 'Yes, VenueBoost allows you to update inventory information for accuracy and real-time availability.',
                'Is there monitoring functionality in VenueBoost?' => 'VenueBoost provides monitoring features to keep a close eye on your inventory for efficient operations.',
                'Does VenueBoost support guest profile management?' => 'Absolutely, VenueBoost includes guest profile management for personalized services.',
                'How can I analyze inventory data and booking patterns?' => 'VenueBoost enables you to analyze inventory data and booking patterns for strategic decision-making.',
                'What is Staff Management in VenueBoost?' => 'Staff Management in VenueBoost simplifies team management for optimal operations. Here are key aspects:',
                'How does VenueBoost automate staff scheduling?' => 'VenueBoost offers automated scheduling based on occupancy, reducing scheduling stress.',
                'Can I track time-off requests and vacation days with VenueBoost?' => 'Yes, VenueBoost allows you to track time-off requests and vacation days, facilitating staff coordination.',
                'How does VenueBoost streamline payroll processing?' => 'VenueBoost streamlines payroll processing with easy wage calculations, saving time and effort.',
                'Does VenueBoost offer performance monitoring?' => 'VenueBoost provides performance monitoring to identify coaching opportunities and ensure exceptional service.',
                'How does VenueBoost help in staff coordination?' => 'VenueBoost ensures exceptional service through staff coordination, enhancing guest experiences.',
                'What is Marketing in VenueBoost?' => 'Marketing in VenueBoost aims to expand reach and brand awareness. Here are key details:',
                'How can I use VenueBoost for email marketing?' => 'VenueBoost supports email marketing to engage with your audience effectively.',
                'Can I create promotions and campaigns with VenueBoost?' => 'Yes, VenueBoost allows you to create promotions and campaigns to attract guests.',
                'Does VenueBoost offer referral marketing features?' => 'VenueBoost includes referral marketing features to drive guest referrals.',
                'What are the benefits of using VenueBoost for marketing?' => 'VenueBoost brings together the marketing tools your accommodation needs for a comprehensive marketing strategy.',
                'What is Loyalty in VenueBoost?' => 'Loyalty in VenueBoost focuses on building guest loyalty and retention. Here are key aspects:',
                'How can I build special tiers and benefits for loyal members with VenueBoost?' => 'VenueBoost allows you to build special tiers and benefits for loyal members, enhancing guest loyalty.',
                'Can I view member profiles and track purchase history with VenueBoost?' => 'Yes, VenueBoost provides the capability to view member profiles and track purchase history for personalized services.',
                'Does VenueBoost support sending tailored rewards and offers?' => 'VenueBoost enables you to send tailored rewards and offers to boost guest loyalty.',
                'How does VenueBoost help in analyzing program activity and engagement?' => 'VenueBoost allows you to analyze program activity and engagement to strengthen relationships with return guests.',
                'What is Reporting in VenueBoost?' => 'Reporting in VenueBoost provides actionable accommodation insights. Here are key details:',
                'How can I monitor KPIs like occupancy rates and RevPAR with VenueBoost?' => 'VenueBoost allows you to monitor KPIs like occupancy rates and RevPAR for better decision-making.',
                'Does VenueBoost offer guest segmentation for spending insights?' => 'Yes, VenueBoost provides guest segmentation for valuable spending insights.',
                'Can I optimize labor costs with staffing insights in VenueBoost?' => 'VenueBoost offers staffing insights to help you optimize labor costs for efficient operations.',
                'How does VenueBoost use source of booking analysis to inform marketing?' => 'VenueBoost uses source of booking analysis to inform your marketing strategies for targeted campaigns.',
                'What are the benefits of using VenueBoost for financial reporting?' => 'VenueBoost financial reporting provides clear profit visibility, helping you run a smarter accommodation business.',
                "What is the primary purpose of VenueBoost's Guest Surveys and Ratings feature?" => "VenueBoost's Guest Surveys and Ratings feature allows venues to gather valuable feedback and enhance guest experiences. It offers customized guest surveys and the ability to collect ratings and reviews to gauge satisfaction.",
                "How can businesses make informed improvements using VenueBoost's Guest Surveys and Ratings?" => "VenueBoost's Guest Surveys and Ratings feature provides insights into preferences and opinions. Businesses can use data-driven decisions to make informed improvements and optimize their offerings based on customer feedback.",
                "Why should businesses use VenueBoost's Guest Surveys and Ratings feature?" => "Businesses should use VenueBoost's Guest Surveys and Ratings feature to listen to their customers, gather valuable feedback, and optimize their offerings. It helps in enhancing guest experiences and making data-driven improvements.",
                "What are the key benefits of utilizing VenueBoost's Guest Surveys and Ratings feature?" => "Key benefits of using VenueBoost's Guest Surveys and Ratings feature include gathering valuable feedback, creating customized surveys, collecting ratings and reviews, gaining insights into preferences, and making informed improvements based on data-driven decisions.",
                "How can VenueBoost's Guest Surveys and Ratings feature help businesses improve guest satisfaction?" => "VenueBoost's Guest Surveys and Ratings feature helps businesses improve guest satisfaction by gathering feedback, gauging satisfaction through ratings and reviews, and making informed improvements based on customer preferences and opinions.",
                "How can VenueBoost help businesses expand their reach and revenue through affiliate partnerships?" => "VenueBoost's Affiliate Partnerships feature allows businesses to establish affiliate partnerships seamlessly, track referrals and commissions in real-time, and access valuable marketing resources. This extends the brand's reach through strategic collaborations.",
                "What is the primary purpose of VenueBoost's Affiliate Partnerships feature?" => "The primary purpose of VenueBoost's Affiliate Partnerships feature is to help businesses grow their reach and revenue by partnering with like-minded organizations, unlocking new opportunities.",
                "Why should businesses consider joining forces with affiliates using VenueBoost?" => "Joining forces with affiliates using VenueBoost can help businesses expand their reach and revenue by establishing partnerships and accessing valuable marketing resources.",
                "What are the key benefits of VenueBoost's Affiliate Partnerships feature?" => "Key benefits of VenueBoost's Affiliate Partnerships feature include expanding business reach, tracking referrals and commissions, and accessing valuable marketing resources for strategic collaborations.",
                "How can businesses maximize their revenue potential with VenueBoost's Affiliate Partnerships?" => "Businesses can maximize their revenue potential by partnering with like-minded organizations, tracking referrals and commissions, and utilizing valuable marketing resources through VenueBoost's Affiliate Partnerships.",
                "What services does VenueBoost offer in the guest management suite?" => "VenueBoost's guest management suite includes personalized stays, optimized experiences, guest management preferences, and guest management loyalty programs. It allows businesses to deliver exceptional stays by understanding each guest's preferences and needs.",
                "How can VenueBoost help businesses build guest loyalty?" => "VenueBoost helps build guest loyalty by offering personalized stays, optimized experiences, and customized loyalty programs with special tiers. This encourages guests to return, engage, and retain them.",
                "What are the benefits of using VenueBoost's guest management features?" => "VenueBoost's guest management features enable businesses to create customized guest surveys, collect ratings and reviews to gauge satisfaction, gain insights into preferences and opinions, and make informed improvements using data-driven decisions.",
                "What is the main goal of VenueBoost's guest management suite?" => "The main goal of VenueBoost's guest management suite is to enhance guest experiences, build guest loyalty, and optimize offerings by understanding guest preferences and feedback.",
                "How can VenueBoost's guest management features help businesses attract and retain guests?" => "VenueBoost's guest management features help businesses attract and retain guests by providing personalized stays, gathering feedback through surveys and ratings, and making data-driven improvements to enhance the overall guest experience.",
                "What is the purpose of VenueBoost's Payment Links feature?" => "VenueBoost's Payment Links feature enables streamlined online booking payments. It allows businesses in the accommodation industry to securely accept deposits, prepayments, and balances while providing insights into bookings and revenue from links.",
                "How does VenueBoost ensure the security of payment transactions?" => "VenueBoost ensures the security of payment transactions by securely accepting deposits, prepayments, and balances. It provides a seamless and convenient payment process for guests.",
                "Can businesses track their financial transactions with VenueBoost?" => "Yes, VenueBoost provides an organized link list to track transactions, allowing businesses to have visibility into their financial transactions.",
                "What are the key benefits of using Payment Links?" => "Key benefits of using Payment Links include secure transaction handling, organized tracking of financial transactions, insights into bookings and revenue, and a seamless payment process for guests.",
                "How can businesses improve their financial operations with Payment Links?" => "Businesses can improve their financial operations by securely accepting deposits and prepayments, tracking transactions, and gaining insights into their revenue using VenueBoost's Payment Links."
            ];

            $reportKeywords = [
                // Single Words
                'features',
                'bookings',
                'staff',
                'marketing',
                'loyalty',
                'analytics',
                'payments',
                'guest',
                'iCal',
                'affiliates',
                'feedback',
                'behavior',
                'automation',
                'cards',
                'integration',
                'inventory',
                'scheduling',
                'reports',
                // Two-Word Phrases
                "bookings management",
                "staff management",
                "marketing tools",
                "loyalty program",
                "guest feedback",
                "behavior analytics",
                "marketing automation",
                "iCal integration",
                "payment links",
                "guest cards",
                "affiliate partnerships",
                "guest surveys",
            ];
        }

        if ($venue->venueType->definition === 'retail') {
            // Predefined Questions and Answers
            $predefinedQuestions = [
                'Hi' => 'Hello! How can I assist you today?',
                'Hello' => 'Hello! How can I assist you today?',
                'How are you today' => 'I\'m just a chatbot, but I\'m here to help. How can I assist you today?',
                'Can you help me with something' => 'Of course, I\'m here to assist you. What do you need help with?',
                'What is the main purpose of VenueBoost\'s Consistent Inventory feature?' => 'The main purpose is to ensure consistent inventory management for retail businesses across multiple platforms like WooCommerce and Shopify.',
                'How does VenueBoost ensure inventory consistency across multiple platforms?' => 'VenueBoost synchronizes inventory levels in real-time, preventing discrepancies.',
                'What benefits can businesses gain from using VenueBoost\'s Consistent Inventory feature?' => 'Businesses can enhance customer satisfaction through accurate availability and avoid over/understocking.',
                'Why is real-time data synchronization important in retail?' => 'Real-time synchronization helps retailers provide a smooth shopping experience and prevents inventory issues.',
                'How does VenueBoost help in enhancing customer satisfaction through accurate availability?' => 'VenueBoost simplifies inventory management by synchronizing levels between WooCommerce and Shopify, ensuring a consistent shopping experience.',
                'What does VenueBoost\'s Retail Customer Loyalty Program offer to retailers?' => 'It offers tools to enhance shopping experiences, build loyalty, and personalize promotions.',
                'How can personalized discounts and promotions enhance the shopping experience?' => 'Personalized discounts make customers feel valued and increase loyalty.',
                'What role does tracking customer preferences and purchase history play in building loyalty?' => 'It allows businesses to tailor offers to individual preferences, strengthening customer relationships.',
                'How can targeted marketing campaigns drive repeat business?' => 'Targeted campaigns based on customer behavior encourage repeat purchases.',
                'How does strengthening customer relationships contribute to increased sales?' => 'Strong customer relationships lead to customer retention and increased sales.',
                'What is the primary goal of VenueBoost\'s Advanced Customer Behavior Analytics?' => 'The goal is to gain deep insights into customer behavior and tailor services accordingly.',
                'How does VenueBoost enable businesses to gain deep insights into customer behavior?' => 'It provides analytics to analyze preferences, history, and engagement patterns.',
                'Why is data-driven decision-making important for retailers?' => 'Data-driven decisions improve customer experiences and marketing effectiveness.',
                'How can VenueBoost\'s analytics help in targeting marketing strategies effectively?' => 'Analytics provide insights for more targeted marketing campaigns.',
                'What benefits do businesses get from understanding customer behavior?' => 'Understanding customer behavior leads to better service and more satisfied customers.',
                'How does VenueBoost\'s Marketing Automation feature boost engagement and sales?' => 'It enables businesses to automate and personalize campaigns for improved customer engagement and increased sales.',
                'What are the key advantages of automating and personalizing campaigns?' => 'Automation allows for targeted email campaigns, personalized offers, and effective strategy optimization.',
                'Why is creating targeted email campaigns based on customer behavior important?' => 'It ensures that emails are relevant to each customer, increasing engagement.',
                'How can personalized offers and recommendations impact customer engagement?' => 'Personalization makes customers feel valued, leading to higher engagement.',
                'What benefits do businesses get from measuring the effectiveness of marketing strategies?' => 'Measuring effectiveness allows for strategy optimization, resulting in better outcomes.',
                'What is Order Management?' => 'Order Management in this context refers to the key feature highlighted on this page, which is a part of VenueBoost. It focuses on efficiently handling and processing orders for retail and ecommerce businesses.',
                'How does VenueBoost streamline order processing?' => 'VenueBoost streamlines order processing by providing real-time order notifications, an intuitive interface for viewing and updating orders, and the ability to track order history and customer details.',
                'What is the main goal of ensuring fast fulfillment and shipping?' => 'The main goal of ensuring fast fulfillment and shipping is to make sure that orders are processed quickly and delivered promptly to enhance customer satisfaction.',
                'Why is it important to focus on customers, not paperwork?' => 'Focusing on customers, not paperwork, is important because it improves the overall customer experience and allows businesses to provide better service by redirecting their attention to customers\' needs.',
                'How does VenueBoost unify order management workflows?' => 'VenueBoost unifies order management workflows by eliminating fragmented processes and providing a comprehensive platform for efficient order processing. It ensures that all aspects of order management are interconnected and work seamlessly together.',
                'What is Retail Inventory Management?' => 'Retail Inventory Management in this context refers to the key feature highlighted on this page, which is a part of VenueBoost. It focuses on managing and optimizing inventory for businesses in the retail industry.',
                'How does VenueBoost provide actionable inventory insights?' => 'VenueBoost provides actionable inventory insights through features like inventory tracking, updates, and detailed insights into inventory history and details.',
                'Why is inventory tracking important for retail businesses?' => 'Inventory tracking is crucial for retail businesses to keep a real-time record of stock levels and ensure products are available when customers need them.',
                'How does VenueBoost help businesses update their inventory efficiently?' => 'VenueBoost streamlines the process of updating inventory by providing tools and features that make it easier to manage and make changes as needed.',
                'What benefits can businesses gain from having detailed inventory insights?' => 'Detailed inventory insights help businesses make informed decisions, reduce overstocking or understocking issues, and ultimately optimize their inventory management.',
                'Why is it important for businesses to have access to inventory history?' => 'Access to inventory history allows businesses to track trends, analyze past performance, and plan for the future more effectively.',
                'What is the primary goal of VenueBoost\'s Retail Inventory Management feature?' => 'The primary goal of VenueBoost\'s Retail Inventory Management feature is to provide the data and tools necessary to optimize inventory for businesses in the retail industry.',
                'How does the Retail Marketing feature help in expanding reach and brand awareness?'=>'The Retail Marketing feature consolidates marketing activities to expand reach and increase brand awareness for retailers.',
                'What specific capabilities are included in the Retail Marketing feature?' => 'The Retail Marketing feature includes email marketing, coupon management, campaign handling, and referral management.',
                'Can you explain how Retail Marketing simplifies marketing for retailers?' => 'Retail Marketing simplifies marketing through email, coupon management, campaign handling, and referral management.',
                'What is the primary goal of the Retail Marketing feature?'=> 'The primary goal of the Retail Marketing feature is to consolidate activities and provide retailers with the tools they need for growth.',
                'How does Retail Marketing contribute to brand awareness?'=> 'Retail Marketing contributes to brand awareness by providing effective marketing and promotional capabilities for retailers.',
                'How does the staff management feature simplify retail operations?' =>  'The staff management feature streamlines retail operations by providing automated scheduling, time off request management, payroll processing, sales performance tracking, and coaching opportunity identification.',
                'What are the key benefits of using the staff management feature in the retail industry?' =>  'The staff management feature offers benefits such as automated scheduling based on store needs, efficient time off request management, accurate payroll processing with wage calculations, tracking sales performance to inform commissions, and identifying coaching opportunities using metrics.',
                'How does the staff management feature handle payroll processing?'=>  'The staff management feature simplifies payroll processing by automatically calculating wages for retail staff.',
                'What is the primary objective of the staff management feature for retailers?'=>  'The primary objective of the staff management feature is to eliminate hassles and make retail staff management more efficient.',
                'How does the staff management feature contribute to improving sales performance in retail?'=>  'The staff management feature contributes to improving sales performance by providing tools to track and analyze sales metrics for informed decision-making.',
                'How does the loyalty feature in the retail industry work?'=>  'The loyalty feature allows retailers to segment members into tailored loyalty tiers, send personalized rewards and offers, track purchase history, and analyze membership engagement.',
                'What benefits does the loyalty feature provide for retailers?'=>  'The loyalty feature benefits retailers by strengthening relationships with regular buyers, boosting loyalty, improving retention, and encouraging repeat visits.',
                'How does the loyalty feature help retailers build customer loyalty?'=> 'The loyalty feature helps retailers build customer loyalty by segmenting members, offering personalized rewards, and tracking their shopping habits.',
                'What is the primary goal of the loyalty feature in the retail industry?'=> 'The primary goal of the loyalty feature is to enhance customer loyalty, retention, and repeat business for retailers.',
                'What tools are provided by the loyalty feature to improve customer retention?'=>  'The loyalty feature provides tools for segmenting members, sending personalized rewards, tracking purchase history, and analyzing membership engagement to improve customer retention.',
                'What insights can retailers gain from the dashboard feature in VenueBoost?'=>  'The dashboard feature provides actionable insights into sales, coupons, categories, products sold, and order management for retailers.',
                'How does the dashboard feature assist retailers in optimizing their business?'=>  'The dashboard feature assists retailers in optimizing their business by offering comprehensive data and insights on sales, coupons, categories, products sold, and order management.',
                'What is the primary purpose of the dashboard feature for retailers?'=>  'The primary purpose of the dashboard feature is to help retailers make informed decisions and optimize their retail operations.',
                'How does the dashboard feature contribute to sales improvement in the retail industry?'=> 'The dashboard feature contributes to sales improvement by providing valuable insights into sales performance.',
                'What specific data can retailers access through the dashboard feature?'=> 'Retailers can access data related to sales, coupons, categories, products sold, and order management through the dashboard feature.'
            ];

            $reportKeywords = [
                'report', 'feedback', 'review', 'evaluation',
                'Inventory Management', 'Sales Performance',
                'Customer Satisfaction', 'Marketing Effectiveness',
                'Customer Reviews', 'Retail Marketing', 'Coupon Management',
                'Campaign Handling', 'Referral Management', 'Staff Management',
                'Payroll Processing', 'Coaching Opportunity',
                'Retail Inventory Management', 'Wage Calculations',
                'Sales Performance Tracking', 'Loyalty Feature',
                'Loyalty Tiers', 'Personalized Rewards', 'Purchase History',
                'Membership Engagement', 'Dashboard Feature',
                'Sales Improvement', 'Sales Metrics', 'Order Management',
                'Efficient Time Off Request Management',
                'management', 'performance', 'satisfaction', 'effectiveness',
                'campaign', 'referral', 'payroll', 'coaching', 'wage',
                'personalized', 'purchase', 'membership', 'dashboard',
            ];
        }

        if ($venue->venueType->definition === 'sport_entertainment') {
            // Predefined Questions and Answers
            $predefinedQuestions = [
                'Hi' => 'Hello! How can I assist you today?',
                'Hello' => 'Hello! How can I assist you today?',
                'How are you today' => 'I\'m just a chatbot, but I\'m here to help. How can I assist you today?',
                'Can you help me with something' => 'Of course, I\'m here to assist you. What do you need help with?',
                "What does VenueBoost\'s Entertainment Membership Program offer to members of sports venues and entertainment hubs?"=> "VenueBoost\'s Entertainment Membership Program offers exclusive benefits and experiences to members of sports venues and entertainment hubs to encourage attendance, provide access or discounts, boost engagement, and monetize fan loyalty with special offerings.",
                "How does the Entertainment Membership Program enhance the entertainment experience?"=>"The Entertainment Membership Program enhances the entertainment experience by providing loyal fans with exclusive benefits, encouraging attendance, and offering special discounts and experiences.",
                "What are the key features of VenueBoost\'s Entertainment Membership Program?"=>"Key features include encouraging fans to attend events and earn points, providing exclusive access or discounts to loyal members, boosting attendance and engagement, and monetizing fan loyalty with special offerings.",
                "How can VenueBoost help businesses in the entertainment industry with their loyalty programs?"=> "VenueBoost assists businesses in the entertainment industry by providing a comprehensive Entertainment Membership Program that encourages fan engagement, loyalty, and attendance.",
                "What is the primary goal of VenueBoost\'s Entertainment Membership Program?"=> "The primary goal of VenueBoost\'s Entertainment Membership Program is to enhance fan engagement, loyalty, and attendance at sports venues and entertainment hubs by offering exclusive benefits and experiences to members.",
                "What are the benefits of VenueBoost\'s Advanced Customer Behavior Analytics for businesses?"=>"VenueBoost\'s Advanced Customer Behavior Analytics provides businesses with deep insights into customer behavior, the ability to analyze preferences, history, and engagement patterns, make data-driven decisions to improve experiences, and effectively target marketing based on behavior insights.",
                "How does VenueBoost help businesses gain a better understanding of their customers through Advanced Customer Behavior Analytics?"=> "VenueBoost empowers businesses to gain a better understanding of their customers by providing insights into preferences, history, and engagement patterns through Advanced Customer Behavior Analytics.",
                "What tools are available through VenueBoost\'s Advanced Customer Behavior Analytics feature?"=>"VenueBoost\'s Advanced Customer Behavior Analytics feature offers tools for analyzing customer behavior, making data-driven decisions to enhance experiences, and targeting marketing campaigns based on behavior insights.",
                "How does VenueBoost enable businesses to tailor their services based on customer behavior insights?"=> "VenueBoost enables businesses to tailor their services based on insights gained through Advanced Customer Behavior Analytics, helping them better meet the needs and preferences of their customers.",
                "What is the primary goal of VenueBoost\'s Advanced Customer Behavior Analytics feature?"=>"The primary goal of VenueBoost\'s Advanced Customer Behavior Analytics feature is to provide businesses with the tools and insights needed to make data-driven decisions and enhance customer experiences based on customer behavior analysis.",
                "How can VenueBoost's Marketing Automation feature help businesses boost engagement and sales?"=>"VenueBoost\'s Marketing Automation feature allows businesses to create targeted email campaigns based on customer behavior, send personalized offers and recommendations, and measure the effectiveness of marketing strategies to optimize engagement and sales.",
                "What are the benefits of using VenueBoost's Marketing Automation for businesses?'=> 'Using VenueBoost\'s Marketing Automation, businesses can streamline marketing efforts, create personalized campaigns, and measure the effectiveness of their strategies to drive engagement and increase sales.",
                "What tools are available through VenueBoost's Marketing Automation feature?'=>'VenueBoost's Marketing Automation feature provides tools for creating targeted email campaigns, sending personalized offers, and measuring the effectiveness of marketing strategies.",
                "How does VenueBoost's Marketing Automation help businesses personalize their marketing efforts?'=>'VenueBoost's Marketing Automation enables businesses to send personalized offers and recommendations to customers based on their behavior and preferences, allowing for more effective and personalized marketing campaigns.",
                "What is the primary goal of VenueBoost's Marketing Automation feature?'=>'The primary goal of VenueBoost's Marketing Automation feature is to streamline and optimize marketing efforts by automating campaigns, personalizing offers, and measuring their effectiveness for improved engagement and sales.",
                "What is Bookings Management about?" => "Bookings Management in VenueBoost is all about optimized entertainment bookings for various businesses. It provides amusement parks, cinemas, golf courses, theaters, museums, and more with robust tools to seamlessly manage reservations and ticketing.",
                "What features does VenueBoost provide for creating and managing bookings?" => "VenueBoost offers the following features for creating and managing bookings:
                - Easily create bookings specifying dates, tickets, and guests.
                - Dashboard to view, modify, and organize reservations.
                - RSVP features for smooth communication.
                - Gain insights into attendance patterns.",
                "What is the primary goal of VenueBoost's Bookings Management?" => "The primary goal of VenueBoost's Bookings Management is to offer businesses in the entertainment industry complete and customized reservations management, optimizing the booking process for both businesses and their customers.",
                "What is Inventory Management about?" => "Inventory Management in VenueBoost is all about streamlined equipment and inventory management for the sport and entertainment industry. It simplifies the process of tracking, updating, and viewing inventory, as well as analyzing trends and managing the lifecycle of equipment and assets.",
                "What features does VenueBoost provide for inventory management?" => "VenueBoost offers the following features for inventory management:
                - Tracking equipment and inventory.
                - Updating inventory information.
                - Analyzing trends related to inventory.
                - Viewing and managing inventory assets.
                - Managing the lifecycle of equipment and assets.",
                "How does VenueBoost help venues keep their operations running smoothly with inventory management?" => "VenueBoost helps venues keep their operations running smoothly by providing insights and tools to effectively manage equipment and inventory. It simplifies inventory management, ensuring venues have the insights they need to run efficiently.",
                "What is the primary goal of VenueBoost's Inventory Management?" => "The primary goal of VenueBoost's Inventory Management is to assist the sport and entertainment industry in effectively managing their equipment and inventory, ensuring smooth operations and optimized asset utilization.",
                "What is VenueBoost's Staff Management all about?" => "VenueBoost's Staff Management is all about simplified scheduling and payroll for the sport and entertainment industry. It provides tools to automate scheduling based on events, track time-off requests, integrate payroll processing, monitor performance to identify coaching opportunities, and coordinate staff for seamless operations.",
                "What features does VenueBoost provide for staff management?" => "VenueBoost offers the following features for staff management:
                - Automated scheduling based on events.
                - Time-off request tracking.
                - Integrated payroll processing.
                - Performance monitoring to identify coaching opportunities.
                - Coordination of staff for seamless operations.",
                "How does VenueBoost help venues optimize their operations with staff management?" => "VenueBoost helps venues optimize their operations by streamlining team management. It provides the tools needed for scheduling, payroll, performance monitoring, and staff coordination, ensuring optimal operations.",
                "What is the primary goal of VenueBoost's Staff Management?" => "The primary goal of VenueBoost's Staff Management is to assist the sport and entertainment industry in effectively managing their staff, scheduling, and payroll processes, ensuring seamless operations and performance monitoring.",
                "What is VenueBoost's Loyalty feature all about?" => "VenueBoost's Loyalty feature is all about building guest loyalty and retention in the sport and entertainment industry. It allows you to create special tiers and benefits for loyal members, view purchase history and activity trends, send tailored rewards and offers, and analyze engagement across tiers.",
                "How does VenueBoost help enhance guest loyalty and retention?" => "VenueBoost helps enhance guest loyalty and retention by providing tools to create customized loyalty programs, gather insights from guest surveys, and offer tailored rewards. This strengthens relationships with return guests and encourages repeat visits.",
                "What are the key benefits of using VenueBoost's Loyalty feature?" => "The key benefits of using VenueBoost's Loyalty feature include:
                - Building loyal customer relationships.
                - Encouraging repeat visits.
                - Increasing customer retention.
                - Providing customized rewards and offers.
                - Analyzing guest engagement and preferences.",
                "Why is guest loyalty important for the sport and entertainment industry?" => "Guest loyalty is crucial for the sport and entertainment industry because it helps in retaining customers, increasing revenue, and creating a strong customer base. Loyal guests are more likely to return, spend more, and recommend the venue to others.",
                "What does VenueBoost's Reporting feature offer for the sport and entertainment industry?" => "VenueBoost's Reporting feature offers actionable entertainment insights for the sport and entertainment industry. It includes features like tracking sales, hours, metrics, insights, and courses, providing data-driven decisions to optimize venue operations.",
                "How does VenueBoost's Reporting feature help venues?" => "VenueBoost's Reporting feature helps venues by providing insights into sales volumes, revenue, and various performance metrics. This data helps venues make informed improvements and optimize their operations for better results.",
                "Why is data-driven decision-making important for the sport and entertainment industry?" => "Data-driven decision-making is important for the sport and entertainment industry because it allows venues to make informed choices based on real-time data and analytics. This can lead to better customer experiences, increased revenue, and more efficient operations.",
                "What types of metrics can VenueBoost's Reporting feature track?" => "VenueBoost's Reporting feature can track various metrics, including sales data, operational hours, guest satisfaction metrics, and course performance. These metrics provide valuable insights for optimizing venue operations.",
                "What is VenueBoost's Payment Links feature all about?" => "VenueBoost's Payment Links feature is all about streamlined online ticketing payments. It allows venues to securely accept ticket purchases and deposits, organize link lists to track transactions, gain insights into sales volumes and revenue, and offer convenient integrated booking payments.",
                "How does VenueBoost's Payment Links feature benefit entertainment businesses?" => "VenueBoost's Payment Links feature benefits entertainment businesses by simplifying online ticketing payments, making it easy to accept payments, track transactions, and gain insights into revenue. This streamlines the payment process for both venues and customers.",
                "Why are streamlined online ticketing payments important for entertainment venues?" => "Streamlined online ticketing payments are important for entertainment venues because they enhance the customer experience and make it easier for guests to purchase tickets and complete bookings. This convenience can lead to increased ticket sales and customer satisfaction.",
                "What advantages do integrated booking payments offer to venues?" => "Integrated booking payments offer venues the advantage of convenience and efficiency. They simplify the payment process for guests, leading to smoother transactions and a more positive booking experience.",
                "What does VenueBoost's Customer Management feature offer for entertainment businesses?" => "VenueBoost's Customer Management feature offers tools for personalized interactions, loyal relationships, and effective customer engagement. It helps businesses manage customer profiles, preferences, history, and loyalty programs with special tiers.",
                "How does VenueBoost's Customer Management feature improve the guest experience?" => "VenueBoost's Customer Management feature improves the guest experience by allowing businesses to provide personalized interactions, manage guest profiles, preferences, and loyalty programs. This leads to more tailored and engaging experiences for visitors.",
                "Why is understanding guest preferences and profiles important for entertainment venues?" => "Understanding guest preferences and profiles is important for entertainment venues because it enables them to deliver personalized experiences, engage visitors effectively, and build strong, loyal relationships. It enhances the overall guest experience and encourages repeat visits.",
                "What benefits do custom loyalty programs with special tiers offer to entertainment businesses?" => "Custom loyalty programs with special tiers offer entertainment businesses the benefit of rewarding and retaining loyal customers. These programs can increase customer retention and encourage guests to spend more, ultimately boosting revenue.",
                "What is VenueBoost's Surveys and Ratings feature all about?" => "VenueBoost's Surveys and Ratings feature is all about gathering valuable feedback from guests to enhance their experiences. It allows venues to create customized guest surveys, collect ratings and reviews to gauge satisfaction, gain insights into preferences and opinions, and make informed improvements using data-driven decisions.",
                "How do guest surveys and ratings benefit entertainment venues?" => "Guest surveys and ratings benefit entertainment venues by providing valuable feedback and insights from guests. This feedback helps venues understand guest preferences, satisfaction levels, and areas for improvement, leading to enhanced guest experiences and better offerings.",
                "Why is gathering feedback important for entertainment venues?" => "Gathering feedback is important for entertainment venues as it helps them understand guest satisfaction, preferences, and areas for improvement. This data-driven approach allows venues to continually enhance their offerings and meet guest expectations.",
                "How can data-driven decisions based on surveys and ratings improve entertainment venues?" => "Data-driven decisions based on surveys and ratings can improve entertainment venues by helping them make informed changes and enhancements. This can lead to better guest satisfaction, increased repeat visits, and higher revenue.",
                "What does VenueBoost's Affiliates feature offer for entertainment businesses?" => "VenueBoost's Affiliates feature offers the ability to establish affiliate partnerships seamlessly, track referrals and commissions in real-time, and access valuable marketing resources. It allows businesses to extend their brand's reach through strategic collaborations.",
                "How do affiliate partnerships benefit entertainment businesses?" => "Affiliate partnerships benefit entertainment businesses by expanding their reach and revenue through collaborations with like-minded organizations. These partnerships can lead to increased brand exposure, more referrals, and higher revenue.",
                "Why should entertainment businesses consider affiliate partnerships?" => "Entertainment businesses should consider affiliate partnerships because they offer the opportunity to grow and diversify revenue streams, gain access to new customer segments, and enhance marketing efforts through strategic collaborations.",
                "What advantages does real-time commission tracking offer to businesses?" => "Real-time commission tracking offers businesses the advantage of staying up to date with their affiliate program's performance. It allows for immediate insights into referral activity and commissions earned.",
            ];

            $reportKeywords = [
                // Single Words
                'entertainment',
                'membership',
                'benefits',
                'loyalty',
                'analytics',
                'marketing',
                'campaigns',
                'bookings',
                'management',
                'inventory',
                'staff',
                'scheduling',
                'payroll',
                'reporting',
                'data',
                'metrics',
                'payments',
                'customer',
                'surveys',
                'feedback',
                'affiliates',
                'commissions',
                // Two-Word Phrases
                "entertainment membership",
                "loyalty programs",
                "customer behavior",
                "marketing strategies",
                "bookings management",
                "inventory management",
                "staff management",
                "customer engagement",
                "surveys and ratings",
                "affiliate partnerships",
            ];
        }

        if ($venue->venueType->definition === 'food') {
            // Predefined Questions and Answers
            $predefinedQuestions = [
                'Hi' => 'Hello! How can I assist you today?',
                'Hello' => 'Hello! How can I assist you today?',
                'How are you today' => 'I\'m just a chatbot, but I\'m here to help. How can I assist you today?',
                'Can you help me with something' => 'Of course, I\'m here to assist you. What do you need help with?',
                'What is the main feature of VenueBoost\'s Premium Tables with Bidding?' => 'VenueBoost\'s Premium Tables with Bidding allows guests to bid on premium tables for special events, adding excitement and revenue potential to table reservations.',
                'How can VenueBoost help increase revenue with Premium Tables with Bidding?' => 'VenueBoost allows businesses to set dynamic pricing based on demand and popularity, optimizing table allocations and enhancing the guest experience with personalized table choices.',
                'What does the Guest Loyalty Program offered by VenueBoost entail?' => 'VenueBoost\'s Dining Guest Loyalty Program allows guests to earn points with every order or reservation, offers rewards like free meals or discounts based on points earned, and enhances guest experiences with personalized services.',
                'How can this program benefit a dining business?' => 'This program can streamline operations, manage guest interactions efficiently, and drive loyalty through exclusive offers, ultimately elevating the dining business.',
                'What insights can businesses gain from VenueBoost\'s Advanced Guest Behavior Analytics?' => 'With this feature, businesses can analyze guest preferences, history, and engagement patterns, enabling data-driven decisions to improve guest experiences and target marketing effectively.',
                'How can businesses use these insights to their advantage?' => 'The insights obtained through Advanced Guest Behavior Analytics empower businesses to enhance guest engagement through data-driven strategies.',
                'What is the key offering of VenueBoost\'s Marketing Automation feature?' => 'VenueBoost\'s Marketing Automation feature allows businesses to create targeted email campaigns based on guest behavior, send personalized offers and recommendations, and measure effectiveness to optimize marketing strategies.',
                'How does VenueBoost streamline marketing across industries with Marketing Automation?' => "VenueBoost's Marketing Automation feature streamlines marketing efforts by seamlessly automating and personalizing campaigns, ultimately driving engagement and sales.",
                "What is the primary focus of VenueBoost in the food industry?" => "VenueBoost primarily focuses on inventory management in the food industry.",
                "How does VenueBoost simplify inventory management for the food industry?" => "VenueBoost simplifies inventory management by providing tracking and insights that are easy to understand.",
                "What is the key feature of VenueBoost's inventory management?" => "The key feature of VenueBoost's inventory management is the ability to track and update inventory efficiently.",
                "How does VenueBoost offer insights in inventory management?" => "VenueBoost provides insights into inventory management to help businesses make informed decisions.",
                "What is the goal of VenueBoost's inventory management?" => "The goal of VenueBoost's inventory management is to streamline purchasing and menu management for a smoother operation.",
                "What is the focus of VenueBoost in staff management for the food industry?" => "VenueBoost focuses on staff management, including scheduling and payroll for restaurants, cafes, bars, and more.",
                "How does VenueBoost simplify staff management for businesses in the food industry?" => "VenueBoost simplifies staff management by offering features like creating and updating schedules, automating wage and tip calculations, tracking hours and staff performance, and identifying scheduling gaps and bottlenecks.",
                "What are the key benefits of VenueBoost's staff management features?" => "The key benefits include easier scheduling, efficient payroll processing, and data-driven insights to optimize operations.",
                "How does VenueBoost help in automating staff management tasks?" => "VenueBoost automates tasks such as wage and tip calculations to make staff management more efficient.",
                "What is the goal of VenueBoost in staff management?" => "The goal of VenueBoost in staff management is to centralize and optimize operations for dining venues.",
                "What does VenueBoost offer in terms of marketing for the food industry?" => "VenueBoost offers marketing solutions for expanding reach and promoting brands.",
                "How does VenueBoost help businesses in the food industry expand their reach?" => "VenueBoost helps businesses expand their reach through marketing activities such as email marketing, promotions, campaigns, and referrals.",
                "What are the key benefits of VenueBoost's marketing features?" => "The key benefits include expanding brand reach and consolidating marketing activities to support business growth.",
                "How does VenueBoost assist in brand promotion for dining venues?" => "VenueBoost assists in brand promotion by providing marketing tools and strategies to attract more customers.",
                "What is the goal of VenueBoost in marketing for the food industry?" => "The goal of VenueBoost in marketing is to consolidate marketing activities and provide the capabilities needed for dining venues to grow.",
                "What loyalty features does VenueBoost offer for the food industry?" => "VenueBoost offers loyalty features that help businesses reward their guests and encourage return visits.",
                "How does VenueBoost motivate return visits for dining venues?" => "VenueBoost motivates return visits by tailoring tiers and benefits, tracking member profiles and purchase history, sending personalized rewards and offers, and analyzing engagement and activity.",
                "What are the key benefits of VenueBoost's loyalty features?" => "The key benefits include strengthening relationships with regular customers, increasing loyalty, retention, and repeat visits.",
                "What is the goal of VenueBoost in loyalty management for dining venues?" => "The goal of VenueBoost in loyalty management is to provide tools that enhance customer loyalty, drive repeat visits, and strengthen relationships with regular customers.",
                "What reporting features does VenueBoost offer for the food industry?" => "VenueBoost offers reporting features that provide actionable dining insights to enhance business operations.",
                "What types of insights can businesses gain from VenueBoost's reporting features?" => "VenueBoost's reporting features offer insights into various aspects such as sales, staff performance, table management, insights, and waitlist management.",
                "Why should businesses use VenueBoost's reporting features?" => "Businesses should use VenueBoost's reporting features to stop relying on guesswork and gain clear visibility into key metrics, allowing them to run a smarter dining business.",
                "How can VenueBoost's reporting features benefit dining venues?" => "VenueBoost's reporting features can benefit dining venues by helping them make data-driven decisions, optimize operations, and improve the overall dining experience.",
                "What does VenueBoost offer in terms of online reservation payment links?" => "VenueBoost offers a feature that allows you to create secure online reservation payment links.",
                "How does VenueBoost help track payment links for online reservations?" => "VenueBoost provides an organized list to track payment links for online reservations.",
                "What insights can businesses gain from using VenueBoost's payment links feature?" => "With VenueBoost, businesses can gain insights into transaction volumes and revenue from online reservations.",
                "How does VenueBoost ensure a smooth and convenient payment process for guests using payment links?" => "VenueBoost offers a smooth and convenient payment process for guests making online reservations.",
                "What does VenueBoost offer in terms of streamlined dining delivery?" => "VenueBoost provides features for streamlined dining delivery, making the order-to-door workflow more efficient.",
                "What are some key components of VenueBoost's dining delivery features?" => "VenueBoost's dining delivery features include house management, provider management, order management, order details, and order history.",
                "How does VenueBoost simplify the dining order-to-door workflow?" => "VenueBoost simplifies the dining order-to-door workflow to ensure a hassle-free dining delivery process.",
                "What does VenueBoost offer in terms of guest management for the food industry?" => "VenueBoost allows you to streamline reservations, loyalty, and communication with its guest management suite.",
                "How does VenueBoost help businesses understand their guests better?" => "VenueBoost provides tools to engage, retain, and attract customers by truly understanding your guests.",
                "What are some key features of VenueBoost's guest management suite?" => "VenueBoost's guest management suite includes preferences, loyalty programs, and custom loyalty programs with special rewards.",
                "What opportunities does VenueBoost's Affiliate Partnerships feature offer?" => "With VenueBoost's Affiliate Partnerships, businesses can expand their reach and revenue by partnering with like-minded organizations.",
                "How can businesses establish affiliate partnerships using VenueBoost?" => "VenueBoost allows businesses to establish affiliate partnerships seamlessly.",
                "What are the benefits of tracking referrals and commissions in real-time with VenueBoost's Affiliate Partnerships feature?" => "Tracking referrals and commissions in real-time with VenueBoost helps businesses make data-driven decisions and optimize their partnerships.",
                "What does VenueBoost offer in terms of guest surveys and ratings?" => "VenueBoost provides powerful Surveys and Ratings to help venues enhance guest experiences and gather crucial feedback.",
                "How can businesses gather valuable feedback using VenueBoost's Surveys and Ratings feature?" => "With VenueBoost, businesses can create customized guest surveys, collect ratings and reviews, and gain insights into preferences and opinions.",
                "What benefits do businesses gain from making data-driven decisions using VenueBoost's Surveys and Ratings?" => "Making data-driven decisions with VenueBoost's Surveys and Ratings allows businesses to make informed improvements and optimize their offerings.",

            ];

            $reportKeywords = [
                'report', 'feedback', 'review', 'evaluation',
                'Menu Analysis', 'Customer Reviews', 'Sales Performance',
                'Inventory Management', 'Food Quality Assessment', 'Beverage Selection',
                'Staff Efficiency', 'Customer Satisfaction', 'Cost Analysis',
                'Marketing Effectiveness', 'Supplier Relationships',
                'Health and Safety Compliance', 'Seasonal Trends',
                'Food and Beverage Trends', 'Promotions', 'Discounts',
                'Food', 'Beverage', 'Promotions', 'Discounts', 'guest',
                'guests', 'customer', 'customers', 'client', 'clients',
                'patron', 'patrons', 'diner', 'diners', 'customer satisfaction',
            ];
        }


        // Set the probability of showing feedback based on the number of responses and report-related keywords
        $showFeedbackProbability = 0;

        // Check the number of prompts responses for the venue ID
        $venueResponsesCount = PromptsResponses::where('venue_id', $venue->id)->count();

        // Calculate the probability based on a frequency interval (e.g., every 30 responses)
        $frequencyInterval = 30;
        if ($venueResponsesCount >= $frequencyInterval) {
            $showFeedbackProbability = ($venueResponsesCount / $frequencyInterval) - floor($venueResponsesCount / $frequencyInterval);
        }

        // Check if the user's question contains report-related keywords
        $containsReportKeyword = false;
        foreach ($reportKeywords as $keyword) {
            if (stripos($request->question, $keyword) !== false) {
                $containsReportKeyword = true;
                break;
            }
        }

        // Randomly decide whether to show feedback based on probability and keywords
        $showFeedback = $containsReportKeyword && (mt_rand(0, 100) < ($showFeedbackProbability * 100));

        // Check if the user's question matches any predefined question
        $matchedPredefinedQuestion = null;
        foreach ($predefinedQuestions as $question => $answer) {
            if (stripos($request->question, $question) !== false) {
                $matchedPredefinedQuestion = $question;
                break;
            }
        }

        // If a matching predefined question is found, use the predefined answer
        if ($matchedPredefinedQuestion !== null) {
            $relatedQuestions = $this->suggestRelatedQuestions($predefinedQuestions, $matchedPredefinedQuestion, 2);
            return response()->json(['response' => $predefinedQuestions[$matchedPredefinedQuestion], 'suggested_questions' => $relatedQuestions, 'show_feedback' => $showFeedback], 200);
        }
        ;

        // check if there are fine tuning jobs for the venue and get the latest and then use this model to generate the response

        $latestFineTuningJob = FineTuningJob::where('venue_id', $venue->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($latestFineTuningJob) {
//            // Use the fine-tuned model in the response
//            $result = OpenAI::completions()->create([
//                'max_tokens' => 100,
//                'model' => 'ft:gpt-3.5-turbo:' . $latestFineTuningJob->job_id,
//                'messages' => [
//                    [
//                        'role' => 'user',
//                        'content' => $request->question,
//                    ],
//                ],
//            ]);
            // If no fine-tuning job found, use the default model
            $result = OpenAI::completions()->create([
                'max_tokens' => 100,
                'model' => 'text-davinci-003',
                'prompt' => $request->question,
            ]);
        } else {
            // If no fine-tuning job found, use the default model
            $result = OpenAI::completions()->create([
                'max_tokens' => 100,
                'model' => 'text-davinci-003',
                'prompt' => $request->question,
            ]);
        }

    $response = $result->choices[0]->text;

        // Generate suggested questions based on both predefined and ChatGPT-generated questions
        $relatedQuestions = $this->suggestRelatedQuestions($predefinedQuestions, $request->question, 2);

        $responseData = ['response' => $response];

        // Check if there are related questions available
        if (!empty($relatedQuestions)) {
            shuffle($relatedQuestions); // Shuffle the array randomly
            $suggestedQuestions = array_slice($relatedQuestions, 0, 2); // Select up to two random questions

            // Include the selected suggested questions in the response
            $responseData['suggested_questions'] = $suggestedQuestions;
            $responseData['show_feedback'] = $showFeedback;
        }

        // trim the response remove special characters

        $response = trim(preg_replace('/\s+/', ' ', $response));

        // TODO: check if we need to store emoji thumbs up and down as emojis or as text code for the emoji
        // Store the response in the database
        $promptsResponse = new PromptsResponses;
        $promptsResponse->prompt = $request->question;
        $promptsResponse->response = $response;
        $promptsResponse->for = 'admin';
        $promptsResponse->industry = $venue->VenueType->definition;
        $promptsResponse->venue_id = $venue->id;
        $promptsResponse->save();

        // Return the response data as JSON
        return response()->json($responseData, 200);
    }

    private function suggestRelatedQuestions($predefinedQuestions, $currentQuestion, $count): array
    {
        $suggestedQuestions = [];

        // Randomly select predefined questions as suggestions
        $randomPredefinedQuestions = array_rand($predefinedQuestions, $count);

        foreach ($randomPredefinedQuestions as $index) {
            if ($predefinedQuestions[$index] !== $currentQuestion) {
                $suggestedQuestions[] = $index;
            }
        }

        // Add ChatGPT-suggested questions here
        // You can use ChatGPT to generate related questions based on the current question
        // and add them to the $suggestedQuestions array

        return $suggestedQuestions;
    }
}
