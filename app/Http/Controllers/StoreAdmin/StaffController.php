<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StoreStaff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Staff accounts and roles, all inside this store only. */
class StaffController extends Controller
{
    public function index()
    {
        $store = $this->currentStore();

        return view('admin.staff', [
            'staff' => StoreStaff::with('user', 'role')->get(),
            'roles' => Role::where('store_id', $store->id)->with('permissions')->orderBy('id')->get(),
            'permissions' => Permission::all()->groupBy('group'),
            'tokens' => auth()->user()->tokens()->where('name', 'like', 'store:'.$store->id.':%')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $store = $this->currentStore();

        if (($limit = $store->plan?->max_staff) && StoreStaff::count() >= $limit) {
            return back()->with('error', "Your plan allows {$limit} staff accounts.");
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:150',
            'password' => ['required', Password::min(10)],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('store_id', $store->id)],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if ($user && StoreStaff::where('user_id', $user->id)->exists()) {
            return back()->with('error', 'That person already works for this store.');
        }

        // An existing account (for example someone who also works for another store) keeps its own password.
        $user ??= User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password']]);

        StoreStaff::create(['user_id' => $user->id, 'role_id' => $data['role_id'], 'status' => 'active']);
        ActivityLog::record('staff.added', $user, ['role' => Role::find($data['role_id'])->slug]);

        return back()->with('status', $user->name.' can now sign in at '.$store->url('/admin').'.');
    }

    public function update(Request $request, StoreStaff $staff)
    {
        $data = $request->validate([
            'role_id' => ['required', Rule::exists('roles', 'id')->where('store_id', $this->currentStore()->id)],
            'status' => 'required|in:active,disabled',
        ]);

        if ($staff->user_id === $this->currentStore()->owner_id) {
            return back()->with('error', 'The store owner always keeps full access.');
        }

        $staff->update($data);
        ActivityLog::record('staff.updated', $staff, $data);

        return back()->with('status', 'Staff member updated.');
    }

    public function destroy(StoreStaff $staff)
    {
        if ($staff->user_id === $this->currentStore()->owner_id || $staff->user_id === auth()->id()) {
            return back()->with('error', 'You cannot remove the owner or yourself.');
        }

        $staff->delete();
        ActivityLog::record('staff.removed', $staff);

        return back()->with('status', 'Staff member removed from this store.');
    }

    /** A personal API token for /api/v1, limited to this store. The plain token is shown once. */
    public function createToken(Request $request)
    {
        $store = $this->currentStore();
        abort_unless($store->hasFeature('api'), 403, 'API access is not part of your plan.');
        $data = $request->validate(['label' => 'required|string|max:60']);

        $token = $request->user()->createToken('store:'.$store->id.':'.$data['label'], ['orders:read']);
        ActivityLog::record('api.token_created', null, ['label' => $data['label']]);

        return back()->with('token', $token->plainTextToken);
    }

    public function revokeToken(Request $request, int $token)
    {
        $request->user()->tokens()->where('id', $token)->where('name', 'like', 'store:'.$this->currentStore()->id.':%')->delete();

        return back()->with('status', 'Token revoked.');
    }

    public function storeRole(Request $request)
    {
        $store = $this->currentStore();
        $data = $request->validate(['name' => 'required|string|max:60', 'permissions' => 'array', 'permissions.*' => 'exists:permissions,id']);

        $role = Role::create(['store_id' => $store->id, 'name' => $data['name'], 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4))]);
        $role->permissions()->sync($data['permissions'] ?? []);

        return back()->with('status', 'Role created.');
    }

    public function updateRole(Request $request, Role $role)
    {
        abort_unless($role->store_id === $this->currentStore()->id, 404);

        if ($role->slug === 'owner') {
            return back()->with('error', 'The owner role always has every permission.');
        }

        $data = $request->validate(['permissions' => 'array', 'permissions.*' => 'exists:permissions,id']);
        $role->permissions()->sync($data['permissions'] ?? []);
        ActivityLog::record('role.updated', $role);

        return back()->with('status', $role->name.' permissions saved.');
    }
}
