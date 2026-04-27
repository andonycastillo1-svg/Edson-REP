<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\User;

class BodegaAccessService
{
    public function canView(User $user, int $bodegaId): bool
    {
        if ((int) $user->role_id === 1) {
            return true;
        }

        if (!$user->bodega_id) {
            return false;
        }

        if ((int) $user->bodega_id === $bodegaId) {
            return true;
        }

        return (int) $user->role_id === 2 && $this->isPrincipal((int) $user->bodega_id);
    }

    public function canModifyStock(User $user, int $bodegaId): bool
    {
        if ((int) $user->role_id === 1) {
            return true;
        }

        return (int) $user->role_id === 2
            && $user->bodega_id
            && (int) $user->bodega_id === $bodegaId;
    }

    public function canReceiveStock(User $user, int $bodegaId): bool
    {
        return $this->canModifyStock($user, $bodegaId);
    }

    public function visibleBodegaIds(User $user): ?array
    {
        if ((int) $user->role_id === 1) {
            return null;
        }

        if (!$user->bodega_id) {
            return [];
        }

        if ((int) $user->role_id === 2 && $this->isPrincipal((int) $user->bodega_id)) {
            return null;
        }

        return [(int) $user->bodega_id];
    }

    private function isPrincipal(int $bodegaId): bool
    {
        return Bodega::whereKey($bodegaId)->where('tipo', 'Principal')->exists();
    }
}
