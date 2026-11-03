<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 14mm 12mm 20mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000000; font-family: "Times New Roman", Times, serif; font-size: 10px; }
        .toolbar { margin-bottom: 16px; display: flex; gap: 8px; font-family: Arial, sans-serif; }
        .toolbar button { border: 0; border-radius: 6px; background: #374151; color: white; cursor: pointer; padding: 9px 14px; font-weight: 700; }
        .toolbar .secondary { background: #6b7280; }

        .letterhead { border-collapse: collapse; width: 100%; }
        .letterhead td { padding: 0; vertical-align: middle; }
        .logo-cell { width: 62px; }
        .logo { display: block; height: 66px; }
        .institution { padding-left: 12px !important; }
        .institution .name { font-size: 15px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
        .institution .unit { font-size: 10px; margin-top: 3px; }
        .rule { border: 0; border-top: 1.5px solid #000000; height: 0; margin: 8px 0 12px; }

        .title-box { margin-bottom: 10px; text-align: center; }
        .title-box h1 { font-size: 15px; letter-spacing: .05em; margin: 0; text-decoration: underline; text-transform: uppercase; }

        .info { border-collapse: collapse; margin-bottom: 12px; width: 100%; }
        .info td { border: 1px solid #000000; padding: 4px 8px; width: 33.33%; }
        .info .label { display: block; font-size: 8px; letter-spacing: .05em; text-transform: uppercase; }
        .info .value { font-size: 11px; font-weight: 700; }

        table.data { border-collapse: collapse; table-layout: auto; width: 100%; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data th { background: #e5e7eb; border: 1px solid #000000; font-size: 9px; padding: 6px 5px; text-align: center; text-transform: uppercase; }
        table.data td { border: 1px solid #000000; padding: 5px; vertical-align: top; word-wrap: break-word; }
        .code { white-space: nowrap; }
        table.data td.index { text-align: center; width: 28px; }
        .empty { border: 1px solid #000000; padding: 25px; text-align: center; }

        .total { margin-top: 6px; text-align: right; }
        .signature { margin-top: 46px; page-break-inside: avoid; text-align: center; }
        .signature .line { border-top: 1px solid #000000; display: inline-block; padding-top: 4px; width: 220px; }
        .signature .name { font-weight: 700; }
        .signature .role { font-size: 9px; }

        .footer { border-top: 1px solid #000000; bottom: -14mm; font-size: 9px; left: 0; padding-top: 4px; position: fixed; right: 0; text-align: center; }
        .page-number:after { content: "Página " counter(page); }
        @media print {
            .toolbar { display: none; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    @if ($printable)
        <div class="toolbar">
            <button type="button" onclick="window.print()">Imprimir o guardar como PDF</button>
            <button type="button" class="secondary" onclick="window.close()">Cerrar</button>
        </div>
    @endif

    <table class="letterhead">
        <tr>
            <td class="logo-cell">
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="Logotipo institucional">
                @endif
            </td>
            <td class="institution">
                <div class="name">{{ $institution?->organization_name ?? 'Mi organización' }}</div>
                <div class="unit">{{ $institution?->unit_name ?? 'Área de Parques y Mantenimiento' }}</div>
                @if ($institution?->address)
                    <div class="unit">{{ $institution->address }}</div>
                @endif
            </td>
        </tr>
    </table>
    <hr class="rule">

    <div class="title-box">
        <h1>{{ $title }}</h1>
    </div>

    <table class="info">
        <tr>
            <td><span class="label">Fecha de emisión</span><span class="value">{{ $generatedAt }}</span></td>
            <td><span class="label">Total de registros</span><span class="value">{{ count($rows) }}</span></td>
            <td><span class="label">Sistema</span><span class="value">Gestión de Áreas Verdes</span></td>
        </tr>
    </table>

    @if (count($rows))
        <table class="data">
            <thead>
                <tr>
                    <th>Ítem</th>
                    @foreach ($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="index">{{ $loop->iteration }}</td>
                        @foreach ($row as $value)
                            <td>{!! preg_replace('/\b[A-Z]{2,4}-\d[\d-]*/', '<span class="code">$0</span>', e($value === null || $value === '' ? '-' : $value)) !!}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="total">Total: {{ count($rows) }} {{ count($rows) === 1 ? 'registro' : 'registros' }}</div>
    @else
        <div class="empty">No existen registros para los criterios seleccionados.</div>
    @endif

    @if ($institution?->manager_name)
        <div class="signature">
            <div class="line">
                <div class="name">{{ $institution->manager_name }}</div>
                <div class="role">{{ $institution->unit_name ?? 'Área de Parques y Mantenimiento' }}</div>
            </div>
        </div>
    @endif

    <footer class="footer">
        <div>{{ $institution?->document_footer ?? 'Reporte referencial del Sistema de Gestión de Áreas Verdes' }}</div>
        <div class="page-number"></div>
    </footer>
</body>
</html>
