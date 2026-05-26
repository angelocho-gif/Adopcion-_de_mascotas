<?php

/**
 * index.php — Vista Principal
 *
 * Punto de entrada para el usuario. Inicia sesión, renderiza
 * la estructura HTML y carga los assets. Los datos del catálogo
 * se cargan dinámicamente vía Fetch API (app.js).
 *
 * En una arquitectura más grande, esta vista sería invocada
 * por un router front-controller (index.php → Router → Controller → View).
 *
 * @arquitectura  Capa de Vistas (MVC)
 * @seguridad     Sesión segura, output escapado, CSP básico
 */

declare(strict_types=1);

// ─── Inicio de sesión seguro ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => false, // true en producción con HTTPS
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
    ]);
}

// ─── Simulación de usuario logueado para demo ─────────────────────────────────
// En producción: verificar credenciales reales con bcrypt
// Para probar sin login, se setea una sesión de demo automáticamente.
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['usuario_id']     = 2;           // ID de usuario demo
    $_SESSION['usuario_nombre'] = 'María González';
    $_SESSION['usuario_email']  = 'maria@ejemplo.com';
}

// Sanitizar para output (defensa en profundidad)
$nombreUsuario = htmlspecialchars(
    $_SESSION['usuario_nombre'] ?? 'Visitante',
    ENT_QUOTES,
    'UTF-8'
);
$estaLogueado = isset($_SESSION['usuario_id']);
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Plataforma de adopción de perros. Encuentra a tu compañero ideal.">

    <!-- Seguridad: prevenir clickjacking y sniffing de tipo MIME -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">

    <title>PataHogar — Adopción de Perros</title>

    <!-- Google Fonts: Playfair Display + DM Sans (para carácter editorial) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* ─── Variables de diseño (editorial cálido) ────────────────────── */
        :root {
            --cream:     #FDF8F0;
            --caramel:   #C8853A;
            --caramel-d: #A06828;
            --bark:      #2C1A0E;
            --bark-soft: #4A3728;
            --moss:      #4A7C59;
            --moss-d:    #355A42;
            --sand:      #E8D9C0;
            --sand-d:    #D4C0A0;
            --error:     #C0392B;
            --error-bg:  #FDECEA;
        }

        /* ─── Reset y base ──────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--cream);
            color: var(--bark);
            min-height: 100vh;
        }

        /* ─── Tipografía display ────────────────────────────────────────── */
        .font-display { font-family: 'Playfair Display', serif; }

        /* ─── HEADER ────────────────────────────────────────────────────── */
        .site-header {
            background: var(--bark);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 20px rgba(44,26,14,0.4);
        }

        .logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            color: var(--sand);
            letter-spacing: -0.02em;
        }

        .logo-paw { color: var(--caramel); }

        .usuario-chip {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            color: var(--sand);
            padding: 0.4rem 1rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ─── HERO ──────────────────────────────────────────────────────── */
        .hero {
            background: linear-gradient(135deg, var(--bark) 0%, var(--bark-soft) 100%);
            padding: 5rem 2rem 4rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 20% 50%, rgba(200,133,58,0.2) 0%, transparent 70%),
                radial-gradient(ellipse 40% 60% at 80% 30%, rgba(74,124,89,0.15) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-titulo {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            color: #fff;
            line-height: 1.1;
            margin-bottom: 1.5rem;
        }

        .hero-titulo span { color: var(--caramel); }

        .hero-subtitulo {
            color: var(--sand-d);
            font-size: 1.2rem;
            max-width: 560px;
            margin: 0 auto 2.5rem;
            font-weight: 300;
            line-height: 1.7;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .stat-chip {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            color: #fff;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .stat-num {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--caramel);
            font-weight: 700;
            display: block;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--sand-d);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        /* ─── SECCIÓN CATÁLOGO ──────────────────────────────────────────── */
        .seccion-catalogo {
            max-width: 1280px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }

        .seccion-titulo {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--bark);
            margin-bottom: 0.5rem;
        }

        .seccion-subtitulo {
            color: var(--bark-soft);
            opacity: 0.7;
            margin-bottom: 2.5rem;
            font-size: 1rem;
        }

        /* ─── GRID DE TARJETAS ──────────────────────────────────────────── */
        #catalogo-perros {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        /* ─── TARJETA DE PERRO ──────────────────────────────────────────── */
        .tarjeta-perro {
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(44,26,14,0.08);
            border: 1px solid var(--sand);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: tarjeta-entrada 0.5s ease both;
        }

        @keyframes tarjeta-entrada {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .tarjeta-perro:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(44,26,14,0.15);
        }

        /* ── Salida animada ── */
        .tarjeta-saliendo {
            animation: tarjeta-salida 0.5s ease forwards !important;
        }

        @keyframes tarjeta-salida {
            0%   { opacity: 1; transform: scale(1); }
            50%  { opacity: 0.5; transform: scale(1.04); }
            100% { opacity: 0; transform: scale(0.9); }
        }

        .tarjeta-imagen-wrap {
            position: relative;
            height: 220px;
            overflow: hidden;
        }

        .tarjeta-imagen {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .tarjeta-perro:hover .tarjeta-imagen {
            transform: scale(1.05);
        }

        .badge-disponible {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--moss);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
        }

        .tarjeta-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .tarjeta-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .tarjeta-nombre {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            color: var(--bark);
            font-weight: 700;
        }

        .tarjeta-raza {
            background: var(--sand);
            color: var(--bark-soft);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .tarjeta-edad {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--caramel);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .icono-inline {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }

        .tarjeta-desc {
            color: var(--bark-soft);
            font-size: 0.9rem;
            line-height: 1.6;
            opacity: 0.8;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ─── BOTÓN ADOPTAR ─────────────────────────────────────────────── */
        .btn-adoptar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.875rem;
            background: var(--caramel);
            color: #fff;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.15s ease;
            margin-top: 0.5rem;
        }

        .btn-adoptar:hover:not(:disabled) {
            background: var(--caramel-d);
            transform: translateY(-1px);
        }

        .btn-adoptar:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-adoptar:disabled,
        .btn-adoptar.btn-bloqueado {
            background: var(--sand-d);
            cursor: not-allowed;
            transform: none;
        }

        .icono-btn {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .icono-spin {
            animation: girar 0.8s linear infinite;
        }

        @keyframes girar {
            to { transform: rotate(360deg); }
        }

        /* ─── LOADER ────────────────────────────────────────────────────── */
        .loader-overlay {
            position: fixed;
            inset: 0;
            background: rgba(253,248,240,0.85);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 200;
        }

        .loader-spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--sand);
            border-top-color: var(--caramel);
            border-radius: 50%;
            animation: girar 0.8s linear infinite;
        }

        /* ─── MODAL ─────────────────────────────────────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(44,26,14,0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 300;
            padding: 1.5rem;
        }

        .modal-box {
            background: #fff;
            border-radius: 20px;
            padding: 2.5rem;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 24px 60px rgba(44,26,14,0.3);
            animation: modal-entrada 0.3s ease;
        }

        @keyframes modal-entrada {
            from { opacity: 0; transform: scale(0.92) translateY(16px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-icono {
            width: 56px;
            height: 56px;
            background: var(--sand);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--caramel);
        }

        .modal-icono svg { width: 28px; height: 28px; }

        .modal-titulo {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--bark);
            text-align: center;
            margin-bottom: 0.75rem;
        }

        .modal-nombre-perro {
            color: var(--caramel);
        }

        .modal-texto {
            color: var(--bark-soft);
            text-align: center;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .modal-acciones {
            display: flex;
            gap: 1rem;
        }

        .btn-cancelar {
            flex: 1;
            padding: 0.875rem;
            background: transparent;
            border: 2px solid var(--sand-d);
            color: var(--bark-soft);
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancelar:hover {
            border-color: var(--bark-soft);
            color: var(--bark);
        }

        .btn-confirmar {
            flex: 2;
            padding: 0.875rem;
            background: var(--moss);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-confirmar:hover { background: var(--moss-d); }

        /* ─── TOASTS ────────────────────────────────────────────────────── */
        #toast-zona {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 400;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-width: 380px;
            width: calc(100vw - 3rem);
        }

        .toast {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            background: #fff;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            box-shadow: 0 8px 30px rgba(44,26,14,0.18);
            border-left: 4px solid;
            animation: toast-entrada 0.35s ease;
        }

        @keyframes toast-entrada {
            from { opacity: 0; transform: translateX(20px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .toast-saliendo {
            animation: toast-salida 0.4s ease forwards !important;
        }

        @keyframes toast-salida {
            to { opacity: 0; transform: translateX(30px); }
        }

        .toast-exito  { border-color: var(--moss);  }
        .toast-error  { border-color: var(--error); }
        .toast-info   { border-color: var(--caramel); }

        .toast-exito .toast-icono { color: var(--moss);  }
        .toast-error .toast-icono { color: var(--error); }
        .toast-info  .toast-icono { color: var(--caramel); }

        .toast-icono { width: 22px; height: 22px; flex-shrink: 0; margin-top: 1px; }

        .toast-texto {
            flex: 1;
            font-size: 0.9rem;
            color: var(--bark);
            line-height: 1.5;
        }

        .toast-cerrar {
            background: none;
            border: none;
            color: var(--bark-soft);
            opacity: 0.4;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0;
            transition: opacity 0.2s;
            flex-shrink: 0;
        }

        .toast-cerrar:hover { opacity: 0.8; }

        /* ─── ESTADO VACÍO ──────────────────────────────────────────────── */
        .estado-vacio {
            text-align: center;
            padding: 5rem 2rem;
            color: var(--bark-soft);
        }

        .estado-vacio svg {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            color: var(--sand-d);
        }

        /* ─── FOOTER ────────────────────────────────────────────────────── */
        .site-footer {
            background: var(--bark);
            color: var(--sand-d);
            text-align: center;
            padding: 2rem;
            font-size: 0.85rem;
            margin-top: 4rem;
        }

        /* ─── Utility ───────────────────────────────────────────────────── */
        .hidden { display: none !important; }
    </style>
</head>

<body>

    <!-- ═══════════════════════════════════════════════════════════════════════
         LOADER (overlay global)
    ════════════════════════════════════════════════════════════════════════ -->
    <div id="loader" class="loader-overlay" role="status" aria-label="Cargando...">
        <div class="loader-spinner"></div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         MODAL DE CONFIRMACIÓN
    ════════════════════════════════════════════════════════════════════════ -->
    <div id="modal-overlay" class="modal-overlay hidden" role="dialog"
         aria-modal="true" aria-labelledby="modal-titulo-text">
        <div class="modal-box">
            <div class="modal-icono">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </div>
            <h2 id="modal-titulo-text" class="modal-titulo">
                Adoptar a <span id="modal-perro-nombre" class="modal-nombre-perro"></span>
            </h2>
            <p id="modal-mensaje" class="modal-texto"></p>
            <div class="modal-acciones">
                <button id="modal-cancelar"  class="btn-cancelar">Cancelar</button>
                <button id="modal-confirmar" class="btn-confirmar">
                    ¡Sí, quiero adoptarlo!
                </button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         ZONA DE TOASTS
    ════════════════════════════════════════════════════════════════════════ -->
    <div id="toast-zona" aria-live="polite" aria-atomic="false"></div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════════════════════════════════ -->
    <header class="site-header">
        <div class="logo-text">
            <span class="logo-paw">🐾</span> PataHogar
        </div>

        <?php if ($estaLogueado): ?>
            <div class="usuario-chip">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <?= $nombreUsuario ?>
            </div>
        <?php else: ?>
            <a href="login.php" class="usuario-chip" style="text-decoration:none;">
                Iniciar sesión
            </a>
        <?php endif; ?>
    </header>

    <!-- ═══════════════════════════════════════════════════════════════════════
         HERO
    ════════════════════════════════════════════════════════════════════════ -->
    <section class="hero">
        <h1 class="hero-titulo">
            Cada perro merece<br>
            <span>un hogar con amor</span>
        </h1>
        <p class="hero-subtitulo">
            Conectamos perros que buscan hogar con familias que tienen amor para dar.
            El proceso es simple, seguro y está lleno de esperanza.
        </p>
        <div class="hero-stats">
            <div class="stat-chip">
                <span class="stat-num" id="contador-num">…</span>
                <span class="stat-label">Perros disponibles</span>
            </div>
            <div class="stat-chip">
                <span class="stat-num">100%</span>
                <span class="stat-label">Proceso gratuito</span>
            </div>
            <div class="stat-chip">
                <span class="stat-num">♥</span>
                <span class="stat-label">Con amor</span>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════════════
         CATÁLOGO DE PERROS
    ════════════════════════════════════════════════════════════════════════ -->
    <main class="seccion-catalogo">
        <h2 class="seccion-titulo font-display">Perros disponibles</h2>
        <p class="seccion-subtitulo">
            Haz clic en "Quiero adoptarlo" para iniciar tu solicitud de adopción.
        </p>

        <!-- Grid dinámico — llenado por app.js -->
        <div id="catalogo-perros" role="list" aria-label="Catálogo de perros disponibles">
        </div>

        <!-- Estado vacío (oculto hasta que no queden perros) -->
        <div id="sin-perros" class="estado-vacio hidden" role="status">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="font-display text-2xl mb-2">¡Todos adoptados!</h3>
            <p class="opacity-60">
                Por ahora no hay perros disponibles.<br>
                ¡Vuelve pronto para ver nuevos amigos!
            </p>
        </div>
    </main>

    <!-- ═══════════════════════════════════════════════════════════════════════
         FOOTER
    ════════════════════════════════════════════════════════════════════════ -->
    <footer class="site-footer">
        <p>
            🐾 <strong>PataHogar</strong> — Sistema de Adopción de Perros
            &nbsp;·&nbsp; PHP 8 + MySQL + Tailwind CSS
            &nbsp;·&nbsp; Desarrollado con Ingeniería Aumentada
        </p>
        <p style="margin-top:0.5rem; opacity:0.5;">
            Este sistema es un proyecto de demostración con fines educativos.
        </p>
    </footer>

    <!-- ─── JavaScript ────────────────────────────────────────────────────── -->
    <script src="public/js/app.js"></script>

</body>
</html>
