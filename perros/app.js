/**
 * app.js — Lógica Frontend del Sistema de Adopción
 *
 * Módulo JavaScript ES6+ que gestiona:
 *   - Carga dinámica del catálogo de perros (Fetch API)
 *   - Flujo de adopción sin recarga de página
 *   - Alertas modernas de éxito/error
 *   - Prevención de doble clic
 *   - Eliminación animada de tarjetas post-adopción
 *
 * Arquitectura: Módulo IIFE con separación clara de responsabilidades.
 * No usa frameworks externos — JavaScript vanilla moderno.
 *
 * @module AdopcionApp
 * @version 1.0.0
 */

'use strict';

// ─── Módulo principal (IIFE para encapsulamiento) ─────────────────────────────
const AdopcionApp = (() => {

    // ── Configuración centralizada ─────────────────────────────────────────────
    const CONFIG = {
        API_BASE:      'api.php',
        ANIM_DURACION: 500,       // ms para animaciones de salida
        TOAST_DURACION: 5000,     // ms que permanece el toast
    };

    // ── Referencias al DOM (cacheadas para eficiencia) ─────────────────────────
    const DOM = {
        get catalogo()   { return document.getElementById('catalogo-perros'); },
        get loader()     { return document.getElementById('loader'); },
        get sinPerros()  { return document.getElementById('sin-perros'); },
        get toastZona()  { return document.getElementById('toast-zona'); },
        get contadorNum(){ return document.getElementById('contador-num'); },
        get modalOverlay(){ return document.getElementById('modal-overlay'); },
        get modalNombre(){ return document.getElementById('modal-perro-nombre'); },
        get modalMensaje(){ return document.getElementById('modal-mensaje'); },
        get modalConfirm(){ return document.getElementById('modal-confirmar'); },
        get modalCancelar(){ return document.getElementById('modal-cancelar'); },
    };

    // ── Estado de la aplicación ────────────────────────────────────────────────
    let estadoApp = {
        perros:           [],       // catálogo actual
        solicitudPendiente: null,   // { perroId, nombrePerro, btnEl }
    };

    // ═══════════════════════════════════════════════════════════════════════════
    //  INICIALIZACIÓN
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Punto de entrada. Se llama cuando el DOM está listo.
     */
    function init() {
        cargarCatalogo();
        registrarEventosGlobales();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  CARGA DEL CATÁLOGO
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Obtiene los perros disponibles desde la API y renderiza las tarjetas.
     */
    async function cargarCatalogo() {
        mostrarLoader(true);

        try {
            const respuesta = await fetchApi('listar_perros');

            if (!respuesta.ok) {
                throw new Error(respuesta.mensaje || 'Error al cargar perros.');
            }

            estadoApp.perros = respuesta.data.perros;
            renderizarCatalogo(estadoApp.perros);
            actualizarContador(respuesta.data.total);

        } catch (error) {
            console.error('[AdopcionApp] Error cargando catálogo:', error);
            mostrarToast('No se pudo cargar el catálogo. Intenta de nuevo.', 'error');
        } finally {
            mostrarLoader(false);
        }
    }

    /**
     * Genera y muestra las tarjetas visuales de cada perro.
     *
     * @param {Array} perros - Lista de objetos perro
     */
    function renderizarCatalogo(perros) {
        const contenedor = DOM.catalogo;
        if (!contenedor) return;

        contenedor.innerHTML = '';

        if (perros.length === 0) {
            DOM.sinPerros?.classList.remove('hidden');
            return;
        }

        DOM.sinPerros?.classList.add('hidden');

        // Fragment para un solo reflow del DOM
        const fragment = document.createDocumentFragment();

        perros.forEach((perro, index) => {
            const tarjeta = crearTarjetaPerro(perro, index);
            fragment.appendChild(tarjeta);
        });

        contenedor.appendChild(fragment);
    }

    /**
     * Crea el elemento DOM de una tarjeta de perro.
     *
     * @param {Object} perro - Datos del perro
     * @param {number} index - Índice para animación escalonada
     * @returns {HTMLElement}
     */
    function crearTarjetaPerro(perro, index) {
        const div = document.createElement('div');
        div.className = 'tarjeta-perro';
        div.dataset.perroId = perro.id;

        // Delay de entrada escalonado para efecto cascada
        div.style.animationDelay = `${index * 80}ms`;

        div.innerHTML = `
            <div class="tarjeta-imagen-wrap">
                <img
                    src="${perro.imagen_url}"
                    alt="Foto de ${perro.nombre}"
                    class="tarjeta-imagen"
                    loading="lazy"
                    onerror="this.src='https://via.placeholder.com/400x280?text=Sin+imagen'"
                />
                <span class="badge-disponible">Disponible</span>
            </div>
            <div class="tarjeta-body">
                <div class="tarjeta-header">
                    <h3 class="tarjeta-nombre">${perro.nombre}</h3>
                    <span class="tarjeta-raza">${perro.raza}</span>
                </div>
                <p class="tarjeta-edad">
                    <svg class="icono-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    ${perro.edad_formateada}
                </p>
                <p class="tarjeta-desc">${perro.descripcion}</p>
                <button
                    class="btn-adoptar"
                    data-perro-id="${perro.id}"
                    data-perro-nombre="${perro.nombre}"
                    aria-label="Solicitar adopción de ${perro.nombre}"
                >
                    <svg class="icono-btn" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    Quiero adoptarlo
                </button>
            </div>
        `;

        return div;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  FLUJO DE ADOPCIÓN
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Inicia el proceso de adopción al hacer clic en "Adoptar".
     * Bloquea el botón inmediatamente y abre el modal de confirmación.
     *
     * @param {Event} event - Click event
     */
    function iniciarAdopcion(event) {
        const btn = event.target.closest('.btn-adoptar');
        if (!btn) return;

        const perroId     = parseInt(btn.dataset.perroId, 10);
        const nombrePerro = btn.dataset.perroNombre;

        if (isNaN(perroId) || !nombrePerro) return;

        // ── Bloquear botón INMEDIATAMENTE (evitar doble clic) ────────────────
        bloquearBoton(btn, true);

        // Guardar estado para usar en la confirmación
        estadoApp.solicitudPendiente = { perroId, nombrePerro, btnEl: btn };

        // Mostrar modal de confirmación
        abrirModalConfirmacion(nombrePerro);
    }

    /**
     * Envía la solicitud de adopción a la API tras confirmación.
     */
    async function confirmarAdopcion() {
        const { perroId, nombrePerro, btnEl } = estadoApp.solicitudPendiente ?? {};

        if (!perroId) return;

        cerrarModal();
        mostrarLoader(true);

        try {
            const respuesta = await fetchApi('solicitar_adopcion', {
                method: 'POST',
                body: JSON.stringify({
                    perro_id: perroId,
                    mensaje:  '', // Extensible: agregar campo de texto al modal
                }),
            });

            if (respuesta.ok) {
                // ── ÉXITO: animar y eliminar tarjeta del DOM ──────────────────
                mostrarToast(
                    `🐾 ¡Solicitud enviada! Pronto te contactaremos sobre ${nombrePerro}.`,
                    'exito'
                );
                eliminarTarjetaAnimada(perroId);
            } else {
                // ── ERROR: desbloquear botón para reintentar ───────────────────
                mostrarToast(respuesta.mensaje || 'Error al enviar la solicitud.', 'error');
                if (btnEl) bloquearBoton(btnEl, false);
            }

        } catch (error) {
            console.error('[AdopcionApp] Error en adopción:', error);
            mostrarToast('Error de conexión. Verifica tu internet e intenta de nuevo.', 'error');
            if (btnEl) bloquearBoton(btnEl, false);
        } finally {
            mostrarLoader(false);
            estadoApp.solicitudPendiente = null;
        }
    }

    /**
     * Cancela el proceso de adopción y desbloquea el botón.
     */
    function cancelarAdopcion() {
        const { btnEl } = estadoApp.solicitudPendiente ?? {};
        if (btnEl) bloquearBoton(btnEl, false);

        estadoApp.solicitudPendiente = null;
        cerrarModal();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  UI: TARJETAS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Anima y elimina la tarjeta de un perro del catálogo.
     *
     * @param {number} perroId
     */
    function eliminarTarjetaAnimada(perroId) {
        const tarjeta = document.querySelector(`.tarjeta-perro[data-perro-id="${perroId}"]`);
        if (!tarjeta) return;

        tarjeta.classList.add('tarjeta-saliendo');

        setTimeout(() => {
            tarjeta.remove();
            // Actualizar contador
            const restantes = document.querySelectorAll('.tarjeta-perro').length;
            actualizarContador(restantes);

            if (restantes === 0) {
                DOM.sinPerros?.classList.remove('hidden');
            }
        }, CONFIG.ANIM_DURACION);
    }

    /**
     * Bloquea o desbloquea un botón de adoptar.
     *
     * @param {HTMLElement} btn
     * @param {boolean}     bloquear
     */
    function bloquearBoton(btn, bloquear) {
        btn.disabled = bloquear;
        btn.classList.toggle('btn-bloqueado', bloquear);

        if (bloquear) {
            btn.dataset.textoOriginal = btn.innerHTML;
            btn.innerHTML = `
                <svg class="icono-btn icono-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Procesando...
            `;
        } else {
            btn.innerHTML = btn.dataset.textoOriginal || 'Quiero adoptarlo';
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  UI: MODAL DE CONFIRMACIÓN
    // ═══════════════════════════════════════════════════════════════════════════

    function abrirModalConfirmacion(nombrePerro) {
        if (DOM.modalNombre)  DOM.modalNombre.textContent  = nombrePerro;
        if (DOM.modalMensaje) DOM.modalMensaje.textContent =
            `¿Estás seguro que deseas solicitar la adopción de ${nombrePerro}? Un coordinador se pondrá en contacto contigo para continuar el proceso.`;

        DOM.modalOverlay?.classList.remove('hidden');
        DOM.modalOverlay?.classList.add('modal-abierto');

        // Foco al botón de confirmar (accesibilidad)
        setTimeout(() => DOM.modalConfirm?.focus(), 50);
    }

    function cerrarModal() {
        DOM.modalOverlay?.classList.add('hidden');
        DOM.modalOverlay?.classList.remove('modal-abierto');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  UI: TOASTS / NOTIFICACIONES
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Muestra una notificación toast moderna.
     *
     * @param {string} mensaje
     * @param {'exito'|'error'|'info'} tipo
     */
    function mostrarToast(mensaje, tipo = 'info') {
        const zona = DOM.toastZona;
        if (!zona) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${tipo}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');

        const iconos = {
            exito: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="toast-icono">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>`,
            error: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="toast-icono">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>`,
            info:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="toast-icono">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>`,
        };

        toast.innerHTML = `
            ${iconos[tipo] || iconos.info}
            <span class="toast-texto">${escapeHtml(mensaje)}</span>
            <button class="toast-cerrar" aria-label="Cerrar notificación">✕</button>
        `;

        // Cerrar al hacer clic
        toast.querySelector('.toast-cerrar').addEventListener('click', () => {
            cerrarToast(toast);
        });

        zona.appendChild(toast);

        // Auto-cerrar después de CONFIG.TOAST_DURACION
        setTimeout(() => cerrarToast(toast), CONFIG.TOAST_DURACION);
    }

    function cerrarToast(toast) {
        toast.classList.add('toast-saliendo');
        setTimeout(() => toast.remove(), 400);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  UI: LOADER Y CONTADOR
    // ═══════════════════════════════════════════════════════════════════════════

    function mostrarLoader(visible) {
        DOM.loader?.classList.toggle('hidden', !visible);
    }

    function actualizarContador(total) {
        if (DOM.contadorNum) {
            DOM.contadorNum.textContent = total;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  COMUNICACIÓN CON LA API
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Wrapper de Fetch API con manejo de errores centralizado.
     *
     * @param {string} accion   - Nombre de la acción
     * @param {Object} opciones - Opciones de fetch (method, body, etc.)
     * @returns {Promise<Object>} Respuesta JSON parseada
     */
    async function fetchApi(accion, opciones = {}) {
        const url = `${CONFIG.API_BASE}?accion=${encodeURIComponent(accion)}`;

        const opcionesFetch = {
            method:  'GET',
            headers: {
                'Content-Type':     'application/json',
                'X-Requested-With': 'XMLHttpRequest', // Distinguir requests AJAX
            },
            ...opciones,
        };

        const response = await fetch(url, opcionesFetch);

        if (!response.ok && response.status !== 201 && response.status !== 409) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.mensaje || `HTTP ${response.status}`);
        }

        return response.json();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  UTILIDADES DE SEGURIDAD
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Escapa HTML para prevenir XSS en contenido dinámico.
     *
     * @param {string} str
     * @returns {string}
     */
    function escapeHtml(str) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(str).replace(/[&<>"']/g, m => map[m]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  EVENTOS GLOBALES
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Registra todos los listeners de eventos usando delegación.
     * Delegación de eventos: un listener en el padre cubre todos los hijos.
     */
    function registrarEventosGlobales() {
        // ── Click en catálogo: detectar botones de adoptar ──────────────────
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-adoptar')) {
                iniciarAdopcion(e);
            }
        });

        // ── Modal: confirmar ────────────────────────────────────────────────
        DOM.modalConfirm?.addEventListener('click', confirmarAdopcion);

        // ── Modal: cancelar ─────────────────────────────────────────────────
        DOM.modalCancelar?.addEventListener('click', cancelarAdopcion);

        // ── Modal: cerrar con ESC ───────────────────────────────────────────
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') cancelarAdopcion();
        });

        // ── Modal: cerrar al hacer clic en el overlay ───────────────────────
        DOM.modalOverlay?.addEventListener('click', (e) => {
            if (e.target === DOM.modalOverlay) cancelarAdopcion();
        });
    }

    // ─── API pública del módulo ───────────────────────────────────────────────
    return { init };

})();

// ─── Arranque cuando el DOM esté listo ───────────────────────────────────────
document.addEventListener('DOMContentLoaded', AdopcionApp.init);
