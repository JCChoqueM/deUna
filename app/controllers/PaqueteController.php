<?php
/**
 * DeUna - Paquete Controller
 * Gestión completa de paquetes: recepción, entrega, pagos, QR
 */

class PaqueteController extends Controller
{
    private $qr;

    public function __construct()
    {
        parent::__construct();
        $this->qr = new QRGen(5, 'M');
    }

    /**
     * List all packages with search and filters
     */
    public function index()
    {
        $filters = [];
        if (isset($_GET['q'])) $filters['codigo'] = trim($_GET['q']);
        if (isset($_GET['comprador'])) $filters['comprador'] = trim($_GET['comprador']);
        if (isset($_GET['vendedor'])) $filters['vendedor'] = trim($_GET['vendedor']);
        if (isset($_GET['estado']) && $_GET['estado'] !== '') $filters['estado'] = trim($_GET['estado']);
        if (isset($_GET['fecha_desde'])) $filters['fecha_desde'] = trim($_GET['fecha_desde']);
        if (isset($_GET['fecha_hasta'])) $filters['fecha_hasta'] = trim($_GET['fecha_hasta']);

        $model = $this->model('Paquete');
        $paquetes = $model->search($filters);

        $this->view('paquetes/index', [
            'paquetes' => $paquetes,
            'filters' => $filters,
        ]);
    }

    /**
     * Show new package form
     */
    public function crear()
    {
        $vendedorModel = $this->model('Vendedor');
        $compradorModel = $this->model('Comprador');

        $vendedores = $vendedorModel->allOrdered();
        $compradores = $compradorModel->allOrdered();
        $nextNumero = $this->model('Paquete')->getNextNumeroLista();

        $this->view('paquetes/create', [
            'vendedores' => $vendedores,
            'compradores' => $compradores,
            'next_numero' => $nextNumero,
        ]);
    }

    /**
     * Handle package creation (POST)
     */
    public function guardar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('paquetes/crear');
        }

        $model = $this->model('Paquete');
        $vendedorModel = $this->model('Vendedor');
        $compradorModel = $this->model('Comprador');
        $autorizadoModel = $this->model('Autorizado');
        $historialModel = $this->model('Historial');

        $errors = [];

        // Sanitize and validate input
        $numeroLista = (int)($_POST['numero_lista'] ?? 0);
        $vendedorId = (int)($_POST['vendedor_id'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $montoBase = (float)($_POST['monto_base'] ?? 0);

        $vendedorNombre = trim($_POST['vendedor_nombre'] ?? '');
        $vendedorCelular = trim($_POST['vendedor_celular'] ?? '');
        $compradorNombre = trim($_POST['comprador_nombre'] ?? '');
        $compradorCelular = trim($_POST['comprador_celular'] ?? '');
        $autorizadoNombre = trim($_POST['autorizado_nombre'] ?? '');
        $autorizadoCelular = trim($_POST['autorizado_celular'] ?? '');
        $relacion = trim($_POST['relacion'] ?? 'Comprador');
        $observaciones = trim($_POST['observaciones'] ?? '');

        // Validate required fields
        if ($vendedorId == 0) {
            if (empty($vendedorNombre) && empty($vendedorCelular)) {
                $errors[] = 'El vendedor es obligatorio';
            } elseif (empty($vendedorNombre)) {
                $errors[] = 'El nombre del vendedor es obligatorio';
            } elseif (empty($vendedorCelular)) {
                $errors[] = 'El celular del vendedor es obligatorio';
            }
        }
        if (empty($compradorNombre)) {
            $errors[] = 'El comprador es obligatorio';
        }
        if (empty($montoBase) || $montoBase <= 0) {
            $montoBase = (float)Helper::config('tarifa_base', 10);
        }

        // Stop if validation errors
        if (!empty($errors)) {
            Session::flash('error', implode(', ', $errors));
            $this->redirect('paquetes/crear');
            return;
        }

        // Get or create vendor
        if ($vendedorId == 0) {
            $vendedorId = $vendedorModel->findOrCreate([
                'nombre' => $vendedorNombre,
                'celular' => $vendedorCelular,
                'observaciones' => $observaciones,
            ]);
        }

        // Get or create buyer
        $comprador = $compradorModel->findOrCreate($compradorNombre, $compradorCelular);

        // Calculate next visible code: initial of buyer name + sequence number
        $inicial = $compradorModel->getInicial($compradorNombre);
        $sequence = $this->model('Paquete')->getNextVisibleCodeSequence($comprador);
        $codigoVisible = $inicial . '-' . $sequence;

        // Get or create authorized person
        $autorizadoId = 0;
        if (!empty($autorizadoNombre)) {
            $autorizadoId = $autorizadoModel->findOrCreate($autorizadoNombre, $autorizadoCelular, $comprador, $relacion);
        } elseif (!empty($autorizadoCelular)) {
            // If no name but phone, try to find existing
            $autorizado = $autorizadoModel->where('celular', $autorizadoCelular);
            if ($autorizado) {
                $autorizadoId = $autorizado['id'];
            }
        }

        // Generate QR token (secure random)
        $qrToken = Helper::generateToken(32);

        // Generate expiration date (7 days from now)
        $fechaVencimiento = date('Y-m-d H:i:s', strtotime('+' . Helper::config('plazo_dias', 7) . ' days'));

        // Create package
        $paqueteId = $model->create([
            'numero_lista' => $numeroLista ?: $model->getNextNumeroLista(),
            'codigo_visible' => $codigoVisible,
            'qr_token' => $qrToken,
            'vendedor_id' => $vendedorId,
            'comprador_id' => $comprador,
            'autorizado_id' => $autorizadoId > 0 ? $autorizadoId : null,
            'fecha_recepcion' => date('Y-m-d H:i:s'),
            'fecha_vencimiento' => $fechaVencimiento,
            'estado' => 'Pendiente',
            'ubicacion' => 'DeUna',
            'observaciones' => $observaciones,
            'descripcion' => $descripcion,
            'monto_base' => $montoBase,
        ]);

        // Register in history
        $historialModel->registerChange($paqueteId, 'recepcion', null, 'Pendiente', Auth::userId(), 
            'Paquete recibido. Código: ' . $codigoVisible);

        Session::flash('success', 'Paquete registrado correctamente. Código: ' . $codigoVisible);
        $this->redirect('paquetes/confirmacion/' . $paqueteId);
    }

    /**
     * Show package confirmation (with code and QR)
     */
    public function confirmacion($id = null)
    {
        if ($id === null) {
            $this->redirect('paquetes');
        }

        $model = $this->model('Paquete');
        $paquete = $model->getDetalle($id);

        if (!$paquete) {
            Session::flash('error', 'Paquete no encontrado');
            $this->redirect('paquetes');
        }

        $qrUrl = Helper::getPackageUrl($paquete['qr_token']);
        $qrDataUri = $this->qr->toDataURI($qrUrl, 256, 4);

        $payment = $model->calculatePayment($id);
        $diasTranscurridos = $model->getDiasTranscurridos($id);

        $this->view('paquetes/confirm', [
            'paquete' => $paquete,
            'qr_url' => $qrUrl,
            'qr_data_uri' => $qrDataUri,
            'payment' => $payment,
            'dias_transcurridos' => $diasTranscurridos,
        ]);
    }

    /**
     * Public package lookup via QR token (entrega/{token})
     */
    public function entregaPublica($token = null): void
    {
        $model = $this->model('Paquete');
        $paquete = $model->getByQrToken($token);

        if (!$paquete) {
            http_response_code(404);
            echo 'Package not found';
            return;
        }

        $paquete = $model->getDetalle($paquete['id']);
        $payment = $model->calculatePayment($paquete['id']);
        $diasTranscurridos = $model->getDiasTranscurridos($paquete['id']);

        $qrUrl = Helper::getPackageUrl($paquete['qr_token']);
        $qrDataUri = $this->qr->toDataURI($qrUrl, 256, 4);

        $this->view('paquetes/show', [
            'paquete' => $paquete,
            'qr_url' => $qrUrl,
            'qr_data_uri' => $qrDataUri,
            'payment' => $payment,
            'dias_transcurridos' => $diasTranscurridos,
            'historial' => $this->model('Historial')->getByPaquete($paquete['id']),
            'public_view' => true,
        ]);
    }

    /**
     * Show package detail
     */
    public function detalle($id = null)
    {
        if ($id === null) {
            $this->redirect('paquetes');
        }

        $model = $this->model('Paquete');
        $historialModel = $this->model('Historial');

        $paquete = $model->getDetalle($id);
        if (!$paquete) {
            Session::flash('error', 'Paquete no encontrado');
            $this->redirect('paquetes');
        }

        $historial = $historialModel->getByPaquete($id);
        $payment = $model->calculatePayment($id);
        $diasTranscurridos = $model->getDiasTranscurridos($id);

        $this->view('paquetes/show', [
            'paquete' => $paquete,
            'historial' => $historial,
            'payment' => $payment,
            'dias_transcurridos' => $diasTranscurridos,
        ]);
    }

    /**
     * Quick delivery: search by code or show delivery form
     */
    public function entrega($id = null)
    {
        $model = $this->model('Paquete');

        if (isset($_POST['codigo_busqueda'])) {
            $codigo = trim($_POST['codigo_busqueda']);
            $paquete = $model->getByCodigoVisible($codigo);

            if (!$paquete) {
                Session::flash('error', 'No se encontró ningún paquete con el código: ' . $codigo);
                $this->redirect('paquetes/entrega');
            }

            $this->redirect('paquetes/pago/' . $paquete['id']);
        }

        if ($id !== null) {
            // Show payment/delivery form for specific package
            $paquete = $model->getDetalle($id);
            if (!$paquete) {
                Session::flash('error', 'Paquete no encontrado');
                $this->redirect('paquetes/entrega');
            }
            $payment = $model->calculatePayment($id);
            $diasTranscurridos = $model->getDiasTranscurridos($id);

            $this->view('paquetes/entrega', [
                'paquete' => $paquete,
                'payment' => $payment,
                'dias_transcurridos' => $diasTranscurridos,
            ]);
        } else {
            // Show quick delivery search form
            $recientes = $model->query("
                SELECT p.*, c.nombre_completo as comprador_nombre
                FROM paquetes p
                LEFT JOIN compradores c ON c.id = p.comprador_id
                WHERE p.estado = 'Pendiente'
                ORDER BY p.fecha_recepcion DESC
                LIMIT 10
            ");

            $this->view('paquetes/entrega', [
                'paquetes_recientes' => $recientes,
                'payment' => null,
                'paquete' => null,
            ]);
        }
    }

    /**
     * Show payment form for a package
     */
    public function pago($id = null)
    {
        $model = $this->model('Paquete');
        $paquete = $model->getDetalle($id);

        if (!$paquete) {
            Session::flash('error', 'Paquete no encontrado');
            $this->redirect('paquetes/entrega');
        }

        $payment = $model->calculatePayment($id);
        $diasTranscurridos = $model->getDiasTranscurridos($id);

        $this->view('paquetes/pago', [
            'paquete' => $paquete,
            'payment' => $payment,
            'dias_transcurridos' => $diasTranscurridos,
        ]);
    }

    /**
     * Process package delivery (POST)
     */
    public function entregar($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('paquetes/entrega/' . $id);
        }

        $model = $this->model('Paquete');
        $pagoModel = $this->model('Pago');
        $historialModel = $this->model('Historial');

        $paquete = $model->find($id);
        if (!$paquete) {
            Session::flash('error', 'Paquete no encontrado');
            $this->redirect('paquetes/entrega');
        }

        $errors = [];

        // Validate payment
        $montoBase = (float)($_POST['monto_base'] ?? 0);
        $comisionAtraso = (float)($_POST['comision_atraso'] ?? 0);
        $medioPago = trim($_POST['medio_pago'] ?? '');
        $referencia = trim($_POST['referencia'] ?? '');

        if (empty($medioPago)) {
            $errors[] = 'Debe seleccionar el medio de pago';
        }

        if (!empty($errors)) {
            Session::flash('error', implode(', ', $errors));
            $this->redirect('paquetes/pago/' . $id);
        }

        $total = $montoBase + $comisionAtraso;

        // Register payment
        $pagoModel->create([
            'paquete_id' => $id,
            'monto_base' => $montoBase,
            'comision_atraso' => $comisionAtraso,
            'total' => $total,
            'medio_pago' => $medioPago,
            'fecha_hora' => date('Y-m-d H:i:s'),
            'usuario_id' => Auth::userId(),
            'referencia' => $referencia,
        ]);

        // Update package status
        $estadoAnterior = $paquete['estado'];
        $model->update($id, [
            'estado' => 'Entregado',
            'observaciones' => trim($_POST['observaciones'] ?? '') . ' | Entregado el ' . date('Y-m-d H:i:s'),
            'fecha_actualizacion' => date('Y-m-d H:i:s'),
        ]);

        // Register in history
        $historialModel->registerChange($id, 'entrega', $estadoAnterior, 'Entregado', Auth::userId(), 
            'Pago: $' . number_format($total, 2) . ' | ' . $medioPago . ($referencia ? ' Ref: ' . $referencia : ''));

        Session::flash('success', 'Paquete entregado. Total cobrado: $' . number_format($total, 2));
        $this->redirect('paquetes/detalle/' . $id);
    }

    /**
     * Print/download package label (with code and QR)
     */
    public function imprimir($id = null)
    {
        $model = $this->model('Paquete');
        $paquete = $model->getDetalle($id);

        if (!$paquete) {
            Session::flash('error', 'Paquete no encontrado');
            $this->redirect('paquetes');
        }

        $qrUrl = Helper::getPackageUrl($paquete['qr_token']);
        $qrDataUri = $this->qr->toDataURI($qrUrl, 256, 4);

        $this->view('paquetes/print', [
            'paquete' => $paquete,
            'qr_data_uri' => $qrDataUri,
            'qr_url' => $qrUrl,
        ]);
    }

    /**
     * Generate QR code image (server-side PNG)
     */
    public function qrCode($token = null)
    {
        if (empty($token)) {
            http_response_code(404);
            exit('Not found');
        }

        $model = $this->model('Paquete');
        $paquete = $model->getByQrToken($token);

        if (!$paquete) {
            http_response_code(404);
            exit('Package not found');
        }

        // Generate QR code PNG that encodes the package URL
        $packageUrl = Helper::getPackageUrl($token);
        $this->qr->outputPNG($packageUrl, 320, 4);
    }

    /**
     * API: Search package by code for quick delivery
     */
    public function apiBuscar()
    {
        header('Content-Type: application/json');

        $codigo = trim($_GET['codigo'] ?? $_POST['codigo'] ?? '');
        $model = $this->model('Paquete');

        $paquete = $model->getByCodigoVisible($codigo);

        if (!$paquete) {
            echo json_encode(['success' => false, 'message' => 'Paquete no encontrado']);
            return;
        }

        $detalle = $model->getDetalle($paquete['id']);
        $payment = $model->calculatePayment($paquete['id']);
        $diasTranscurridos = $model->getDiasTranscurridos($paquete['id']);

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $paquete['id'],
                'codigo_visible' => $paquete['codigo_visible'],
                'qr_token' => $paquete['qr_token'],
                'estado' => $paquete['estado'],
                'comprador_nombre' => $detalle['comprador_nombre'],
                'vendedor_nombre' => $detalle['vendedor_nombre'],
                'vendedor_celular' => $detalle['vendedor_celular'],
                'autorizado_nombre' => $detalle['autorizado_nombre'],
                'fecha_recepcion' => $paquete['fecha_recepcion'],
                'dias_transcurridos' => $diasTranscurridos,
                'monto_base' => $payment['base'],
                'comision_atraso' => $payment['comision'],
                'total_pagar' => $payment['total'],
            ],
        ]);
    }

    /**
     * API: Search packages for autocomplete
     */
    public function apiBuscarLista()
    {
        header('Content-Type: application/json');

        $query = trim($_GET['q'] ?? '');
        $model = $this->model('Paquete');

        if (strlen($query) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }

        $paquetes = $model->query("
            SELECT p.id, p.codigo_visible, c.nombre_completo as comprador_nombre
            FROM paquetes p
            LEFT JOIN compradores c ON c.id = p.comprador_id
            WHERE (p.codigo_visible LIKE ? OR p.id LIKE ?)
              AND p.estado IN ('Pendiente', 'Incidencia')
            ORDER BY p.fecha_recepcion DESC
            LIMIT 10
        ", ['%' . $query . '%', '%' . $query . '%']);

        echo json_encode([
            'success' => true,
            'data' => $paquetes,
        ]);
    }

    /**
     * Anular (cancel) a package
     */
    public function anular($id = null)
    {
        $model = $this->model('Paquete');
        $historialModel = $this->model('Historial');

        $paquete = $model->find($id);
        if (!$paquete) {
            Session::flash('error', 'Paquete no encontrado');
            $this->redirect('paquetes');
        }

        if ($paquete['estado'] === 'Anulado') {
            Session::flash('warning', 'El paquete ya está anulado');
            $this->redirect('paquetes/detalle/' . $id);
        }

        $estadoAnterior = $paquete['estado'];
        $model->update($id, ['estado' => 'Anulado']);
        $historialModel->registerChange($id, 'anulacion', $estadoAnterior, 'Anulado', Auth::userId(),
            'Paquete anulado');

        Session::flash('success', 'Paquete anulado correctamente');
        $this->redirect('paquetes/detalle/' . $id);
    }
}
