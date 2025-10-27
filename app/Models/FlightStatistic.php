<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlightStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'flight_id',
        'passengers_count',
        'pax_bus',
        'go_pass_count',
        'fret_count',
        'excedents',
        'passengers_ecart',
        'has_justification',
        'justification',
    ];

    protected $casts = [
        'fret_count' => 'array',
        'excedents' => 'array',
        'justification' => 'array',
        'has_justification' => 'boolean',
    ];

    // relations
    public function flight()
    {
        return $this->belongsTo(Flight::class);
    }

    // helper to calculate passengers_ecart and has_justification
    public function computeEcart()
    {
        $this->passengers_ecart = $this->passengers_count - $this->go_pass_count;
        $this->has_justification = $this->passengers_ecart > 0;
    }
}
