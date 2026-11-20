<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntregaInventario extends Model
{
    public const MEDIOS = [
        'presencial' => 'Presencial',
        'telefono' => 'Teléfono',
        'whatsapp' => 'WhatsApp',
        'correo' => 'Correo',
        'otro' => 'Otro medio',
    ];

    protected $table = 'entregas_inventario';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'modalidad', 'tercero', 'salida_en', 'recibida_en', 'confirmada_en',
        'confirmada_por', 'medio_confirmacion',
    ];

    protected $casts = [
        'salida_en' => 'datetime',
        'recibida_en' => 'datetime',
        'confirmada_en' => 'datetime',
    ];

    public function asignaciones()
    {
        return $this->hasMany(AsignacionInventario::class, 'grupo_asignacion');
    }

    public function documentos()
    {
        return $this->hasMany(AsignacionInventarioArchivo::class, 'grupo_entrega');
    }

    public function confirmador()
    {
        return $this->belongsTo(User::class, 'confirmada_por');
    }

    public function faltaHojaFirmada(): bool
    {
        return !$this->documentos->contains('tipo_documento', 'asignacion_firmada');
    }
}
