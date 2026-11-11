<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>@yield('title', 'Mapa en vivo') · Nexo GPS</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @include('components.reading-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-page="@yield('page')">
    <a class="skip-link" href="#main-content">Ir al contenido principal</a>
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-symbol"><i data-lucide="radar"></i></span><span>NEXO<span class="brand-light"> GPS</span><small>CONECTA CADA MOVIMIENTO</small></span></a>
        <div class="workspace"><span class="workspace-icon"><i data-lucide="layers"></i></span><div>Mi espacio<small>Centro de seguimiento</small></div><span class="workspace-dot"></span></div>
        <span class="nav-label">MONITOREO</span>
        <nav aria-label="Navegación principal">
            <a href="{{ route('dashboard') }}" @class(['nav-item', 'active' => request()->routeIs('dashboard')])><i data-lucide="map-pinned"></i>Mapa en vivo<span class="nav-dot"></span></a>
            <a href="{{ route('devices.index') }}" @class(['nav-item', 'active' => request()->routeIs('devices.*')])><i data-lucide="smartphone"></i>Dispositivos</a>
            <a href="{{ route('history') }}" @class(['nav-item', 'active' => request()->routeIs('history')])><i data-lucide="route"></i>Historial de recorridos</a>
            <a href="{{ route('enrollments.index') }}" @class(['nav-item', 'active' => request()->routeIs('enrollments.*')])><i data-lucide="radio-tower"></i>Solicitudes<span id="pending-nav" class="nav-count" hidden></span></a>
            <span class="nav-label nav-separator">ADMINISTRACIÓN</span>
            <a href="{{ route('settings') }}" @class(['nav-item', 'active' => request()->routeIs('settings*')])><i data-lucide="settings-2"></i>Configuración</a>
        </nav>
        <div class="sidebar-bottom"><div class="private-note"><i data-lucide="shield-check"></i><div>Acceso privado<small>Solo administradores</small></div></div><div class="account"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div>{{ auth()->user()->name }}<small>Administrador</small></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-button" title="Cerrar sesión" aria-label="Cerrar sesión"><i data-lucide="log-out"></i></button></form></div></div>
    </aside>
    <div class="main-shell">
        <header class="topbar"><div class="breadcrumb"><button class="icon-button mobile-menu" id="menu-toggle" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false"><i data-lucide="menu"></i></button><span>Mi espacio</span><i data-lucide="chevron-right"></i><strong>@yield('title', 'Mapa en vivo')</strong></div><div class="topbar-right">@include('components.reading-controls')<span class="local-tag">{{ app()->environment('local') ? 'Entorno local' : 'Centro de control' }}</span><span class="today">{{ now()->timezone(config('tracking.timezone'))->format('d / m / Y') }}</span><span class="avatar small">{{ mb_substr(auth()->user()->name, 0, 1) }}</span></div></header>
        <main class="main-content" id="main-content" tabindex="-1">
            @if(session('success'))<div class="notice success" role="status"><i data-lucide="circle-check"></i>{{ session('success') }}</div>@endif
            @if($errors->any())<div class="notice error" role="alert"><i data-lucide="circle-alert"></i><div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div></div>@endif
            @yield('content')
        </main>
        <footer class="footer"><span>Nexo GPS <span class="footer-separator">/</span> Cada ubicación, bajo tu control.</span><span>Hora de Colombia · UTC−5</span></footer>
    </div>
    <div class="toast" id="toast" role="status" hidden></div>
    <script type="application/json" id="app-config">{!! json_encode(['snapshotUrl' => route('dashboard.snapshot'), 'demoUrl' => route('dashboard.demo'), 'historyUrl' => route('history.data'), 'enrollmentsUrl' => route('enrollments.index'), 'tileUrl' => config('tracking.map_url'), 'attribution' => config('tracking.map_attribution'), 'center' => config('tracking.center'), 'local' => app()->environment('local'), 'reverb' => config('broadcasting.default') === 'reverb' ? ['key' => config('broadcasting.connections.reverb.key'), 'host' => config('reverb.client.host'), 'port' => config('reverb.client.port'), 'scheme' => config('reverb.client.scheme')] : null], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</body>
</html>
