<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppClient;
use App\Models\Chat;
use App\Models\Employee;
use App\Models\Message;
use App\Models\User;
use App\Events\MessageSent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StaffChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user()->load('employee.role');
        $selectedTab = $request->input('tab', 'staff');

        // Count unread messages for staff and client chats separately
        $staffUnreadCount = Message::whereHas('chat', function ($query) use ($user) {
            $query->where('type', 'staff')
                ->where(function($q) use ($user) {
                    $q->where('sender_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                });
        })->where('receiver_id', $user->id)
            ->where('is_read', 0)
            ->count();

        $clientUnreadCount = Message::whereHas('chat', function ($query) use ($user) {
            $query->where('type', 'client')
                ->where(function($q) use ($user) {
                    $q->where('sender_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                });
        })->where('receiver_id', $user->id)
            ->where('is_read', 0)
            ->count();

        $query = Chat::with(['sender', 'receiver', 'messages' => function($query) {
            $query->orderBy('created_at', 'desc');
        }])
            ->where('status', '!=', 'deleted');

        // Filter by project if provided
        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        if ($selectedTab === 'customers') {
            // Only Operations Manager can see client chats
            if ($user->employee?->role?->name !== 'Operations Manager') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            $query->where('type', 'client');
        } else {
            // Get staff-to-staff chats
            $query->where('type', 'staff');
        }

        // Show chats where user is participant
        $query->where(function($q) use ($user) {
            $q->where('sender_id', $user->id)
                ->orWhere('receiver_id', $user->id);
        });

        $chats = $query->orderBy('updated_at', 'desc')->paginate(10);

        $chats->getCollection()->transform(function ($chat) use ($user) {
            $lastMessage = $chat->messages->first();
            $otherUser = $chat->sender_id === $user->id ?
                User::with('employee.role')->find($chat->receiver_id) :
                User::with('employee.role')->find($chat->sender_id);

            return [
                'id' => $chat->id,
                'name' => $otherUser->name,
                'role' => $otherUser->employee?->role?->name ?? 'Client',
                'message' => $lastMessage?->content ?? '',
                'time' => $this->formatDateTime($lastMessage?->created_at ?? $chat->created_at),
                'unread' => $chat->messages()
                    ->where('receiver_id', $user->id)
                    ->where('is_read', 0)
                    ->count(),
                'isOnline' => $this->isUserOnline($otherUser),
                'project' => $chat->project ? [
                    'id' => $chat->project->id,
                    'name' => $chat->project->name,
                    'status' => $chat->project->status
                ] : null
            ];
        });

        return response()->json([
            'chats' => $chats,
            'unread_counts' => [
                'staff' => $staffUnreadCount,
                'client' => $clientUnreadCount
            ]
        ]);
    }


    public function getMessages($chatId): JsonResponse
    {
        $user = auth()->user();

        $chat = Chat::where('id', $chatId)
            ->where(function($query) use ($user) {
                $query->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->firstOrFail();

        $messages = Message::where('chat_id', $chatId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->transform(function ($message) use ($user) {
                return [
                    'id' => $message->id,
                    'content' => $message->type === 'image'
                        ? Storage::disk('s3')->temporaryUrl($message->content, '+5 minutes')
                        : $message->content,
                    'type' => $message->type,
                    'sentByMe' => $message->sender_id === $user->id,
                    'created_at' => $message->created_at
                ];
            });

        // Mark messages as read
        Message::where('chat_id', $chatId)
            ->where('receiver_id', $user->id)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        return response()->json($messages);
    }

    public function sendMessage(Request $request, $chatId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required_if:type,text',
            'type' => 'required|in:text,image',
            'image' => 'required_if:type,image|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user = auth()->user();

        $chat = Chat::where('id', $chatId)
            ->where(function($query) use ($user) {
                $query->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->firstOrFail();

        $receiverId = $chat->sender_id === $user->id ? $chat->receiver_id : $chat->sender_id;

        $content = $request->input('content');
        if ($request->type === 'image') {
            $content = Storage::disk('s3')->putFile(
                "chat_files/{$chatId}",
                $request->file('image')
            );
        }

        $message = Message::create([
            'chat_id' => $chatId,
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'content' => $content,
            'type' => $request->type
        ]);

        // Update chat timestamp
        $chat->touch();

        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'id' => $message->id,
            'content' => $request->type === 'image'
                ? Storage::disk('s3')->temporaryUrl($content, '+5 minutes')
                : $content,
            'type' => $message->type,
            'sentByMe' => true,
            'created_at' => $message->created_at
        ]);
    }

    public function startChat(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'project_id' => 'nullable|exists:app_projects,id',
            'message' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user = auth()->user()->load('employee.role');
        $receiver = User::with('employee.role')->findOrFail($request->receiver_id);

        // Check if receiver is app client or employee
        $chatType = $receiver->is_app_client ? 'client' : 'staff';

        // Validate permissions
        if ($chatType === 'client') {
            // Only Operations Manager can chat with clients
            if (!$user->employee?->role?->name === 'Operations Manager') {
                return response()->json(['error' => 'Only Operations Manager can chat with clients'], 403);
            }
        } else {
            // For staff chat, both users must be employees
            if (!$user->employee || !$receiver->employee) {
                return response()->json(['error' => 'Both users must be staff members for staff chat'], 400);
            }
        }


        // Check if chat exists
        $chat = Chat::where(function($query) use ($user, $receiver) {
            $query->where(function($q) use ($user, $receiver) {
                $q->where('sender_id', $user->id)
                    ->where('receiver_id', $receiver->id);
            })->orWhere(function($q) use ($user, $receiver) {
                $q->where('sender_id', $receiver->id)
                    ->where('receiver_id', $user->id);
            });
        })
            ->where('type', $chatType) // Changed from chat_type to type
            ->when($request->project_id, function($query) use ($request) {
                return $query->where('project_id', $request->project_id);
            })
            ->first();

        if (!$chat) {
            $chat = Chat::create([
                'sender_id' => $user->id,
                'receiver_id' => $receiver->id,
                'type' => $chatType, // Changed from chat_type to type
                'project_id' => $request->project_id,
                'venue_id' => $user->employee?->venue_id,
                'status' => 'active'
            ]);
        }

        $message = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $user->id,
            'receiver_id' => $receiver->id,
            'content' => $request->message,
            'type' => 'text'
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'id' => $chat->id,
            'receiver' => [
                'id' => $receiver->id,
                'name' => $receiver->name,
                'type' => $chatType,
                'role' => $receiver->employee?->role?->name ?? 'Client'
            ],
            'message' => [
                'id' => $message->id,
                'content' => $message->content,
                'created_at' => $message->created_at
            ]
        ]);
    }

    private function formatDateTime($dateTime): string
    {
        if (!$dateTime) return '';

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
        }
        return $carbonDateTime->format('j F Y');
    }

    private function isUserOnline(User $user): bool
    {
        return $user->loginActivities()
            ->where('created_at', '>', now()->subMinutes(5))
            ->exists();
    }


    public function searchChats(Request $request): JsonResponse
    {
        $user = auth()->user()->load('employee.role');
        $search = $request->input('search');
        $selectedTab = $request->input('tab', 'staff');

        $query = Chat::with(['sender', 'receiver', 'messages' => function($query) {
            $query->orderBy('created_at', 'desc');
        }])
            ->where('status', '!=', 'deleted');

        // Filter by user's role and chat type
        if ($selectedTab === 'customers') {
            if ($user->employee?->role?->name !== 'Operations Manager') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            $query->where('type', 'client');
        } else {
            $query->where('type', 'staff');
        }

        // Show chats where user is participant
        $query->where(function($q) use ($user) {
            $q->where('sender_id', $user->id)
                ->orWhere('receiver_id', $user->id);
        });

        // Apply search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->whereHas('sender', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhereHas('receiver', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhereHas('messages', function($q) use ($search) {
                    $q->where('content', 'like', "%{$search}%")
                        ->where('type', 'text');
                });
            });
        }

        $chats = $query->orderBy('updated_at', 'desc')->paginate(10);

        $chats->getCollection()->transform(function ($chat) use ($user) {
            $lastMessage = $chat->messages->first();
            $otherUser = $chat->sender_id === $user->id ?
                User::with('employee.role')->find($chat->receiver_id) :
                User::with('employee.role')->find($chat->sender_id);

            return [
                'id' => $chat->id,
                'name' => $otherUser->name,
                'role' => $otherUser->employee?->role?->name ?? 'Client',
                'message' => $lastMessage?->content ?? '',
                'time' => $this->formatDateTime($lastMessage?->created_at ?? $chat->created_at),
                'unread' => $chat->messages()
                    ->where('receiver_id', $user->id)
                    ->where('is_read', 0)
                    ->count(),
                'isOnline' => $this->isUserOnline($otherUser),
                'avatar' => $otherUser->profile_picture ?
                    Storage::disk('s3')->temporaryUrl($otherUser->profile_picture, '+5 minutes') :
                    null
            ];
        });

        return response()->json($chats);
    }

    public function listEmployees(): JsonResponse
    {
        $user = auth()->user()->load('employee.role');

        // Get IDs of users that current user already has chats with
        $existingChatUserIds = Chat::where('type', 'staff')
            ->where(function($q) use ($user) {
                $q->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->where('status', '!=', 'deleted')
            ->get()
            ->map(function ($chat) use ($user) {
                return $chat->sender_id === $user->id ? $chat->receiver_id : $chat->sender_id;
            });

        $employees = Employee::with(['user', 'role'])
            ->whereHas('user')
            ->whereNotIn('user_id', $existingChatUserIds) // Exclude users with existing chats
            ->where('user_id', '!=', $user->id) // Exclude current user
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'user_id' => $employee->user_id,
                    'name' => $employee->user->name,
                    'avatar' => $employee->profile_picture ?
                        Storage::disk('s3')->temporaryUrl($employee->profile_picture, '+5 minutes')
                        : $this->getInitials($employee->user->name),
                    'role' => $employee->role?->name
                ];
            });

        return response()->json($employees);
    }

    public function listClients(): JsonResponse
    {
        $user = auth()->user()->load('employee.role');

        // Check if user is Operations Manager
        if ($user->employee?->role?->name !== 'Operations Manager') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get IDs of clients that already have chats
        $existingChatUserIds = Chat::where('type', 'client')
            ->where(function($q) use ($user) {
                $q->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->where('status', '!=', 'deleted')
            ->get()
            ->map(function ($chat) use ($user) {
                return $chat->sender_id === $user->id ? $chat->receiver_id : $chat->sender_id;
            });

        $clients = AppClient::with('user')
            ->whereHas('user')
            ->whereNotIn('user_id', $existingChatUserIds) // Exclude clients with existing chats
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'user_id' => $client->user_id,
                    'name' => $client->user->name,
                    'avatar' => $client->user->profile_picture ?
                        Storage::disk('s3')->temporaryUrl($client->user->profile_picture, '+5 minutes') :
                        $this->getInitials($client->user->name),
                    'type' => 'Client'
                ];
            });

        return response()->json($clients);
    }

    /**
     * Helper function to generate initials from name
     */
    private function getInitials($name): string
    {
        $words = explode(' ', $name);
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return substr($initials, 0, 2);
    }
}
