<?php

namespace App\Models;

use App\Models\Flight;
use App\Models\Operator;
use App\Models\AircraftType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Aircraft extends Model
{
    use HasFactory;

    protected $table = 'aircrafts';
    protected $fillable = [
        'immatriculation',
        'pmad',
        'in_activity',
        'aircraft_type_id',
        'operator_id',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(AircraftType::class, 'aircraft_type_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class);
    }
}
