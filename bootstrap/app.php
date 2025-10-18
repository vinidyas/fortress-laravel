<?php

declare(strict_types=1);

use App\Console\Commands\GenerateInvoices;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        ]);
    })
    ->withCommands([
        GenerateInvoices::class,
        \App\Console\Commands\ImportLegacyData::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        // Geracao automatica de faturas congelada; nenhuma tarefa agendada aqui no momento.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
