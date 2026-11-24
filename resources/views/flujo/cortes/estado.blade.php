<span @class(['ui-badge', 'ui-badge-success' => $corte->estado === 'confirmado', 'ui-badge-warning' => $corte->estado === 'borrador'])>
    {{ \App\Models\CorteRequerimiento::ESTADOS[$corte->estado] }}
</span>
