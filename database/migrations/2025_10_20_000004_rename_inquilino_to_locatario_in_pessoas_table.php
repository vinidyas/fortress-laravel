<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('pessoas')
            ->whereJsonContains('papeis', 'Inquilino')
            ->orderBy('id')
            ->chunkById(100, function ($pessoas) {
                foreach ($pessoas as $pessoa) {
                    $papeis = $this->decodePapeis($pessoa->papeis);
                    if ($papeis === null) {
                        continue;
                    }

                    $updated = collect($papeis)
                        ->map(fn ($papel) => $papel === 'Inquilino' ? 'Locatario' : $papel)
                        ->unique()
                        ->values()
                        ->all();

                    DB::table('pessoas')
                        ->where('id', $pessoa->id)
                        ->update(['papeis' => json_encode($updated, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('pessoas')
            ->whereJsonContains('papeis', 'Locatario')
            ->orderBy('id')
            ->chunkById(100, function ($pessoas) {
                foreach ($pessoas as $pessoa) {
                    $papeis = $this->decodePapeis($pessoa->papeis);
                    if ($papeis === null) {
                        continue;
                    }

                    $updated = collect($papeis)
                        ->map(fn ($papel) => $papel === 'Locatario' ? 'Inquilino' : $papel)
                        ->unique()
                        ->values()
                        ->all();

                    DB::table('pessoas')
                        ->where('id', $pessoa->id)
                        ->update(['papeis' => json_encode($updated, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>|null
     */
    private function decodePapeis(mixed $value): ?array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
        } elseif (is_array($value)) {
            $decoded = $value;
        } else {
            $decoded = null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        return array_values(array_filter($decoded, fn ($papel) => is_string($papel) && $papel !== ''));
    }
};
