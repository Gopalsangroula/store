@extends('admin.layout', ['title' => 'Staff & roles'])

@section('content')
<div class="page-head"><div><h1>Staff & roles</h1><div class="sub">Each person only sees this store, and only what their role allows.</div></div></div>
<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card-a"><div class="table-responsive"><table class="table table-a">
            <thead><tr><th>Person</th><th>Role</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($staff as $member)
                <tr>
                    <td class="fw-semibold">{{ $member->user->name }}<div class="small text-muted">{{ $member->user->email }}</div></td>
                    <td colspan="2">
                        @if ($member->user_id === $store->owner_id)
                            <span class="badge text-bg-dark">Owner</span>
                        @else
                            <form method="POST" action="{{ route('admin.staff.update', $member) }}" class="d-flex gap-2">
                                @csrf @method('PUT')
                                <select name="role_id" class="form-select form-select-sm">@foreach ($roles->where('slug', '!=', 'owner') as $role)<option value="{{ $role->id }}" @selected($member->role_id === $role->id)>{{ $role->name }}</option>@endforeach</select>
                                <select name="status" class="form-select form-select-sm" style="max-width:120px"><option value="active" @selected($member->status === 'active')>Active</option><option value="disabled" @selected($member->status === 'disabled')>Disabled</option></select>
                                <button class="btn btn-sm btn-light">Save</button>
                            </form>
                        @endif
                    </td>
                    <td class="text-end">@if ($member->user_id !== $store->owner_id && $member->user_id !== auth()->id())<form method="POST" action="{{ route('admin.staff.destroy', $member) }}" onsubmit="return confirm('Remove this person from the store?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="bi bi-person-x"></i></button></form>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div></div>
    </div>
    <div class="col-xl-4">
        <form method="POST" action="{{ route('admin.staff.store') }}" class="card-a">
            @csrf
            <div class="head">Add staff</div>
            <div class="body">
                <div class="mb-2"><input name="name" class="form-control" placeholder="Full name" required value="{{ old('name') }}"></div>
                <div class="mb-2"><input name="email" type="email" class="form-control" placeholder="Email" required value="{{ old('email') }}"></div>
                <div class="mb-2"><input name="password" type="password" class="form-control" placeholder="Password (10+ characters)" required autocomplete="new-password"></div>
                <div class="mb-3"><select name="role_id" class="form-select">@foreach ($roles->where('slug', '!=', 'owner') as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></div>
                <button class="btn btn-brand w-100">Add staff member</button>
            </div>
        </form>
    </div>
</div>

<div class="card-a" x-data="{ open: null }">
    <div class="head">Roles and what they can do</div>
    @foreach ($roles as $role)
        <div class="border-bottom">
            <button type="button" class="btn w-100 text-start d-flex justify-content-between px-3 py-2" @click="open = open === {{ $role->id }} ? null : {{ $role->id }}">
                <span class="fw-semibold">{{ $role->name }}</span>
                <span class="small text-muted">{{ $role->slug === 'owner' ? 'Everything' : $role->permissions->count().' permissions' }} <i class="bi bi-chevron-down"></i></span>
            </button>
            <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="px-3 pb-3" x-show="open === {{ $role->id }}" x-cloak>
                @csrf @method('PUT')
                <div class="row">
                    @foreach ($permissions as $group => $list)
                        <div class="col-md-4 mb-2"><div class="small fw-bold text-uppercase text-muted">{{ $group }}</div>
                            @foreach ($list as $permission)
                                <label class="d-block small"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->slug === 'owner' || $role->permissions->contains($permission)) @disabled($role->slug === 'owner')> {{ $permission->name }}</label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                @unless ($role->slug === 'owner')<button class="btn btn-sm btn-brand">Save {{ $role->name }}</button>@endunless
            </form>
        </div>
    @endforeach
    <form method="POST" action="{{ route('admin.roles.store') }}" class="body d-flex gap-2">
        @csrf
        <input name="name" class="form-control" placeholder="New role name, e.g. Packer" required>
        <button class="btn btn-light text-nowrap">Create role</button>
    </form>
</div>

@if ($store->hasFeature('api'))
<div class="card-a mt-3">
    <div class="head">API access <span class="small text-muted fw-normal">for apps and integrations</span></div>
    <div class="body">
        @if (session('token'))
            <div class="alert alert-warning"><strong>Copy this token now. It will not be shown again.</strong><div class="font-monospace small mt-1 text-break">{{ session('token') }}</div></div>
        @endif
        <p class="small text-muted">Public: <code>GET {{ $store->url('/api/v1/products') }}</code>. With a token (header <code>Authorization: Bearer …</code>): <code>GET /api/v1/orders</code>.</p>
        <table class="table table-a mb-3"><tbody>
            @forelse ($tokens as $token)
                <tr><td>{{ \Illuminate\Support\Str::after($token->name, 'store:'.$store->id.':') }}</td><td class="small text-muted">last used {{ $token->last_used_at?->diffForHumans() ?? 'never' }}</td><td class="text-end"><form method="POST" action="{{ route('admin.tokens.destroy', $token->id) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger">Revoke</button></form></td></tr>
            @empty
                <tr><td class="empty">No tokens.</td></tr>
            @endforelse
        </tbody></table>
        <form method="POST" action="{{ route('admin.tokens.store') }}" class="d-flex gap-2">@csrf<input name="label" class="form-control" placeholder="Token name, e.g. Mobile app" required><button class="btn btn-light text-nowrap">Create token</button></form>
    </div>
</div>
@endif
@endsection
