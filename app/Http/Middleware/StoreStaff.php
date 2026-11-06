<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The signed-in user must work for THIS store (the one from the host name), and hold the
 * permission when one is given: "store.staff:orders.manage". Super admins may enter any
 * store's dashboard for support.
 */
class StoreStaff
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user('web');

        if (! $user) {
            return redirect()->guest(route('admin.login'));
        }

        $store = $this->tenant->store();

        if ($user->is_super_admin) {
            return $next($request);
        }

        if (! $user->membershipFor($store)) {
            abort(403, 'You do not have access to this store.');
        }

        if ($permission && ! $user->canInStore($store, $permission)) {
            abort(403, 'Your role does not allow this.');
        }

        return $next($request);
    }
}
