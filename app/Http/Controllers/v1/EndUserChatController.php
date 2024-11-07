<?php

namespace App\Http\Controllers\v1;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\EndUserService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EndUserChatController extends Controller
{
    //
    protected EndUserService $endUserService;

    public function __construct(EndUserService $endUserService)
    {
        $this->endUserService = $endUserService;
    }

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse; // If it's a JsonResponse, return it immediately
        }

        $perPage = 10;
        $currentPage = $request->input('page', 1);

        $query = Chat::with(['endUser', 'messages' => function($query) {
            $query->orderBy('created_at', 'desc');
        }])
            ->where('venue_id', $userOrResponse?->customer?->venue_id)
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
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $venue = Restaurant::where('id', $request->venue_id)->first();

        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:order,booking',
            'order_id' => 'required_if:type,order|exists:orders,id',
            'booking_id' => 'required_if:type,booking|exists:bookings,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Check if the chat already exists
        $chatQuery = Chat::where('end_user_id', $request->user_id)
            ->where('venue_user_id', $venue->user_id)
            ->where('venue_id', $userOrResponse?->customer?->venue_id)
            ->where('type', $request->type);

        if ($request->type === Chat::TYPE_ORDER) {
            $chatQuery->where('order_id', $request->order_id);
        } else {
            $chatQuery->where('booking_id', $request->booking_id);
        }

        $chat = $chatQuery->first();

        // If the chat doesn't exist, create a new one
        if (!$chat) {
            $chatData = [
                'end_user_id' => $request->user_id,
                'venue_user_id' => $venue->user_id,
                'venue_id' => $venue->id,
                'status' => Chat::STATUS_ACTIVE,
                'type' => $request->type
            ];

            if ($request->type === Chat::TYPE_ORDER) {
                $chatData['order_id'] = $request->order_id;
            } else {
                $chatData['booking_id'] = $request->booking_id;
            }

            $chat = Chat::create($chatData);
        }

        return response()->json($chat, 201);
    }

    // Socket use
    public function getMessages($chatId): JsonResponse
    {

        $endUserId = request()->get('enduser_id');
        if (!$endUserId) {
            return response()->json(['error' => 'Chat not accessible'], 400);
        }

        $endUser = User::where('id', $endUserId)->where('enduser', 1)->first();

        // Check if the chat belongs to the venue
        $chat = Chat::where('id', $chatId)->first();
        if (!$chat) {
            return response()->json(['error' => 'Chat not found'], 404);
        }

        // Get the messages for the chat and transform them to include the sentByMe property
        $messages = Message::where('chat_id', $chatId)->get()->transform(function ($message) use ($endUser) {
            $message->sentByMe = $message->sender_id === $endUser->id;

            if ($message->type == 'image') {
                $newPath = Storage::disk('s3')->temporaryUrl($message->content, '+5 minutes');
                // $newPath = Storage::url($message->content); // for local disk
                $message->content =  $newPath;
            }

            return $message;
        });

        return response()->json($messages);
    }

    public function storeMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|exists:chats,id',
            'sender_id' => 'required|exists:users,id',
            'venue_id' => 'required',
            'content' => 'nullable|string',
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $chat_id = $request->input('chat_id');
        $type = $request->input('type');

        $path = null;
        if ($type == 'image' && $request->file('image')) {
            $chatImage = $request->file('image');
            $filename = Str::random(20) . '.' . $chatImage->getClientOriginalExtension();

            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('chat_files/' . $chat_id, $chatImage, $filename);

            // $path = Storage::disk('public')->putFileAs('chat_files/' . $chat_id, $chatImage, $filename);
        }

        $venue = Restaurant::where('id', $request->input('venue_id'))->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }
        $message = new Message();
        $message->chat_id = $chat_id;
        $message->sender_id = $request->input('sender_id');
        $message->receiver_id = $venue->user_id;
        $message->content = $type == 'image' ? $path : $request->input('content');
        $message->type = $type;
        $message->save();

        broadcast(new MessageSent($message))->toOthers();
        return response()->json($message, 201);
    }
}
