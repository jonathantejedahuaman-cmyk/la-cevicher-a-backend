<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Schema\Blueprint;

/*
|--------------------------------------------------------------------------
| API Routes - La Cevichería (Sistema Restaurante Full Stack)
|--------------------------------------------------------------------------
*/

// --- 1. REGISTRO Y LOGIN DE CLIENTES ---
Route::post('/registro', function (Request $request) {
    try {
        if (!Schema::hasTable('usuarios')) {
            Schema::create('usuarios', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->string('dni')->nullable();
                $table->string('telefono')->nullable();
                $table->string('direccion')->nullable();
                $table->boolean('bloqueado')->default(false);
                $table->timestamps();
            });
        }

        $colsVerificar = ['nombre', 'dni', 'telefono', 'direccion', 'bloqueado', 'created_at', 'updated_at'];
        foreach ($colsVerificar as $col) {
            if (!Schema::hasColumn('usuarios', $col)) {
                Schema::table('usuarios', function (Blueprint $table) use ($col) {
                    if ($col === 'bloqueado') {
                        $table->boolean('bloqueado')->default(false);
                    } elseif ($col === 'created_at' || $col === 'updated_at') {
                        $table->timestamp($col)->nullable();
                    } else {
                        $table->string($col)->nullable();
                    }
                });
            }
        }

        $correoLimpio = trim($request->email);
        $existe = DB::table('usuarios')->where('email', $correoLimpio)->first();
        if ($existe) {
            return response()->json(['error' => 'El correo ' . $correoLimpio . ' ya está registrado.'], 400);
        }

        $id = DB::table('usuarios')->insertGetId([
            'nombre'    => $request->nombre ?? 'Comensal',
            'email'     => $correoLimpio,
            'password'  => Hash::make($request->password),
            'dni'       => $request->dni ?? '',
            'telefono'  => $request->telefono ?? '',
            'direccion' => $request->direccion ?? 'San Miguel',
            'bloqueado' => 0,
            'created_at'=> now(),
            'updated_at'=> now()
        ]);

        $usuarioCreado = [
            'id' => $id, 
            'nombre' => $request->nombre, 
            'email' => $correoLimpio, 
            'dni' => $request->dni, 
            'telefono' => $request->telefono, 
            'direccion' => $request->direccion,
            'bloqueado' => 0
        ];

        return response()->json(['user' => $usuarioCreado, 'message' => 'Usuario registrado con éxito'], 200);

    } catch (\Throwable $e) {
        return response()->json(['error' => 'Error en base de datos: ' . $e->getMessage()], 500);
    }
});

Route::post('/login', function (Request $request) {
    try {
        if (!Schema::hasTable('usuarios')) return response()->json(['error' => 'No hay usuarios registrados'], 404);
        
        $user = DB::table('usuarios')->where('email', trim($request->email))->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        if (isset($user->bloqueado) && $user->bloqueado == 1) {
            return response()->json(['error' => 'Tu cuenta ha sido BLOQUEADA por exceso de reservas suspicaces. Contacta al 930482207.'], 403);
        }

        return response()->json(['user' => $user], 200);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Error al iniciar sesión: ' . $e->getMessage()], 500);
    }
});

// --- 2. PANEL CLIENTES ADMIN ---
Route::get('/usuarios-admin', function () {
    try {
        if (!Schema::hasTable('usuarios')) return response()->json([], 200);
        
        $usuarios = DB::table('usuarios')->get();
        $hoy = date('Y-m-d');

        $resultado = $usuarios->map(function ($u) use ($hoy) {
            $reservasHoy = Schema::hasTable('reservas') 
                ? DB::table('reservas')
                    ->where(function($q) use ($u) {
                        $q->where('correo', $u->email)->orWhere('email', $u->email);
                    })
                    ->where('fecha', '>=', $hoy)
                    ->count() 
                : 0;

            return [
                'id' => $u->id,
                'nombre' => $u->nombre ?? 'Sin nombre',
                'dni' => $u->dni ?? 'N/A',
                'email' => $u->email,
                'telefono' => $u->telefono ?? '',
                'direccion' => $u->direccion ?? 'No registrada',
                'reservas_hoy' => $reservasHoy,
                'bloqueado' => isset($u->bloqueado) ? (int)$u->bloqueado : 0
            ];
        });

        return response()->json($resultado, 200);
    } catch (\Throwable $e) {
        return response()->json([], 200);
    }
});

Route::post('/toggle-bloqueo-usuario', function (Request $request) {
    try {
        if (Schema::hasTable('usuarios') && Schema::hasColumn('usuarios', 'bloqueado')) {
            $nuevoEstado = $request->bloqueado ? 1 : 0;
            DB::table('usuarios')->where('id', $request->usuario_id)->update(['bloqueado' => $nuevoEstado]);
        }
        return response()->json(['status' => 'ok', 'bloqueado' => $request->bloqueado]);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Error al actualizar'], 500);
    }
});

// --- 3. RESERVAS ---
Route::get('/reservas', function (Request $request) {
    try {
        if (!Schema::hasTable('reservas')) return response()->json([], 200);
        return response()->json(DB::table('reservas')->orderBy('id', 'desc')->get(), 200);
    } catch (\Throwable $e) {
        return response()->json([], 200);
    }
});

Route::post('/reservas-segura', function (Request $request) {
    try {
        // Verificar primero si el local está cerrado por evento
        $localCerrado = Schema::hasTable('configuraciones') 
            ? DB::table('configuraciones')->where('clave', 'local_cerrado')->value('valor') 
            : '0';

        if ($localCerrado == '1') {
            return response()->json([
                'error' => '🔒 El local se encuentra cerrado por evento privado en este momento.'
            ], 400);
        }

        if (!Schema::hasTable('reservas')) {
            Schema::create('reservas', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->nullable();
                $table->string('dni')->nullable();
                $table->string('telefono')->nullable();
                $table->string('correo')->nullable();
                $table->string('email')->nullable();
                $table->string('mesa')->nullable();
                $table->string('hora')->nullable();
                $table->string('fecha')->nullable();
                $table->integer('personas')->default(2)->nullable();
                $table->text('notas')->nullable();
                $table->string('codigo')->nullable();
                $table->string('estado')->default('Reservado')->nullable();
                $table->timestamps();
            });
        }

        $correoCliente = $request->input('email', $request->input('correo', ''));
        $fechaReserva = $request->input('fecha', date('Y-m-d'));

        // Verificar si usuario está bloqueado
        $userDB = Schema::hasTable('usuarios') ? DB::table('usuarios')->where('email', $correoCliente)->first() : null;

        if ($userDB && isset($userDB->bloqueado) && $userDB->bloqueado == 1) {
            return response()->json([
                'error' => '🔒 Tu cuenta se encuentra BLOQUEADA. Comunícate al 📞 930482207 para solicitar verificación y desbloqueo.'
            ], 403);
        }

        // Control de límite de 3 reservas
        $reservasHoy = DB::table('reservas')
            ->where(function($q) use ($correoCliente) {
                $q->where('correo', $correoCliente)->orWhere('email', $correoCliente);
            })
            ->where('fecha', $fechaReserva)
            ->count();

        if ($reservasHoy >= 3 && (!$userDB || $userDB->bloqueado != 0)) {
            if ($userDB) {
                DB::table('usuarios')->where('id', $userDB->id)->update(['bloqueado' => 1]);
            }
            return response()->json([
                'error' => '⚠️ Has alcanzado el límite de 3 reservas. Para reservas adicionales, comunícate al 📞 930482207.'
            ], 400);
        }

        // Insertar Reserva
        $dataInsert = [
            'nombre'     => $request->input('nombre', 'Cliente'),
            'dni'        => $request->input('dni', '00000000'),
            'telefono'   => $request->input('telefono', ''),
            'correo'     => $correoCliente,
            'email'      => $correoCliente,
            'mesa'       => (string)$request->input('mesa', '1'),
            'hora'       => $request->input('hora', '12:00'),
            'fecha'      => $fechaReserva,
            'personas'   => (int)$request->input('personas', 2),
            'notas'      => $request->input('observaciones', $request->input('notas', '')),
            'codigo'     => $request->input('codigo', 'CEV-' . rand(1000, 9999)),
            'estado'     => 'Reservado',
            'created_at' => now(),
            'updated_at' => now()
        ];

        $colsExistentes = Schema::getColumnListing('reservas');
        $dataFinal = array_intersect_key($dataInsert, array_flip($colsExistentes));

        $id = DB::table('reservas')->insertGetId($dataFinal);
        $reservaGuardada = DB::table('reservas')->where('id', $id)->first();

        return response()->json(['message' => 'Reserva guardada con éxito', 'data' => $reservaGuardada], 200);

    } catch (\Throwable $e) {
        return response()->json(['error' => 'Error en base de datos: ' . $e->getMessage()], 500);
    }
});

Route::post('/reservas/liberar', function (Request $request) {
    try {
        $idReserva = $request->input('id');
        $codigo = $request->input('codigo');

        if (Schema::hasTable('reservas')) {
            $query = DB::table('reservas');
            if ($idReserva) {
                $query->where('id', $idReserva);
            } elseif ($codigo) {
                $query->where('codigo', $codigo);
            }
            $query->update(['estado' => 'Completada', 'updated_at' => now()]);
        }

        return response()->json(['status' => 'ok', 'message' => 'Reserva liberada exitosamente'], 200);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Error al liberar reserva: ' . $e->getMessage()], 500);
    }
});

// --- 4. ESTADO LOCAL ---
Route::get('/estado-local', function () {
    $cerrado = Schema::hasTable('configuraciones') ? DB::table('configuraciones')->where('clave', 'local_cerrado')->value('valor') : '0';
    return response()->json(['cerrado' => $cerrado == '1']);
});

Route::post('/estado-local', function (Request $request) {
    if (!Schema::hasTable('configuraciones')) {
        Schema::create('configuraciones', function (Blueprint $table) { 
            $table->string('clave')->primary(); 
            $table->text('valor'); 
        });
    }
    DB::table('configuraciones')->updateOrInsert(['clave' => 'local_cerrado'], ['valor' => $request->cerrado ? '1' : '0']);
    return response()->json(['cerrado' => (bool)$request->cerrado]);
});

Route::post('/toggle-cierre-local', function (Request $request) {
    if (!Schema::hasTable('configuraciones')) {
        Schema::create('configuraciones', function (Blueprint $table) { 
            $table->string('clave')->primary(); 
            $table->text('valor'); 
        });
    }
    DB::table('configuraciones')->updateOrInsert(['clave' => 'local_cerrado'], ['valor' => $request->cerrado ? '1' : '0']);
    return response()->json(['cerrado' => (bool)$request->cerrado]);
});

// --- 5. MESAS DEL SALÓN ---
Route::get('/mesas-bd', function () {
    try {
        if (!Schema::hasTable('mesas')) {
            Schema::create('mesas', function (Blueprint $table) {
                $table->id();
                $table->string('numero')->unique();
                $table->integer('capacidad')->default(4);
                $table->string('zona')->default('Salón Principal');
                $table->string('estado')->default('Activa');
                $table->timestamps();
            });

            $mesasIniciales = [
                ['numero' => '1', 'capacidad' => 4, 'zona' => 'Zona Ventana', 'estado' => 'Activa', 'created_at' => now(), 'updated_at' => now()],
                ['numero' => '2', 'capacidad' => 2, 'zona' => 'Salón Principal', 'estado' => 'Activa', 'created_at' => now(), 'updated_at' => now()],
                ['numero' => '3', 'capacidad' => 6, 'zona' => 'Zona Familiar', 'estado' => 'Activa', 'created_at' => now(), 'updated_at' => now()],
                ['numero' => '4', 'capacidad' => 4, 'zona' => 'Salón Principal', 'estado' => 'Activa', 'created_at' => now(), 'updated_at' => now()],
                ['numero' => '5', 'capacidad' => 8, 'zona' => 'Zona Terraza / VIP', 'estado' => 'Activa', 'created_at' => now(), 'updated_at' => now()],
                ['numero' => '6', 'capacidad' => 2, 'zona' => 'Zona Ventana', 'estado' => 'Activa', 'created_at' => now(), 'updated_at' => now()],
                ['numero' => '7', 'capacidad' => 4, 'zona' => 'Zona Terraza / VIP', 'estado' => 'Activa', 'created_at' => now(), 'updated_at' => now()],
                ['numero' => '8', 'capacidad' => 4, 'zona' => 'Zona Familiar', 'estado' => 'Activa', 'created_at' => now(), 'updated_at' => now()],
            ];
            DB::table('mesas')->insert($mesasIniciales);
        }

        return response()->json(DB::table('mesas')->get(), 200);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Error al consultar mesas: ' . $e->getMessage()], 500);
    }
});

Route::post('/mesas-bd', function (Request $request) {
    try {
        $numero = strtoupper(trim($request->input('numero')));

        $existe = DB::table('mesas')->where('numero', $numero)->first();
        if ($existe) {
            return response()->json(['error' => "La mesa '{$numero}' ya existe."], 400);
        }

        $id = DB::table('mesas')->insertGetId([
            'numero'    => $numero,
            'capacidad' => (int)$request->input('capacidad', 4),
            'zona'      => $request->input('zona', 'Salón Principal'),
            'estado'    => 'Activa',
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        return response()->json(DB::table('mesas')->where('id', $id)->first(), 200);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Error al guardar mesa: ' . $e->getMessage()], 500);
    }
});

Route::post('/mesas-mantenimiento', function (Request $request) {
    try {
        $id = $request->input('id');
        $numero = $request->input('numero');

        $query = DB::table('mesas');
        if ($id) {
            $query->where('id', $id);
        } else {
            $query->where('numero', $numero);
        }

        $mesa = $query->first();
        $nuevoEstado = ($mesa && $mesa->estado === 'Mantenimiento') ? 'Activa' : 'Mantenimiento';

        $query->update(['estado' => $nuevoEstado, 'updated_at' => now()]);

        return response()->json(['status' => 'ok', 'estado' => $nuevoEstado], 200);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Error al actualizar estado'], 500);
    }
});

// --- 6. PEDIDOS DELIVERY ---
Route::get('/pedidos', function (Request $request) {
    try {
        if (!Schema::hasTable('pedidos')) return response()->json([], 200);
        return response()->json(DB::table('pedidos')->get(), 200);
    } catch (\Throwable $e) {
        return response()->json([], 200);
    }
});

Route::post('/pedidos', function (Request $request) {
    try {
        if (!Schema::hasTable('pedidos')) {
            Schema::create('pedidos', function (Blueprint $table) {
                $table->id();
                $table->string('cliente')->nullable();
                $table->string('telefono')->nullable();
                $table->string('direccion')->nullable();
                $table->string('metodo_pago')->nullable();
                $table->decimal('total', 10, 2)->default(0);
                $table->string('fecha')->nullable();
                $table->timestamps();
            });
        }

        $id = DB::table('pedidos')->insertGetId([
            'cliente'     => $request->input('cliente', 'Comensal General'),
            'telefono'    => $request->input('telefono', '000000000'),
            'direccion'   => $request->input('direccion', 'Entrega en Salón'),
            'metodo_pago' => $request->input('metodo_pago', 'efectivo'),
            'total'       => $request->input('total', 0),
            'fecha'       => $request->input('fecha', date('Y-m-d')),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(DB::table('pedidos')->where('id', $id)->first(), 200);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'ok'], 200);
    }
});