<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><script>
(() => { let theme = 'light'; try { if (localStorage.getItem('nexo-theme') === 'dark') theme = 'dark'; } catch {} document.documentElement.dataset.theme = theme; document.documentElement.style.colorScheme = theme; })();
</script><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title ?? 'Acceso' }} · Nexo Ventas</title><link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}"><script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script></head>
<body class="{{ isset($store) ? 'workspace' : (isset($platformArea) ? 'workspace' : 'auth-body') }}">
@if(isset($store) || isset($platformArea))
<aside class="sidebar" id="sidebar">
 <a class="brand" href="{{ isset($store)?route('dashboard'):route('platform.index') }}"><span class="brand-mark"><x-icon name="box"/></span><span>nexo<span class="brand-sub">VENTAS</span></span></a>
 <div class="workspace-card"><span class="store-avatar"><x-icon name="store"/></span><div><strong>{{ $store->name ?? 'Administración' }}</strong><small>{{ isset($store) ? 'Tienda · '.$store->code : 'Panel de la plataforma' }}</small></div><span class="online-dot"></span></div>
 <div class="nav-label">ESPACIO DE TRABAJO</div>
 <nav aria-label="Navegación principal">
 @if(isset($store))
 @php
 $nav=[['dashboard','Resumen','grid',route('dashboard'),'dashboard'],['sales','Ventas','cart',route('sales'),'sales*'],['products','Productos','box',route('catalog.index','products'),'catalog.products'],['inventory','Inventario','list',route('inventory'),'inventory*'],['purchases','Compras','truck',route('purchases'),'purchases*'],['customers','Clientes','users',route('catalog.index','customers'),'catalog.customers'],['suppliers','Proveedores','truck',route('catalog.index','suppliers'),'catalog.suppliers'],['cash','Caja','cash',route('cash'),'cash*'],['reports','Reportes','chart',route('reports'),'reports']];
 @endphp
 @foreach($nav as [$permission,$label,$icon,$url,$active])
 @if(\App\Services\Access::allows($permission))
 @php($selected = str_starts_with($active,'catalog.') ? request()->route('module')===substr($active,8) : (request()->routeIs($active) || (request()->routeIs('order') && request()->route('mode')===$permission)))
 <a class="nav-item {{ $selected?'selected':'' }}" href="{{ $url }}"><x-icon :name="$icon"/><span>{{ $label }}</span>@if($selected)<span class="nav-active-dot"></span>@endif</a>
 @endif
 @endforeach
 <div class="nav-label">ADMINISTRACIÓN</div>
 @foreach([['categories','Categorías','tag',route('catalog.index','categories')],['brands','Marcas','tag',route('catalog.index','brands')],['returns','Devoluciones','return',route('returns')],['users','Usuarios y roles','shield',route('catalog.index','users')],['audit','Auditoría','clock',route('audit')],['settings','Configuración','settings',route('settings')]] as [$permission,$label,$icon,$url])
 @if(\App\Services\Access::allows($permission))<a class="nav-item {{ (request()->route('module')===$permission || request()->routeIs($permission))?'selected':'' }}" href="{{ $url }}"><x-icon :name="$icon"/><span>{{ $label }}</span></a>@endif
 @endforeach
 @else
 <a class="nav-item selected" href="{{ route('platform.index') }}"><x-icon name="store"/>Negocios y suscripciones</a>
 <a class="nav-item" href="{{ route('login') }}"><x-icon name="arrow"/>Acceso a tiendas</a>
 @endif
 </nav>
 <div class="sidebar-bottom"><div class="security-note"><x-icon name="shield"/><div><strong>{{ isset($store)?'Tu negocio, conectado':'Control de la plataforma' }}</strong><small>{{ isset($store)?'Base de datos independiente':'Gestión de negocios y acceso' }}</small></div></div><form method="post" action="{{ route('logout') }}">@csrf<button class="nav-item logout" type="submit"><x-icon name="logout"/>Cerrar sesión</button></form></div>
</aside>
<div class="main-shell"><header class="topbar"><button class="icon-button mobile-menu" data-menu aria-label="Abrir menú"><x-icon name="menu"/></button><div class="breadcrumb">Espacio de trabajo <span>/</span> <strong>{{ $title ?? '' }}</strong></div><div class="topbar-right">@include('partials.theme-switch')<span class="date"><x-icon name="clock"/>{{ now()->format('d/m/Y') }}</span><span class="top-divider"></span><span class="user-avatar">{{ mb_substr($storeUser->name ?? 'A',0,1) }}</span><div class="user-label"><strong>{{ $storeUser->name ?? 'Administrador' }}</strong><small>{{ isset($storeUser)?\App\Services\Access::ROLES[$storeUser->role]:'Propietario del software' }}</small></div></div></header>
<main class="page">
 @include('partials.messages')
 @yield('content')
 <footer class="page-footer"><span>Nexo Ventas <span class="muted">/ Gestión de negocios</span></span><span class="muted">{{ isset($store)?'Tienda '.$store->code:'Administración central' }}</span></footer>
</main></div>
@else
 <div class="auth-theme-switch">@include('partials.theme-switch')</div>
 @yield('content')
@endif
</body></html>
