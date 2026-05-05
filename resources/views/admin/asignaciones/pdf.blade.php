<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Constancia de Asignación</title>
<link rel="stylesheet" href="{{ asset('css/admin-theme.css') }}">

<style>
body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    color: #000;
}

.container {
    width: 100%;
    margin: 0 auto;
}

.header {
    text-align: center;
    margin-bottom: 20px;
}

.header h2 {
    margin: 0;
}

.info {
    margin-bottom: 15px;
}

.box {
    border: 1px solid #ccc;
    padding: 10px;
    margin-bottom: 15px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    border: 1px solid #ccc;
    padding: 6px;
}

th {
    background: #f2f2f2;
}

.total {
    text-align: right;
    margin-top: 10px;
    font-weight: bold;
}

.firmas {
    margin-top: 50px;
    display: flex;
    justify-content: space-between;
}

.firma {
    width: 45%;
    text-align: center;
}

.linea {
    border-top: 1px solid #000;
    margin-top: 40px;
    padding-top: 5px;
}

.small {
    font-size: 10px;
    margin-top: 10px;
}

.acciones {
    text-align: right;
    margin-bottom: 10px;
}

.btn-print {
    border: 1px solid #444;
    background: #fff;
    padding: 6px 10px;
    font-size: 12px;
    cursor: pointer;
}

@media print {
    .acciones {
        display: none;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="acciones">
        <button type="button" onclick="window.print()" class="btn-print">🖨️ Imprimir ficha</button>
    </div>

    {{-- ENCABEZADO --}}
    <div class="header">
        <h2>Constancia de Asignación de Equipo</h2>
        <p>Generado: {{ now()->format('d/m/Y') }}</p>
    </div>

    {{-- DATOS COLABORADOR --}}
    <div class="box">
        <strong>Colaborador:</strong> {{ $colaborador->nombre }} <br>
        <strong>Código:</strong> {{ $colaborador->codigo }} <br>
        <strong>Fecha de asignación:</strong> {{ now()->format('d/m/Y') }} <br>
        <strong>Asignado por (Almacenista):</strong> {{ $asignadorNombre }} <br>
        <strong>Bodega del asignador:</strong> {{ $bodegaAsignador }}
    </div>

    {{-- TABLA --}}
    <div class="box">
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Bodega</th>
                    <th>Cantidad</th>
                    <th>Costo Unitario</th>
                    <th>Total</th>
                    <th>Vence</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($asignaciones as $a)
                @php
                    $vencimiento = $a->fecha_vencimiento ? \Illuminate\Support\Carbon::parse($a->fecha_vencimiento) : null;
                    $estadoVidaUtil = !$vencimiento ? 'Sin vida útil' : (now()->gt($vencimiento) ? 'Vencido' : 'Vigente');
                @endphp
                <tr>
                    <td>{{ $a->producto->nombre }}</td>
                    <td>{{ $a->bodega->nombre }}</td>
                    <td>{{ $a->cantidad_asignada }}</td>
                    <td>Q {{ number_format($a->costo_unitario ?? 0, 2) }}</td>
                    <td>
                        Q {{ number_format(($a->costo_unitario ?? 0) * $a->cantidad_asignada, 2) }}
                    </td>
                    <td>{{ $vencimiento?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $estadoVidaUtil }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total">
            Total asignado: Q {{ number_format($total, 2) }}
        </div>
    </div>

    {{-- RESPONSABILIDAD --}}
    <div class="box small">
        <strong>Lineamientos de Responsabilidad:</strong>
        <ul>
            <li>El colaborador es responsable del equipo asignado.</li>
            <li>Debe darle uso adecuado y reportar daños.</li>
            <li>En caso de pérdida o daño por negligencia, podrá aplicarse descuento.</li>
            <li>El equipo debe ser devuelto cuando se solicite.</li>
        </ul>
    </div>

    {{-- FIRMAS --}}
    <div class="firmas">

        <div class="firma">
            <div class="linea"></div>
            <strong>Firma Trabajador</strong><br>
            {{ $colaborador->nombre }}
        </div>

        <div class="firma">
            <div class="linea"></div>
            <strong>Firma Almacenista (quien asigna)</strong><br>
            {{ $asignadorNombre }}
        </div>

    </div>

</div>

</body>
</html>
