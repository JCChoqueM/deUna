<?php
/**
 * DeUna - Dashboard Controller
 * Panel principal con estadísticas
 */

class DashboardController extends Controller
{
    /**
     * Dashboard principal
     */
    public function index()
    {
        $paqueteModel = $this->model('Paquete');
        $stats = $paqueteModel->getStats();

        // Get recent pending packages
        $pendientes = $paqueteModel->getPending();

        // Get recent packages (last 5)
        $recientes = $paqueteModel->query("
            SELECT p.*, 
                   v.nombre as vendedor_nombre,
                   c.nombre_completo as comprador_nombre
            FROM paquetes p
            LEFT JOIN vendedores v ON v.id = p.vendedor_id
            LEFT JOIN compradores c ON c.id = p.comprador_id
            ORDER BY p.fecha_recepcion DESC
            LIMIT 5
        ");

        $this->view('dashboard/index', [
            'stats' => $stats,
            'pendientes' => $pendientes,
            'recientes' => $recientes,
        ]);
    }
}
