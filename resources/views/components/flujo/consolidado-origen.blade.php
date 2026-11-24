@props(['consolidados'])

@if($consolidados->isNotEmpty())
    <label class="ui-label block">
        Vincular a un requerimiento aprobado (opcional)
        <select class="ui-input mt-1" name="consolidado_id" aria-describedby="consolidado-origen-ayuda">
            <option value="">Asignación independiente</option>
            @foreach($consolidados as $origen)
                <option value="{{ $origen->id }}" @selected((int) old('consolidado_id', request('consolidado_id')) === $origen->id)>{{ $origen->nombre }} · #{{ $origen->id }}</option>
            @endforeach
        </select>
        <span class="mt-1 block text-sm font-normal" id="consolidado-origen-ayuda">Si vinculas la entrega, declara únicamente productos aprobados pendientes de ese colaborador.</span>
    </label>
@endif
@error('consolidado_id')<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
