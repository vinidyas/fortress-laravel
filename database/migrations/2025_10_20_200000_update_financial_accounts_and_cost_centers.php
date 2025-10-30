<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->string('apelido', 60)->nullable()->after('nome');
            $table->string('instituicao', 120)->nullable()->after('tipo');
            $table->string('carteira', 20)->nullable()->after('numero');
            $table->char('moeda', 3)->default('BRL')->after('carteira');
            $table->date('data_saldo_inicial')->nullable()->after('saldo_inicial');
            $table->decimal('saldo_atual', 15, 2)->default(0)->after('saldo_inicial');
            $table->decimal('limite_credito', 15, 2)->nullable()->after('saldo_atual');
            $table->enum('categoria', ['operacional', 'reserva', 'investimento'])->default('operacional')->after('limite_credito');
            $table->boolean('permite_transf')->default(true)->after('categoria');
            $table->boolean('padrao_recebimento')->default(false)->after('permite_transf');
            $table->boolean('padrao_pagamento')->default(false)->after('padrao_recebimento');
            $table->json('integra_config')->nullable()->after('padrao_pagamento');
            $table->text('observacoes')->nullable()->after('integra_config');
            $table->softDeletes();
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `financial_accounts` MODIFY `tipo` ENUM('conta_corrente','poupanca','investimento','caixa','outro') NOT NULL DEFAULT 'conta_corrente'");
        } elseif ($driver === 'sqlite') {
            Schema::create('financial_accounts_tmp', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 120);
                $table->string('apelido', 60)->nullable();
                $table->enum('tipo', ['conta_corrente', 'poupanca', 'investimento', 'caixa', 'outro'])->default('conta_corrente');
                $table->string('instituicao', 120)->nullable();
                $table->string('banco', 120)->nullable();
                $table->string('agencia', 20)->nullable();
                $table->string('numero', 40)->nullable();
                $table->string('carteira', 20)->nullable();
                $table->char('moeda', 3)->default('BRL');
                $table->decimal('saldo_inicial', 15, 2)->default(0);
                $table->date('data_saldo_inicial')->nullable();
                $table->decimal('saldo_atual', 15, 2)->default(0);
                $table->decimal('limite_credito', 15, 2)->nullable();
                $table->enum('categoria', ['operacional', 'reserva', 'investimento'])->default('operacional');
                $table->boolean('permite_transf')->default(true);
                $table->boolean('padrao_recebimento')->default(false);
                $table->boolean('padrao_pagamento')->default(false);
                $table->json('integra_config')->nullable();
                $table->text('observacoes')->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });

            $columns = [
                'id',
                'nome',
                'apelido',
                'tipo',
                'instituicao',
                'banco',
                'agencia',
                'numero',
                'carteira',
                'moeda',
                'saldo_inicial',
                'data_saldo_inicial',
                'saldo_atual',
                'limite_credito',
                'categoria',
                'permite_transf',
                'padrao_recebimento',
                'padrao_pagamento',
                'integra_config',
                'observacoes',
                'ativo',
                'created_at',
                'updated_at',
                'deleted_at',
            ];

            $columnList = implode(', ', $columns);
            DB::statement("INSERT INTO financial_accounts_tmp ({$columnList}) SELECT {$columnList} FROM financial_accounts");

            Schema::drop('financial_accounts');
            Schema::rename('financial_accounts_tmp', 'financial_accounts');
        }

        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->index(['categoria', 'ativo']);
            $table->index('padrao_recebimento');
            $table->index('padrao_pagamento');
        });

        DB::table('financial_accounts')->update([
            'saldo_atual' => DB::raw('saldo_inicial'),
        ]);

        Schema::table('cost_centers', function (Blueprint $table) {
            $table->enum('tipo', ['fixo', 'variavel', 'investimento'])->default('variavel')->after('descricao');
            $table->boolean('ativo')->default(true)->after('tipo');
            $table->decimal('orcamento_anual', 15, 2)->nullable()->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropIndex(['categoria', 'ativo']);
            $table->dropIndex(['padrao_recebimento']);
            $table->dropIndex(['padrao_pagamento']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `financial_accounts` MODIFY `tipo` ENUM('conta_corrente','caixa','outro') NOT NULL DEFAULT 'conta_corrente'");
        }

        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'apelido',
                'instituicao',
                'carteira',
                'moeda',
                'data_saldo_inicial',
                'saldo_atual',
                'limite_credito',
                'categoria',
                'permite_transf',
                'padrao_recebimento',
                'padrao_pagamento',
                'integra_config',
                'observacoes',
            ]);

            $table->dropSoftDeletes();
        });

        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropColumn([
                'tipo',
                'ativo',
                'orcamento_anual',
            ]);
        });
    }
};
