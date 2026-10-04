<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityDocumentClaim extends Model
{
    protected $fillable = ['kind', 'fingerprint', 'owner_user_id'];
}
