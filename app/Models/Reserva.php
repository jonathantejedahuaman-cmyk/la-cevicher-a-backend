<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'dni', 'telefono', 'email', 'fecha', 'hora', 'mesa', 'personas'];
}