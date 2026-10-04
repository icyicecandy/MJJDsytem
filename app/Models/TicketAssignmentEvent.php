<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketAssignmentEvent extends Model
{
    protected $fillable = [
        'ticket_id',
        'from_staff_id',
        'to_staff_id',
        'actor_id',
        'source',
        'reason',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function fromStaff()
    {
        return $this->belongsTo(User::class, 'from_staff_id');
    }

    public function toStaff()
    {
        return $this->belongsTo(User::class, 'to_staff_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
