<?php

namespace App\Models;

use App\Enums\UserFunction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFunctionHistory extends Model
{
    protected $fillable = [
        'user_id',
        'function',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date'   => 'date:Y-m-d',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    /** Filtre les fonctions actuellement actives (end_date IS NULL). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('end_date');
    }

    /** Filtre les fonctions terminées. */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('end_date');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    public function functionEnum(): ?UserFunction
    {
        return UserFunction::tryFrom($this->function);
    }

    public function isActive(): bool
    {
        return $this->end_date === null;
    }
}
