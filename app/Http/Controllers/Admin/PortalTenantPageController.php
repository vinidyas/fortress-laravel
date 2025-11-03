<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalTenantPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->can('admin.access'), 403);

        return Inertia::render('Admin/Portal/Tenants');
    }
}
