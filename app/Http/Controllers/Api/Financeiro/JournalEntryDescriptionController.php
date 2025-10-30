<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\JournalEntryDescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryDescriptionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);

        $limit = min(max($request->integer('limit', 10), 1), 25);
        $search = trim((string) $request->string('search'));

        $descriptions = JournalEntryDescription::query()
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.str_replace('%', '', $search).'%';
                $query->where('texto', 'like', $term);
            })
            ->orderByDesc('uso_total')
            ->orderByDesc('ultima_utilizacao')
            ->limit($limit)
            ->get(['id', 'texto', 'uso_total', 'ultima_utilizacao']);

        return response()->json([
            'data' => $descriptions->map(fn (JournalEntryDescription $description) => [
                'id' => $description->id,
                'texto' => $description->texto,
                'uso_total' => $description->uso_total,
                'ultima_utilizacao' => $description->ultima_utilizacao?->toIso8601String(),
            ]),
        ]);
    }
}
