<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserResource
 *
 * Exposé dans les listes admin.
 * N'expose jamais le mot de passe ni le remember_token.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'username'    => $this->username,
            'role'        => $this->getRoleNames()->first(),
            'function'    => $this->whenLoaded('currentFunction', fn () => [
                'value'      => $this->currentFunction?->function,
                'label'      => $this->currentFunction?->functionEnum()?->label(),
                'start_date' => $this->currentFunction?->start_date?->toDateString(),
            ]),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
