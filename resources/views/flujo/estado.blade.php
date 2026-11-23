<span @class([
    'ui-badge',
    'ui-badge-success' => in_array($estado, ['aprobado', 'cerrado'], true),
    'ui-badge-info' => in_array($estado, ['enviado', 'revision', 'recibido'], true),
    'ui-badge-warning' => $estado === 'correccion',
    'border-slate-200 bg-slate-50 text-slate-700' => $estado === 'borrador',
])>{{ \App\Models\Consolidado::ESTADOS[$estado] ?? 'Recibido' }}</span>
