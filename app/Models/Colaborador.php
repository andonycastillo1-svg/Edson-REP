<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Colaborador extends Model
{
    protected $table = 'colaboradores';

    // PK = codigo
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'int'; // si codigo es INT

    protected $fillable = [
        'codigo',
        'nombre',
        'puesto',
        'estado',
        'created_at',
        'updated_at',
    ];
}