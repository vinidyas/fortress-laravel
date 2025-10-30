<?php

namespace App\Domain\Financeiro\Actions;

use App\Models\JournalEntryDescription;
use Illuminate\Support\Str;

class DescriptionResolver
{
    public function resolve(?string $text): ?JournalEntryDescription
    {
        $normalized = $this->normalize($text);

        if ($normalized === null) {
            return null;
        }

        /** @var JournalEntryDescription|null $existing */
        $existing = JournalEntryDescription::query()->where('texto', $normalized)->first();

        if ($existing) {
            $existing->incrementEach(['uso_total' => 1], ['ultima_utilizacao' => now()]);

            return $existing;
        }

        return JournalEntryDescription::create([
            'texto' => $normalized,
            'uso_total' => 1,
            'ultima_utilizacao' => now(),
        ]);
    }

    private function normalize(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $trimmed = trim($text);

        if ($trimmed === '') {
            return null;
        }

        return Str::ascii(Str::lower($trimmed));
    }
}
