@props(['tabla', 'registro'])

@php($valores = ($tabla['valoresPresentados'] ?? $tabla['valores'])->get((string) $registro, collect()))
@foreach($tabla['campos'] as $campo)
    @php($valor = isset($tabla['valoresPresentados']) ? $valores->get($campo->id, '') : app(\App\Sistema\ValoresCampos::class)->mostrar($campo, $valores->get($campo->id)))
    <td class="px-4 py-3">{{ $valor === '' ? '—' : $valor }}</td>
@endforeach
