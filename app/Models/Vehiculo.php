<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehiculo extends Model
{
    protected $table = 'vehiculos';

    protected $primaryKey = 'vin';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'vin',
        'placa',
        'marca',
        'modelo',
        'estado'
    ];
}
