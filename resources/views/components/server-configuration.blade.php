<section class="card form-card" id="server-configuration" aria-labelledby="server-configuration-title">
    <h2 id="server-configuration-title">Dominio y servidor</h2>
    <p class="muted">Prepara la dirección de tus pruebas en internet. Cada instalación tiene su propia configuración: puedes seguir desarrollando en local.</p>
    <div class="detail-grid">
        <div>
            <h3>Esta instalación</h3>
            <dl class="server-summary">
                <dt>Entorno</dt>
                <dd>{{ app()->environment('local') ? 'Desarrollo local' : (app()->environment('production') ? 'Servidor público' : 'Pruebas') }}</dd>
                <dt>Dirección configurada</dt>
                <dd><code>{{ config('app.url') }}</code></dd>
                <dt>Dirección para la aplicación móvil</dt>
                <dd><code>{{ rtrim(config('app.url'), '/') }}/api/v1</code></dd>
                <dt>Actualización del mapa</dt>
                <dd>{{ config('broadcasting.default') === 'reverb' ? 'En vivo si hay conexión; consulta de respaldo cada 15 segundos' : 'Consulta cada 15 segundos' }}</dd>
            </dl>
        </div>
        <form method="POST" action="{{ route('settings.server.preview') }}">
            @csrf
            <h3>Preparar cPanel o Plesk</h3>
            <label>Dominio de destino
                <input type="url" name="server_url" required maxlength="255" placeholder="https://gps.tudominio.com" autocomplete="url" spellcheck="false" value="{{ old('server_url', session('server_setup.url', app()->environment('production') ? config('app.url') : '')) }}" aria-describedby="server-url-help">
                <small id="server-url-help">Escribe https:// y el dominio o subdominio, sin /api ni otras rutas.</small>
            </label>
            <p class="muted">El asistente prepara los ajustes para aplicarlos en el hosting. El dominio y el certificado HTTPS se activan desde cPanel o Plesk.</p>
            <button class="button primary section-gap" type="submit"><i data-lucide="settings-2"></i>Preparar configuración</button>
        </form>
    </div>
    @if(session('server_setup'))
        <div class="server-preview section-gap" role="status">
            <h3>Configuración preparada para {{ session('server_setup.url') }}</h3>
            <p class="muted">Pendiente de aplicar en el hosting. Esta vista previa no cambia la dirección de esta instalación.</p>
            <p class="section-gap">La aplicación móvil usará: <code>{{ session('server_setup.api_url') }}</code></p>
            <label class="section-gap" for="server-environment">Valores para el archivo .env del hosting</label>
            <textarea id="server-environment" rows="6" readonly spellcheck="false">{{ session('server_setup.environment') }}</textarea>
            <button class="button secondary" type="button" data-copy-target="server-environment"><i data-lucide="check"></i>Copiar configuración</button>
            <p class="muted section-gap">El mapa consultará las ubicaciones cada 15 segundos mientras esté abierto. Este modo de pruebas no necesita mantener Reverb ni un proceso de cola en ejecución.</p>
            <details class="section-gap">
                <summary>Cómo aplicar la configuración en el hosting</summary>
                <ol class="server-steps">
                    <li>Activa PHP 8.3 o superior, crea la base de datos MySQL y habilita HTTPS para el dominio. La raíz pública del dominio debe ser la carpeta <code>public</code> del proyecto.</li>
                    <li>Sube el proyecto y los recursos compilados de <code>public/build</code>. Instala las dependencias con <code>composer install --no-dev --optimize-autoloader</code>. No subas <code>public/hot</code>.</li>
                    <li>En una instalación nueva, copia <code>.env.example</code> como <code>.env</code> y completa la conexión a la base de datos del hosting. Sustituye en ese archivo los seis valores preparados arriba, sin duplicarlos. Conserva las demás variables.</li>
                    <li>Solo si la instalación es nueva y no tiene clave, ejecuta <code>php artisan key:generate --no-interaction</code>. Después ejecuta <code>php artisan migrate --force --no-interaction</code>.</li>
                    <li>Crea tu administrador con <code>php artisan gps:admin tu-correo --generate --no-interaction</code>, reemplazando <code>tu-correo</code> por tu dirección. El acceso se guarda en <code>storage/app/private/ACCESO_LOCAL.txt</code>.</li>
                    <li>Aplica los ajustes con <code>php artisan config:cache --no-interaction</code>. Comprueba que <code>storage</code> y <code>bootstrap/cache</code> permiten escritura al servidor.</li>
                    <li>Abre el dominio, inicia sesión y comprueba aquí la dirección configurada. Para verificar la recepción de ubicaciones, vincula un cliente de prueba y envía posiciones; la aplicación móvil aún está pendiente.</li>
                </ol>
                <p class="muted">Usa la terminal del hosting o solicita estos pasos a su soporte. Esta preparación no comprueba la disponibilidad del dominio ni la conexión desde internet.</p>
            </details>
        </div>
    @endif
</section>
