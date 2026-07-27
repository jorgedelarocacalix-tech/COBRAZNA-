# Cobranza La Roca — versión PHP + MySQL (para VPS con cPanel)

Esta carpeta es una versión paralela de la app, adaptada para correr en un
VPS con cPanel usando PHP + MySQL en lugar de Supabase. **No reemplaza** el
`index.html` de la raíz del repo (ese sigue funcionando igual, en Netlify /
GitHub Pages con Supabase, sin cambios). Nada fuera de `php-mysql/` fue
modificado.

## Qué cambia respecto a la versión de Supabase

- **Backend**: PHP + PDO/MySQL en vez de la API REST de Supabase.
- **Autenticación**: sesión de PHP. `login.php` valida el PIN contra la
  tabla `usuarios` (que reemplaza al objeto `USUARIOS` que antes estaba fijo
  dentro del JS) y guarda `$_SESSION['cob_user']` con nombre, rol y las
  carteras asignadas. `logout.php` cierra la sesión.
- **Se quitó el Panel de Análisis IA y el Chat IA (Claude/Anthropic)**.
  La app original llamaba a dos Edge Functions de Supabase
  (`ai-recomendaciones` y `ai-chat`) que a su vez usaban la API de Claude
  Haiku — un costo recurrente de Anthropic + Supabase que Jorge quiere
  eliminar al salir de esos servicios. Esta versión **no reimplementa esa
  llamada a ningún proveedor de IA de pago**. Todo lo que antes se hacía
  "hablándole al bot" (registrar promesa, pago, nota, visita) se sigue
  haciendo en esta versión con los botones/formularios normales que ya
  existían en cada pestaña de cliente (Promesas, Notas, Visitas, etc.) —
  esas partes de la app no dependían de IA y quedaron intactas.
- El resto de la app (parser de PDF con pdf.js, carteras, promesas,
  historial, Pulso ECG, Cobro Mensual, Proyección, Mora Global, Alertas
  manuales) se portó tal cual, solo cambiando la capa de datos.

## Estructura

```
php-mysql/
  schema.sql              — tablas MySQL (importar en phpMyAdmin)
  config.php               — credenciales de la base de datos (editar antes de subir)
  auth.php                 — helpers de sesión/autenticación
  login.php                — valida el PIN contra `usuarios` y abre sesión
  logout.php                — cierra sesión
  api/
    carteras.php            — listar/guardar carteras (clientes + historial de carga)
    promesas.php             — listar/guardar/borrar el estado de gestión por cliente
    historial.php             — bitácora de notas, visitas, pagos, promesas
    snapshots.php               — fotos de tramos de mora por carga de PDF (Pulso ECG)
    alertas.php                  — alertas manuales del equipo + confirmaciones de lectura
    proyecciones.php               — proyección semanal editable por asesores
    cierre_proyeccion.php           — cierre de mes bloqueado (columna "Proyectado")
    mora_overrides.php               — corrección manual de refinanciamiento por cliente
  index.html                — la app (mismo diseño, sin Supabase ni IA)
```

## Modelo de datos (carteras, cuotas, moras, proyecciones)

- **`usuarios`**: PIN, nombre, rol (`admin`/`gerente`/`cobrador`) y las
  carteras asignadas (array JSON). Reemplaza al objeto `USUARIOS` fijo.
- **`carteras`**: una fila por cartera de crédito (ej. `LA_ROCA_COMERCIAL_1`).
  `clientes` es un JSON con el arreglo completo de clientes (nombre, saldo,
  cuota, tramo de mora, día de pago, último pago, etc.) que se sube/parsea
  desde el PDF del ERP — igual que en Supabase, se reescribe entero cada
  vez que se procesa un PDF nuevo.
- **`promesas`**: el estado de gestión vigente por cliente (prometió pagar,
  pagó, abonó, etc.), una fila por `cartera_id + cliente_nombre`.
- **`historial_clientes`**: la bitácora completa de notas, visitas,
  promesas, pagos y abonos por cliente — es lo que alimenta el historial
  que se ve al abrir un cliente.
- **`snapshots`**: una foto de cuántos clientes hay en cada tramo de mora
  (Al día, Vencido, 60/90/120/150+, Inactivo) cada vez que se sube un PDF;
  es lo que dibuja el gráfico "Pulso ECG".
- **`proyecciones`** / **`cierre_proyeccion`**: la proyección de cobro
  semanal que editan los asesores, y el "cierre de mes" que la congela
  (con PIN de 6 dígitos para reabrir edición) — usada en el panel de
  gerente para comparar "Sistema vs Asesores".
- **`mora_overrides`**: correcciones manuales cuando el PDF no refleja bien
  un refinanciamiento (número real de cuotas / crédito total real).
- **`alertas`** / **`alertas_lecturas`**: alertas manuales que el admin le
  manda al equipo (no relacionado con IA) y su registro de "visto".

## Simplificaciones (documentadas, no pixel-perfect)

- **Panel de Análisis IA y Chat IA**: eliminados por completo (ver arriba).
  Antes mostraban un "consejo de la semana" generado por Claude y permitían
  registrar gestiones por lenguaje natural; ahora esas gestiones se
  registran con los formularios normales.
- El resto de las pantallas (Pulso ECG, Cobro Mensual, Proyección,
  Directorio de clientes, Mora Global) se portaron completas porque son el
  corazón del negocio (carteras, cuotas, mora, proyecciones de cobro) y no
  dependen de ningún servicio de pago.
- La sincronización en vivo entre dispositivos (antes cada 3s contra
  Supabase) se mantuvo igual, ahora contra MySQL vía `api/promesas.php` y
  `api/alertas.php`. En hosting compartido de cPanel esto genera una
  consulta cada 3 segundos por usuario conectado — con el equipo actual
  (4 códigos de acceso) no debería ser un problema, pero si el sitio se
  siente lento vale la pena subir ese intervalo (buscar `},3000);` en
  `index.html`, función `startSync`).

## Pasos para desplegar en cPanel

1. **Base de datos**: en cPanel → *MySQL® Databases*, crea una base de datos
   y un usuario con todos los privilegios sobre ella. Anota el nombre de la
   base, el usuario y la contraseña.
2. **Importar el esquema**: en cPanel → *phpMyAdmin*, selecciona la base de
   datos y ejecuta el contenido de `schema.sql` (pestaña "SQL"). Esto crea
   las tablas y los 4 usuarios de fábrica (mismos PINs que hoy: `12345`
   Administrador, `7777` Jorge/gerente, `1111` Equipo Roca, `2222` Equipo
   Su Mueble).
3. **Subir los archivos**: sube toda esta carpeta (`php-mysql/`) al
   directorio público de tu dominio o subdominio (ej.
   `public_html/cobranza/`).
4. **Configurar credenciales**: edita `config.php` en el servidor y
   reemplaza `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` con los datos del
   paso 1.
5. **PINs y carteras asignadas**: si necesitas cambiar un PIN, agregar un
   usuario nuevo o ajustar qué carteras ve cada equipo, edita la tabla
   `usuarios` directamente desde phpMyAdmin (columna `carteras` es un JSON
   como `["ROCA_COMERCIAL","MOTORS"]`). La app original tampoco tenía una
   pantalla para esto — siempre se editó a mano.
6. Abre la URL de tu dominio/subdominio en el navegador — debe pedir el PIN
   igual que la versión actual.

## Migrar los datos existentes (opcional)

Las carteras, promesas, historial, proyecciones, etc. que hoy están en
Supabase no se migran automáticamente — hay que exportarlas (por ejemplo
desde el editor de tablas de Supabase, a CSV o JSON) e importarlas a las
tablas equivalentes en MySQL con phpMyAdmin. Avísame cuando tengas acceso
al servidor y te ayudo con ese paso.

## Cosas para revisar antes de irse a producción

- **No se encontró ninguna API key de pago expuesta en el `index.html`
  original** (el cliente no llama directamente a Anthropic; llamaba a dos
  Edge Functions de Supabase que sí usaban una key de Claude guardada del
  lado del servidor de Supabase, no visible en el navegador). Esa
  dependencia se eliminó por completo en esta versión, tal como se pidió.
- Las fechas de "alertas" y "cierre de mes" ahora se normalizan a la hora
  UTC del servidor PHP (antes las guardaba el navegador vía
  `toISOString()` contra Supabase). Si el VPS y los usuarios están en
  zonas horarias distintas, las horas mostradas en alertas podrían diferir
  por unas horas respecto a la hora local de Honduras — no afecta los
  montos ni las fechas de pago (esas siguen siendo `YYYY-MM-DD` simples),
  solo la hora exacta que se muestra en el banner de alerta.
- Prueba subir un PDF de cada una de las 5 carteras después de desplegar,
  para confirmar que el parser (que corre igual que antes, en el
  navegador) sigue guardando bien contra la base de datos nueva.
