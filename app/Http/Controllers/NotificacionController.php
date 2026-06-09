<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificacionController extends Controller
{
    public function index(Request $request): View
    {
        $notificaciones = $request->user()
            ->notifications()
            ->latest()
            ->paginate(15);

        return view('notificaciones.index', compact('notificaciones'));
    }

    public function marcarLeida(Request $request, string $id): RedirectResponse
    {
        $notificacion = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notificacion->markAsRead();

        return back()->with('success', 'Notificación marcada como leída.');
    }

    public function marcarTodasLeidas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Todas las notificaciones fueron marcadas como leídas.');
    }

    public function eliminar(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->delete();

        return back()->with('success', 'Notificación eliminada.');
    }

    public function eliminarTodas(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back()->with('success', 'Todas las notificaciones fueron eliminadas.');
    }
}
