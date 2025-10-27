<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AircraftType extends Model
{
    protected $fillable = ['name', 'sigle'];

    public function aircrafts(): HasMany
    {
        return $this->hasMany(Aircraft::class);
    }
}
