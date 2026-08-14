<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Reserva;
use Illuminate\Support\Facades\Schedule;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Tarea diaria para finalizar reservas pasadas
Schedule::call(function () {
    Reserva::where('fecha', '<', Carbon::today())
           ->where('estado', 'pendiente')
           ->update(['estado' => 'finalizado']);
})->daily();