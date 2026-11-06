<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Tenancy\TenantContext;

abstract class Controller
{
    protected function currentStore(): Store
    {
        return app(TenantContext::class)->store();
    }
}
