<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_fiadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['contrato_id', 'pessoa_id']);
        });

        Schema::create('contrato_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->unsignedSmallInteger('prazo_meses')->nullable()->after('data_proximo_reajuste');
            $table->unsignedSmallInteger('carencia_meses')->nullable()->after('prazo_meses');
            $table->date('data_entrega_chaves')->nullable()->after('carencia_meses');
            $table->unsignedTinyInteger('reajuste_periodicidade_meses')->nullable()->default(12)->after('reajuste_indice');
            $table->decimal('desconto_mensal', 12, 2)->nullable()->after('valor_aluguel');
            $table->decimal('multa_atraso_percentual', 5, 2)->nullable()->after('desconto_mensal');
            $table->decimal('juros_mora_percentual_mes', 5, 2)->nullable()->after('multa_atraso_percentual');
            $table->boolean('repasse_automatico')->default(false)->after('juros_mora_percentual_mes');
            $table->foreignId('conta_cobranca_id')->nullable()->after('repasse_automatico')->constrained('financial_accounts')->nullOnDelete();
            $table->string('forma_pagamento_preferida', 30)->nullable()->after('conta_cobranca_id');
            $table->string('tipo_contrato', 30)->nullable()->after('forma_pagamento_preferida');
        });

        if (Schema::hasColumn('contratos', 'fiador_id')) {
            $fiadores = DB::table('contratos')
                ->select('id as contrato_id', 'fiador_id as pessoa_id')
                ->whereNotNull('fiador_id')
                ->get();

            if ($fiadores->isNotEmpty()) {
                DB::table('contrato_fiadores')->insert(
                    $fiadores->map(fn ($row) => [
                        'contrato_id' => $row->contrato_id,
                        'pessoa_id' => $row->pessoa_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            }

            Schema::table('contratos', function (Blueprint $table) {
                $table->dropForeign(['fiador_id']);
                $table->dropColumn('fiador_id');
            });
        }

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contratos MODIFY status ENUM('Ativo','Suspenso','Encerrado','Rescindido','EmAnalise') DEFAULT 'Ativo'");
            DB::statement("ALTER TABLE contratos MODIFY garantia_tipo ENUM('Fiador','Seguro','Caucao','SemGarantia') DEFAULT 'SemGarantia'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contratos MODIFY status ENUM('Ativo','Suspenso','Encerrado') DEFAULT 'Ativo'");
        }

        Schema::table('contratos', function (Blueprint $table) {
            if (Schema::hasColumn('contratos', 'conta_cobranca_id')) {
                $table->dropForeign(['conta_cobranca_id']);
            }

            $table->dropColumn([
                'prazo_meses',
                'carencia_meses',
                'data_entrega_chaves',
                'reajuste_periodicidade_meses',
                'desconto_mensal',
                'multa_atraso_percentual',
                'juros_mora_percentual_mes',
                'repasse_automatico',
                'conta_cobranca_id',
                'forma_pagamento_preferida',
                'tipo_contrato',
            ]);
        });

        Schema::table('contratos', function (Blueprint $table) {
            if (! Schema::hasColumn('contratos', 'fiador_id')) {
                $table->foreignId('fiador_id')->nullable()->after('locatario_id')->constrained('pessoas')->cascadeOnUpdate()->nullOnDelete();
            }
        });

        $fiadoresAgrupados = DB::table('contrato_fiadores')
            ->select('contrato_id', DB::raw('MIN(pessoa_id) as pessoa_id'))
            ->groupBy('contrato_id')
            ->get();

        foreach ($fiadoresAgrupados as $row) {
            DB::table('contratos')->where('id', $row->contrato_id)->update(['fiador_id' => $row->pessoa_id]);
        }

        Schema::dropIfExists('contrato_anexos');
        Schema::dropIfExists('contrato_fiadores');
    }
};
