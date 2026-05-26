<?php

/**
 * api.php — Punto de Entrada de la API
 *
 * Recibe todas las peticiones Fetch del frontend,
 * las valida superficialmente y las despacha al controlador.
 *
 * URL patrón: /api.php?accion=nombre_accion
 *
 * @arquitectura  Capa de Enrutamiento / API Gateway
 * @seguridad     Sesión iniciada, validación de parámetro accion
 */

declare(strict_types=1);

// ─── Inicio de sesión seguro ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,   // JS no puede leer la cookie de sesión
        'cookie_secure'   => false,  // Cambiar a true en producción (HTTPS)
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
    ]);
}

// ─── Cabeceras de seguridad ───────────────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ─── Autoloading manual (sin Composer para máxima compatibilidad) ─────────────
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Perro.php';
require_once __DIR__ . '/models/Solicitud.php';
require_once __DIR__ . '/controllers/AdopcionController.php';

// ─── Leer y validar acción solicitada ────────────────────────────────────────
// Sanitizar: solo letras minúsculas y guiones bajos (whitelist de caracteres)
$accionRaw = $_GET['accion'] ?? '';
$accion    = preg_replace('/[^a-z_]/', '', strtolower((string)$accionRaw));

if (empty($accion)) {
    http_response_code(400);
    echo json_encode([
        'ok'      => false,
        'mensaje' => 'Parámetro "accion" requerido.',
    ]);
    exit;
}

// ─── Despachar al controlador ─────────────────────────────────────────────────
$controller = new AdopcionController();
$controller->manejarRequest($accion);
