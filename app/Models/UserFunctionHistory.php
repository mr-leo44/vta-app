<?php

namespace App\Models;

use App\Enums\UserFunction;
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
            'start_date' => 'date',
            'end_date'   => 'date',
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
    // Helpers
    // ───────────────────────────────────────────────────────────  ──────────

    public function functionEnum(): ?UserFunction
    {
        return UserFunction::tryFrom($this->function);
    }

    public function isActive(): bool
    {
        return $this->end_date === null;
    }
}
