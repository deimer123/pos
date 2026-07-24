<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Catalogo editable de planes/tarifas para que el super_admin (unico que
// puede crear empresas) pueda elegir el plan comercial de cada empresa
// nueva, con complementos y paquetes de usuarios adicionales, y que el
// sistema totalice el valor a cobrar. Se siembran los 3 planes iniciales
// (con precios ya duplicados respecto a la version publica anterior, a
// pedido del usuario), pero quedan editables/ampliables desde el admin.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedSmallInteger('meses');
            $table->decimal('precio', 15, 2);
            $table->unsignedSmallInteger('usuarios_incluidos')->default(1);
            $table->boolean('recomendado')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('complementos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->decimal('precio', 15, 2);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('paquetes_usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedSmallInteger('usuarios_adicionales');
            $table->decimal('precio', 15, 2);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('empresa_complementos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('complemento_id')->constrained('complementos')->cascadeOnDelete();
            // Precio al momento de aplicarlo -- si despues el super_admin
            // cambia el precio del complemento en el catalogo, no se altera
            // retroactivamente lo que ya se le cobro a empresas anteriores.
            $table->decimal('precio_aplicado', 15, 2);
            $table->timestamps();
            $table->unique(['empresa_id', 'complemento_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('plan_meses')->constrained('planes')->nullOnDelete();
            $table->foreignId('paquete_usuarios_id')->nullable()->after('plan_id')->constrained('paquetes_usuarios')->nullOnDelete();
            $table->decimal('valor_plan_total', 15, 2)->nullable()->after('paquete_usuarios_id');
        });

        DB::table('planes')->insert([
            ['nombre' => 'Plan Emprende', 'meses' => 3, 'precio' => 330000, 'usuarios_incluidos' => 1, 'recomendado' => false, 'activo' => true, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Plan Crece', 'meses' => 6, 'precio' => 600000, 'usuarios_incluidos' => 2, 'recomendado' => false, 'activo' => true, 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Plan Empresarial', 'meses' => 12, 'precio' => 1000000, 'usuarios_incluidos' => 3, 'recomendado' => true, 'activo' => true, 'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('complementos')->insert([
            ['nombre' => 'Facturación Electrónica (Activación)', 'precio' => 800000, 'activo' => true, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Renovación anual Facturación Electrónica', 'precio' => 800000, 'activo' => true, 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'POS Híbrido (por equipo)', 'precio' => 600000, 'activo' => true, 'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('paquetes_usuarios')->insert([
            ['nombre' => '1 usuario adicional', 'usuarios_adicionales' => 1, 'precio' => 160000, 'activo' => true, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Hasta 3 usuarios adicionales', 'usuarios_adicionales' => 3, 'precio' => 400000, 'activo' => true, 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Hasta 5 usuarios adicionales', 'usuarios_adicionales' => 5, 'precio' => 600000, 'activo' => true, 'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paquete_usuarios_id');
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('valor_plan_total');
        });

        Schema::dropIfExists('empresa_complementos');
        Schema::dropIfExists('paquetes_usuarios');
        Schema::dropIfExists('complementos');
        Schema::dropIfExists('planes');
    }
};
