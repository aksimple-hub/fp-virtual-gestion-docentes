<?php

use App\Models\Usuario;
use App\Models\Centro;
use App\Models\Docente;
use App\Models\CentroDocente;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================
// BLOQUE 1: Búsqueda de docente (endpoint AJAX)
// GET /comprobar-docente/{dni}
// ============================================================

/** 7. AJAX - DNI existente devuelve datos correctos */
test('GET /comprobar-docente devuelve nombre, apellido y email si el docente existe', function () {
    $centro = Centro::forceCreate(['id_centro' => 'U100', 'nombre' => 'Centro Update Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'U100']);

    Docente::forceCreate([
        'dni'          => '22222222J',
        'nombre'       => 'Laura',
        'apellido'     => 'Sánchez Ramos',
        'email_virtual' => 'laura@centro.com',
    ]);

    // Registrar también en centro_docente para que devuelva el email del centro
    CentroDocente::forceCreate([
        'dni'       => '22222222J',
        'id_centro' => 'U100',
        'email'     => 'laura@centro.com',
    ]);

    $response = $this->actingAs($usuario)->getJson('/comprobar-docente/22222222J');

    $response->assertStatus(200)
             ->assertJsonFragment([
                 'existe'   => true,
                 'nombre'   => 'Laura',
                 'apellido' => 'Sánchez Ramos',
                 'email'    => 'laura@centro.com',
             ]);
});

/** 8. AJAX - DNI inexistente devuelve existe: false */
test('GET /comprobar-docente devuelve existe false si el DNI no está registrado', function () {
    $centro = Centro::forceCreate(['id_centro' => 'U101', 'nombre' => 'Centro Update Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'U101']);

    $response = $this->actingAs($usuario)->getJson('/comprobar-docente/99988877X');

    $response->assertStatus(200)
             ->assertJsonFragment(['existe' => false]);
});

// ============================================================
// BLOQUE 2: Actualización de correo (Upsert via store)
// POST /alta-docente
// ============================================================

/** 9. UPSERT - Un DNI ya registrado no provoca error de validación */
test('el store no devuelve error de validación cuando el DNI ya existe (upsert)', function () {
    $centro = Centro::forceCreate(['id_centro' => 'U200', 'nombre' => 'Centro Update Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'U200']);

    Docente::forceCreate([
        'dni'           => '33333333P',
        'nombre'        => 'Pedro',
        'apellido'      => 'García López',
        'email_virtual' => 'pedro@antiguo.com',
    ]);

    $datos = [
        'dni'      => '33333333P',
        'nombre'   => 'Pedro',
        'apellido' => 'García López',
        'email'    => 'pedro@nuevo.com',
        'id_centro' => 'U200',
    ];

    $response = $this->actingAs($usuario)->post('/alta-docente', $datos);

    // No debe haber errores de sesión por DNI duplicado
    $response->assertSessionDoesntHaveErrors(['dni']);
});

/** 10. UPSERT - El email se actualiza correctamente en la base de datos */
test('el store actualiza el email_virtual del docente cuando ya existe en BD', function () {
    $centro = Centro::forceCreate(['id_centro' => 'U300', 'nombre' => 'Centro Update Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'U300']);

    Docente::forceCreate([
        'dni'           => '44444444W',
        'nombre'        => 'Ana',
        'apellido'      => 'Martínez Gil',
        'email_virtual' => 'ana@viejo.com',
    ]);

    $datos = [
        'dni'      => '44444444W',
        'nombre'   => 'Ana',
        'apellido' => 'Martínez Gil',
        'email'    => 'ana@nuevo.com',
        'id_centro' => 'U300',
    ];

    $this->actingAs($usuario)->post('/alta-docente', $datos);

    $this->assertDatabaseHas('docentes', [
        'dni'           => '44444444W',
        'email_virtual' => 'ana@nuevo.com',
    ]);
});

// ============================================================
// BLOQUE 3: Integridad de datos
// ============================================================

/** 11. INTEGRIDAD - Actualizar un docente existente no duplica registros */
test('actualizar un docente existente no incrementa el número de docentes en BD', function () {
    $centro = Centro::forceCreate(['id_centro' => 'U400', 'nombre' => 'Centro Update Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'U400']);

    Docente::forceCreate([
        'dni'           => '55555555F',
        'nombre'        => 'Carlos',
        'apellido'      => 'Ruiz Mena',
        'email_virtual' => 'carlos@viejo.com',
    ]);

    $totalAntes = Docente::count();

    $datos = [
        'dni'      => '55555555F',
        'nombre'   => 'Carlos',
        'apellido' => 'Ruiz Mena',
        'email'    => 'carlos@nuevo.com',
        'id_centro' => 'U400',
    ];

    $this->actingAs($usuario)->post('/alta-docente', $datos);

    expect(Docente::count())->toBe($totalAntes);
});
