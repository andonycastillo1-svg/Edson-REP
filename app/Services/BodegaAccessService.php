<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BodegaAccessService
{
    public function canView(User $user, int $bodegaId): bool
    {
        if ($user->rolOperativo() === 1) {
            return true;
        }

        if (!$user->bodega_id) {
            return false;
        }

        if ((int) $user->bodega_id === $bodegaId) {
            return true;
        }

        return $user->rolOperativo() === 2 && $this->isPrincipal((int) $user->bodega_id);
    }

    public function canModifyStock(User $user, int $bodegaId): bool
    {
        if ($user->rolOperativo() === 1) {
            return true;
        }

        return $user->rolOperativo() === 2
            && $user->bodega_id
            && (int) $user->bodega_id === $bodegaId;
    }

    public function canReceiveStock(User $user, int $bodegaId): bool
    {
        return $this->canModifyStock($user, $bodegaId);
    }

    public function canViewCompras(User $user): bool
    {
        if ($user->rolOperativo() === 1) {
            return true;
        }

        $principal = $this->principal()?->id;

        return $principal !== null && $this->canView($user, (int) $principal);
    }

    public function visibleBodegaIds(User $user): ?array
    {
        if ($user->rolOperativo() === 1) {
            return null;
        }

        if (!$user->bodega_id) {
            return [];
        }

        if ($user->rolOperativo() === 2 && $this->isPrincipal((int) $user->bodega_id)) {
            return null;
        }

        return [(int) $user->bodega_id];
    }

    public function principal(): ?Bodega
    {
        $id = app(\App\Sistema\Ajustes::class)->general('bodega_principal');
        if ($id !== null) {
            return Bodega::find($id);
        }

        return Bodega::where('tipo', 'Principal')->orderBy('id')->first();
    }

    public function tieneHistorialOperativo(): bool
    {
        return DB::table('compras')->exists() || DB::table('movimientos')->exists() || DB::table('operaciones')->exists();
    }

    private function isPrincipal(int $bodegaId): bool
    {
        $configurada = app(\App\Sistema\Ajustes::class)->general('bodega_principal');

        return $configurada === null ? Bodega::whereKey($bodegaId)->where('tipo', 'Principal')->exists() : (int) $configurada === $bodegaId;
    }
}
