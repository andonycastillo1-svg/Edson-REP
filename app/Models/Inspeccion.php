<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inspeccion extends Model
{
    protected $table = 'inspecciones';

    protected $fillable = ['supervisor_id', 'coordinador_id', 'region_id', 'colaborador_codigo', 'puesto', 'fecha', 'solicitud_id', 'observaciones', 'captura_regional'];

    protected $casts = ['fecha' => 'date'];

    public function supervisor() { return $this->belongsTo(User::class); }
    public function coordinador() { return $this->belongsTo(User::class); }
    public function region() { return $this->belongsTo(Region::class); }
    public function colaborador() { return $this->belongsTo(Colaborador::class, 'colaborador_codigo'); }
    public function consolidados() { return $this->belongsToMany(Consolidado::class, 'consolidado_inspeccion'); }

    public function lineas()
    {
        return $this->hasMany(InspeccionLinea::class);
    }

    public function fotos()
    {
        return $this->hasMany(FotoInspeccion::class);
    }
}
