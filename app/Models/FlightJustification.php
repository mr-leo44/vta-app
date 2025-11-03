<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlightJustification extends Model
{
    /** @use HasFactory<\Database\Factories\FlightJustificationFactory> */
    use HasFactory;

    protected $fillable = ['name'];
}
