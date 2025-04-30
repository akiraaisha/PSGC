<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Region extends Model
{
    //

    public function region():BelongsTo {
        return $this->belongsTo(Region::class);
    }

}
