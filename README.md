# Nexo GPS

Sistema de seguimiento con Laravel. El administrador ve todos los dispositivos en un mapa grande, consulta recorridos y aprueba instalaciones sin QR. Las credenciales móviles solo permiten enviar telemetría y consultar su configuración; no dan acceso a ubicaciones.

Primera versión web local implementada. La aplicación móvil, el despliegue público HTTPS y la integración con GPS físicos corresponden a las siguientes etapas de [PLAN_DE_TRABAJO.md](PLAN_DE_TRABAJO.md).

## Abrir en esta computadora

1. Iniciar Apache y MySQL desde Laragon.
2. Abrir **http://gps.test**.
3. Usar el correo **admin@gps.local** y la contraseña guardada en `storage/app/private/ACCESO_LOCAL.txt`. Cambiarla desde Configuración. Ese archivo contiene el acceso inicial y no se actualiza al cambiar la contraseña desde el panel.
4. Para recibir cambios al instante, ejecutar en PowerShell desde esta carpeta:

```powershell
.\Start-Gps.ps1
```

Este script inicia Reverb y la cola en segundo plano, sin ventanas adicionales. Ejecutarlo nuevamente después de reiniciar Laragon o Windows. El mapa también consulta el servidor cada 15 segundos si los eventos no están disponibles. `Stop-Gps.ps1` detiene únicamente los procesos registrados de este proyecto. No hay tareas instaladas para arrancar automáticamente con Windows.

El sitio usa Apache de Laragon, raíz `C:/laragon/www/gps/public`, PHP 8.3.33 y MySQL 8.0.30. La base es `gps_tracking`, con un usuario dedicado `gps_app`. La configuración y sus contraseñas están en `.env`, excluido de Git. El dominio local no permite recibir datos desde teléfonos conectados a internet.

Se añadieron reglas de Apache en `C:/laragon/etc/apache2/sites-enabled/gps.conf` para impedir servir archivos de la raíz del proyecto, también a través de `localhost/gps`. Laragon mantiene además su archivo automático `auto.gps.test.conf`. Al cambiar de computadora hay que recrear el dominio, apuntar a `public` y conservar esa protección.

Para diagnóstico, `Start-Gps.ps1 -Standalone` abre `http://localhost:8085` con un Apache aislado de Laragon. El uso normal es `gps.test`. Las rutas de ejecutables en los scripts corresponden a esta instalación; revisarlas si cambia la versión de PHP/Apache o la ubicación de Laragon.

## Recorrido por el sistema

- **Mapa en vivo:** lista plegable, búsqueda por nombre/grupo, filtros, marcadores agrupados, selección, seguimiento opcional, “Ver todos” y pantalla completa. Batería, precisión y velocidad aparecen cuando el equipo las envía.
- **Dispositivos:** nombre, grupo, teléfono/vehículo asociado, referencia/placa, estado y comunicación. Desactivar revoca las credenciales y conserva el historial.
- **Solicitudes:** aprobar o rechazar instalaciones. Comparar el identificador de instalación y los datos con el teléfono esperado; un nombre o modelo declarado no demuestra su identidad. Seleccionar un elemento existente reemplaza su dispositivo anterior y conserva las asignaciones históricas.
- **Historial:** consultar por dispositivo o elemento asociado y fechas en hora de Colombia. Hasta siete días y 5.000 puntos por consulta; se advierte cuando el resultado se recorta. Los huecos de más de cinco minutos, cambios de origen/asignación y puntos de baja precisión separan los tramos.
- **Configuración:** abrir/cerrar nuevas vinculaciones, intervalos de envío, umbral de antigüedad, contraseña y últimas acciones administrativas.

Hay cuatro equipos de **demostración** en la instalación local. No son teléfonos conectados. “Simular movimiento” genera ubicaciones cada cinco segundos mientras la pestaña esté activa; al salir o esconderla se detiene. “Eliminar demo” borra solo los equipos y recorridos simulados. El simulador no está disponible en producción.

Para simular desde la terminal, incluso con varios paneles abiertos:

```powershell
php artisan gps:simulate --devices=4 --steps=12 --interval=5
```

El máximo del simulador es 200 equipos; esto no representa capacidad de producción validada. Puede limpiarse con `php artisan gps:simulate --clear`. No se generan tokens móviles para los equipos de demostración.

## Dependencias y desarrollo

Versiones verificadas: Laravel 13.39.0, Sanctum 4.3.3, Reverb 1.12.0, Leaflet 1.9.4, MarkerCluster 1.5.3, Echo 2.6.3, Vite 8.3.5, Node 24.19.0 y Composer 2.10.3. `composer.lock` y `package-lock.json` fijan las versiones instalables.

Desde una terminal con PHP 8.3+, Composer 2 y Node compatible con Vite 8:

```powershell
composer install
npm ci
npm run build
php artisan migrate --seed
```

En una instalación nueva, copiar `.env.example` a `.env`, crear una base MySQL y un usuario limitado a ella, completar la conexión y generar `APP_KEY` con `php artisan key:generate`. Generar claves aleatorias distintas para `REVERB_APP_KEY` y `REVERB_APP_SECRET` antes de compilar los recursos; la primera identifica la aplicación en el navegador, la segunda permanece solo en el servidor. Nunca regenerar `APP_KEY` de una instalación existente como paso rutinario.

En esta computadora hay Composer actualizado en `.tools/composer.phar`; puede usarse `php .tools/composer.phar install`. `Start-Gps.ps1 -Build` recompila los recursos con el Node disponible de esta instalación. `.tools` contiene herramientas locales ignoradas, no archivos necesarios para instalar desde Git.

El sitio se sirve desde Laragon. Para desarrollo visual usar `npm run dev`; para utilizarlo sin ese proceso, `npm run build` y comprobar que no quede un `public/hot` de una sesión anterior. Reverb escucha en `127.0.0.1:8086`. La cola usa MySQL. Logs de procesos: `.runtime/`; errores de Laravel: `storage/logs/laravel.log`.

Crear administrador o recuperar acceso desde la terminal del servidor:

```powershell
php artisan gps:admin administrador@ejemplo.com --generate
php artisan gps:admin admin@gps.local --reset --generate
```

El segundo comando cambia la contraseña y cierra las sesiones de esa cuenta. La contraseña generada se escribe en el archivo privado de acceso, no en la consola. No hay registro público de administradores ni recuperación por correo configurada.

### Pruebas

```powershell
php artisan test --compact
php vendor/bin/pint --dirty --format agent
```

Se verificaron 31 pruebas y 164 aserciones con SQLite en memoria y con MySQL de Laragon en la base independiente `gps_tracking_test`. Las pruebas rechazan cualquier otra base para evitar borrar datos de trabajo. Cubren sesiones, autorización privada, vinculación, sustitución de equipos, revocación, idempotencia, fechas, datos atrasados, precisión, historial y aislamiento del simulador.

Para repetir con MySQL, preparar únicamente `gps_tracking_test`, conceder acceso de prueba a `gps_app` y establecer `DB_CONNECTION=mysql` y `DB_DATABASE=gps_tracking_test` en el proceso de la terminal. No cambiar la base de `.env` ni usar `migrate:fresh` sobre `gps_tracking`. Al finalizar, retirar esas variables del proceso.

## Contrato de la API móvil v1

Base local: `http://gps.test/api/v1`. En dispositivos reales se utilizará una dirección pública **HTTPS**. Todas las peticiones llevan `Accept: application/json`; los cuerpos JSON además llevan `Content-Type: application/json`. Los ejemplos son ilustrativos: generar UUID y fechas actuales en la aplicación.

### 1. Solicitar vinculación

`POST /enrollments`, sin credencial:

```json
{
  "installation_id": "834a763a-b74b-489c-bbea-0aca89f0990c",
  "name": "Teléfono de Juan",
  "platform": "android",
  "model": "Modelo informado por la aplicación"
}
```

`installation_id`: UUID nuevo por instalación, no IMEI ni nombre. Plataformas: `android`, `ios`, `test`. Nombre obligatorio, máximo 100 caracteres; modelo opcional, máximo 100. Respuesta `201`:

```json
{
  "enrollment_id": "uuid-de-la-solicitud",
  "enrollment_secret": "secreto-temporal-entregado-una-vez",
  "status": "pending",
  "expires_at": "2026-10-29T19:00:00+00:00",
  "poll_after_seconds": 10
}
```

Guardar el secreto en almacenamiento seguro de la aplicación. El servidor almacena su hash. La solicitud vence en 24 horas. Registro cerrado: `403`; identidad repetida: `422`; límite de 20 solicitudes/hora por IP: `429`.

### 2. Consultar y activar

`GET /enrollments/{enrollment_id}`, cabecera `Authorization: Bearer <enrollment_secret>`. Devuelve `status`, `expires_at`, `poll_after_seconds`. Estados: `pending`, `approved`, `rejected`, `expired`, `exchanged`.

Tras la aprobación administrativa, `POST /enrollments/{enrollment_id}/exchange`, con el mismo secreto temporal y sin cuerpo. Respuesta `200`:

```json
{
  "device_id": 123,
  "token_type": "Bearer",
  "access_token": "credencial-del-dispositivo-entregada-una-vez"
}
```

El intercambio es único; repetirlo responde `409`. Solicitud vencida: `410`; dispositivo desactivado: `403`. La aprobación abre 24 horas para intercambiar. Estado e intercambio comparten 30 peticiones/minuto por credencial temporal y un límite general de 600/minuto por IP: respetar la espera y `Retry-After`. Cada instalación tiene su propio límite, incluso en una red compartida.

Si la respuesta del registro o intercambio se pierde antes de guardar su secreto/token, este no se puede recuperar. Crear una identidad de instalación nueva y pedir al administrador que rechace/desactive la anterior o apruebe la nueva como reemplazo. Esta recuperación es distinta al reintento idempotente de posiciones.

### 3. Enviar posiciones

`POST /positions`, cabecera `Authorization: Bearer <access_token>`:

```json
{
  "positions": [
    {
      "event_id": "3ec94264-ab75-4649-a565-6876630345a6",
      "recorded_at": "2026-10-28T19:00:00Z",
      "latitude": 4.6534,
      "longitude": -74.0836,
      "accuracy": 12.0,
      "speed": 3.5,
      "heading": 120.0,
      "battery": 85
    }
  ]
}
```

Entre 1 y 200 puntos. UUID de evento estable entre reintentos; fecha ISO 8601 con zona horaria, coordenadas WGS84. Precisión en **metros**, velocidad en **metros/segundo**, dirección en grados `[0, 360)`, batería entera de 0 a 100. Sensores opcionales y con `null` permitido.

Se rechazan fechas más de dos minutos en el futuro o más de un año en el pasado. Precisión mayor a 250 m: se conserva como `low_accuracy`, pero no se usa como última posición ni para unir recorridos. No se infieren sensores ausentes.

Respuesta `200`, incluso con rechazos parciales:

```json
{
  "accepted": 1,
  "results": [
    {"index": 0, "event_id": "3ec94264-ab75-4649-a565-6876630345a6", "status": "accepted", "quality": "usable"}
  ],
  "server_time": "2026-10-28T19:00:01+00:00"
}
```

Cada resultado es `accepted`, `duplicate` (`reason: already_received`) o `rejected` con `errors` o `reason: event_conflict`. Un reintento no modifica el punto guardado. El cliente elimina de su cola solo los eventos aceptados/repetidos, corrige o aparta los rechazados y reintenta con espera los errores transitorios. Lote con estructura inválida: `422`. El historial atrasado no reemplaza una última posición más reciente. La identidad se obtiene del token; enviar otro `device_id` no cambia el destino.

### 4. Estado y configuración

`POST /heartbeat`, con token activo:

```json
{"tracking_state":"active","recorded_at":"2026-10-28T19:00:00Z"}
```

Estados: `active`, `stopped`, `permission_missing`, `gps_disabled`. Fecha: hasta un día de retraso y dos minutos de adelanto. Responde `{"status":"received","server_time":"..."}`. Un estado antiguo no sobrescribe uno reciente. Esta comunicación no crea posiciones.

`GET /configuration`, con token activo, devuelve exclusivamente `name`, `moving_interval_seconds`, `stationary_interval_seconds`, `max_batch_size`, `server_time`. Intervalos iniciales: 15 segundos en movimiento y 60 detenido. Son configuración de partida, no garantías de captura del sistema operativo móvil.

Posiciones, estado y configuración comparten 120 peticiones/minuto por dispositivo. Token inexistente/revocado: `401`; token sin alcance o dispositivo desactivado: `403`. No seguir enviando indefinidamente ante esos códigos: informar el estado y volver al flujo de vinculación cuando corresponda. Las rutas de lectura y el canal privado requieren sesión administrativa, no token móvil.

### Errores generales

| Código | Significado y acción |
| --- | --- |
| 401 | Credencial ausente/no válida; recuperar autorización. |
| 403 | Operación no autorizada, registro cerrado o equipo desactivado. |
| 404 | Solicitud o recurso inexistente. |
| 409 | Estado incompatible o intercambio ya utilizado. |
| 410 | Solicitud vencida. |
| 422 | Validación; revisar `message` y `errors` cuando estén presentes. |
| 429 | Esperar `Retry-After` y aumentar la espera entre reintentos. |
| 5xx / sin conexión | Conservar eventos y reintentar con espera incremental. |

## Operación y próximos pasos

- No hay eliminación automática de historial. Acordar el plazo antes de activarla.
- Leaflet y OpenStreetMap usan atribución y caché normal del navegador. Sin precarga de mapas ni geocodificación. El servicio público no garantiza uso ilimitado o disponibilidad; `MAP_TILE_URL` permite cambiar a infraestructura propia. Ajustar también atribución en `config/tracking.php` si cambia el proveedor. [Política oficial de mapas](https://operations.osmfoundation.org/policies/tiles/).
- Reverb publica avisos de cambio por canal privado; el navegador autorizado consulta las últimas posiciones. La recuperación cada 15 segundos actualiza también los estados antiguos. Los eventos no contienen coordenadas.
- Para producción faltan dominio/HTTPS, cookies seguras, orígenes de Reverb, supervisión de procesos, copias/restauración comprobada, retención y pruebas de capacidad. El servidor público no se ha configurado. La configuración actual es local.
- Antes de crear la aplicación: confirmar Android/iOS, cantidad de equipos, uso continuo o por jornadas e intervalos. Después: piloto real con batería, permisos, pantalla apagada y redes móviles.
- Teléfonos y vehículos comparten el modelo de asignaciones, pero no se decodifican protocolos TCP/UDP de GPS físicos todavía. Se necesita el modelo y protocolo de cada equipo.

Fuentes técnicas: [Laravel](https://laravel.com/docs), [Sanctum](https://laravel.com/framework/docs/13.x/sanctum), [Reverb](https://laravel.com/framework/docs/13.x/reverb), [Leaflet](https://leafletjs.com/reference.html).
