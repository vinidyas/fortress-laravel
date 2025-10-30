<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class);

$imovel = App\Models\Imovel::with('contratos')->first();
if ($imovel && $imovel->contratos()->exists()) {
    try {
        $imovel->delete();
        echo "deleted";
    } catch (Throwable $e) {
        echo get_class($e)." :: ".$e->getCode();
    }
} else {
    echo "no-related";
}
