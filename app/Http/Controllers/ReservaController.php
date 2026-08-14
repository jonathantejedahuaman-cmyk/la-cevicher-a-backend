<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReservaController extends Controller
{
    public function index()
    {
        try {
            if (Schema::hasTable('reservas')) {
                $reservas = DB::table('reservas')->get();
                return response()->json($reservas, 200);
            }
        } catch (\Throwable $e) {
            // Previene caídas de la API
        }

        return response()->json([], 200);
    }

    public function store(Request $request)
    {
        try {
            if (Schema::hasTable('reservas')) {
                $id = DB::table('reservas')->insertGetId([
                    'nombre' => $request->input('nombre', 'Cliente'),
                    'dni' => $request->input('dni', '00000000'),
                    'mesa' => $request->input('mesa', '1'),
                    'hora' => $request->input('hora', '12:00'),
                    'fecha' => $request->input('fecha', date('Y-m-d')),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return response()->json(['status' => 'ok', 'id' => $id], 200);
            }
        } catch (\Throwable $e) {
            // Previene caídas de la API
        }

        return response()->json(['status' => 'ok'], 200);
    }

    public function destroy($id)
    {
        try {
            if (Schema::hasTable('reservas')) {
                DB::table('reservas')->where('id', $id)->delete();
            }
        } catch (\Throwable $e) {
            // Previene caídas de la API
        }

        return response()->json(['status' => 'ok'], 200);
    }
}