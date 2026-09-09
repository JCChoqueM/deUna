<?php
/**
 * DeUna - Reporte Controller
 * Reportes y estadísticas
 */

class ReporteController extends Controller
{
    /**
     * Show reports page
     */
    public function index()
    {
        $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-7 days'));
        $hasta = $_GET['hasta'] ?? date('Y-m-d');

        $pagoModel = $this->model('Pago');
        $paqueteModel = $this->model('Paquete');

        // Income by date range
        $totalIngresos = $pagoModel->getTotalIncome($desde, $hasta);
        $totalComisiones = $pagoModel->getTotalCommissions($desde, $hasta);

        // Payments by method
        $byMedioPago = $pagoModel->getByMedioPago($desde, $hasta);

        // Packages delivered in date range
        $paquetesEntregados = $paqueteModel->query("
            SELECT COUNT(*) as total,
                   COALESCE(SUM(p.monto_base), 0) as base_total,
                   COALESCE(SUM(CAST(pg.total AS REAL)), 0) as total_cobrado
            FROM paquetes p
            JOIN pagos pg ON pg.paquete_id = p.id
            WHERE date(p.fecha_recepcion) >= ? AND date(p.fecha_recepcion) <= ?
        ", [$desde, $hasta]);

        // Top buyers
        $topBuyers = $paqueteModel->query("
            SELECT c.nombre_completo, COUNT(p.id) as total_paquetes,
                   COALESCE(SUM(pg.total), 0) as total_cobrado
            FROM paquetes p
            LEFT JOIN compradores c ON c.id = p.comprador_id
            LEFT JOIN pagos pg ON pg.paquete_id = p.id
            WHERE p.estado = 'Entregado'
              AND date(p.fecha_recepcion) >= ? AND date(p.fecha_recepcion) <= ?
            GROUP BY c.id, c.nombre_completo
            ORDER BY total_paquetes DESC
            LIMIT 10
        ", [$desde, $hasta]);

        $this->view('reportes/index', [
            'desde' => $desde,
            'hasta' => $hasta,
            'total_ingresos' => $totalIngresos,
            'total_comisiones' => $totalComisiones,
            'by_medio_pago' => $byMedioPago,
            'paquetes_entregados' => $paquetesEntregados[0],
            'top_buyers' => $topBuyers,
        ]);
    }
}
