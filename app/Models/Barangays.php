<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Barangays extends Model
{

	protected $table = 'barangays';

	public function barangays(): BelongsTo
	{
		return $this->belongsTo(Barangays::class);
	}

	public function region(): BelongsTo
	{
		return $this->belongsTo(Region::class);
	}

	public function province(): BelongsTo
	{
		return $this->belongsTo(Province::class);
	}

    public function city_municipality(): BelongsTo
    {
        return $this->belongsTo(CityMunicipalities::class);
    }
}
