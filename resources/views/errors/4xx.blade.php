@include('errors.estado', ['codigo' => $exception->getStatusCode(), 'titulo' => 'No fue posible completar la solicitud', 'mensaje' => 'Vuelve al inicio para continuar.'])
