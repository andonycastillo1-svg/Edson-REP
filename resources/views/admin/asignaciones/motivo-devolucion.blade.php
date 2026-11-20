<label class="mt-2 block text-sm font-semibold">Motivo de la operación
    <select name="motivo_administrativo" class="mt-1 block rounded-xl border-slate-300 text-sm" @isset($formulario) form="{{ $formulario }}" @endisset>
        <option value="ordinaria">Retorno o regularización</option>
        <option value="renuncia">Renuncia · genera hoja</option>
        <option value="despido">Despido · genera hoja</option>
    </select>
</label>
