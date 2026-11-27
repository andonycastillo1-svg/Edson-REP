@props(['consolidados'])

@if($consolidados->isNotEmpty())
    <label class="ui-label block">
        Requerimiento de origen
        <select class="ui-input mt-1" name="consolidado_id">
            <option value="">Asignación independiente</option>
            @foreach($consolidados->groupBy('corte_id') as $grupo)
                @php($corte = $grupo->first()->corte)
                <optgroup label="{{ $corte ? $corte->nombre.' · #'.$corte->id : 'Consolidados sin corte' }}">
                    @foreach($grupo as $origen)
                        <option value="{{ $origen->id }}" @selected((int) old('consolidado_id', request('consolidado_id')) === $origen->id)>
                            {{ $origen->coordinador?->name ?? $origen->nombre }} · #{{ $origen->id }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </label>
@endif
@error('consolidado_id')<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
