# Plan de trabajo: plataforma de seguimiento GPS

Fecha: 28 de octubre de 2026.  
Proyecto: `C:\laragon\www\gps`.  
Estado: primera versión web local implementada y verificada en Laragon; aplicación móvil y despliegue público pendientes.

## 1. Objetivo y alcance

Construir un sistema Laravel que reciba ubicaciones de varios teléfonos y permita al administrador verlos simultáneamente en un mapa grande, consultar sus recorridos y administrar los dispositivos. Después se construirá una aplicación móvil sencilla dedicada a enviar ubicación. La arquitectura permitirá incorporar GPS físicos instalados en vehículos.

Este documento es la guía para ejecutar el trabajo por partes. La instalación y el sistema web fueron autorizados posteriormente y su avance se registra en las casillas y la bitácora.

### Decisiones confirmadas

- El proyecto se desarrollará en esta carpeta de Laragon.
- Primero se construye el sistema web y después la aplicación móvil.
- Solo el administrador puede consultar ubicaciones e historial, tanto en las pantallas como en los servicios del servidor.
- La aplicación móvil sirve para seguimiento: no muestra mapas, recorridos ni ubicaciones propias o ajenas.
- La vinculación no utiliza QR ni requiere escanear códigos.
- La aplicación lleva la dirección del servidor preconfigurada; el administrador aprueba cada instalación desde el panel.
- El mapa general muestra simultáneamente los dispositivos y permite pantalla completa.
- Se conservará historial de ubicaciones para consultar recorridos.
- La aplicación mostrará el estado del seguimiento, solicitará permisos y permitirá iniciarlo y detenerlo. El seguimiento será visible para quien use el teléfono.
- Cada instalación tendrá una identidad y una credencial propias. El nombre visible no será su identificador de seguridad.
- No se contratarán suscripciones cartográficas ni otros servicios externos de pago. La infraestructura se apoyará en el servidor del propietario.
- Se contemplan teléfonos y, en una etapa posterior, GPS físicos para vehículos.

### Decisiones pendientes

Estas decisiones se resolverán en la etapa correspondiente. No deben convertirse en requisitos confirmados sin una respuesta del propietario.

| Decisión | Propuesta de partida | Momento límite |
| --- | --- | --- |
| Plataforma móvil | Android primero; Kotlin si se confirma Android | Antes de implementar la aplicación |
| Cantidad inicial y prevista de dispositivos | Pendiente; usar una cantidad configurable en el simulador | Antes de cerrar pruebas de capacidad |
| Horario de seguimiento | Inicio y detención visibles; definir jornadas o uso continuo | Antes de implementar el servicio móvil |
| Frecuencia de captura y envío | Ensayar 10–15 segundos en movimiento y 60 segundos detenido | Antes del piloto; ajustar con mediciones |
| Conservación de historial | Evaluar 30 días inicialmente | Antes de configurar cualquier eliminación automática |
| Administradores | Un administrador inicial; confirmar si habrá más | Antes de finalizar permisos y cuentas |
| Separación por clientes o empresas | Fuera de la primera versión salvo nueva confirmación | Antes de diseñar acceso de terceros |
| Servidor y dirección pública | Confirmar sistema operativo, capacidad y dominio o subdominio disponible | Antes de pruebas externas |
| Distribución de la aplicación | APK para pruebas si se confirma Android | Antes del piloto móvil |

No fijar versiones de Laravel, PHP o dependencias hasta revisar la compatibilidad del entorno. Los intervalos de ubicación serán objetivos de operación, no garantías exactas del sistema móvil.

## 2. Arquitectura propuesta

```text
Aplicación del teléfono
  -> API HTTPS de Laravel
      -> Identificación, permisos y validación
      -> Historial y última posición
      -> Eventos privados
          -> Panel del administrador y mapa general

GPS físico de vehículo, en una etapa posterior
  -> Adaptador del protocolo del equipo
      -> Mismo proceso de almacenamiento y actualización del mapa
```

El servidor continúa recibiendo posiciones aunque nadie tenga abierto el panel. El teléfono transmite por HTTPS; las actualizaciones en vivo conectan Laravel con los navegadores del administrador.

| Parte | Propuesta | Finalidad |
| --- | --- | --- |
| Sistema y API | Laravel, en un mismo proyecto | Administración y recepción de ubicaciones |
| Base de datos | MySQL, sujeto a revisión de Laragon | Dispositivos, credenciales, posiciones e historial |
| Interfaz | Blade y componentes JavaScript | Pantallas de administración e interacción con el mapa |
| Mapa | Leaflet y cartografía de OpenStreetMap | Marcadores, recorridos y vista general |
| Actualización en vivo | Laravel Reverb y Echo | Cambios sin recargar la página |
| Credenciales móviles | Laravel Sanctum con permisos por dispositivo | Envíos autenticados y revocables |
| Tareas del servidor | Colas y tareas programadas de Laravel | Emisión de eventos y mantenimiento |
| Aplicación | Kotlin si se confirma Android | Ubicación, almacenamiento temporal y envío |

### Presupuesto y cartografía

- Leaflet no cobra por marcadores ni por actualizaciones de posiciones.
- Las coordenadas y los recorridos se guardan en nuestro servidor.
- Las posiciones nuevas actualizan los marcadores; no deben recargar toda la cartografía.
- El mapa inicial utilizará el servicio público de OpenStreetMap respetando sus condiciones: atribución visible, caché normal y ausencia de descargas masivas o precarga para uso sin conexión.
- El servicio público no ofrece una cuota ilimitada garantizada ni disponibilidad contractual. No contratar un proveedor de pago como solución automática.
- El proveedor cartográfico quedará configurable. Si el uso supera lo apropiado para el servicio público, evaluar cartografía alojada en infraestructura propia y su consumo de disco, memoria y transferencia antes de ampliar el despliegue.
- Guardar posiciones móviles sin internet no implica descargar mapas sin conexión.
- La primera versión no consultará una dirección postal por cada punto ni incluirá cálculo externo de rutas. Dibujar el recorrido registrado no necesita esos servicios.

## 3. Reglas de datos y acceso

### Datos principales

| Entidad | Responsabilidad |
| --- | --- |
| Administradores | Acceso al panel y permisos para consultar información |
| Solicitudes de vinculación | Instalaciones pendientes, aprobadas, rechazadas o vencidas |
| Dispositivos | Identidad, tipo de origen, nombre, configuración y estado administrativo |
| Credenciales | Autenticación individual, alcance limitado y revocación |
| Elementos rastreados y asignaciones | Relacionar un dispositivo con un teléfono o vehículo y conservar los cambios de asignación |
| Posiciones | Historial con datos de cada captura y su recepción |
| Última posición | Consulta eficiente de la posición más reciente sin recorrer todo el historial |
| Registro de acciones | Aprobaciones, rechazos, cambios de asignación y desactivaciones |

Definir el modelo mínimo de elementos rastreados en la etapa 2. Los datos específicos de vehículos se añadirán después. Un cambio de dispositivo o asignación no debe adjudicar recorridos históricos al elemento incorrecto.

### Reglas obligatorias

- Una credencial de dispositivo no puede consultar posiciones ni historial; solo las operaciones de envío y estado/configuración propios que se definan.
- La sesión del administrador y las credenciales móviles tienen permisos separados.
- El servidor obtiene la identidad del dispositivo desde su autenticación, no desde un identificador arbitrario enviado por el cliente.
- Los canales de actualización del mapa son privados y autorizados para administradores.
- Registrar por separado la hora de captura y la hora de recepción. Guardar fechas en UTC y mostrarlas inicialmente en la zona `America/Bogota`.
- Diferenciar última comunicación, última posición válida y estado de seguimiento informado. Recibir una posición antigua no demuestra que exista una posición actual.
- Cada punto lleva un identificador de evento para impedir duplicados ante reintentos.
- Un punto retrasado puede completar el historial, pero no reemplazar la última posición si su captura es anterior.
- Validar coordenadas, fechas y rangos de sensores. Establecer un tratamiento para relojes incorrectos y puntos con mala precisión.
- Usar coordenadas WGS84, precisión en metros y unidades de velocidad explícitas en el contrato.
- Batería, velocidad, dirección y otros sensores pueden faltar; no convertir ausencia de información en valores cero.
- El historial debe conservar el origen y la asignación temporal correctos, también cuando se reciban lotes atrasados.
- La ausencia de reportes no permite afirmar por sí sola que el teléfono está apagado: el panel mostrará la antigüedad y el estado conocido.
- No aplicar eliminación automática del historial hasta definir su plazo de conservación.

## 4. Forma de ejecutar este plan

1. Trabajar sobre una etapa o un conjunto de pasos claramente indicado en cada solicitud.
2. Resolver los pendientes que afecten a esa etapa; continuar con trabajo independiente cuando sea posible.
3. Completar y verificar el entregable antes de marcarlo como terminado.
4. Actualizar las casillas y la bitácora de este archivo con resultados reales.
5. Registrar cualquier cambio de alcance o dependencia que altere los siguientes pasos.

El avance se mide por funcionamiento comprobado. No marcar una etapa como terminada solo porque sus archivos ya existan.

## 5. Etapas de construcción

### Etapa 0. Cerrar el alcance de la primera versión

- [x] 0.1. Documentar las decisiones de la conversación y dividir el proyecto por etapas.
- [ ] 0.2. Confirmar plataforma móvil, cantidad de dispositivos y modalidad de seguimiento.
- [ ] 0.3. Confirmar quién administrará el sistema y si existe necesidad inmediata de separar clientes.
- [ ] 0.4. Definir objetivos de frecuencia, conservación de historial y capacidad inicial.
- [x] 0.5. Confirmar las pantallas de la primera versión: acceso, mapa, dispositivos, pendientes, detalle, historial y configuración.

**Entregable:** alcance definido y decisiones pendientes identificadas con su etapa límite.

**Criterio de cierre:** los requisitos que cambian la arquitectura inicial están resueltos; las decisiones posteriores tienen un punto de resolución documentado.

### Etapa 1. Preparar el proyecto Laravel en Laragon

**Depende de:** alcance inicial de la etapa 0.

- [x] 1.1. Revisar PHP, Composer, Node, base de datos y servidor web disponibles.
- [x] 1.2. Elegir una versión compatible y mantenida de Laravel y fijar sus dependencias.
- [x] 1.3. Inicializar Laravel conservando este plan. La carpeta ya no estará vacía: no borrar ni sobrescribir el documento durante la instalación.
- [x] 1.4. Configurar el sitio local de Laragon con su raíz pública apuntando a `public`.
- [x] 1.5. Configurar variables de entorno, conexión a base de datos y manejo seguro de secretos.
- [x] 1.6. Crear el repositorio Git si no existe y excluir secretos, dependencias y archivos temporales.
- [x] 1.7. Comprobar arranque del sitio, migraciones, compilación de recursos y configuración de colas.

**Entregable:** proyecto Laravel funcional en el entorno local y código bajo control de versiones.

**Criterio de cierre:** el sitio abre correctamente, accede a la base de datos y puede prepararse desde las instrucciones del proyecto sin exponer secretos.

### Etapa 2. Construir acceso y modelo de datos

**Depende de:** etapa 1.

- [x] 2.1. Implementar inicio y cierre de sesión del administrador, sin registro público de cuentas administrativas.
- [x] 2.2. Definir un procedimiento de creación y recuperación de acceso del administrador.
- [x] 2.3. Crear migraciones y relaciones para dispositivos, vinculaciones, credenciales, posiciones y última posición.
- [x] 2.4. Definir la relación con elementos rastreados y asignaciones, evitando perder el origen del historial al cambiar un dispositivo.
- [x] 2.5. Separar estado administrativo, estado de seguimiento y antigüedad de comunicación/ubicación.
- [x] 2.6. Crear índices para consultas por dispositivo y fecha, unicidad de eventos y última posición.
- [x] 2.7. Implementar reglas de acceso para el panel, la API y los futuros canales privados.
- [x] 2.8. Probar que visitantes y credenciales de dispositivos no pueden leer ubicaciones ni administrar cuentas.

**Entregable:** estructura de datos y acceso exclusivo del administrador.

**Criterio de cierre:** las restricciones se cumplen también al llamar directamente a los servicios, aunque se omita la interfaz.

### Etapa 3. Implementar vinculación sin escaneo

**Depende de:** etapa 2.

- [x] 3.1. Diseñar un contrato versionado de la API y documentar respuestas y errores.
- [x] 3.2. Implementar solicitudes de instalación con nombre e identidad generada por la aplicación, sin depender del nombre ni del IMEI como autenticación.
- [x] 3.3. Limitar la frecuencia de solicitudes, establecer su vencimiento y proteger el acceso a su estado con una credencial temporal.
- [x] 3.4. Crear la bandeja de pendientes con aprobación y rechazo del administrador. Mostrar datos suficientes para reconocer la instalación esperada; no confiar solo en un nombre declarado.
- [x] 3.5. Permitir que el administrador habilite o cierre la recepción de nuevas vinculaciones para reducir registros no solicitados.
- [x] 3.6. Entregar la credencial activa únicamente a la instalación aprobada, mediante un intercambio autenticado y no reutilizable.
- [x] 3.7. Implementar desactivación, revocación y revinculación tras reinstalación, conservando el historial previo.
- [x] 3.8. Registrar las acciones administrativas y probar solicitudes vencidas, rechazadas, repetidas y desactivadas.

**Entregable:** flujo completo de registro y aprobación probado con un cliente de prueba.

**Criterio de cierre:** una instalación aprobada obtiene acceso limitado; una instalación pendiente o rechazada no puede enviar posiciones ni obtener datos de otras instalaciones.

### Etapa 4. Recibir ubicaciones y construir el simulador

**Depende de:** etapa 3.

- [x] 4.1. Definir el formato de punto: evento, hora de captura, coordenadas, precisión y sensores opcionales con unidades documentadas.
- [x] 4.2. Implementar recepción de puntos individuales y lotes con tamaño limitado.
- [x] 4.3. Validar fechas, rangos y estructura; responder qué eventos fueron aceptados, repetidos o rechazados.
- [x] 4.4. Guardar historial y actualizar la última posición de forma consistente, incluso con envíos simultáneos.
- [x] 4.5. Implementar deduplicación y manejo de puntos atrasados, relojes incorrectos y precisión insuficiente.
- [x] 4.6. Recibir el estado propio del seguimiento sin confundirlo con una posición GPS nueva.
- [x] 4.7. Crear un simulador configurable de varios dispositivos y recorridos, restringido al entorno de desarrollo/pruebas.
- [x] 4.8. Probar pérdida de respuesta, reintentos, lotes desordenados, datos inválidos y credenciales revocadas.

**Entregable:** API de ubicación comprobada y herramienta para probar el sistema antes de crear la aplicación.

**Criterio de cierre:** varios dispositivos reportan de forma independiente; no se duplican puntos ni retrocede la última posición por recibir historial atrasado.

### Etapa 5. Construir administración y mapa general

**Depende de:** etapa 4.

- [x] 5.1. Construir navegación y pantallas administrativas en español.
- [x] 5.2. Añadir listado de dispositivos con nombre, aprobación, estado y última comunicación.
- [x] 5.3. Integrar Leaflet y la cartografía gratuita con atribución visible, caché correcta y proveedor configurable.
- [x] 5.4. Crear el mapa como pantalla principal, ocupando la mayor parte del espacio.
- [x] 5.5. Mostrar marcadores identificables y una lista lateral plegable con búsqueda y filtros.
- [x] 5.6. Añadir pantalla completa, botón “Ver todos” y selección de un dispositivo.
- [x] 5.7. Diferenciar posiciones recientes y antiguas con texto y aspecto visual; mostrar batería y precisión cuando existan.
- [x] 5.8. Agrupar marcadores cercanos cuando su cantidad lo requiera y mostrar explícitamente los dispositivos que aún no tienen ubicación.
- [x] 5.9. Crear el detalle del dispositivo y estados vacíos, errores y fallos de carga de cartografía.

**Entregable:** panel y mapa grande utilizables con datos simulados.

**Criterio de cierre:** el administrador localiza todos los dispositivos, identifica datos atrasados y puede trabajar en pantalla completa sin perder los controles esenciales.

### Etapa 6. Actualización en vivo e historial

**Depende de:** etapa 5.

- [x] 6.1. Configurar Reverb, Echo y el proceso de colas necesario.
- [x] 6.2. Autorizar canales privados exclusivamente para sesiones administrativas válidas.
- [x] 6.3. Publicar cambios después de guardar las posiciones y actualizar únicamente los marcadores afectados.
- [x] 6.4. Implementar reconexión y resincronización desde el servidor si se pierde la conexión de eventos.
- [x] 6.5. Mostrar el estado de conexión del panel y permitir seguimiento visual opcional de un dispositivo sin mover involuntariamente el mapa.
- [x] 6.6. Crear consulta de historial por dispositivo/elemento y rango de fechas, con límites de tamaño.
- [x] 6.7. Dibujar recorridos, inicio y final; distinguir huecos de datos para no presentar una línea entre puntos distantes como trayecto comprobado.
- [x] 6.8. Limitar o simplificar la representación de recorridos extensos sin alterar los datos originales almacenados.
- [ ] 6.9. Probar varias sesiones administrativas, desconexión del navegador y reinicio de los procesos de eventos. **Parcial:** reinicio de Reverb/cola y reconexión comprobados; quedan sesiones independientes y desconexión de red en el piloto.

**Entregable:** mapa actualizado automáticamente e historial consultable.

**Criterio de cierre:** las ubicaciones cambian sin recargar; al reconectar se recupera el estado actual y el historial respeta el rango de fechas y las asignaciones.

### Etapa 7. Preparar un entorno accesible por internet

**Depende de:** API estable de la etapa 4; panel de etapas 5 y 6 para validar el flujo completo.

- [ ] 7.1. Revisar el servidor disponible, su sistema operativo, capacidad y dirección pública.
- [ ] 7.2. Definir dominio o subdominio disponible y HTTPS, sin contratar servicios adicionales automáticamente.
- [ ] 7.3. Preparar despliegue de Laravel, base de datos, recursos compilados y configuración de producción.
- [ ] 7.4. Ejecutar y supervisar servidor web, colas, Reverb y tareas programadas con recuperación tras reinicio.
- [ ] 7.5. Restringir acceso a la base de datos, desactivar depuración pública y evitar secretos o ubicaciones completas en registros innecesarios.
- [ ] 7.6. Configurar copias de seguridad y comprobar una restauración en un entorno separado.
- [ ] 7.7. Probar recepción autenticada desde otra red, conexión segura del mapa y recuperación tras reiniciar servicios.

**Entregable:** entorno de pruebas accesible desde datos móviles y dirección estable para la aplicación.

**Criterio de cierre:** el flujo funciona fuera de la red de Laragon y no depende de mantener una terminal abierta manualmente.

### Etapa 8. Construir la aplicación móvil sencilla

**Depende de:** plataforma confirmada, contrato de API estable y entorno externo de la etapa 7.

Los siguientes pasos presuponen Android si se confirma esa plataforma. Si también se requiere iPhone, revisar permisos, ejecución, herramientas y distribución antes de ampliar esta etapa.

- [ ] 8.1. Crear el proyecto móvil y documentar plataformas/versiones admitidas.
- [ ] 8.2. Preconfigurar la dirección del servidor y separar entornos de prueba y operación.
- [ ] 8.3. Implementar nombre inicial, solicitud de vinculación y estado pendiente/aprobado/rechazado, sin escaneo.
- [ ] 8.4. Guardar la identidad y credencial en almacenamiento apropiado de la plataforma, sin credenciales administrativas.
- [ ] 8.5. Diseñar una pantalla mínima: dispositivo, estado, último envío, permisos y controles de inicio/detención.
- [ ] 8.6. Implementar solicitud de permisos y validar el seguimiento con pantalla apagada en un teléfono real antes de ampliar funciones.
- [ ] 8.7. Implementar el servicio de ubicación visible y la notificación requerida; evaluar permisos adicionales solo cuando el funcionamiento los necesite.
- [ ] 8.8. Capturar y enviar posiciones con frecuencia configurable y tratamiento de precisión, movimiento y consumo de batería.
- [ ] 8.9. Guardar eventos pendientes localmente y reintentar con espera progresiva. Borrar eventos pendientes únicamente después de su confirmación de recepción.
- [ ] 8.10. Definir capacidad de almacenamiento pendiente y mostrar problemas de espacio sin pérdida silenciosa de datos.
- [ ] 8.11. Manejar pérdida de internet, GPS desactivado, permisos revocados, batería restringida y desactivación desde el administrador.
- [ ] 8.12. Definir y probar qué ocurre al reiniciar el teléfono, cerrar la interfaz y forzar la detención. Documentar las acciones de reanudación que requiera el sistema operativo.
- [ ] 8.13. Generar una versión instalable de prueba con firma, versión y procedimiento de actualización documentados.

**Entregable:** aplicación instalable que solo envía ubicación y muestra su propio estado operativo.

**Criterio de cierre:** un teléfono se vincula sin QR, reporta cuando está aprobado y activo, conserva envíos durante una desconexión y no tiene acceso a consultas de ubicación.

### Etapa 9. Piloto y puesta en funcionamiento

**Depende de:** etapas 1 a 8.

- [ ] 9.1. Ejecutar pruebas con al menos dos teléfonos reales simultáneamente y varios recorridos.
- [ ] 9.2. Probar pantalla apagada, cambios Wi-Fi/datos móviles, periodos sin cobertura y recuperación de envíos.
- [ ] 9.3. Medir batería, datos móviles, demora entre captura y visualización, volumen de puntos y recursos del servidor.
- [ ] 9.4. Verificar que la aplicación y usuarios no administrativos no puedan consultar ubicaciones por ningún servicio.
- [ ] 9.5. Probar desactivación, reinstalación y revinculación sin adjudicar incorrectamente el historial.
- [ ] 9.6. Ejecutar una prueba de capacidad con la cantidad y frecuencia acordadas, sin generar carga artificial sobre los mapas públicos.
- [ ] 9.7. Ajustar intervalos, umbrales de datos atrasados, índices y representación del mapa según las mediciones.
- [ ] 9.8. Implementar conservación y eliminación programada solo con el plazo ya definido; documentar su relación con las copias de seguridad.
- [ ] 9.9. Verificar uso adecuado de cartografía y decidir si la infraestructura inicial soporta la operación prevista sin servicios de pago.
- [ ] 9.10. Documentar instalación, uso administrativo, operación, recuperación y limitaciones conocidas de los teléfonos probados.
- [ ] 9.11. Preparar la versión de operación y comprobar instalación/actualización sin pérdida de datos.

**Entregable:** primera versión operativa, documentación y resultados del piloto.

**Criterio de cierre:** se cumplen los criterios de aceptación de la sección 6 con evidencia registrada y sin fallos pendientes que impidan el seguimiento acordado.

### Etapa 10. Incorporar GPS físicos para vehículos

**Etapa futura; no condiciona la entrega inicial con teléfonos.**

- [ ] 10.1. Elegir modelos concretos de GPS y obtener documentación de su protocolo, conexión y configuración de destino.
- [ ] 10.2. Confirmar si requieren conexión TCP, UDP, HTTP u otro mecanismo y evaluar un adaptador apropiado alojado en infraestructura propia.
- [ ] 10.3. Definir la identificación/autenticación admitida por el equipo; no tratar un identificador declarado como prueba suficiente de autenticidad.
- [ ] 10.4. Normalizar sus mensajes al modelo común de posiciones, incluyendo confirmaciones, reintentos y datos opcionales.
- [ ] 10.5. Añadir datos de vehículos, como placa y alias, y asignaciones con fechas de vigencia.
- [ ] 10.6. Añadir encendido, alimentación u otros sensores únicamente cuando el modelo de GPS los reporte.
- [ ] 10.7. Probar conjuntamente teléfonos y GPS físicos en el mismo mapa, con sus respectivos historiales.
- [ ] 10.8. Probar cambio de dispositivo en un vehículo y recepción tardía de puntos sin mezclar asignaciones.

**Entregable:** integración comprobada con los modelos de GPS seleccionados.

**Criterio de cierre:** un GPS físico reporta al mismo sistema y aparece en el mapa e historial sin modificar el contrato móvil existente.

Un GPS físico suele utilizar su propio firmware; no se presupone que admita instalar la aplicación del teléfono. La compatibilidad se confirma por modelo y protocolo. También habrá que considerar su conectividad móvil como costo operativo independiente del mapa.

## 6. Criterios de aceptación de la primera versión

- [x] Solo el administrador puede ver ubicaciones actuales e historial.
- [x] Los dispositivos se vinculan sin escaneo y requieren aprobación.
- [x] Una instalación pendiente, rechazada o desactivada no puede enviar posiciones nuevas.
- [ ] La aplicación permite activar/detener seguimiento y muestra su estado sin incluir mapas ni consultas de ubicación.
- [ ] Se visualizan varios teléfonos simultáneamente en un mapa grande con pantalla completa.
- [x] Los marcadores se actualizan sin recargar la página ni descargar nuevamente todo el mapa.
- [x] El panel distingue datos recientes, antiguos y dispositivos sin ubicación.
- [x] El historial puede filtrarse por dispositivo y fechas, sin mezclar asignaciones.
- [x] Los reintentos no duplican posiciones y los lotes atrasados no hacen retroceder la última posición.
- [ ] Se recuperan los envíos pendientes después de una pérdida de internet.
- [ ] Se prueba seguimiento con pantalla apagada en los modelos móviles del piloto y se documentan sus limitaciones.
- [ ] El sistema funciona desde datos móviles mediante HTTPS.
- [ ] Los procesos del servidor se recuperan tras reinicios y existe una restauración de copia de seguridad comprobada.
- [x] No existe una dependencia obligatoria de servicios externos de pago.
- [ ] Se documentan capacidad validada, frecuencia, conservación del historial y consumo observado.

## 7. Funciones reservadas para etapas posteriores

- Alertas por zonas, velocidad, batería o ausencia de reportes.
- Reportes exportables y resúmenes de distancias/paradas con reglas de cálculo definidas.
- Separación por clientes o empresas y nuevos perfiles de acceso, si se solicitan.
- Aplicación para iPhone, si no se incluye en el alcance inicial.
- Direcciones postales, navegación o cálculo de rutas, previa evaluación de infraestructura y presupuesto.
- Cartografía alojada en infraestructura propia cuando el volumen lo justifique.

## 8. Bitácora

| Fecha | Etapa | Avance comprobado | Siguiente paso |
| --- | --- | --- | --- |
| 2026-10-28 | Planificación | Carpeta revisada y encontrada vacía. Creado este plan con las decisiones acordadas. No se ha instalado Laravel ni desarrollado código de la aplicación. | Resolver los pendientes de alcance que afecten al inicio y después ejecutar la etapa 1. |
| 2026-10-28 | Etapa 1 | Laravel 13.39.0 instalado con PHP 8.3.33, MySQL 8.0.30, Sanctum, Reverb, Leaflet, Echo y recursos compilados. Sitio http://gps.test con raíz public y archivos privados protegidos. Git inicializado; secretos y temporales excluidos. | Mantener Apache/MySQL activos y ejecutar Start-Gps.ps1 después de reinicios. |
| 2026-10-28 | Etapas 2–4 | Acceso administrativo, modelo de asignaciones, aprobación sin QR, tokens de solo envío, API v1 documentada, validación, deduplicación, revocación, historial atrasado y simulador local implementados. | Conectar la aplicación cuando se construya. |
| 2026-10-28 | Etapas 5–6 | Interfaz en español, mapa general/pantalla completa, filtros, selección, agrupación, actualización privada y consultas de recorridos. Cuatro equipos simulados verificados visualmente. Caída/reinicio de eventos recupera la conexión; consulta alternativa cada 15 s. | Completar prueba de sesiones independientes y piloto real. |
| 2026-10-28 | Verificación | 31 pruebas, 164 aserciones aprobadas en SQLite y en MySQL separado gps_tracking_test. Compilación de recursos y formato comprobados. Acceso a .env y archivo de credenciales por HTTP rechazado; sin trabajos fallidos en cola al cerrar la revisión. | Etapas 7–9: servidor público, móvil, capacidad, recuperación y operación. |

### Entrega local y límites de esta verificación

- Abrir **http://gps.test**. Acceso inicial en `storage/app/private/ACCESO_LOCAL.txt`, fuera del directorio público y de Git.
- Ejecutar `Start-Gps.ps1` para iniciar eventos y cola; Apache/MySQL se gestionan desde Laragon. No se instaló arranque automático con Windows.
- Usar el botón de demostración para comprobar movimiento; los cuatro equipos son ficticios. La visualización simultánea se probó con esos equipos, no con teléfonos reales.
- La cuenta inicial es única. Los intervalos de 15/60 segundos y antigüedad de 120 segundos son valores iniciales configurables, sujetos al piloto.
- La API no permite leer ubicaciones con credenciales móviles. El móvil no está construido todavía y su consumo, permisos y seguimiento en segundo plano no se han medido.
- No hay eliminación automática ni prueba de capacidad de producción. No se ha configurado el servidor público ni HTTPS; backups/restauración y supervisión permanente quedan en la etapa 7.
- El modelo admite elementos de tipo vehículo, referencia/placa y asignaciones con fechas. Los protocolos de GPS físicos permanecen en la etapa 10.
## 9. Referencias técnicas

Las dependencias y políticas se comprobarán de nuevo cuando se implemente la etapa correspondiente.

- Laravel: https://laravel.com/docs
- Autenticación de API con Sanctum: https://laravel.com/framework/docs/13.x/sanctum
- Eventos y actualizaciones en vivo: https://laravel.com/framework/docs/13.x/broadcasting
- Leaflet: https://leafletjs.com/reference
- Condiciones del servicio público de mapas: https://operations.osmfoundation.org/policies/tiles/
- Permisos de ubicación en Android: https://developer.android.com/develop/sensors-and-location/location/permissions

**Próxima ejecución prevista:** revisar la primera versión en http://gps.test, confirmar plataforma y cantidad de teléfonos, preparar el entorno HTTPS (etapa 7) y después construir la aplicación móvil (etapa 8). El contrato de API y las instrucciones locales están en README.md.
