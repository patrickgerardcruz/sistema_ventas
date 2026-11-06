@extends('layouts.app')
@section('content')
<div class="page-heading"><div><h1>Auditoría</h1><p>Consulta quién registró cada operación y cuándo se realizó.</p></div></div><section class="panel"><div class="table-scroll"><table><thead><tr><th>FECHA</th><th>USUARIO</th><th>ACCIÓN</th><th>DETALLE</th><th>IP</th></tr></thead><tbody>@forelse($rows as $a)<tr><td>{{ $a->created_at }}</td><td>{{ $a->name ?? 'Sistema' }}</td><td><span class="badge gray">{{ $a->action }}</span></td><td>{{ $a->detail }}</td><td>{{ $a->ip ?? '—' }}</td></tr>@empty<tr><td class="empty" colspan="5">No hay operaciones registradas.</td></tr>@endforelse</tbody></table></div>@include('partials.pagination')</section>
@endsection
