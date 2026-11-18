<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = ['nombre', 'descripcion', 'ambito_operativo', 'activo'];

    protected $casts = ['activo' => 'boolean', 'ambito_operativo' => 'integer'];

    public function ambito(): int
    {
        return in_array((int) $this->id, [1, 2, 3, 4], true) ? (int) $this->id : (int) $this->ambito_operativo;
    }

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    public function reglasSistema()
    {
        return $this->hasMany(\App\Sistema\ReglaRol::class);
    }
}
