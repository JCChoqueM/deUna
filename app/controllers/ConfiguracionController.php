<?php
/**
 * DeUna - Configuracion Controller
 * Configuración del sistema (admin only - RF-03)
 */

class ConfiguracionController extends Controller
{
    /**
     * Show configuration page
     */
    public function index()
    {
        $model = $this->model('Configuracion');
        $configs = $model->getConfig();

        $this->view('configuracion/index', [
            'config' => $configs,
        ]);
    }

    /**
     * Save configuration (POST)
     */
    public function guardar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('configuracion');
        }

        $model = $this->model('Configuracion');

        $configurables = [
            'nombre_negocio', 'tarifa_base', 'tipo_comision',
            'monto_comision', 'porcentaje_comision', 'plazo_dias',
            'numeracion_tipo',
        ];

        foreach ($configurables as $clave) {
            $valor = $_POST[$clave] ?? null;
            if ($valor !== null) {
                $model->setValue($clave, $valor, Auth::userId());
            }
        }

        Session::flash('success', 'Configuración guardada correctamente');
        $this->redirect('configuracion');
    }
}
