<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Province extends Model
{
    //
    protected $fillable = [
        'name',
        'code',
        'population',
        'PSGC_Code',
        'region_id',

    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function CityMunicipalities(): BelongsTo
    {
        return $this->belongsTo(CityMunicipalities::class);
    }
}
