<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Aísla los registros por cuenta: cada usuario solo ve y crea datos propios.
 * Fuera de una sesión autenticada (consola, seeders) no se filtra.
 */
trait BelongsToOwner
{
    protected static function bootBelongsToOwner(): void
    {
        static::addGlobalScope('owner', function (Builder $query): void {
            if (Auth::hasUser()) {
                $query->where($query->getModel()->getTable().'.owner_id', Auth::id());
            }
        });

        static::creating(function ($model): void {
            if (empty($model->owner_id) && Auth::hasUser()) {
                $model->owner_id = Auth::id();
            }
        });
    }
}
