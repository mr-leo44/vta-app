<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aircraft extends Model
{
    protected $fillable = [
        'registration', 'pmad', 'in_activity', 'aircraft_type_id', 'operator_id'
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(AircraftType::class, 'aircraft_type_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
