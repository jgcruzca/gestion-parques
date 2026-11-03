# Sistema Verde — Gestión de parques y mantenimiento

Aplicación web para administrar parques, plazas y demás áreas verdes, programar sus mantenimientos, asignar cuadrillas y consultar indicadores operativos desde un solo lugar. Pensada para cualquier organización: municipios, urbanizaciones, empresas, universidades o jardineros independientes.

Cada persona que se registra obtiene un **espacio privado**: sus parques, trabajadores, mantenimientos, configuración y reportes no son visibles para nadie más. Al crear la cuenta se cargan catálogos editables y unos pocos **datos de demostración ficticios** que se eliminan con un clic desde *Configuración*.

## Funcionalidades

- **Dashboard operativo:** indicadores, metas mensuales, tendencias, alertas y mantenimientos prioritarios.
- **Parques y áreas verdes:** registro, ubicación en mapa (cualquier lugar del mundo), fotos, zonas, conservación y puntos de agua.
- **Mantenimientos:** programación periódica por reglas de frecuencia o extraordinaria, seguimiento por estados y partes de atención en PDF.
- **Auditoría:** historial de cambios, motivos de retraso y documentos de respaldo.
- **Trabajadores:** catálogo, cargos, cuadrillas y actividades.
- **Configuración:** tipos de área, tipos de mantenimiento, reglas y datos de tu organización (nombre, logo, responsable) para los documentos.
- **Reportes:** filtros combinables; exporta a PDF, CSV (Excel) o impresión.
- **Yaku:** asistente conversacional en español, basado en reglas locales y de solo lectura (sin servicios de IA externos).

## Tecnologías

Laravel 12 · Inertia.js 2 · React 19 + TypeScript · Tailwind CSS 4 · Vite · Dompdf · Pest.

## Instalación local

Requisitos: PHP 8.2+, Composer 2, Node.js 20+.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # PowerShell: New-Item database/database.sqlite
php artisan migrate
npm run dev        # en otra terminal: php artisan serve
```

Abre la aplicación y pulsa **Registrarse**: tu cuenta se prepara automáticamente. Pruebas: `php artisan test`.

## Despliegue en Vercel

Laravel no es un runtime oficial de Vercel: se usa el runtime comunitario [`vercel-php`](https://github.com/vercel-community/php) (ver `vercel.json` y `api/index.php`). Como las funciones son *serverless* y de solo lectura, se necesitan tres servicios externos:

| Servicio | Para qué | Variables |
|---|---|---|
| [Neon](https://neon.tech) (PostgreSQL) | Base de datos | `DB_CONNECTION=pgsql`, `DB_URL` |
| [Cloudflare R2](https://developers.cloudflare.com/r2/) | Fotos, logos y documentos | `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, `R2_PUBLIC_URL` |
| [Resend](https://resend.com) | Correo de recuperación de contraseña | `MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS` |

1. Crea la base en Neon y ejecuta las migraciones **una vez desde tu equipo**:
   ```bash
   DB_CONNECTION=pgsql DB_URL="postgresql://..." php artisan migrate --force
   ```
2. Crea un bucket en R2 con lectura pública (dominio `r2.dev` o propio) y un token de API con lectura/escritura.
3. Importa el repositorio en Vercel y define las variables anteriores, más `APP_KEY` (`php artisan key:generate --show`) y `APP_URL`.
4. Despliega: Vercel ejecuta `npm run build` y sirve `api/index.php` con PHP.

Las sesiones usan cookies y los logs van a `stderr`. Las fotos de los parques son públicas por URL; no subas información sensible en ellas.

## Privacidad de los datos

El aislamiento se aplica en los modelos (`App\Models\Concerns\BelongsToOwner`): toda consulta se filtra por el `owner_id` de la cuenta autenticada y los registros nuevos se le asignan automáticamente. Ver `tests/Feature/AccountIsolationTest.php`.

## Licencia

[MIT](LICENSE).
