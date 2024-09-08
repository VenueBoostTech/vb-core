<?php

namespace App\Http\Controllers\v1;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Events\MessageSent;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function index($chatId): JsonResponse
    {
        // Check if the authenticated user has any associated restaurants
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        // Get the venue short code from the request
        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        // Find the venue associated with the user and the short code
        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Check if the chat belongs to the venue
        $chat = Chat::where('id', $chatId)->where('venue_id', $venue->id)->first();
        if (!$chat) {
            return response()->json(['error' => 'Chat not found'], 404);
        }

        // Get the messages for the chat and transform them to include the sentByMe property
        $messages = Message::where('chat_id', $chatId)->get()->transform(function ($message) use ($venue) {
            $message->sentByMe = $message->sender_id === $venue->user_id;

            if ($message->type == 'image') {
                $newPath = Storage::disk('s3')->temporaryUrl($message->content, '+5 minutes');
                // $newPath = Storage::url($message->content); // for local disk
                $message->content =  $newPath;
            }

            return $message;
        });

        return response()->json($messages);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|exists:chats,id',
            'sender_id' => 'required|exists:users,id',
            'receiver_id' => 'required|exists:users,id',
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
        
        $message = new Message();
        $message->chat_id = $chat_id;
        $message->sender_id = $request->input('sender_id');
        $message->receiver_id = $request->input('receiver_id');
        $message->content = $type == 'image' ? $path : $request->input('content');
        $message->type = $type;
        $message->save();

        broadcast(new MessageSent($message))->toOthers();
        return response()->json($message, 201);
    }
}
