-- DeUna - Database Schema
-- Sistema de recepción, custodia y entrega de paquetes
-- SQLite compatible

-- Drop existing tables (for re-migration during development)
DROP TABLE IF EXISTS historial;
DROP TABLE IF EXISTS pagos;
DROP TABLE IF EXISTS paquetes;
DROP TABLE IF EXISTS autorizados;
DROP TABLE IF EXISTS compradores;
DROP TABLE IF EXISTS vendedores;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS configuracion;

-- Configuration table (tarifas, plazos, parámetros)
CREATE TABLE configuracion (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    clave TEXT NOT NULL UNIQUE,
    valor TEXT NOT NULL,
    fecha_actualizacion TEXT NOT NULL,
    usuario_id INTEGER,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Users (admin and operators)
CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    usuario TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    rol TEXT NOT NULL CHECK (rol IN ('admin', 'operador')),
    estado TEXT NOT NULL DEFAULT 'activo' CHECK (estado IN ('activo', 'inactivo')),
    fecha_creacion TEXT NOT NULL
);

-- Vendors
CREATE TABLE vendedores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    celular TEXT NOT NULL,
    observaciones TEXT,
    fecha_registro TEXT NOT NULL
);

-- Buyers
CREATE TABLE compradores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_completo TEXT NOT NULL,
    celular TEXT,
    observaciones TEXT
);

-- Authorized persons (can pick up packages)
CREATE TABLE autorizados (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_completo TEXT NOT NULL,
    celular TEXT,
    relacion_con_comprador TEXT,
    comprador_id INTEGER,
    FOREIGN KEY (comprador_id) REFERENCES compradores(id) ON DELETE SET NULL
);

-- Packages (central entity)
CREATE TABLE paquetes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    numero_lista INTEGER NOT NULL,
    codigo_visible TEXT NOT NULL,
    qr_token TEXT NOT NULL UNIQUE,
    vendedor_id INTEGER NOT NULL,
    comprador_id INTEGER NOT NULL,
    autorizado_id INTEGER,
    fecha_recepcion TEXT NOT NULL,
    fecha_vencimiento TEXT NOT NULL,
    estado TEXT NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente', 'Entregado', 'Anulado', 'Incidencia')),
    ubicacion TEXT,
    observaciones TEXT,
    descripcion TEXT,
    monto_base REAL DEFAULT 0,
    FOREIGN KEY (vendedor_id) REFERENCES vendedores(id),
    FOREIGN KEY (comprador_id) REFERENCES compradores(id),
    FOREIGN KEY (autorizado_id) REFERENCES autorizados(id)
);

-- Payments
CREATE TABLE pagos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    paquete_id INTEGER NOT NULL,
    monto_base REAL NOT NULL,
    comision_atraso REAL NOT NULL DEFAULT 0,
    total REAL NOT NULL,
    medio_pago TEXT NOT NULL CHECK (medio_pago IN ('Efectivo', 'Tarjeta', 'Transferencia', 'Otro')),
    fecha_hora TEXT NOT NULL,
    usuario_id INTEGER NOT NULL,
    referencia TEXT,
    FOREIGN KEY (paquete_id) REFERENCES paquetes(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Audit history
CREATE TABLE historial (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    paquete_id INTEGER NOT NULL,
    accion TEXT NOT NULL,
    estado_anterior TEXT,
    estado_nuevo TEXT,
    fecha_hora TEXT NOT NULL,
    usuario_id INTEGER,
    detalle TEXT,
    FOREIGN KEY (paquete_id) REFERENCES paquetes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Indexes for performance
CREATE INDEX idx_paquetes_codigo ON paquetes(codigo_visible);
CREATE INDEX idx_paquetes_qr ON paquetes(qr_token);
CREATE INDEX idx_paquetes_estado ON paquetes(estado);
CREATE INDEX idx_paquetes_vendedor ON paquetes(vendedor_id);
CREATE INDEX idx_paquetes_comprador ON paquetes(comprador_id);
CREATE INDEX idx_paquetes_fecha ON paquetes(fecha_recepcion);
CREATE INDEX idx_pagos_paquete ON pagos(paquete_id);
CREATE INDEX idx_historial_paquete ON historial(paquete_id);
