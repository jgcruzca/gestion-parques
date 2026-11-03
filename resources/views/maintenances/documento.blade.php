<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parte de Mantenimiento - {{ $maintenance->maintenance_number }}</title>
    <style>
        @page { size: A4; margin: 15mm 14mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #e5e7eb;
            color: #000000;
            font-family: "Times New Roman", Times, serif;
            font-size: 14px;
            padding: 24px;
        }

        .actions-bar {
            align-items: center;
            display: flex;
            font-family: Arial, Helvetica, sans-serif;
            gap: 8px;
            justify-content: space-between;
            margin: 0 auto 16px;
            max-width: 820px;
        }

        .btn {
            background: #374151;
            border: 0;
            border-radius: 4px;
            color: #ffffff;
            cursor: pointer;
            display: inline-block;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            padding: 9px 16px;
            text-decoration: none;
        }

        .btn:hover { background: #111827; }
        .btn-secondary { background: #ffffff; border: 1px solid #9ca3af; color: #111827; }
        .btn-secondary:hover { background: #f3f4f6; }

        .paper {
            background: #ffffff;
            border: 1px solid #9ca3af;
            margin: 0 auto;
            max-width: 820px;
            padding: 40px;
        }

        .letterhead { border-collapse: collapse; width: 100%; }
        .letterhead td { vertical-align: middle; }
        .logo-cell { width: 84px; }
        .logo { display: block; height: 82px; max-width: 72px; object-fit: contain; }
        .institution .name { font-size: 19px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
        .institution .unit { font-size: 14px; margin-top: 3px; }
        .doc-tag { text-align: right; white-space: nowrap; }
        .doc-tag .type { font-size: 12px; letter-spacing: .05em; text-transform: uppercase; }
        .doc-tag .number { font-size: 19px; font-weight: 700; }

        .rule { border: 0; border-top: 2px solid #000000; margin: 12px 0 16px; }

        .title { font-size: 18px; letter-spacing: .05em; margin-bottom: 14px; text-align: center; text-decoration: underline; text-transform: uppercase; }

        table.grid { border-collapse: collapse; width: 100%; }
        table.grid th, table.grid td { border: 1px solid #000000; padding: 6px 9px; text-align: left; vertical-align: top; }
        table.grid th { background: #e5e7eb; font-weight: 700; width: 20%; }

        .info { margin-bottom: 18px; }
        .info td { width: 33.33%; }
        .info .label { display: block; font-size: 11px; letter-spacing: .05em; text-transform: uppercase; }
        .info .value { font-size: 15px; font-weight: 700; }

        .section-title { border-bottom: 1px solid #000000; font-size: 14px; letter-spacing: .04em; margin: 18px 0 8px; padding-bottom: 3px; text-transform: uppercase; }
        table.grid + .section-title { margin-top: 20px; }

        .photos { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .photos td { border: 1px solid #000000; padding: 0; text-align: center; vertical-align: top; width: 50%; }
        .photos img { display: block; height: 200px; object-fit: cover; width: 100%; }
        .photos .caption { border-top: 1px solid #000000; font-size: 13px; padding: 5px; }

        .signatures { border-collapse: collapse; margin-top: 70px; page-break-inside: avoid; width: 100%; }
        .signatures td { padding: 0 24px; text-align: center; width: 50%; }
        .signatures .line { border-top: 1px solid #000000; padding-top: 5px; }
        .signatures .name { font-weight: 700; }
        .signatures .role { font-size: 12px; }

        .footnote { border-top: 1px solid #000000; font-size: 12px; margin-top: 28px; padding-top: 6px; text-align: center; }

        table.grid tr { page-break-inside: avoid; }

        @media print {
            body { background: none; padding: 0; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .paper { border: 0; max-width: 100%; padding: 0; }
            .actions-bar { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="actions-bar">
        <a href="{{ route('maintenances.index') }}" class="btn btn-secondary">Volver a la bandeja de mantenimientos</a>
        <button type="button" onclick="window.print()" class="btn">Imprimir o guardar como PDF</button>
    </div>

    <div class="paper">
        <table class="letterhead">
            <tr>
                <td class="logo-cell">
                    <img class="logo" src="{{ $institution?->logo_url ?: asset('images/logo-sistema-verde.svg') }}" alt="Logotipo institucional">
                </td>
                <td class="institution">
                    <div class="name">{{ $institution?->organization_name ?? 'Mi organización' }}</div>
                    <div class="unit">{{ $institution?->unit_name ?? 'Área de Parques y Mantenimiento' }}</div>
                </td>
                <td class="doc-tag">
                    <div class="type">N.° de parte</div>
                    <div class="number">{{ $maintenance->maintenance_number }}</div>
                </td>
            </tr>
        </table>
        <hr class="rule">

        <h1 class="title">Parte de Mantenimiento de Área Verde</h1>

        <table class="grid info">
            <tr>
                <td><span class="label">Fecha de emisión</span><span class="value">{{ now()->format('d/m/Y H:i') }}</span></td>
                <td><span class="label">Estado del mantenimiento</span><span class="value">{{ ucfirst(str_replace('_', ' ', $maintenance->status)) }}</span></td>
                <td><span class="label">Modalidad</span><span class="value">{{ $maintenance->is_extraordinary ? 'Extraordinario' : 'Por frecuencia automatizada' }}</span></td>
            </tr>
        </table>

        <h3 class="section-title">1. Datos del parque o área verde</h3>
        <table class="grid">
            <tr>
                <th>Código del parque</th>
                <td>{{ $park->code ?? ('PRQ-' . str_pad($park->id, 4, '0', STR_PAD_LEFT)) }}</td>
                <th>Denominación</th>
                <td>{{ $park->name }}</td>
            </tr>
            <tr>
                <th>Tipo de área verde</th>
                <td>{{ $park->green_area_type ?? 'Parque' }}</td>
                <th>Sector / zona</th>
                <td>{{ $park->zone ?? 'Sector Central' }}</td>
            </tr>
            <tr>
                <th>Ubicación</th>
                <td colspan="3">{{ $park->address ?: 'Sin dirección registrada' }} {{ $park->reference ? ' (' . $park->reference . ')' : '' }}</td>
            </tr>
        </table>

        <h3 class="section-title">2. Detalle del mantenimiento ejecutado</h3>
        <table class="grid">
            <tr>
                <th>Tipo de mantenimiento</th>
                <td>{{ $maintenance->maintenance_type }}</td>
                <th>Trabajador / cuadrilla</th>
                <td>{{ $maintenance->responsible_worker ?: 'No especificado' }}</td>
            </tr>
            <tr>
                <th>Fecha programada</th>
                <td>{{ $maintenance->scheduled_date ? $maintenance->scheduled_date->format('d/m/Y') : 'N/A' }}</td>
                <th>Fecha real de ejecución</th>
                <td>{{ $maintenance->execution_date ? $maintenance->execution_date->format('d/m/Y') : now()->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <th>Hora de inicio</th>
                <td>{{ $maintenance->start_time ?: 'No registrada' }}</td>
                <th>Hora de finalización</th>
                <td>{{ $maintenance->end_time ?: 'No registrada' }}</td>
            </tr>
            <tr>
                <th>Duración estimada</th>
                <td>
                    @if ($maintenance->duration_minutes)
                        {{ floor($maintenance->duration_minutes / 60) }} h {{ $maintenance->duration_minutes % 60 }} min ({{ $maintenance->duration_minutes }} minutos)
                    @else
                        No calculada
                    @endif
                </td>
                <th>Resultado de la labor</th>
                <td>{{ $maintenance->result ?: 'Conforme / Óptimo' }}</td>
            </tr>
            @if ($maintenance->is_extraordinary && $maintenance->reason)
                <tr>
                    <th>Motivo extraordinario</th>
                    <td colspan="3">{{ $maintenance->reason }}</td>
                </tr>
            @endif
            <tr>
                <th>Descripción del trabajo</th>
                <td colspan="3">{{ $maintenance->work_description ?: 'Mantenimiento integral realizado según los estándares de la organización.' }}</td>
            </tr>
            @if ($maintenance->initial_observation)
                <tr>
                    <th>Observación inicial</th>
                    <td colspan="3">{{ $maintenance->initial_observation }}</td>
                </tr>
            @endif
            @if ($maintenance->observations)
                <tr>
                    <th>Observaciones generales</th>
                    <td colspan="3">{{ $maintenance->observations }}</td>
                </tr>
            @endif
        </table>

        @if ($maintenance->photo_before_url || $maintenance->photo_after_url)
            <h3 class="section-title">3. Evidencia fotográfica</h3>
            <table class="photos">
                <tr>
                    @if ($maintenance->photo_before_url)
                        <td>
                            <img src="{{ str_starts_with($maintenance->photo_before_url, 'http') ? $maintenance->photo_before_url : Storage::disk('public')->url($maintenance->photo_before_url) }}" alt="Antes del trabajo">
                            <div class="caption">Fotografía previa al mantenimiento</div>
                        </td>
                    @endif
                    @if ($maintenance->photo_after_url)
                        <td>
                            <img src="{{ str_starts_with($maintenance->photo_after_url, 'http') ? $maintenance->photo_after_url : Storage::disk('public')->url($maintenance->photo_after_url) }}" alt="Posterior al trabajo">
                            <div class="caption">Fotografía posterior al mantenimiento</div>
                        </td>
                    @endif
                </tr>
            </table>
        @endif

        <table class="signatures">
            <tr>
                <td>
                    <div class="line">
                        <div class="name">{{ $maintenance->responsible_worker ?: 'Responsable de cuadrilla' }}</div>
                        <div class="role">Firma del encargado / trabajador</div>
                    </div>
                </td>
                <td>
                    <div class="line">
                        <div class="name">{{ $institution?->manager_name ?: 'Supervisor de Áreas Verdes' }}</div>
                        <div class="role">V.° B.° {{ $institution?->unit_name ?? 'Área de Parques y Mantenimiento' }}</div>
                    </div>
                </td>
            </tr>
        </table>

        @if ($institution?->document_footer)
            <p class="footnote">{{ $institution->document_footer }}</p>
        @endif
    </div>

</body>
</html>
