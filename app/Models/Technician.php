<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Technician extends Model
{
   public function installations()
{
    return $this->hasMany(Installation::class);
}
 //
}
