<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class CityMunicipalities extends Model
{
    //
    protected $fillable = [
        'name',
        'code',
        'population',
        'PSGC_Code',
        'RegionProvinceCityMun_Code',
        'province_id',
    ];

    public function CityMunicipalities(): BelongsTo
    {
        return $this->belongsTo(CityMunicipalities::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
