<?php

namespace App\Http\Controllers\v1\AI;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *   title="AI API",
 *   version="1.0",
 *   description="This API allows use AI Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="AI",
 *   description="AI"
 * )
 */
class ChatbotController extends Controller
{
    /**
     * @OA\Post(
     *     path="/ai/chat",
     *     tags={"AI"},
     *     summary="Send a chat request to the OpenAI",
     *     @OA\Response(response="200", description="List chat responses from OpenAI"),
     *     @OA\Response(response="400", description="Validation errors"),
     *     @OA\Response(response="500", description="Internal server error")
     * )
     */

    public function sendChat(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Predefined Questions and Answers
        $predefinedQuestions = [
            'I am thinking about using VenueBoost at my venue' => 'Great to hear that you\'re considering VenueBoost for your venue! VenueBoost offers a comprehensive set of features designed to enhance restaurant operations and elevate the guest experience. With VenueBoost, you can streamline waitlist management, optimize table seating, manage reservations, update menus, and more. If you have any specific questions or need further information, feel free to ask!',
            'What are the key benefits of using VenueBoost for my venue?' => 'VenueBoost offers a range of benefits for your venue. It streamlines waitlist management, optimizes table seating, enables seamless reservations, and simplifies menu updates. These features enhance your operations and guest satisfaction.',
            // VenueBoost
            'What is VenueBoost?' => 'VenueBoost is a comprehensive platform designed to enhance restaurant operations and elevate the guest experience. It offers a range of features and capabilities tailored to meet the unique needs of hospitality venues.',
            // Features
            'What are the main features of VenueBoost?' => 'VenueBoost offers a wide range of features, including waitlist management, table management, reservations, menu management, and staff management. Each feature is designed to streamline operations and improve efficiency.',
            // Who We Serve
            'Who does VenueBoost serve?' => 'VenueBoost caters to a variety of hospitality venues, including restaurants, cafes, bistros, pubs & clubs, bars, sports & entertainment venues, and hotels. It provides tailored solutions to meet the specific needs of each venue type.',
            // Waitlist Management
            'Can you tell me more about waitlist management in VenueBoost?' => 'With VenueBoost\'s waitlist management feature, you can streamline your waitlist process and efficiently manage customer queues. It ensures a smooth and organized flow of guests, reducing wait times and enhancing the overall dining experience.',
            // Menu Management
            'How does VenueBoost help with menu management?' => 'VenueBoost\'s menu management feature allows you to effortlessly update and showcase your menu offerings. You can highlight special dishes, promotions, and dietary options, keeping your customers informed and engaged.',
            // Resources
            'Are there any resources available for VenueBoost users?' => 'Yes, VenueBoost provides a range of resources to support its users. These include FAQs, a customer hub, tools/guides, and success stories. These resources offer valuable insights, tips, and inspiration to maximize your experience with VenueBoost.',
            // Venue Types
            'What type of venues can benefit from VenueBoost?' => 'VenueBoost caters to various types of hospitality venues, including restaurants, cafes, bistros, pubs & clubs, bars, sports & entertainment venues, and hotels. Each venue type can leverage VenueBoost\'s features to streamline operations and drive success.',
            // Table Management
            'How can VenueBoost help with table management?' => 'VenueBoost\'s table management feature allows you to optimize your table seating arrangement, maximize capacity, and provide a seamless dining experience for your guests. It helps you efficiently assign tables, track availability, and manage reservations.',
            // Online Reservations
            'Does VenueBoost support online reservations?' => 'Absolutely! VenueBoost provides an easy-to-use online reservation system. Your customers can book their preferred date, time, and table with convenience, while you can manage and accept reservations seamlessly.',
            // FAQs
            'What resources are available in VenueBoost\'s FAQs section?' => 'VenueBoost\'s FAQs section covers a wide range of commonly asked questions about the platform\'s features and capabilities. You can find answers related to waitlist management, table management, reservations, menu management, and more.',
            // Customer Hub
            'How can I access VenueBoost\'s customer hub?' => 'VenueBoost\'s customer hub is a dedicated space where you can access essential tools and resources to maximize your experience with the platform. It provides access to training materials, user guides, and support channels.',
            // Success Stories
            'Are there any success stories of venues using VenueBoost?' => 'Yes, VenueBoost features success stories from hospitality venues similar to yours. These stories highlight how venues have achieved remarkable success using VenueBoost, offering valuable insights and inspiration for your own journey.',
            // Sports and Entertainment Venues
            'Can sports and entertainment venues benefit from VenueBoost?' => 'Absolutely! VenueBoost caters to sports and entertainment venues as well. It offers easy management solutions for golf, gym venues, and other sports entertainment venues, ensuring smooth operations and enhanced guest experiences.',
            // Hotels
            'How does VenueBoost support hotels?' => 'VenueBoost is designed to streamline hotel operations and elevate marketing efforts. It offers effortless management solutions, including table management, reservations, menu updates, and more, to enhance the overall hotel guest experience.',
            'How can VenueBoost help with waitlist management?' => 'With VenueBoost\'s waitlist management feature, you can efficiently manage customer queues, track wait times, and optimize table seating. It helps you provide a seamless and organized dining experience for your guests.',
            'Can VenueBoost notify guests when their table is ready?' => 'Yes, VenueBoost has built-in notification capabilities that allow you to notify guests when their table is ready. You can send automated SMS or email notifications to keep your guests informed and minimize wait times.',
            'How does VenueBoost handle table management?' => 'VenueBoost\'s table management feature allows you to optimize your table seating arrangement, maximize capacity, and provide a seamless dining experience for your guests. It helps you efficiently assign tables, track availability, and manage reservations.',
            'Can VenueBoost help me with staff management?' => 'Yes, VenueBoost offers a staff management feature that enables you to effectively schedule and manage your staff shifts. You can track their performance, ensure smooth coordination, and deliver exceptional service.',
            'Does VenueBoost provide analytics and reporting?' => 'Yes, VenueBoost offers robust analytics and reporting capabilities. You can gain insights into various aspects of your restaurant\'s performance, such as reservation trends, wait times, table utilization, staff productivity, and more.',
            'How does VenueBoost work?' => 'VenueBoost works by providing a centralized platform that helps restaurants manage various aspects of their operations, such as waitlist management, table management, reservations, menu management, and staff management.',
            'Is VenueBoost a cloud-based solution?' => 'Yes, VenueBoost is a cloud-based solution, which means you can access it from anywhere with an internet connection. It offers the flexibility and convenience of managing your restaurant operations remotely.',
            'Which are the main features of VenueBoost?' => 'VenueBoost offers a wide range of features, including waitlist management, table management, reservations, menu management, staff management, loyalty programs, online ordering, analytics and reporting, and integrations with other systems.',
            'How can VenueBoost help my with waitlist management?' => 'With VenueBoost\'s waitlist management feature, you can efficiently manage customer queues, track wait times, and optimize table seating. It helps you provide a seamless and organized dining experience for your guests.',
            'Does VenueBoost notify guests when their table is ready?' => 'Yes, VenueBoost has built-in notification capabilities that allow you to notify guests when their table is ready. You can send automated SMS or email notifications to keep your guests informed and minimize wait times.',
            'Can VenueBoost support online reservations?' => 'Absolutely! VenueBoost provides an easy-to-use online reservation system. Your customers can book their preferred date, time, and table with convenience, while you can manage and accept reservations seamlessly.',
            'How does VenueBoost handle venue table management?' => 'VenueBoost\'s table management feature allows you to optimize your table seating arrangement, maximize capacity, and provide a seamless dining experience for your guests. It helps you efficiently assign tables, track availability, and manage reservations.',
            'Can VenueBoost help me and my venue with staff management?' => 'Yes, VenueBoost offers a staff management feature that enables you to effectively schedule and manage your staff shifts. You can track their performance, ensure smooth coordination, and deliver exceptional service.',
            'Does VenueBoost provide analytics & reporting?' => 'Yes, VenueBoost offers robust analytics and reporting capabilities. You can gain insights into various aspects of your restaurant\'s performance, such as reservation trends, wait times, table utilization, staff productivity, and more.',
            'Can VenueBoost integrate with other systems?' => 'Yes, VenueBoost provides integrations with other systems to streamline your operations. It can integrate with POS systems, online ordering platforms, loyalty programs, and more, allowing for seamless data synchronization and workflow efficiency.',
            'Which venues does VenueBoost serve?' => 'VenueBoost caters to a variety of hospitality venues, including restaurants, cafes, bistros, pubs & clubs, bars, sports & entertainment venues, and hotels. It provides tailored solutions to meet the specific needs of each venue type.',
            'How can restaurants benefit from VenueBoost?' => 'Restaurants can benefit from VenueBoost in several ways. It helps streamline operations, enhance guest experiences, optimize table seating, manage reservations, improve staff coordination, and gain valuable insights through analytics and reporting.',
            'What advantages does VenueBoost offer to cafes?' => 'VenueBoost offers cafes a comprehensive platform to efficiently manage their operations. It helps with table management, waitlist management, reservations, menu updates, staff scheduling, and more. It enables cafes to deliver exceptional service and enhance customer satisfaction.',
            'Can bistros benefit from VenueBoost?' => 'Yes, VenueBoost can benefit bistros in multiple ways. It offers solutions for waitlist management, table management, reservations, menu updates, and staff scheduling. These features help bistros streamline operations, attract more guests, and deliver a seamless dining experience.',
            'How can pubs & clubs leverage VenueBoost?' => 'VenueBoost empowers pubs & clubs with features like table management, reservations, waitlist management, and event coordination. It helps maximize guest attraction, ensure accurate booking management, and enhance overall operational efficiency.',
            'Can bars improve their operations with VenueBoost?' => 'Absolutely! VenueBoost offers bars a comprehensive platform to streamline their operations. It helps with table management, reservations, menu updates, staff scheduling, and event coordination. Bars can leverage these features to enhance guest experiences and drive success.',
            'How can sports & entertainment venues benefit from VenueBoost?' => 'VenueBoost provides easy management solutions for sports & entertainment venues. It helps with table management, reservations, waitlist management, and event coordination. These features enable sports & entertainment venues to deliver exceptional guest experiences and optimize operations.',
            'What advantages does VenueBoost offer to hotels?' => 'VenueBoost is designed to streamline hotel operations and elevate marketing efforts. It offers effortless management solutions, including table management, reservations, menu updates, staff scheduling, and analytics. Hotels can enhance the guest experience and improve overall operational efficiency.',
            // Waitlist Management
            'How can VenueBoost help with waitlist management module?' => 'With VenueBoost\'s waitlist management feature, you can streamline your waitlist process and efficiently manage customer queues. It ensures a smooth and organized flow of guests, reducing wait times and enhancing the overall dining experience.',
            'Does VenueBoost provide wait time estimation?' => 'Yes, VenueBoost offers wait time estimation capabilities. It allows you to estimate wait times based on various factors such as table availability, party size, and current queue length. This helps manage guest expectations and optimize seating arrangements.',
            'Can VenueBoost send automated notifications to guests on the waitlist?' => 'Absolutely! VenueBoost has built-in notification features that allow you to send automated notifications to guests',
            'Can VenueBoost help me manage reservations?' => 'Yes, VenueBoost offers a robust reservation management feature. You can easily accept, manage, and track reservations, assign tables, and provide a seamless booking experience for your guests.',
            'How does VenueBoost handle menu management?' => 'Answer: VenueBoost\'s menu management feature enables you to effortlessly update and showcase your menu offerings. You can add or remove items, set prices, customize descriptions, and highlight special dishes or promotions.',
            'Can VenueBoost help me with staff scheduling?'  => 'Absolutely! VenueBoost includes a staff scheduling feature that allows you to create and manage staff schedules efficiently. You can assign shifts, track availability, and ensure smooth coordination among your team.',
            'Does VenueBoost offer analytics and reporting?'  => 'Yes, VenueBoost provides powerful analytics and reporting tools. You can access key performance metrics, such as reservation statistics, table utilization, guest feedback, and revenue insights, to make data-driven decisions and optimize your operations.',
            'Can VenueBoost integrate with my existing systems?'  => ' VenueBoost offers integrations with various systems commonly used in the hospitality industry. It can seamlessly integrate with POS systems, online ordering platforms, payment gateways, loyalty programs, and more, ensuring a cohesive workflow and data synchronization.',
            'How can I get started with VenueBoost?'  => 'Getting started with VenueBoost is easy. Simply sign up for an account, customize your settings, and start using the platform\'s features to enhance your restaurant operations and guest experience.',
            'Is VenueBoost suitable for small restaurants as well?' =>  'Absolutely! VenueBoost is designed to cater to all types of restaurants, including small establishments. Its flexible features and scalable solutions make it suitable for businesses of any size.',
            'How can I contact VenueBoost\'s customer support?'  => 'If you need any assistance or have questions about VenueBoost, you can reach out to our customer support team through our website or within the platform. We\'re here to help you maximize your VenueBoost experience.',
            'Are there any success stories of restaurants using VenueBoost?'  => 'Yes, VenueBoost showcases success stories from various restaurants that have leveraged the platform to enhance their operations, increase guest satisfaction, and drive success. You can find these inspiring stories on our website or within the VenueBoost platform.',
            'Can VenueBoost help me improve customer loyalty?'  => 'Absolutely! VenueBoost offers a loyalty program management feature that allows you to create and manage loyalty programs for your guests. You can incentivize repeat visits, offer exclusive rewards, and strengthen customer loyalty to your restaurant.',
            // Restaurant-related questions
            "How can VenueBoost help my restaurant streamline operations?" => "VenueBoost provides a comprehensive suite of tools to streamline various aspects of your restaurant operations, including menu management, reservations, table management, staff scheduling, order and pay, and waitlist management. By automating these processes, VenueBoost helps save time, increase efficiency, and improve overall operational effectiveness.",
            "Can VenueBoost integrate with my existing restaurant systems?" => "Yes, VenueBoost is designed to seamlessly integrate with various restaurant systems and technologies. Our platform is flexible and can be customized to work with your current POS (Point of Sale) system, online ordering platforms, and more. This integration ensures a smooth transition and allows you to leverage your existing infrastructure while benefiting from VenueBoost's additional features.",
            "Is VenueBoost suitable for restaurants of all sizes?" => "Absolutely! Whether you run a small independent restaurant or manage a large multi-location chain, VenueBoost is designed to cater to businesses of all sizes. Our scalable solution can be tailored to meet your specific requirements and accommodate your growth plans. With VenueBoost, you can optimize operations and enhance guest experiences, regardless of the size of your restaurant.",
            "How does VenueBoost help improve guest satisfaction?" => "VenueBoost offers a range of features and functionalities that contribute to an elevated guest experience. From easy online reservations and efficient table management to seamless order and payment options, VenueBoost enhances convenience, reduces wait times, and allows for personalized service. By providing a smooth and delightful dining experience, VenueBoost helps boost guest satisfaction and encourages repeat visits.",
            // Sports-related questions
            "Can VenueBoost help increase online bookings for my golf course?" => "Absolutely! With VenueBoost, you can optimize your online presence, capture commission-free reservations, and leverage robust guest data to drive more online bookings for your golf course.",
            "How can VenueBoost streamline operations at my bowling venue?" => "VenueBoost offers features such as automated lane assignment, real-time score tracking, and efficient inventory management, ensuring smooth operations and an enhanced bowling experience for your guests.",
            "Can I customize the gym venue management system to fit my specific needs?" => "Absolutely! VenueBoost is highly customizable, allowing you to tailor the management system to match your gym's unique requirements, including membership management, class scheduling, and equipment tracking.",
            "How can VenueBoost's automated email marketing campaigns benefit my sports and entertainment venue?" => "Our automated email marketing tool enables you to engage with your audience, promote special events, share exclusive offers, and encourage repeat bookings, helping to boost customer loyalty and drive revenue.",
            // Pubs and clubs-related questions
            "Can I track and analyze customer preferences and buying patterns with VenueBoost?" => "Yes, you can! VenueBoost provides powerful customer analytics and reporting capabilities. You can track customer preferences, buying patterns, and demographics to gain valuable insights. This information allows you to tailor your offerings, personalize promotions, and create memorable experiences that keep your pub/club patrons coming back for more.",
            "Is VenueBoost compatible with existing POS systems at my pub/club?" => "Absolutely! VenueBoost integrates seamlessly with a wide range of POS systems. Our platform is designed to work harmoniously with your existing infrastructure, ensuring smooth operations and eliminating any disruptions to your pub/club's day-to-day activities. Our team will assist you in setting up the integration and provide ongoing support to ensure a hassle-free experience.",
            "Can VenueBoost help me manage the entrance and guest list for my club?" => "Absolutely! VenueBoost offers a robust guest management feature that allows you to efficiently manage your club's entrance and guest list. You can easily check-in guests, track capacity, and even implement VIP access controls for a seamless club experience.",
            "How can VenueBoost help me promote special events and themed nights at my pub?" => "With VenueBoost's marketing toolkit, you can effectively promote your pub's special events and themed nights. Utilize social media integration to create buzz, send targeted email campaigns to your loyal customers, and even set up exclusive offers or discounts to attract a larger crowd and increase event attendance.",
            // Hotels-related questions
            "Is VenueBoost suitable for small-scale F&B establishments?" => "Absolutely! VenueBoost caters to businesses of all sizes, providing scalable solutions to optimize F&B operations and drive efficiency, regardless of the scale of your establishment.",
            "Can VenueBoost help us improve menu engineering and optimize profitability?" => "Absolutely! VenueBoost provides powerful menu management tools and analytics that enable you to analyze sales data, identify top-performing items, and make data-driven decisions to optimize your menu and maximize profitability.",
            "Can VenueBoost help us manage special events and promotions?" => "Certainly! VenueBoost offers advanced event management features, allowing you to easily plan, coordinate, and execute special events and promotions, ensuring a seamless and successful experience for both your staff and guests.",
            "Does VenueBoost provide customer support and training?" => "Yes, VenueBoost offers comprehensive customer support and training resources to ensure you maximize the benefits of the platform. Our dedicated support team is available to assist you with any inquiries or technical issues you may encounter.",
            // Cafes-related questions
            "Is VenueBoost compatible with my existing cafe management software?" => "Yes, VenueBoost seamlessly integrates with various cafe management systems, ensuring a smooth transition and compatibility with your current setup.",
            "Can I customize the features of VenueBoost to meet my cafe's specific needs?" => "Absolutely! VenueBoost offers a range of customizable features, allowing you to tailor the platform to your cafe's unique requirements and preferences.",
            // Cafes-related questions (continued)
            "How can VenueBoost help improve my cafe's customer service?" => "VenueBoost provides tools for efficient order management, streamlined table reservations, and personalized customer interactions, enhancing the overall customer experience and satisfaction.",
            "Is VenueBoost suitable for small-scale cafes as well as larger establishments?" => "Yes, VenueBoost caters to cafes of all sizes, from cozy local cafes to large-scale establishments. The platform is designed to scale according to your cafe's needs, ensuring it remains effective and efficient as your business grows.",
            // Bistros-related questions
            "Can VenueBoost handle my bistro's unique menu offerings and specials?" => "Absolutely! VenueBoost provides robust menu management capabilities that allow you to easily customize and update your bistro's menu offerings, including daily specials, seasonal dishes, and promotions. You can showcase your culinary creations and ensure that your menu is always up to date for your guests.",
            "Can I integrate VenueBoost with my existing reservation system?" => "Yes, VenueBoost offers seamless integration options with popular reservation systems, allowing you to streamline your reservation process. By integrating your existing reservation system with VenueBoost, you can centralize all your bookings, manage availability, and provide a smooth reservation experience for your guests.",
            "How does VenueBoost help in optimizing table turnover for my bistro?" => "VenueBoost's table management features are designed to enhance table turnover and maximize seating capacity. With real-time updates and visual representations of your seating arrangements, you can efficiently manage table assignments, track table status, and ensure smooth transitions between guests, ultimately increasing the efficiency of your bistro's operations.",
            "Can VenueBoost help me analyze customer feedback and preferences?" => "Absolutely! VenueBoost offers advanced analytics capabilities that allow you to gain valuable insights into customer feedback and preferences. By analyzing data such as customer reviews, ratings, and dining trends, you can make informed decisions to improve your bistro's offerings, enhance guest experiences, and strengthen customer satisfaction.",
            // Bars-related questions
            "How can VenueBoost help my bar optimize table turnover?" => "VenueBoost's table management features are designed to enhance table turnover and maximize seating capacity. With real-time updates and visual representations of your seating arrangements, you can efficiently manage table assignments, track table status, and ensure smooth transitions between guests, ultimately increasing the efficiency of your bar's operations.",
            "Can VenueBoost assist in promoting my bar's offerings?" => "Absolutely! VenueBoost provides a comprehensive marketing toolkit for bars. From social media integration to email campaigns and loyalty programs, VenueBoost helps you engage with customers, promote your bar's offerings, and drive customer loyalty.",
            "Does VenueBoost offer analytics for bar management?" => "Yes, VenueBoost provides robust analytics capabilities for bar management. You can access real-time data and insights on sales, customer preferences, and performance metrics. This data empowers you to make data-driven decisions, enhance guest experiences, and optimize your bar's profitability.",
            "Can I customize my bar's menu offerings using VenueBoost?" => "Absolutely! VenueBoost offers robust menu management capabilities that allow you to easily customize and update your bar's menu offerings. Whether it's daily specials, seasonal drinks, or promotions, VenueBoost enables you to showcase your unique menu and ensure it's always up to date for your customers.",
            // General questions
            "How many pricing plans does VenueBoost offer?" => "VenueBoost offers four pricing plans to cater to different venue requirements. These plans are designed to provide flexibility and options for our customers.",
            "What are the available pricing plans?" => "VenueBoost offers four pricing plans: Free, Core, Growth, and Enterprise. Each plan comes with its own set of features and benefits, allowing venues to choose the one that best suits their needs and budget.",
            "Can you tell me more about the free plan?" => "VenueBoost offers a Free plan that allows venues to get started with essential features at no cost. This plan is ideal for smaller venues or those who want to explore the platform before upgrading to a paid plan.",
            "What are the available add-ons offered by VenueBoost?" => "VenueBoost provides four add-ons to enhance your experience: Delivery, Order & Pay, Payment Links, and 3rd Party POS Integration. These add-ons offer additional functionality and customization options for venues looking to expand their capabilities.",
            "How can I add an add-on to my pricing plan?" => "When choosing your pricing plan, you will have the option to select and include any desired add-ons. Simply select the plan that suits your needs and add the desired add-ons during the signup process.",
            "Are the add-ons available for all pricing plans?" => "Yes, the add-ons are available for all pricing plans. You can customize your plan by selecting the add-ons that align with your venue's requirements.",
            "Can I upgrade or downgrade my pricing plan at any time?" => "Absolutely! VenueBoost allows you to upgrade or downgrade your pricing plan at any time based on your evolving needs. Our platform is designed to provide flexibility and scalability for your venue.",
        ];

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
            return response()->json(['response' => $predefinedQuestions[$matchedPredefinedQuestion], 'suggested_questions' => $relatedQuestions], 200);
        }

        // User's question is not a predefined question, use ChatGPT to generate a response
        $result = OpenAI::completions()->create([
            'max_tokens' => 100,
            'model' => 'text-davinci-003',
            'prompt' => $request->question,
        ]);

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
        }

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
