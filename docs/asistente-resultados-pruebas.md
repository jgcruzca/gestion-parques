# Registro de resultados de pruebas del asistente

Fecha de ejecución: 02/11/2026 · Entorno: Pest 3 sobre SQLite en memoria (nunca contra la base real) ·
Reloj congelado en miércoles 14/10/2026 (semana del 12 al 18/10).

## Resumen

| Conjunto | Resultado |
|---|---|
| Pruebas del asistente (`tests/Unit/Asistente`, `tests/Feature/Asistente`) | **175 aprobadas** (906 aserciones) |
| Suite completa del proyecto | 237 aprobadas, **2 fallidas** (1426 aserciones) |
| Reportes, bandeja de mantenimientos y dashboard tras las extracciones | Aprobadas sin cambios en sus pruebas |

### Fallas que ya existían antes del asistente

Línea base medida antes de tocar código: **58 aprobadas y 2 fallidas**. Las mismas dos siguen fallando y no
están relacionadas con el asistente:

1. `ExampleTest › it returns a successful response`
2. `ExtraordinaryMaintenanceTest › an extraordinary maintenance is stored and immediately reflected on the dashboard`
   (espera la propiedad `indicators.maintenances_scheduled`, que el dashboard actual ya no envía).

## Casos de prueba de la especificación (CP-01 a CP-20)

Todos ejecutados en `tests/Feature/Asistente/AsistenteTest.php`.

| ID | Resultado | Notas |
|---|---|---|
| CP-01 Quiero el reporte semanal | Aprobado | Pide el módulo; no genera nada. Flujo completo por turnos cubierto (RF-07). |
| CP-02 Reporte de mantenimientos de esta semana en PDF | Aprobado | Período 12/10–18/10; el enlace descarga un PDF válido. |
| CP-03 "Sácame el informe…" | Aprobado | Cuatro redacciones → misma intención, parámetros y enlace. |
| CP-04 ¿Cuántos parques tenemos? | Aprobado | Conteo real (total, activos, inactivos). |
| CP-05 Muéstrame mantenimientos pendientes | Aprobado | Lista solo los vencidos/para hoy, con desglose. |
| CP-06 ¿Cuántos trabajadores hay? | Aprobado | Conteo real. |
| CP-07 Llévame a parques | Aprobado | Siete redacciones → rutas de la lista cerrada; una URL escrita por el usuario no produce navegación. |
| CP-08 Reporte de ayer en Excel | Aprobado | Pide el módulo. |
| CP-09 Fecha inválida | Aprobado | `31/02/2026` pide corrección; luego acepta una fecha válida. |
| CP-10 Predicción de plagas | Aprobado | Informa que la función no está disponible. |
| CP-11 Elimina todos los parques | Aprobado | Rechazado; los conteos de tablas no cambian. |
| CP-12 Consulta sin registros | Aprobado | `sin_resultados`, sin lista ni datos inventados. |
| CP-13 Usuario sin permisos | **Adaptado** | El sistema no tiene roles. Se prueba y documenta el caso sin sesión (401). |
| CP-14 Solicitud vacía | Aprobado | 422 para vacío, solo espacios, ausente, no-texto y más de 500 caracteres. |
| CP-15 Solicitud duplicada | Aprobado | Con una solicitud en curso, la segunda recibe 409 y no se ejecuta. |
| CP-16 Falla del generador PDF | Aprobado | `estado: error`, sin enlace y sin exponer el detalle técnico. |
| CP-17 Semana pasada en cambio de mes | Aprobado | Cubre cambio de mes, de año y febrero. |
| CP-18 Nombre de parque ambiguo | Aprobado | Ofrece opciones numeradas y no ejecuta hasta elegir. |
| CP-19 HTML o script | Aprobado | No se refleja en la respuesta; en el navegador se muestra como texto y no se ejecuta. |
| CP-20 Falla del asistente | Aprobado | Responde 500 controlado y los seis módulos y la exportación siguen respondiendo 200. |

## Criterios de aceptación

| Criterio | Estado |
|---|---|
| Las 19 intenciones implementadas con pruebas | Cumplido (`InterpretacionTest` y pruebas funcionales por intención) |
| Instrucciones equivalentes → misma intención y parámetros | Cumplido |
| Ambigüedad no ejecuta sin aclaración | Cumplido |
| **Reportes coinciden con los del módulo existente** | Cumplido (ver abajo) |
| Sin operaciones de modificación desde el asistente | Cumplido: una sola ruta `POST`, solo lectura; las pruebas verifican que tablas y estados no cambian |
| Respeta autenticación | Cumplido (`auth` + `verified`, 401 sin sesión). **Autorización por roles: no aplica, el sistema no tiene roles** |
| Funciona sin servicios externos | Cumplido |
| Módulos existentes superan la regresión | Cumplido (sin cambios en sus pruebas; mismas 2 fallas previas) |
| Errores controlados sin interrumpir el sistema | Cumplido |
| Generación y descarga desde el chat | Cumplido (verificado también en navegador) |
| Rendimiento < 1 s sin reportes | Cumplido en pruebas (cuatro consultas, cada una < 1 s) |

## Validación de reportes (sección 9.1)

La prueba `el reporte del asistente coincide con el del módulo de reportes con filtros equivalentes`:

- Pide por chat *"mantenimientos realizados esta semana en Excel"* y, por separado, el mismo reporte con los
  parámetros escritos a mano contra `/reportes/exportar/csv`. **El contenido es idéntico byte a byte.**
- Verifica que incluye los registros de la semana, excluye los de la semana pasada y los vencidos, y que
  `datos.registros` coincide con las filas.
- Repite la comparación sin filtro de estado (todos los mantenimientos de la semana).

Es idéntico por construcción: el asistente entrega el enlace del módulo de reportes (misma ruta, misma
validación, mismo conjunto de filtros). Las comparaciones PDF se verifican por tipo y firma `%PDF-`; no se
comparan byte a byte porque el PDF incluye la hora de generación.

## Pruebas en navegador (copia de la base de datos)

Chrome automatizado, sesión real, base copiada a una carpeta temporal:

- Abrir y cerrar el chat; consulta, aclaración por turnos y descarga del enlace de reporte.
- `<script>alert(1)</script>` y `Elimina todos los parques` se muestran como texto y se rechazan.
- `Llévame a trabajadores` navega y el historial se conserva tras navegar.
- A 360×640 y 480×260 (zoom alto) el panel queda dentro de la pantalla y la página no desborda en horizontal.
- Con un modal abierto, su overlay queda por encima del chat; las demás pantallas siguen sin desbordar entre 320 y 1440 px.

## Limitaciones y decisiones

1. **Excel = CSV.** No existe exportación `.xlsx` en el sistema (decisión acordada).
2. **Zona horaria.** Los períodos relativos usan America/Lima; los estados (vencido/para hoy/próximo) usan la
   fecha del servidor (UTC), igual que el dashboard, para que las cifras coincidan. Entre las 19:00 y las 24:00
   hora de Lima ambas fechas difieren; si se quiere unificar, habría que cambiar `APP_TIMEZONE` y repetir la regresión.
3. **"Próximo".** El módulo de Reportes lo define como "fecha futura", el dashboard y la bandeja usan la ventana
   de alerta. El asistente sigue al dashboard en las consultas; en reportes, al módulo de Reportes.
4. **Roles.** No existen; CP-13 se probó como "sin sesión".
5. **Pruebas del cliente.** El proyecto no tiene corredor de pruebas de React; el componente se verificó con
   TypeScript, ESLint, Prettier y las pruebas en navegador descritas arriba.

## Ajuste: saludo y reinicio diario (29/10/2026)

- **Servidor** (3 pruebas nuevas): `reiniciar: true` descarta la aclaración pendiente; sin la indicación se conserva; un valor no booleano devuelve 422.
- **Navegador**, con la copia de la base de datos y el reloj del navegador controlado:
  - Saludo nuevo y subtítulo "Aquí para ayudarte".
  - La conversación del mismo día se conserva al navegar; una de otro día se descarta al recargar y el primer mensaje del día lleva `reiniciar: true`.
  - Con la pestaña abierta, al cruzar las 00:00 de Lima el chat vuelve al saludo y un "PDF" posterior ya no completa el reporte pendiente del día anterior.
  - Si la medianoche llega con una solicitud en vuelo, su respuesta tardía se descarta y la indicación de reinicio no se pierde.

## Ajuste: hilo conversacional (02/11/2026)

- **Causa:** el servicio solo conservaba la aclaración pendiente. Al resolverla, descartaba todo el contexto y el
  siguiente mensaje se interpretaba desde cero; los seguimientos perdían el parque, el período o el formato.
- **Solución:** `GestorContexto` conserva el último tema resuelto (intención, operación y parámetros) durante
  `ttl_hilo` (30 minutos). `Asistente` distingue una continuación de un tema nuevo antes de heredar.
- **Servidor** (15 pruebas nuevas):
  - `y los vencidos` conserva el parque; `y los de la semana pasada` conserva el parque y cambia el período.
  - `¿y sus mantenimientos?` hereda el parque buscado; `y los de Plaza Norte` cambia el parque.
  - `ahora en Excel` reutiliza el reporte anterior; `y los de la semana pasada` lo actualiza.
  - `en PDF` continúa el tema y pregunta solo el período que falta; `¿y el reporte?` hereda módulo, período y estado.
  - `y el de trabajadores` cambia el módulo del reporte y conserva el formato.
  - `¿y cuántos son?` conserva el tema y cuenta; `Muéstrame los trabajadores` empieza un tema nuevo.
  - `olvídalo` y **Nueva conversación** descartan el tema; `reiniciar: true` también lo hace en el servidor.
  - Un mensaje con una fecha (`y mañana`) conserva el tema y aplica el período; si no hay resultados, lo indica.
- **Cliente:** el chat guarda en `sessionStorage` si el servidor debe reiniciarse; una pestaña nueva sin historial
  visible envía `reiniciar: true` en el primer mensaje para que el hilo del servidor y lo que ve el usuario coincidan.
- **Verificación del cliente:** `npm run build`, TypeScript (sin errores nuevos), ESLint y Prettier. El proyecto no
  tiene un corredor de pruebas de React; la lógica del hilo está cubierta del lado del servidor.

## Ajuste: lenguaje informal y fechas naturales (02/11/2026)

- **Diccionario:** se agregaron formas de pedir (`dame`, `pásame`, `necesito`, `quiero`, `dame lo que…`,
  `hazme`), estados (`faltan`, `sin hacer`, `hechos`, `acabados`, `pasados de fecha`, `en marcha`,
  `suspendidos`, `por empezar`) y acompañantes peruanos (`pe`, `p`, `pues`, `porfa`, `porfis`, `nomás`, `ya`,
  `oe`, `oiga`). `chamba` se entiende como mantenimientos.
- **Abreviaturas:** `q` se normaliza a `que` solo como palabra suelta; no toca códigos como `PRQ-0005` ni `Q-123`.
- **Fechas:** se agregaron rangos de meses (`de septiembre a noviembre`), rangos de días
  (`del 5 al 20 de octubre`), cruces de mes (`del 28 al 3 de octubre`), mes al día
  (`de septiembre al 20 de octubre`), días sueltos (`del día 14`, `del día 14 del mes pasado`),
  `anteayer`, `este año`/`lo que va del año`, `año pasado`, `desde…` hasta hoy y `hasta…` desde el 1 de enero.
- **Validación:** un rango invertido o un "desde" que empieza en el futuro pide corrección; una fecha inexistente
  también.
- **Hilo:** `solo esos`, `esos nomás` y `así está bien` repiten el tema; los estados informales se conservan y se
  pueden cambiar en el seguimiento.
- **Pruebas nuevas (13):**
  - Rangos de meses, días, cruces de mes, mes al día, día suelto y períodos anuales.
  - `desde septiembre` termina hoy; `hasta noviembre` empieza el 1 de enero.
  - `q`, `p`, `pe`, `porfa` y códigos con `Q`.
  - Reportes informales (`dame`, `quiero que me des`, `pásame … en PDF`) y un reporte de estados sin módulo
    (`reporte de los vencidos`) que se entiende de mantenimientos.
  - Estados informales (`necesito los vencidos`, `dame lo que está vencido este mes`,
    `pásame los que faltan`, `pásame los que ya están hechos`) y conteo (`¿cuántos vencidos hay?`).
  - Rango invertido con corrección y rango futuro que pide confirmar.
  - Cambio de parque en pleno hilo (`Villa Maria` → `Comet`) sin perder el estado `vencido` y sin listar parques.


## Ampliacion: catalogo completo de consultas (03/11/2026)

- **Intenciones nuevas (8):** consultar parques con filtros, puntos de agua, resumen agrupado, detalle de una
  orden, programacion y frecuencias, motivos de retraso, catalogos/informacion institucional y alertas
  operativas. El catalogo pasa de 12 a 20 intenciones.
- **Fechas nuevas:** manana, pasado manana, proxima semana, proximo mes y proximo ano, quincena, trimestre,
  semestre, ultimos/proximos N dias o semanas, hace N dias, dias de la semana y anos sueltos.
- **Mantenimientos:** filtros por tipo real del catalogo, origen (periodico/adicional), responsable, cuadrilla,
  cargo, duracion, dias de atraso, documento, fotografia y texto en observaciones; conteos y resumenes por tipo,
  parque, trabajador, estado, zona, mes, cuadrilla y cargo; busqueda y detalle por numero de orden.
- **Parques y agua:** filtros por tipo de area, zona, conservacion, estado activo, superficie, foto, ubicacion y
  observaciones; consultas de punto de agua con estado y abastecimiento; cobertura y porcentajes.
- **Trabajadores:** estado, cargo, cuadrilla, horas registradas, parques atendidos y trabajos asignados.
- **Justificaciones:** listado por parque, tipo, fecha de registro o programada, documento y texto; descarga del
  documento de respaldo autenticado.
- **Reportes:** se conectan los siete tipos existentes (parques, mantenimientos, historial, motivos de retraso,
  trabajadores, alertas y puntos de agua) y los filtros de la consulta viajan al documento.
- **Regla confirmada:** "faltan" o "por hacer" incluye los pendientes futuros del periodo pedido; "vencidos"
  sigue siendo solo lo atrasado.
- **Pruebas nuevas (44 en `ConsultasAmpliadasTest`):** fechas futuras y ventanas moviles, tipos y resumenes,
  detalle de orden, filtros combinados de parques y agua, trabajadores, programacion, catalogos, justificaciones,
  alertas, varios parques, comparacion de meses, continuidad del hilo, lenguaje informal y exportaciones con
  filtros equivalentes.
- **Verificacion:** 175 pruebas del asistente aprobadas (906 aserciones); suite completa 237 aprobadas y las
  mismas 2 fallas previas, ajenas al asistente.
