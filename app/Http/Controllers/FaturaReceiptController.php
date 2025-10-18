<?php

namespace App\Http\Controllers;

use App\Models\Fatura;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class FaturaReceiptController extends Controller
{
    public function __invoke(Fatura $fatura): View
    {
        Gate::authorize('view', $fatura);

        $fatura->load(['contrato.locatario', 'contrato.locador', 'contrato.imovel', 'itens']);

        $logoPath = base_path('docs/identidade-visual-fortress_3.jpg');
        $logoBase64 = null;
        if (File::exists($logoPath)) {
            $logoBase64 = 'data:image/jpeg;base64,' . base64_encode(File::get($logoPath));
        }

        return view('receipts.fatura', [
            'fatura' => $fatura,
            'logoBase64' => $logoBase64,
            'company' => [
                'name' => 'Fortress Empreendimentos',
                'phone' => 'Tel: (11) 97279-4688 ou (11) 99388-2274',
                'email' => 'contato@fortressempreendimentos.com.br ; atendimento@fortressempreendimentos.com.br',
            ],
        ]);
    }
}
