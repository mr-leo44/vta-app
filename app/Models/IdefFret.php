<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IdefFret extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'usd',
        'cdf',
    ];
}
