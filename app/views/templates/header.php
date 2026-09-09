<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? Helper::escape($pageTitle) . ' | ' : ''; ?>DeUna</title>
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php if (!Auth::isAuthenticated()): ?>
        <!-- Public content (login page handled separately) -->
    <?php else: ?>
    <nav class="navbar">
        <div class="nav-container">
            <a href="/dashboard" class="navbar-brand">
                <span class="logo-icon">📦</span>
                <span>DeUna</span>
            </a>
            <div class="nav-links">
                <a href="/dashboard" class="nav-link"><span class="icon">🏠</span> Dashboard</a>
                <a href="/paquetes" class="nav-link"><span class="icon">📦</span> Paquetes</a>
                <a href="/paquetes/entrega" class="nav-link"><span class="icon">🚚</span> Entrega Rápida</a>
                <a href="/paquetes/crear" class="nav-link"><span class="icon">➕</span> Nuevo Paquete</a>
                <a href="/reportes" class="nav-link"><span class="icon">📊</span> Reportes</a>
                <?php if (Auth::isAdmin()): ?>
                <a href="/usuarios" class="nav-link"><span class="icon">👤</span> Usuarios</a>
                <a href="/configuracion" class="nav-link"><span class="icon">⚙️</span> Configuración</a>
                <?php endif; ?>
                <div class="user-dropdown">
                    <button class="dropdown-btn">
                        <?= Helper::escape(Auth::getUserName()) ?> <span>▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="/logout">Cerrar Sesión</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flash-container">
        <?php
        $flashes = Session::getFlashes();
        foreach ($flashes as $flash):
        ?>
        <div class="flash flash-<?= $flash['type'] ?>">
            <span class="flash-icon">
                <?php if ($flash['type'] === 'success'): ?>✓
                <?php elseif ($flash['type'] === 'error'): ?>✗
                <?php elseif ($flash['type'] === 'warning'): ?>⚠
                <?php else: ?>ℹ<?php endif; ?>
            </span>
            <span><?= Helper::escape($flash['message']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
