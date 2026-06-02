<script>
function colaboradoresPage(routePrefix) {
  return {
    tab: 'activos',

    selectedCodigos: [],

    modalOpen: false,
    loadingDetalle: false,

    expedienteTab: 'resumen',

    centroRRHHOpen: false,
    cobroActivoIndex: 0,

    vehiculoDetalleOpen: false,

    asignacionesPage: 1,
    asignacionesPorPagina: 5,

    productosVehiculoPage: 1,
    productosVehiculoPorPagina: 5,

    cobrosPage: 1,
    cobrosPorPagina: 4,

    detalle: {},
    asignaciones: [],
    cobros: [],

    vehiculoAsignado: null,
    productosVehiculo: [],

    totalInventario: 0,
    totalProductosVehiculo: 0,
    totalGeneral: 0,
    totalCobros: 0,

    routePrefix: routePrefix,

    selectedCount() {
      return this.selectedCodigos.length;
    },

    isSelected(codigo) {
      return this.selectedCodigos.includes(String(codigo));
    },

    toggleSeleccion(codigo) {
      codigo = String(codigo);

      if (this.isSelected(codigo)) {
        this.selectedCodigos = this.selectedCodigos.filter(item => item !== codigo);
        return;
      }

      this.selectedCodigos.push(codigo);
    },

    seleccionarTodosVisibles(codigos) {
      codigos.forEach(codigo => {
        codigo = String(codigo);

        if (!this.selectedCodigos.includes(codigo)) {
          this.selectedCodigos.push(codigo);
        }
      });
    },

    limpiarSeleccion() {
      this.selectedCodigos = [];
    },

    descargarSeleccionados(form) {
      if (this.selectedCodigos.length === 0) {
        alert('Selecciona al menos un colaborador para descargar las fichas técnicas.');
        return;
      }

      form.submit();
    },

    async openDetalle(codigo) {
      this.modalOpen = true;
      this.loadingDetalle = true;

      this.expedienteTab = 'resumen';

      this.detalle = {};
      this.asignaciones = [];
      this.cobros = [];

      this.vehiculoAsignado = null;
      this.productosVehiculo = [];

      this.totalInventario = 0;
      this.totalProductosVehiculo = 0;
      this.totalGeneral = 0;
      this.totalCobros = 0;

      this.centroRRHHOpen = false;
      this.cobroActivoIndex = 0;

      this.vehiculoDetalleOpen = false;

      this.asignacionesPage = 1;
      this.productosVehiculoPage = 1;
      this.cobrosPage = 1;

      try {
        const res = await fetch(`/${this.routePrefix}/colaboradores/${codigo}/detalle`, {
          headers: {
            'Accept': 'application/json'
          }
        });

        if (!res.ok) {
          throw new Error('No se pudo cargar el expediente del colaborador.');
        }

        const data = await res.json();

        this.detalle = data.colaborador ?? {};

        this.asignaciones = data.asignaciones ?? [];
        this.cobros = data.cobros ?? [];

        this.vehiculoAsignado = data.vehiculo_asignado ?? null;
        this.productosVehiculo = data.productos_vehiculo ?? [];

        this.totalInventario = Number(data.total_inventario ?? 0);
        this.totalProductosVehiculo = Number(data.total_productos_vehiculo ?? 0);
        this.totalGeneral = Number(data.total_general ?? 0);
        this.totalCobros = Number(data.total_cobros ?? 0);
      } catch (error) {
        alert(error.message || 'Error al cargar el expediente.');
        this.closeModal();
      } finally {
        this.loadingDetalle = false;
      }
    },

    closeModal() {
      this.modalOpen = false;
      this.loadingDetalle = false;
      this.centroRRHHOpen = false;
      this.vehiculoDetalleOpen = false;
      this.expedienteTab = 'resumen';

      this.asignacionesPage = 1;
      this.productosVehiculoPage = 1;
      this.cobrosPage = 1;
    },

    money(value) {
      return Number(value || 0).toFixed(2);
    },

    vehicleTitle() {
      if (!this.vehiculoAsignado) {
        return 'Sin vehículo';
      }

      const marca = this.vehiculoAsignado.marca || '';
      const modelo = this.vehiculoAsignado.modelo || '';
      const title = `${marca} ${modelo}`.trim();

      return title || 'Vehículo asignado';
    },

    badgeVidaUtil(estado) {
      if (estado === 'Vencido') {
        return 'bg-red-100 text-red-700';
      }

      if (estado === 'Vigente') {
        return 'bg-emerald-100 text-emerald-700';
      }

      return 'bg-slate-100 text-slate-700';
    },

    badgeCobro(estado) {
      const normalized = String(estado || 'pendiente').toLowerCase();

      if (normalized.includes('aprob')) {
        return 'bg-emerald-100 text-emerald-800';
      }

      if (normalized.includes('rechaz') || normalized.includes('no')) {
        return 'bg-slate-200 text-slate-700';
      }

      if (normalized.includes('descont')) {
        return 'bg-blue-100 text-blue-800';
      }

      return 'bg-amber-100 text-amber-800';
    },

    estadoCobroTexto(estado) {
      const normalized = String(estado || 'pendiente').toLowerCase();

      if (normalized === 'pendiente') {
        return 'Pendiente RRHH';
      }

      if (normalized.includes('aprob')) {
        return 'Aprobado';
      }

      if (normalized.includes('rechaz')) {
        return 'No aplica';
      }

      if (normalized.includes('descont')) {
        return 'Descontado';
      }

      return estado || 'Pendiente RRHH';
    },

    razonCobro(cobro) {
      const detalle = String(cobro.detalle || '').toLowerCase();

      if (detalle.includes('mal uso')) {
        return 'Daño o mal uso reportado';
      }

      if (detalle.includes('reemplazo')) {
        return 'Reemplazo anticipado';
      }

      if (Number(cobro.monto_cobro || 0) > 0) {
        return 'Descuento sugerido';
      }

      return 'Registro informativo';
    },

    abrirCentroRRHH() {
      this.centroRRHHOpen = true;
    },

    cerrarCentroRRHH() {
      this.centroRRHHOpen = false;
    },

    seleccionarCobroRRHH(index) {
      this.cobroActivoIndex = index;
    },

    get cobroActivo() {
      return this.cobros[this.cobroActivoIndex] || null;
    },

    totalPaginasAsignaciones() {
      return Math.max(1, Math.ceil(this.asignaciones.length / this.asignacionesPorPagina));
    },

    asignacionesPaginadas() {
      const inicio = (this.asignacionesPage - 1) * this.asignacionesPorPagina;
      const fin = inicio + this.asignacionesPorPagina;

      return this.asignaciones
        .map((item, index) => ({
          ...item,
          _index: index
        }))
        .slice(inicio, fin);
    },

    cambiarPaginaAsignaciones(page) {
      const total = this.totalPaginasAsignaciones();

      if (page < 1) {
        this.asignacionesPage = 1;
        return;
      }

      if (page > total) {
        this.asignacionesPage = total;
        return;
      }

      this.asignacionesPage = page;
    },

    totalPaginasProductosVehiculo() {
      return Math.max(1, Math.ceil(this.productosVehiculo.length / this.productosVehiculoPorPagina));
    },

    productosVehiculoPaginados() {
      const inicio = (this.productosVehiculoPage - 1) * this.productosVehiculoPorPagina;
      const fin = inicio + this.productosVehiculoPorPagina;

      return this.productosVehiculo
        .map((item, index) => ({
          ...item,
          _index: index
        }))
        .slice(inicio, fin);
    },

    cambiarPaginaProductosVehiculo(page) {
      const total = this.totalPaginasProductosVehiculo();

      if (page < 1) {
        this.productosVehiculoPage = 1;
        return;
      }

      if (page > total) {
        this.productosVehiculoPage = total;
        return;
      }

      this.productosVehiculoPage = page;
    },

    totalPaginasCobros() {
      return Math.max(1, Math.ceil(this.cobros.length / this.cobrosPorPagina));
    },

    cobrosPaginados() {
      const inicio = (this.cobrosPage - 1) * this.cobrosPorPagina;
      const fin = inicio + this.cobrosPorPagina;

      return this.cobros
        .map((cobro, index) => ({
          ...cobro,
          _index: index
        }))
        .slice(inicio, fin);
    },

    cambiarPaginaCobros(page) {
      const total = this.totalPaginasCobros();

      if (page < 1) {
        this.cobrosPage = 1;
        return;
      }

      if (page > total) {
        this.cobrosPage = total;
        return;
      }

      this.cobrosPage = page;
    }
  }
}
</script>