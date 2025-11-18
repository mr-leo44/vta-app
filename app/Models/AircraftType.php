<?php 

namespace App\Models;

use App\Models\Aircraft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AircraftType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sigle'];

    public function aircrafts(): HasMany
    {
        return $this->hasMany(Aircraft::class);
    }
}
