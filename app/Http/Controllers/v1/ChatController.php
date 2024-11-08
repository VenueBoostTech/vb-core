<?php

namespace App\Http\Controllers\v1;

use App\Mail\GuestReceiptEmail;
use App\Mail\NewBookingEmail;
use App\Mail\RentalUnitBookingConfirmationEmail;
use App\Models\Chat;
use App\Models\Gallery;
use App\Models\Guest;
use App\Models\LoyaltyTier;
use App\Models\Message;
use App\Models\PriceBreakdown;
use App\Models\Receipt;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Wallet;
use ICal\ICal;
use Carbon\Carbon;
use App\Models\Booking;
use App\Models\RentalUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\ThirdPartyBooking;
use App\Http\Controllers\Controller;
use App\Models\PricePerNight;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $perPage = 10;
        $currentPage = $request->input('page', 1);

        $query = Chat::with(['endUser', 'messages' => function($query) {
            $query->orderBy('created_at', 'desc');
        }])
            ->where('venue_id', $venue->id)
            ->where('status', '!=', 'deleted')
            ->orderBy(
                Message::select('created_at')
                    ->whereColumn('messages.chat_id', 'chats.id')
                    ->orderBy('created_at', 'desc')
                    ->limit(1),
                'desc'
            );

        $chats = $query->paginate($perPage, ['*'], 'page', $currentPage);

        // Transform the paginated chats to include end user name, last message time, and last message text
        $chats->getCollection()->transform(function ($chat) {
            $lastMessage = $chat->messages->first();
            $lastMessageTime = $lastMessage ? $lastMessage->created_at : $chat->created_at;

            $formattedLastMessageTime = $this->formatDateTime($lastMessageTime);

            return [
                'id' => $chat->id,
                'end_user_id' => $chat->end_user_id,
                'end_user_name' => $chat->endUser->name ?? 'Unknown', // Assuming endUser relation exists
                'venue_user_id' => $chat->venue_user_id,
                'venue_id' => $chat->venue_id,
                'status' => $chat->status,
                'created_at' => $chat->created_at,
                'updated_at' => $chat->updated_at,
                'last_message_time' => $formattedLastMessageTime,
                'last_message_text' => $lastMessage ? $lastMessage->content : '',
                'sentByMe' => $lastMessage && $lastMessage->sender_id === $chat->venue_user_id,
                'messages' => $chat->messages
            ];
        });

        return response()->json($chats);
    }

    private function formatDateTime($dateTime): string
    {
        $carbonDateTime = Carbon::parse($dateTime);
        $now = Carbon::now();

        if ($carbonDateTime->isToday()) {
            return $carbonDateTime->format('h:i A');
        } elseif ($carbonDateTime->isYesterday()) {
            return 'Yesterday';
        } elseif ($carbonDateTime->isCurrentWeek()) {
            return $carbonDateTime->format('l');
        } elseif ($carbonDateTime->isCurrentYear()) {
            return $carbonDateTime->format('j F');
        } else {
            return $carbonDateTime->format('j F Y');
        }
    }



    public function startConversation(Request $request): JsonResponse
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
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Check if the chat already exists with all necessary relationships
        $chat = Chat::with([
            'endUser',
            'messages' => function($query) {
                $query->orderBy('created_at', 'desc');
            },
            'order.items.product',  // Include order details if exists
            'booking.rentalUnit',   // Include booking details if exists
            'booking.guest'
        ])
            ->where('end_user_id', $request->user_id)
            ->where('venue_user_id', $venue->user_id)
            ->where('venue_id', $venue->id)
            ->first();

        // If the chat doesn't exist, create a new one
        if (!$chat) {
            $chat = Chat::create([
                'end_user_id' => $request->user_id,
                'venue_user_id' => $venue->user->id,
                'venue_id' => $venue->id,
                'status' => Chat::STATUS_ACTIVE,
            ]);

            // Reload the chat with all relationships
            $chat->load([
                'endUser',
                'messages' => function($query) {
                    $query->orderBy('created_at', 'desc');
                },
                'order.items.product',
                'booking.rentalUnit',
                'booking.guest'
            ]);
        }

        $lastMessage = $chat->messages->first();
        $lastMessageTime = $lastMessage ? $lastMessage->created_at : $chat->created_at;

        $formattedChat = [
            'id' => $chat->id,
            'end_user_id' => $chat->end_user_id,
            'end_user_name' => $chat->endUser->name ?? 'Unknown',
            'venue_user_id' => $chat->venue_user_id,
            'venue_id' => $chat->venue_id,
            'status' => $chat->status,
            'created_at' => $chat->created_at,
            'updated_at' => $chat->updated_at,
            'last_message_time' => $this->formatDateTime($lastMessageTime),
            'last_message_text' => $lastMessage ? $lastMessage->content : '',
            'sentByMe' => $lastMessage && $lastMessage->sender_id === $chat->venue_user_id,
            'messages' => $chat->messages,
            'order' => $chat->order ? [
                'id' => $chat->order->id,
                'status' => $chat->order->status,
                'total' => $chat->order->total,
                'items' => $chat->order->items->map(function($item) {
                    return [
                        'product_name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'price' => $item->price
                    ];
                })
            ] : null,
            'booking' => $chat->booking ? [
                'id' => $chat->booking->id,
                'status' => $chat->booking->status,
                'check_in_date' => $chat->booking->check_in_date,
                'check_out_date' => $chat->booking->check_out_date,
                'rental_unit' => [
                    'id' => $chat->booking->rentalUnit->id,
                    'name' => $chat->booking->rentalUnit->name
                ],
                'guest' => [
                    'id' => $chat->booking->guest->id,
                    'name' => $chat->booking->guest->name
                ]
            ] : null
        ];

        return response()->json([
            'message' => $chat->wasRecentlyCreated ? 'Chat created successfully' : 'Existing chat retrieved',
            'chat' => $formattedChat
        ], $chat->wasRecentlyCreated ? 201 : 200);
    }
}
