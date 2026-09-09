<?php
/**
 * DeUna - Paquete Model
 * Entidad central del sistema
 */

class Paquete extends Model
{
    protected string $table = 'paquetes';

    /**
     * Get next list number based on configuration
     */
    public function getNextNumeroLista(): int
    {
        $tipo = Helper::config('numeracion_tipo', 'continua');

        if ($tipo === 'diaria') {
            $hoy = date('Y-m-d');
            $stmt = $this->db->prepare(
                "SELECT MAX(numero_lista) as max_num FROM {$this->table} WHERE date(fecha_recepcion) = ?"
            );
            $stmt->execute([$hoy]);
            $max = $stmt->fetch()['max_num'];
            return $max ? (int)$max + 1 : 1;
        } else {
            $stmt = $this->db->prepare("SELECT MAX(numero_lista) as max_num FROM {$this->table}");
            $stmt->execute();
            $max = $stmt->fetch()['max_num'];
            return $max ? (int)$max + 1 : 1;
        }
    }

    /**
     * Get next visible code sequence for a buyer
     * RN-07: codigo_visible = uppercase(firstLetter(buyerName)) + '-' + sequenceNumber
     * The sequence resets per buyer
     */
    public function getNextVisibleCodeSequence(int $compradorId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(MAX(CAST(SUBSTR(codigo_visible, INSTR(codigo_visible, '-') + 1) AS INTEGER)), 0) + 1 as next_seq
             FROM {$this->table}
             WHERE comprador_id = ?"
        );
        $stmt->execute([$compradorId]);
        $result = $stmt->fetch();
        return $result ? (int)$result['next_seq'] : 1;
    }

    /**
     * Get package by visible code (e.g., "M-8")
     */
    public function getByCodigoVisible(string $codigo): ?array
    {
        return $this->where('codigo_visible', $codigo);
    }

    /**
     * Get package by QR token
     */
    public function getByQrToken(string $token): ?array
    {
        return $this->where('qr_token', $token);
    }

    /**
     * Get package with full details by ID
     */
    public function getDetalle(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*,
                   v.nombre as vendedor_nombre, v.celular as vendedor_celular,
                   c.nombre_completo as comprador_nombre, c.celular as comprador_celular,
                   a.nombre_completo as autorizado_nombre, a.celular as autorizado_celular,
                   a.relacion_con_comprador
            FROM {$this->table} p
            LEFT JOIN vendedores v ON v.id = p.vendedor_id
            LEFT JOIN compradores c ON c.id = p.comprador_id
            LEFT JOIN autorizados a ON a.id = p.autorizado_id
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Search packages by various criteria
     */
    public function search(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['codigo'])) {
            $conditions[] = "p.codigo_visible LIKE ?";
            $params[] = '%' . $filters['codigo'] . '%';
        }
        if (!empty($filters['comprador'])) {
            $conditions[] = "c.nombre_completo LIKE ?";
            $params[] = '%' . $filters['comprador'] . '%';
        }
        if (!empty($filters['vendedor'])) {
            $conditions[] = "v.celular LIKE ?";
            $params[] = '%' . $filters['vendedor'] . '%';
        }
        if (!empty($filters['estado'])) {
            $conditions[] = "p.estado = ?";
            $params[] = $filters['estado'];
        }
        if (!empty($filters['fecha_desde'])) {
            $conditions[] = "date(p.fecha_recepcion) >= ?";
            $params[] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $conditions[] = "date(p.fecha_recepcion) <= ?";
            $params[] = $filters['fecha_hasta'];
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $stmt = $this->db->prepare("
            SELECT p.*,
                   v.nombre as vendedor_nombre,
                   c.nombre_completo as comprador_nombre,
                   a.nombre_completo as autorizado_nombre
            FROM {$this->table} p
            LEFT JOIN vendedores v ON v.id = p.vendedor_id
            LEFT JOIN compradores c ON c.id = p.comprador_id
            LEFT JOIN autorizados a ON a.id = p.autorizado_id
            {$whereClause}
            ORDER BY p.fecha_recepcion DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get pending packages (with days elapsed)
     */
    public function getPending(): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*,
                   v.nombre as vendedor_nombre,
                   c.nombre_completo as comprador_nombre,
                   a.nombre_completo as autorizado_nombre
            FROM {$this->table} p
            LEFT JOIN vendedores v ON v.id = p.vendedor_id
            LEFT JOIN compradores c ON c.id = p.comprador_id
            LEFT JOIN autorizados a ON a.id = p.autorizado_id
            WHERE p.estado = 'Pendiente'
            ORDER BY p.fecha_recepcion ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get packages with late commission
     */
    public function getWithLateCommission(): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*,
                   v.nombre as vendedor_nombre,
                   c.nombre_completo as comprador_nombre
            FROM {$this->table} p
            LEFT JOIN vendedores v ON v.id = p.vendedor_id
            LEFT JOIN compradores c ON c.id = p.comprador_id
            WHERE p.estado = 'Pendiente'
              AND datetime(p.fecha_recepcion, '+' || (SELECT valor FROM configuracion WHERE clave = 'plazo_dias') || ' days') < datetime('now')
            ORDER BY p.fecha_recepcion ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get dashboard stats
     */
    public function getStats(): array
    {
        $stats = [];

        // Pending count
        $stats['pendientes'] = $this->count(['estado' => 'Pendiente']);

        // Delivered count (today)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table} 
            WHERE estado = 'Entregado' AND date(fecha_actualizacion) = date('now')
        ");
        $stmt->execute();
        $stats['entregados_hoy'] = (int)$stmt->fetchColumn();

        // Overdue count
        $plazo = (int)Helper::config('plazo_dias', 7);
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table} 
            WHERE estado = 'Pendiente' 
            AND datetime(fecha_recepcion, '+{$plazo} days') < datetime('now')
        ");
        $stmt->execute();
        $stats['atrasados'] = (int)$stmt->fetchColumn();

        // Total received today
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table} 
            WHERE date(fecha_recepcion) = date('now')
        ");
        $stmt->execute();
        $stats['recibidos_hoy'] = (int)$stmt->fetchColumn();

        // Amount collected today
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total), 0) FROM pagos p
            JOIN paquetes pk ON pk.id = p.paquete_id
            WHERE date(p.fecha_hora) = date('now')
        ");
        $stmt->execute();
        $stats['monto_cobrado'] = (float)$stmt->fetchColumn();

        return $stats;
    }

    /**
     * Calculate days elapsed since reception
     */
    public function getDiasTranscurridos(int $id): int
    {
        $paquete = $this->find($id);
        if (!$paquete) return 0;
        $dt = new DateTime($paquete['fecha_recepcion']);
        $now = new DateTime();
        return (int)$dt->diff($now)->format('%a');
    }

    /**
     * Check if package is overdue
     */
    public function isOverdue(int $id): bool
    {
        $paquete = $this->find($id);
        if (!$paquete) return false;
        $plazo = (int)Helper::config('plazo_dias', 7);
        $dt = new DateTime($paquete['fecha_recepcion']);
        $now = new DateTime();
        $diff = $dt->diff($now);
        $totalHours = ($diff->days * 24) + $diff->h;
        return $totalHours > ($plazo * 24);
    }

    /**
     * Calculate payment amount (base + late commission)
     */
    public function calculatePayment(int $id): array
    {
        $paquete = $this->find($id);
        if (!$paquete) return ['base' => 0, 'comision' => 0, 'total' => 0, 'vencido' => false];

        $base = (float)($paquete['monto_base'] ?: Helper::config('tarifa_base', 10));
        $vencido = $this->isOverdue($id);
        $comision = 0;

        if ($vencido) {
            $tipo = Helper::config('tipo_comision', 'fijo');
            if ($tipo === 'fijo') {
                $comision = (float)Helper::config('monto_comision', 5);
            } else {
                $comision = $base * ((float)Helper::config('porcentaje_comision', 0) / 100);
            }
        }

        return [
            'base' => $base,
            'comision' => $comision,
            'total' => $base + $comision,
            'vencido' => $vencido,
            'dias_transcurridos' => $this->getDiasTranscurridos($id),
        ];
    }
}
