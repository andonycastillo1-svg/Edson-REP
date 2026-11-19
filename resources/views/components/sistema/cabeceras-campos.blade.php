@props(['tabla'])

@foreach($tabla['campos'] as $campo)
    <th scope="col" class="px-4 py-3 text-left">{{ $campo->etiqueta }}</th>
@endforeach
