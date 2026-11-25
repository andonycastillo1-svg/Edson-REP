<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = 'regiones';

    protected $fillable = ['nombre', 'activa'];

    public function coordinadoresAdicionales()
    {
        return $this->belongsToMany(User::class, 'region_coordinador_operativo')->withTimestamps();
    }

    public function coordinador()
    {
        return $this->belongsTo(User::class);
    }
}
