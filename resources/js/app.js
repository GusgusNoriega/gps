import L from 'leaflet';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { createIcons, Radar, Layers, MapPinned, Smartphone, Route, RadioTower, Settings2, ShieldCheck, LogOut, Menu, ChevronRight, Plus, Radio, Clock3, ScanLine, RefreshCw, Search, Info, PanelLeft, LocateFixed, Maximize, Play, Pause, CircleCheck, CircleAlert, ArrowRight, ArrowUpRight, ArrowLeft, Check, Save, Power, LockKeyhole, Map as MapIcon, MapPin, Battery, Car, X, Navigation } from 'lucide';

const iconSet = { Radar, Layers, MapPinned, Smartphone, Route, RadioTower, Settings2, ShieldCheck, LogOut, Menu, ChevronRight, Plus, Radio, Clock3, ScanLine, RefreshCw, Search, Info, PanelLeft, LocateFixed, Maximize, Play, Pause, CircleCheck, CircleAlert, ArrowRight, ArrowUpRight, ArrowLeft, Check, Save, Power, LockKeyhole, Map: MapIcon, MapPin, Battery, Car, X, Navigation };
const icons = () => createIcons({ icons: iconSet });
icons();
const $ = (selector) => document.querySelector(selector);
const readingSize = $('#reading-size');
if (readingSize) {
    readingSize.value = document.documentElement.dataset.readingSize || 'large';
    readingSize.addEventListener('change', () => {
        document.documentElement.dataset.readingSize = readingSize.value;
        try { localStorage.setItem('nexo.reading-size', readingSize.value); } catch {}
        window.dispatchEvent(new Event('readingchange'));
    });
}
const remSize = () => parseFloat(getComputedStyle(document.documentElement).fontSize);
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
function replacePreservingFocus(element, html) {
    const active = document.activeElement;
    const key = element.contains(active) ? active.dataset.focusKey || active.id : null;
    element.innerHTML = html;
    if (key) {
        const replacement = [...element.querySelectorAll('[data-focus-key], [id]')].find((item) => (item.dataset.focusKey || item.id) === key);
        replacement?.focus({ preventScroll: true });
    }
}
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character]);
let toastTimer;
function toast(message) {
    const element = $('#toast');
    if (!element) return;
    element.textContent = message;
    element.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { element.hidden = true; }, 12000);
}
const formatDate = (value) => value ? new Intl.DateTimeFormat('es-CO', { timeZone: 'America/Bogota', dateStyle: 'short', timeStyle: 'medium' }).format(new Date(value)) : 'Sin reportes';
const ago = (value) => {
    if (!value) return 'Aún sin ubicación';
    const seconds = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 1000));
    if (seconds < 60) return `Hace ${seconds} s`;
    if (seconds < 3600) return `Hace ${Math.floor(seconds / 60)} min`;
    if (seconds < 86400) return `Hace ${Math.floor(seconds / 3600)} h`;
    return `Hace ${Math.floor(seconds / 86400)} días`;
};
const statusLabels = { online: 'Ubicación reciente', stale: 'Ubicación antigua', no_location: 'Sin ubicación', disabled: 'Desactivado' };
const colorClass = (status) => status === 'online' ? 'green-dot' : status === 'stale' ? 'amber-dot' : 'gray-dot';

document.querySelectorAll('form[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
    if (!confirm(form.dataset.confirm)) event.preventDefault();
}));
document.querySelectorAll('[data-copy-target]').forEach((button) => button.addEventListener('click', async () => {
    const field = document.getElementById(button.dataset.copyTarget);
    try {
        await navigator.clipboard.writeText(field.value);
        toast('Configuración copiada. Aplícala en el hosting.');
    } catch {
        field.focus();
        field.select();
        toast('Texto seleccionado. Usa la opción Copiar de tu navegador.');
    }
}));
document.querySelectorAll('.table-scroll').forEach((container) => {
    const observer = new ResizeObserver(() => {
        const overflow = container.scrollWidth > container.clientWidth + 1;
        container.dataset.overflow = String(overflow);
        container.tabIndex = overflow ? 0 : -1;
        if (overflow) {
            container.setAttribute('role', 'region');
            container.setAttribute('aria-label', 'Tabla de datos. Usa las flechas izquierda y derecha para ver todas las columnas.');
        } else {
            container.removeAttribute('role');
            container.removeAttribute('aria-label');
        }
    });
    observer.observe(container);
    if (container.querySelector('table')) observer.observe(container.querySelector('table'));
    window.addEventListener('pagehide', () => observer.disconnect(), { once: true });
});
const compactNavigation = window.matchMedia('(max-width: 1200px)');
function syncNavigation() {
    const sidebar = $('#sidebar');
    if (!sidebar) return;
    const hidden = compactNavigation.matches && !sidebar.classList.contains('open');
    sidebar.inert = hidden;
    sidebar.setAttribute('aria-hidden', String(hidden));
}
syncNavigation();
compactNavigation.addEventListener('change', syncNavigation);
document.querySelectorAll('.nav-item.active').forEach((link) => link.setAttribute('aria-current', 'page'));
$('#menu-toggle')?.addEventListener('click', () => {
    const open = $('#sidebar').classList.toggle('open');
    $('#menu-toggle').setAttribute('aria-expanded', String(open));
    syncNavigation();
});
document.addEventListener('click', (event) => {
    if (!event.target.closest('#sidebar') && !event.target.closest('#menu-toggle')) {
        $('#sidebar')?.classList.remove('open');
        $('#menu-toggle')?.setAttribute('aria-expanded', 'false');
        syncNavigation();
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && $('#sidebar')?.classList.contains('open')) {
        $('#sidebar').classList.remove('open');
        $('#menu-toggle')?.setAttribute('aria-expanded', 'false');
        $('#menu-toggle')?.focus();
        syncNavigation();
    }
});

const configElement = $('#app-config');
const config = configElement ? JSON.parse(configElement.textContent) : null;
async function request(url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]')?.content ?? '', ...options.headers } });
    if (response.status === 401 || response.status === 419) {
        window.location.assign('/login');
        throw new Error('Tu sesión venció. Vuelve a ingresar.');
    }
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'No se pudo completar la solicitud.');
    return data;
}

function createMap(element) {
    const map = L.map(element, { zoomControl: false, scrollWheelZoom: true, zoomAnimation: !reducedMotion(), fadeAnimation: !reducedMotion(), markerZoomAnimation: !reducedMotion() }).setView(config.center, 12);
    L.control.zoom({ position: 'bottomright', zoomInTitle: 'Acercar mapa', zoomOutTitle: 'Alejar mapa' }).addTo(map);
    const sizeObserver = new ResizeObserver(() => map.invalidateSize({ animate: false, pan: false }));
    sizeObserver.observe(map.getContainer());
    window.addEventListener('pagehide', () => sizeObserver.disconnect(), { once: true });
    let tiles, enlargedLabels;
    function cartography() {
        const large = readingSize?.value === 'television';
        if (tiles && large === enlargedLabels) return;
        enlargedLabels = large;
        tiles?.remove();
        tiles = L.tileLayer(config.tileUrl, { attribution: config.attribution, maxZoom: 19, tileSize: large ? 512 : 256, zoomOffset: large ? -1 : 0, minZoom: large ? 1 : 0 }).addTo(map);
        let tileErrors = 0;
        tiles.on('tileerror', () => { tileErrors++; if (tileErrors > 2 && $('#map-error')) $('#map-error').hidden = false; });
        tiles.on('tileload', () => { tileErrors = 0; if ($('#map-error')) $('#map-error').hidden = true; });
    }
    cartography();
    window.addEventListener('readingchange', cartography);
    return map;
}

async function dashboard() {
    window.L = L;
    await import('leaflet.markercluster');
    const map = createMap('live-map');
    const clusters = L.markerClusterGroup({
        showCoverageOnHover: false, maxClusterRadius: 65, disableClusteringAtZoom: 16, animate: false,
        iconCreateFunction: (cluster) => L.divIcon({ html: `<div><span>${cluster.getChildCount()}</span></div>`, className: 'marker-cluster marker-cluster-small', iconSize: [remSize() * 2.8, remSize() * 2.8] }),
    }).addTo(map);
    const markers = new Map();
    const markerSignatures = new Map();
    let devices = [], filter = 'all', search = '', selectedId = null, following = false, initialized = false, loading = false, demoTimer = null, demoBusy = false, echoConnected = false;
    if (window.innerWidth <= 760) {
        $('#tracking-panel').classList.add('list-hidden');
        $('#toggle-device-panel').setAttribute('aria-expanded', 'false');
    }
    const visible = () => devices.filter((device) => (filter === 'all' || (filter === 'stale' ? ['stale', 'no_location'].includes(device.status) : device.status === filter)) && `${device.name} ${device.group || ''}`.toLocaleLowerCase().includes(search));
    function markerIcon(device) {
        const size = Math.max(44, remSize() * 2.4);
        const state = device.status === 'stale' ? '!' : device.status === 'disabled' ? '×' : '';
        return L.divIcon({ className: 'map-pin-shell', html: `<div class="map-pin ${esc(device.status)} ${device.id === selectedId ? 'selected' : ''}"><span>${esc(device.name.slice(0, 1).toUpperCase())}</span>${state ? `<b class="pin-state" aria-hidden="true">${state}</b>` : ''}</div>`, iconSize: [size, size], iconAnchor: [size / 2, size] });
    }
    function markerLabel(marker, device) {
        const label = `${device.name} · ${statusLabels[device.status]}`;
        marker.options.title = label;
        const television = readingSize?.value === 'television';
        marker.unbindTooltip().bindTooltip(document.createTextNode(television ? device.name : label), { permanent: television, direction: 'top', offset: [0, -remSize() * 2.2], opacity: 1 });
        const element = marker.getElement();
        if (element) { element.setAttribute('aria-label', label); element.setAttribute('title', label); }
    }
    function selectDevice(id) {
        selectedId = id;
        const device = devices.find((item) => item.id === id);
        if (device?.position) {
            map.setView([device.position.latitude, device.position.longitude], Math.max(map.getZoom(), 15), { animate: !reducedMotion() });
            if (window.innerWidth <= 760) { $('#tracking-panel').classList.add('list-hidden'); $('#toggle-device-panel').setAttribute('aria-expanded', 'false'); map.invalidateSize(); }
        }
        render();
    }
    function renderSelected() {
        const device = devices.find((item) => item.id === selectedId);
        const element = $('#selected-device');
        element.hidden = !device;
        if (!device) return;
        const position = device.position;
        replacePreservingFocus(element, `<div class="selected-heading"><h3>${esc(device.name)}</h3><button class="icon-button" id="close-selection" aria-label="Cerrar detalle"><i data-lucide="x"></i></button></div><p>${esc(statusLabels[device.status])}${device.is_simulated ? ' · DEMO' : ''}</p><div class="selected-metrics"><div><small>ÚLTIMA UBICACIÓN</small>${esc(ago(position?.recorded_at))}</div><div><small>PRECISIÓN</small>${position?.accuracy != null ? `${esc(Math.round(position.accuracy))} m` : '—'}</div><div><small>VELOCIDAD</small>${position?.speed != null ? `${(position.speed * 3.6).toFixed(1)} km/h` : '—'}</div><div><small>BATERÍA DEL PUNTO</small>${position?.battery != null ? `${esc(position.battery)}%` : '—'}</div></div><div class="selected-actions"><a class="button secondary small" data-focus-key="history" href="${esc(device.history_url)}"><i data-lucide="route"></i>Historial</a><a class="button secondary small" data-focus-key="detail" href="${esc(device.detail_url)}">Detalle</a>${position ? `<button class="button ${following ? 'primary' : 'secondary'} small" id="follow-device" title="Seguir dispositivo" aria-pressed="${following}"><i data-lucide="navigation"></i>${following ? 'Siguiendo' : 'Seguir'}</button>` : ''}</div>`);
        $('#close-selection').addEventListener('click', () => { selectedId = null; following = false; render(); });
        $('#follow-device')?.addEventListener('click', () => { following = !following; renderSelected(); icons(); });
    }
    function render() {
        const list = visible();
        $('#device-count').textContent = list.length;
        replacePreservingFocus($('#device-list'), list.length ? list.map((device) => `<button class="device-row ${device.id === selectedId ? 'selected' : ''}" data-device="${device.id}" data-focus-key="device-${device.id}" aria-pressed="${device.id === selectedId}"><span class="device-icon"><i data-lucide="${device.asset?.type === 'vehicle' ? 'car' : 'smartphone'}"></i></span><span class="device-info"><strong>${esc(device.name)}</strong><span class="device-subline">${esc(device.group || 'Sin grupo')}${device.is_simulated ? '<span class="badge violet">DEMO</span>' : ''}</span><span class="device-status"><b class="legend-dot ${colorClass(device.status)}" aria-hidden="true"></b><span>${esc(statusLabels[device.status])}<small>${esc(ago(device.position?.recorded_at))}</small></span></span></span><span class="device-battery"><i data-lucide="battery"></i>${device.position?.battery != null ? `${esc(device.position.battery)}%` : '—'}</span></button>`).join('') : `<div class="list-empty">${devices.length ? 'No hay equipos con este filtro.' : 'Tus dispositivos aparecerán aquí<br>cuando apruebes su vinculación.'}</div>`);
        const ids = new Set(list.filter((device) => device.position).map((device) => device.id));
        markers.forEach((marker, id) => { if (!ids.has(id)) { clusters.removeLayer(marker); markers.delete(id); markerSignatures.delete(id); } });
        list.forEach((device) => {
            if (!device.position) return;
            const latlng = [device.position.latitude, device.position.longitude];
            const signature = JSON.stringify([latlng, device.name, device.status, device.id === selectedId, remSize(), readingSize?.value]);
            let marker = markers.get(device.id);
            if (!marker) {
                marker = L.marker(latlng, { icon: markerIcon(device), title: `${device.name} · ${statusLabels[device.status]}` }).on('click', () => selectDevice(device.id));
                markers.set(device.id, marker);
                clusters.addLayer(marker);
                markerLabel(marker, device);
            } else if (markerSignatures.get(device.id) !== signature) {
                marker.setLatLng(latlng).setIcon(markerIcon(device));
                markerLabel(marker, device);
            }
            markerSignatures.set(device.id, signature);
        });
        $('#map-empty').hidden = devices.some((device) => device.position);
        if (following) {
            const selected = devices.find((device) => device.id === selectedId);
            if (selected?.position) map.panTo([selected.position.latitude, selected.position.longitude], { animate: false });
        }
        renderSelected();
        icons();
    }
    function fitAll() {
        const points = visible().filter((device) => device.position).map((device) => [device.position.latitude, device.position.longitude]);
        following = false;
        if (points.length) map.fitBounds(points, { padding: [remSize() * 4, remSize() * 4], maxZoom: 15, animate: !reducedMotion() });
        renderSelected(); icons();
    }
    function connection(live = echoConnected) {
        $('#connection-status').innerHTML = `<span class="live-dot"></span>${live ? 'En vivo' : 'Actualización cada 15 s'}`;
        $('#connection-status').classList.toggle('fallback', !live);
    }
    async function refresh() {
        if (loading) return;
        loading = true;
        try {
            const data = await request(config.snapshotUrl);
            devices = data.devices;
            $('#stat-total').textContent = devices.length;
            $('#stat-online').textContent = devices.filter((device) => device.status === 'online').length;
            $('#stat-stale').textContent = devices.filter((device) => ['stale', 'no_location'].includes(device.status)).length;
            $('#stat-pending').textContent = data.pending;
            $('#pending-nav').textContent = data.pending; $('#pending-nav').hidden = !data.pending;
            $('#last-refresh').textContent = `Actualizado ${formatDate(data.server_time)}`;
            if ($('#demo-strip')) $('#demo-strip').hidden = !devices.some((device) => device.is_simulated);
            render();
            if (!initialized && devices.some((device) => device.position)) { fitAll(); initialized = true; }
            connection();
        } catch (error) {
            $('#connection-status').textContent = 'Sin conexión al servidor';
            $('#connection-status').classList.add('fallback');
            if (!devices.length) $('#device-list').textContent = 'No se pudieron cargar los dispositivos. Reintentando…';
        } finally { loading = false; }
    }
    async function demo(action) {
        if (demoBusy) return;
        demoBusy = true;
        try { await request(config.demoUrl, { method: 'POST', body: JSON.stringify({ action }) }); await refresh(); }
        catch (error) { toast(error.message); stopDemo(); }
        finally { demoBusy = false; }
    }
    function stopDemo() {
        clearInterval(demoTimer); demoTimer = null;
        if ($('#play-demo')) $('#play-demo').innerHTML = '<i data-lucide="play"></i>Simular movimiento';
        icons();
    }
    $('#device-list').addEventListener('click', (event) => { const row = event.target.closest('[data-device]'); if (row) { following = false; selectDevice(Number(row.dataset.device)); } });
    $('#device-search').addEventListener('input', (event) => { search = event.target.value.toLocaleLowerCase(); render(); });
    document.querySelectorAll('[data-filter]').forEach((button) => button.addEventListener('click', () => { filter = button.dataset.filter; document.querySelectorAll('[data-filter]').forEach((tab) => { tab.classList.toggle('selected', tab === button); tab.setAttribute('aria-pressed', String(tab === button)); }); render(); }));
    $('#refresh-map').addEventListener('click', refresh);
    $('#fit-all').addEventListener('click', fitAll);
    $('#toggle-device-panel').addEventListener('click', () => { const hidden = $('#tracking-panel').classList.toggle('list-hidden'); $('#toggle-device-panel').setAttribute('aria-expanded', String(!hidden)); map.invalidateSize(); });
    $('#fullscreen-map').addEventListener('click', async () => { try { if (document.fullscreenElement) await document.exitFullscreen(); else await $('#tracking-panel').requestFullscreen(); } catch { toast('Tu navegador no permite activar pantalla completa.'); } });
    document.addEventListener('fullscreenchange', () => setTimeout(() => map.invalidateSize(), 100));
    const resizeMarkers = () => { render(); clusters.refreshClusters(); };
    window.addEventListener('readingchange', resizeMarkers);
    window.addEventListener('resize', resizeMarkers);
    $('#seed-demo')?.addEventListener('click', async () => { $('#seed-demo').disabled = true; await demo('seed'); $('#seed-demo').disabled = false; });
    $('#play-demo')?.addEventListener('click', () => { if (demoTimer) stopDemo(); else { demo('advance'); demoTimer = setInterval(() => demo('advance'), 5000); $('#play-demo').innerHTML = '<i data-lucide="pause"></i>Pausar simulación'; icons(); } });
    $('#clear-demo')?.addEventListener('click', () => { if (confirm('¿Eliminar únicamente los dispositivos y recorridos simulados?')) { stopDemo(); demo('clear'); initialized = false; selectedId = null; } });
    await refresh();
    const poll = setInterval(() => { if (!document.hidden) refresh(); }, 15000);
    document.addEventListener('visibilitychange', () => { if (document.hidden) stopDemo(); else refresh(); });
    let echo;
    if (config.reverb?.key) {
        window.Pusher = Pusher;
        echo = new Echo({ broadcaster: 'reverb', key: config.reverb.key, wsHost: config.reverb.host || window.location.hostname, wsPort: Number(config.reverb.port), wssPort: Number(config.reverb.port), forceTLS: config.reverb.scheme === 'https', enabledTransports: ['ws', 'wss'], authEndpoint: '/broadcasting/auth', auth: { headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').content } } });
        echo.private('fleet').listen('.fleet.updated', refresh).subscribed(() => { echoConnected = true; connection(); refresh(); }).error(() => { echoConnected = false; connection(); });
        echo.connector.pusher.connection.bind('state_change', ({ current }) => { if (current !== 'connected') { echoConnected = false; connection(); } });
    }
    window.addEventListener('pagehide', () => { clearInterval(poll); stopDemo(); echo?.disconnect(); });
}

function history() {
    const map = createMap('history-map');
    const layer = L.featureGroup().addTo(map);
    const form = $('#history-form');
    $('#history-mode').addEventListener('change', (event) => {
        const byAsset = event.target.value === 'asset';
        $('#history-device-label').hidden = byAsset; $('#history-device').disabled = byAsset;
        $('#history-asset-label').hidden = !byAsset; $('#history-asset').disabled = !byAsset;
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const params = new URLSearchParams(new FormData(form));
            const data = await request(`${config.historyUrl}?${params}`);
            layer.clearLayers();
            data.segments.forEach((segment) => { if (segment.length > 1) L.polyline(segment, { color: '#237e6e', weight: 4, opacity: .85 }).addTo(layer); else if (segment.length) L.circleMarker(segment[0], { radius: 4, color: '#237e6e' }).addTo(layer); });
            const usable = data.points.filter((point) => point.is_usable);
            if (usable.length) {
                const first = usable[0], last = usable[usable.length - 1];
                L.circleMarker([first.latitude, first.longitude], { radius: 7, color: '#fff', weight: 3, fillColor: '#28a177', fillOpacity: 1 }).bindTooltip('Inicio · ' + formatDate(first.recorded_at)).addTo(layer);
                L.circleMarker([last.latitude, last.longitude], { radius: 7, color: '#fff', weight: 3, fillColor: '#4b83c5', fillOpacity: 1 }).bindTooltip('Final · ' + formatDate(last.recorded_at)).addTo(layer);
                map.fitBounds(layer.getBounds(), { padding: [40, 40], maxZoom: 16 });
            }
            $('#history-count').textContent = `${data.returned.toLocaleString('es-CO')} puntos`;
            $('#history-message span').textContent = data.truncated ? `Se muestran los primeros ${data.returned} de ${data.total} puntos. Reduce el intervalo para consultar el recorrido completo.` : data.total ? `${data.total} puntos encontrados. Los huecos de más de cinco minutos y los cambios de asignación se muestran separados.` : 'No se encontraron ubicaciones en este intervalo. Prueba con otra fecha o dispositivo.';
            $('#history-table').innerHTML = data.points.length ? data.points.slice(-100).reverse().map((point) => `<tr><td>${esc(formatDate(point.recorded_at))}</td><td class="coordinates">${point.latitude.toFixed(6)}, ${point.longitude.toFixed(6)}</td><td>${point.accuracy != null ? `${esc(point.accuracy)} m` : '—'}</td><td>${point.speed != null ? `${(point.speed * 3.6).toFixed(1)} km/h` : '—'}</td><td><span class="badge ${point.is_usable ? 'green' : 'amber'}">${point.is_usable ? 'Utilizable' : 'Baja precisión'}</span></td></tr>`).join('') : '<tr><td colspan="5" class="table-empty">No hay puntos en este intervalo.</td></tr>';
        } catch (error) { toast(error.message); }
        finally { button.disabled = false; }
    });
    if ($('#history-device').value) form.requestSubmit();
}

if (document.body.dataset.page === 'dashboard') dashboard().catch(() => toast('No se pudo iniciar el mapa. Actualiza la página.'));
if (document.body.dataset.page === 'history') history();
