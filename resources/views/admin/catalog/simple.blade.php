{{-- A list with an add/edit form beside it, for small records (brands, warehouses, suppliers). --}}
@extends('admin.layout', ['title' => $title])

@section('content')
<div class="page-head"><div><h1>{{ $title }}</h1><div class="sub">{{ $intro }}</div></div></div>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-a"><div class="table-responsive"><table class="table table-a">
            <thead><tr>@foreach ($columns as $label)<th>{{ $label }}</th>@endforeach<th></th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $key => $label)
                        <td @class(['fw-semibold' => $loop->first])>
                            @php $value = data_get($row, $key); @endphp
                            @if (is_bool($value)){!! $value ? '<i class="bi bi-check-circle text-success"></i>' : '—' !!}@else{{ $value ?? '—' }}@endif
                        </td>
                    @endforeach
                    <td class="text-end text-nowrap">
                        <a href="{{ route($routes['edit'], $row) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                        @isset($routes['destroy'])
                            <form method="POST" action="{{ route($routes['destroy'], $row) }}" class="d-inline" onsubmit="return confirm('Delete this {{ $singular }}?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
                        @endisset
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 1 }}" class="empty">Nothing here yet.</td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>
    <div class="col-lg-5">
        <form method="POST" action="{{ $editing->exists ? route($routes['update'], $editing) : route($routes['store']) }}" class="card-a">
            @csrf @if ($editing->exists) @method('PUT') @endif
            <div class="head">{{ $editing->exists ? 'Edit '.$singular : 'Add '.$singular }} @if($editing->exists)<a href="{{ route($routes['index']) }}" class="small fw-normal">Cancel</a>@endif</div>
            <div class="body">
                @foreach ($fields as $name => [$label, $type, $required])
                    @if ($type === 'checkbox')
                        <input type="hidden" name="{{ $name }}" value="0">
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" id="f-{{ $name }}" @checked(old($name, $editing->{$name}))><label class="form-check-label" for="f-{{ $name }}">{{ $label }}</label></div>
                    @elseif ($type === 'textarea')
                        <div class="mb-3"><label class="form-label">{{ $label }}</label><textarea name="{{ $name }}" rows="2" class="form-control" @required($required)>{{ old($name, $editing->{$name}) }}</textarea></div>
                    @else
                        <div class="mb-3"><label class="form-label">{{ $label }}</label><input type="{{ $type }}" name="{{ $name }}" class="form-control" value="{{ old($name, $editing->{$name}) }}" @required($required)></div>
                    @endif
                @endforeach
                <button class="btn btn-brand w-100">{{ $editing->exists ? 'Save' : 'Add '.$singular }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
