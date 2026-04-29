<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use App\Models\ChatMessage;

class ChatMessageSent implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(ChatMessage $message)
    {
        $this->message = $message->loadMissing(['fromUser', 'assignedStaff', 'customer']);
    }

    public function broadcastOn()
    {
        $userIds = collect([
            $this->message->customer_id,
            $this->message->assigned_staff_id,
            $this->message->from_user_id,
            $this->message->to_user_id,
        ])->filter()->unique();

        return $userIds->map(fn ($id) => new PrivateChannel('chat.' . $id))->all();
    }

    public function broadcastWith()
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'customer_id' => $this->message->customer_id,
                'assigned_staff_id' => $this->message->assigned_staff_id,
                'from_user_id' => $this->message->from_user_id,
                'to_user_id' => $this->message->to_user_id,
                'message' => $this->message->message,
                'sender_type' => $this->message->sender_type,
                'message_type' => $this->message->message_type,
                'conversation_status' => $this->message->conversation_status,
                'handoff_status' => $this->message->handoff_status,
                'sender_label' => $this->message->senderLabel(),
                'sender_role' => optional($this->message->fromUser)->role,
                'assigned_staff_name' => optional($this->message->assignedStaff)->name,
                'attachment_url' => $this->message->attachmentUrl(),
                'attachment_path' => $this->message->attachmentPath(),
                'attachment_original_name' => $this->message->attachmentOriginalName(),
                'attachment_is_image' => $this->message->hasImageAttachment(),
                'created_at' => $this->message->created_at->toDateTimeString(),
            ]
        ];
    }
}
