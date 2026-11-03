# Manual de uso del asistente

**Yaku** es el asistente del sistema: un chat integrado (botón verde flotante, abajo a la derecha, en
todas las pantallas internas). Entiende instrucciones escritas en lenguaje natural y las convierte en
consultas, navegación o reportes. **No usa inteligencia artificial externa**: interpreta el texto con
reglas y un diccionario de sinónimos, por lo que una misma instrucción produce siempre el mismo resultado.

## Saludo y conversación diaria

Al abrir el chat te recibe con un saludo y tres sugerencias para empezar. La conversación **no se conserva de
un día a otro**: empieza de nuevo cada día a las **00:00 (hora de Lima)**.

- Se conserva mientras navegas por el sistema durante el mismo día, y se pierde al cerrar la pestaña.
- El botón **Nueva conversación** (arriba a la derecha del chat) borra el historial visible, descarta la
  solicitud pendiente y olvida el tema anterior, sin afectar al resto del sistema.
- Si dejas la pestaña abierta y pasa la medianoche, el chat vuelve al saludo por sí solo (revisa cada minuto).
- Una pregunta que estaba esperando tu respuesta (por ejemplo, el período de un reporte) también se descarta,
  para que un "PDF" escrito al día siguiente no complete una solicitud del día anterior.

## Continuar la conversación

Yaku recuerda el último tema durante **30 minutos**. Puedes afinar la consulta sin repetir todo:

| Después de… | Puedes decir… |
|---|---|
| `Mantenimientos de Plaza Mayor` | `y los vencidos` · `y los de la semana pasada` · `en PDF` |
| `Busca el parque Plaza Mayor` | `¿y sus mantenimientos?` |
| `Genera el reporte de mantenimientos de esta semana en PDF` | `y los de la semana pasada` · `ahora en Excel` · `y el de trabajadores` |
| `Muéstrame los parques` | `¿y cuántos son?` |
| `Dame lo que está vencido este mes` | `pásame los que ya están hechos` · `solo esos` · `de septiembre a noviembre` |
| `Quiero los mantenimientos vencidos del parque Villa Maria` | `quiero ahora del parque Comet` |

- Se conservan el parque, el trabajador, el período, el estado y el formato del tema anterior; el dato que
  escribas de nuevo reemplaza al anterior.
- Si mencionas un **parque concreto** (`del parque Comet`, `ahora en Comet`), Yaku mantiene el tema de
  mantenimientos y cambia el parque; si dices **"los parques"** en plural, lista los parques.
- `solo esos`, `esos nomás` o `así está bien` repiten el tema tal como está.
- Una consulta con su propio verbo y módulo (`Muéstrame los trabajadores`) empieza un tema nuevo y no
  arrastra los filtros del anterior.
- `olvídalo` descarta el tema anterior; **Nueva conversación** lo descarta junto con el historial visible.
- Pasados 30 minutos, el asistente vuelve a pedir el contexto que falte.

## Qué puede hacer

| Quiero… | Ejemplos |
|---|---|
| Contar | `¿Cuántos parques tenemos?` · `¿Cuántos trabajadores hay?` · `¿Cuántos vencidos hay?` |
| Listar | `Muéstrame los parques` · `Pásame los trabajadores` · `Pásame los mantenimientos que faltan` |
| Buscar un parque | `Busca el parque Plaza Mayor` · `Busca PRQ-0004` |
| Consultar la ficha de un parque | `¿Cuántos metros cuadrados de área verde tiene Villa Maria?` · `¿Dónde queda?` |
| Filtrar parques | `Muéstrame las plazas` · `Parques de la zona Norte` · `Parques activos en mal estado` · `Los parques más grandes` |
| Consultar el punto de agua | `Dame los parques con punto de agua` · `¿Qué parques no tienen agua?` · `Puntos de agua operativos` |
| Ver mantenimientos por fecha o estado | `Dame lo que está vencido este mes` · `¿Qué falta mañana?` · `Pásame los que ya están hechos` |
| Filtrar mantenimientos | `Dame los riegos realizados ayer` · `Podas pendientes de Villa Maria` · `Los adicionales sin responsable` · `Vencidos con más de 7 días de atraso` |
| Ver el historial de un parque o trabajador | `Mantenimientos del parque Plaza Mayor` · `Actividades de Rosa Campos` |
| Ver una orden concreta | `Muéstrame el mantenimiento PM-000014-2026` · `¿Cuánto duró esa orden?` |
| Resumir por tipo, parque o estado | `¿Qué tipos de mantenimiento se hicieron esta semana?` · `¿Cuántos hay de cada tipo?` · `Agrupados por parque` |
| Consultar trabajadores | `Dame los trabajadores activos` · `¿Quiénes son jardineros?` · `Personal de la cuadrilla Norte` |
| Consultar programación y frecuencias | `¿Cada cuántos días se riega en Villa Maria?` · `¿Cuál es la próxima fecha de poda?` · `¿Qué reglas hay?` |
| Consultar motivos de retraso | `Dame los motivos de retraso de este mes` · `¿Qué justificaciones tienen documento?` · `Dame el respaldo de PM-000012-2026` |
| Consultar catálogos e institución | `¿Qué tipos de mantenimiento están registrados?` · `¿Quién figura como responsable del área?` |
| Consultar alertas | `Dame las alertas` · `¿Qué parques están al día?` · `Alertas de Comet` |
| Interpretar varios parques | `Mantenimientos de Comet y Villa Maria` |
| Comparar períodos | `Compara los mantenimientos realizados de octubre y septiembre` |
| Resumen del dashboard | `Dame el resumen del dashboard` · `¿Cómo vamos este mes?` |
| Ir a un módulo | `Llévame a gestión de parques` · `Abre mantenimientos` · `Ir a reportes` · `Abre mi perfil` |
| Generar reportes | `Dame el reporte de mantenimientos de esta semana en PDF` · `Reporte de puntos de agua en Excel` · `Reporte de motivos de retraso en PDF` |
| Ayuda | `Ayuda` · `¿Qué puedes hacer?` |

## Reportes

El asistente **no crea documentos propios**: arma el enlace del módulo de Reportes existente
(`/reportes/exportar/pdf` o `/reportes/exportar/csv`) con los filtros que entendió, de modo que el
documento es el mismo que se obtiene manualmente con filtros equivalentes. Antes de ofrecer el enlace
genera el documento una vez para comprobar que funciona; si falla, avisa y no entrega ningún enlace.

- **Módulos con reporte:** mantenimientos, parques, trabajadores, historial por parque, motivos de retraso,
  alertas y puntos de agua.
- **Mantenimientos** admite período, estado, parque, trabajador, tipo, origen, responsable, duración,
  días de atraso, documento, fotografía y texto. **Parques y agua** admiten tipo, zona, conservación,
  estado activo, punto de agua y superficie. **Trabajadores** admite estado, cargo y cuadrilla.
- **"Realizados"** equivale a *Finalizado* en el sistema (incluye los estados antiguos *completado* y
  *ejecutado*) y filtra por la **fecha de ejecución**. Sin estado, el criterio de fechas es el "operativo"
  del módulo de Reportes (fecha de ejecución y, si no existe, fecha programada).
- **"Excel"** se entrega como **CSV separado por punto y coma, compatible con Excel**. El sistema no
  genera archivos `.xlsx`; el asistente lo indica en su respuesta.
- Si mencionas un **formato y un módulo** (`Pásame los mantenimientos en PDF`), el asistente entiende que
  quieres un reporte, no una lista en el chat.

## Períodos

Se calculan en el servidor, en la zona horaria **America/Lima** (`ASISTENTE_TIMEZONE`).

| Dices… | Significa |
|---|---|
| `hoy` · `ayer` · `anteayer` | El día actual · el anterior · hace dos días |
| `mañana` · `pasado mañana` | El día siguiente · dentro de dos días |
| `hace 3 días` · `dentro de 5 días` | Desplazamiento hacia atrás o hacia adelante |
| `el lunes` · `el viernes pasado` · `el próximo martes` | Día de la semana más reciente · de la semana anterior · de la siguiente |
| `esta semana` · `semanal` · `lo que va de la semana` | Del lunes al domingo de la semana en curso |
| `semana pasada` · `próxima semana` | Del lunes al domingo anterior · de la semana siguiente |
| `este mes` · `mensual` · `lo que va del mes` · `mes pasado` · `próximo mes` | Mes calendario |
| `este año` · `lo que va del año` · `año pasado` · `2025` | Año en curso · año anterior · año indicado |
| `esta quincena` · `este trimestre` · `este semestre` | Períodos en curso |
| `últimos 7 días` · `últimos 30 días` · `próximos 15 días` | Ventanas móviles desde hoy |
| `14/10/2026` · `2026-10-14` · `14 de octubre` · `14/10` | Esa fecha |
| `del 05/10/2026 al 09/10/2026` | Ese rango |
| `octubre` · `octubre de 2025` | Ese mes completo |
| `desde septiembre` | Del **1 de septiembre a hoy** (se muestra el rango usado) |
| `desde septiembre de 2025` | Del **1 de septiembre de 2025 a hoy** |
| `hasta noviembre` | Del **1 de enero al 30 de noviembre** del año correspondiente |
| `de septiembre a noviembre` | Del **1 de septiembre al 30 de noviembre** |
| `de septiembre de 2025 a marzo` | Del **1 de septiembre de 2025 al 31 de marzo de 2026** |
| `del 5 al 20 de octubre` | Del **5 al 20 de octubre** |
| `del 28 de septiembre al 3 de octubre` | Ese rango, cruzando de mes |
| `de septiembre al 20 de octubre` | Del **1 de septiembre al 20 de octubre** |
| `del día 14` | El **14 del mes actual**; si dices `del mes pasado`, el 14 del mes anterior |

- Si solo indicas **"desde…"**, el rango termina **hoy** y la respuesta muestra las fechas usadas.
- Si solo indicas **"hasta…"**, el rango empieza el **1 de enero** del año correspondiente.
- Un rango con el inicio posterior al fin pide corrección; un "desde" que empieza en el futuro pide
  confirmar la fecha.
- Una fecha inexistente (por ejemplo `31/02/2026`) no se acepta: el asistente pide corregirla. Si falta el
  período de un reporte de mantenimientos, **lo pregunta; nunca lo supone**.

> Los **estados** (vencido, para hoy, próximo) siguen el mismo criterio que el dashboard y la bandeja de
> mantenimientos, que usan la fecha del servidor. Por eso las cifras del chat coinciden con las de esas pantallas.

### Abreviaturas y lenguaje informal

- `q` se entiende como **que**: `dame lo q está vencido`.
- `pe`, `p`, `pues`, `porfa`, `porfis`, `ya`, `a ver`, `oe`, `oiga` y `solo` se aceptan como acompañantes y no
  se confunden con nombres de parques o trabajadores.
- `dame`, `pásame`, `necesito`, `quiero`, `muéstrame` y `dame lo que…` piden lo mismo.
- `chamba` se entiende como mantenimientos: `pásame la chamba de esta semana`.
- `solo esos`, `esos nomás` o `así está bien` mantienen el tema anterior sin cambiar los filtros.

## Estados de mantenimiento

| Dices… | Estado |
|---|---|
| `pendientes` · `por atender` · `faltan` · `falta` · `sin hacer` · `por terminar` | **Trabajos pendientes del período pedido**, incluidos los futuros. Sin período, son **vencidos + para hoy** (se informa el desglose). |
| `vencidos` · `atrasados` · `pasados de fecha` | Solo lo atrasado: fecha programada anterior a hoy y sin finalizar |
| `próximos` · `por vencer` · `por empezar` | Dentro de su ventana de alerta |
| `en proceso` · `en curso` · `en marcha` | En proceso |
| `realizados` · `ejecutados` · `finalizados` · `terminados` · `hechos` · `acabados` | Finalizado |
| `cancelados` · `anulados` · `suspendidos` | Cancelado |

> En un **reporte**, "pendientes" no se admite como un solo estado (el módulo de Reportes lo separa en
> vencido, para hoy y próximo), así que el asistente pregunta cuál quieres.

## Tipos, filtros y resúmenes

- **Tipos de mantenimiento:** se reconocen desde el catálogo real (`Riego`, `Poda`, `Limpieza`, etc.) y sus
  sinónimos. Si el tipo no existe, Yaku lo dice; no devuelve todos los registros como si hubiera filtrado.
- **"Qué tipos se hicieron"** devuelve un **resumen por tipo**; **"dame los mantenimientos realizados"**
  devuelve las órdenes una por una.
- **Filtros combinables:** parque, trabajador, cuadrilla o cargo, tipo, origen (periódico o adicional),
  responsable, duración, días de atraso, documento, fotografía y texto en las observaciones.
- **Parques:** tipo de área, zona, conservación, activo/inactivo, superficie mínima o máxima, con o sin foto,
  ubicación y observaciones.
- **Puntos de agua:** con o sin punto, estado registrado y tipo de abastecimiento. Tener un punto de agua
  registrado no demuestra que haya suministro en tiempo real.
- **Resúmenes:** "agrupados por tipo, parque, trabajador, estado, zona, cuadrilla, cargo o mes"; incluyen
  cantidades y horas registradas cuando existen.
- **Acciones típicas:** `quítale el parque`, `quítale la fecha` o `sin filtros` ajustan el tema anterior sin
  empezar de cero. `de todos los parques` también retira el filtro de parque.

## Aclaraciones

Si falta información o es ambigua, el asistente pregunta en lugar de adivinar:

```
Tú:        Quiero un reporte
Asistente: ¿De qué módulo necesitas el reporte? Puedo generarlo de mantenimientos, parques, trabajadores, historial por parque, motivos de retraso, alertas o puntos de agua.
Tú:        Mantenimientos
Asistente: ¿De qué período y en qué formato lo necesitas? Por ejemplo: "esta semana en PDF".
Tú:        esta semana en pdf
Asistente: El reporte está disponible: 2 mantenimientos.   [Descargar reporte-mantenimientos-….pdf]
```

- Responde con una palabra o frase corta: se completa la solicitud pendiente.
- Si la fecha no existe o el rango está invertido, pide corregirlo; puedes responder `del 5 al 20 de octubre`.
- Si el nombre de un parque coincide con varios (`plaza` → *Plaza Mayor*, *Plaza Norte*), te ofrece una lista
  numerada: responde `1`, escribe el nombre o pulsa la opción en el chat.
- Para descartar la solicitud pendiente escribe `olvídalo` o `cancela`. La aclaración caduca a los 10 minutos y se descarta a las 00:00.
- Si haces una pregunta nueva, reemplaza a la pendiente: no se mezclan.
- Si no hay una aclaración pendiente, `olvídalo` descarta el tema anterior recordado para los seguimientos.

## Lo que NO hace

- **No crea, edita ni elimina** registros. Si lo pides (`Elimina todos los parques`) lo rechaza.
- No hace predicciones ni análisis de causas (`predicción de plagas`) y no responde temas ajenos al sistema.
- No aprende de las conversaciones ni entiende cualquier redacción: reconoce las variantes documentadas.
  Solo recuerda el último tema durante 30 minutos para resolver seguimientos.
  Si no identifica la solicitud responde: *"No pude identificar tu solicitud. Puedes pedirme información sobre
  parques, trabajadores, mantenimientos o reportes."*
- No existen roles: cualquier usuario con sesión iniciada puede usarlo, igual que el resto del sistema.

## Seguridad y límites

- Solo disponible con sesión iniciada; las descargas vuelven a verificar la sesión.
- Mensaje máximo de **500 caracteres** y **30 solicitudes por minuto** por usuario (`config/asistente.php`).
- Una solicitud a la vez: mientras una se procesa, otra del mismo usuario se rechaza (evita duplicados).
- El texto se muestra siempre como texto: las etiquetas HTML o scripts no se ejecutan.
- La navegación y las descargas salen de una lista cerrada de rutas; nunca de direcciones escritas por el usuario.
- Los fallos se registran en el log sin el texto del usuario ni datos sensibles, y no afectan a los demás módulos.

## Mantenimiento (para desarrollo)

- Vocabulario y sinónimos: `app/Services/Asistente/Diccionario.php`. Para aceptar una nueva variante basta con
  agregarla en la lista correspondiente y añadir un caso en `tests/Unit/Asistente/InterpretacionTest.php`.
- Ajustes: `config/asistente.php` (zona horaria, longitud, límite, filas por respuesta, caducidad de la aclaración y del hilo).
- La lógica de filtros de reportes y de estados operativos se comparte con los módulos existentes en
  `app/Services/Reports/MaintenanceReportFilters.php` y `app/Services/Maintenance/OperationalScope.php`.
