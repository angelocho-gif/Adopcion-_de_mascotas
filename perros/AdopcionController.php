<?php

/**
 * AdopcionController.php — Controlador Principal de Adopciones
 *
 * Actúa como intermediario entre la capa de presentación (JavaScript/Fetch)
 * y los modelos de dominio. Maneja el routing interno, valida sesiones,
 * sanitiza entradas y devuelve respuestas JSON estructuradas.
 *
 * Principios SOLID aplicados:
 *   S — Responsabilidad única: orquestar el flujo de adopción.
 *   O — Abierto/Cerrado: nuevas acciones se añaden sin modificar las existentes.
 *   L — Sustitución de Liskov: métodos con contratos claros.
 *
 * @arquitectura  Capa de Controladores (MVC)
 * @seguridad     Validación de sesión, sanitización, JSON responses, CSRF básico
 */

declare(strict_types=1);

// ─── Dependencias ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Perro.php';
require_once __DIR__ . '/../models/Solicitud.php';

/**
 * Clase AdopcionController
 */
class AdopcionController
{
    /** @var PDO Conexión a base de datos */
    private PDO $db;

    /** @var Perro Repositorio de perros */
    private Perro $perroModel;

    /** @var Solicitud Repositorio de solicitudes */
    private Solicitud $solicitudModel;

    /**
     * Constructor — Inicializa dependencias mediante inyección.
     */
    public function __construct()
    {
        $this->db             = Database::getInstance()->getConnection();
        $this->perroModel     = new Perro($this->db);
        $this->solicitudModel = new Solicitud($this->db);
    }

    // ─── PUNTO DE ENTRADA PRINCIPAL ───────────────────────────────────────────

    /**
     * Router de acciones. Recibe el parámetro 'accion' del request
     * y despacha al método correspondiente.
     *
     * @param string $accion Nombre de la acción solicitada
     * @return void Envía respuesta JSON y termina ejecución
     */
    public function manejarRequest(string $accion): void
    {
        // Forzar respuesta JSON en todos los casos
        header('Content-Type: application/json; charset=UTF-8');
        // Cabeceras de seguridad básicas
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        try {
            match ($accion) {
                'listar_perros'      => $this->listarPerros(),
                'solicitar_adopcion' => $this->solicitarAdopcion(),
                'mis_solicitudes'    => $this->misSolicitudes(),
                default              => $this->responderError('Acción no reconocida.', 400),
            };
        } catch (Throwable $e) {
            // Captura cualquier error no manejado internamente
            error_log('[CONTROLLER_FATAL] ' . $e->getMessage());
            $this->responderError('Error interno del servidor.', 500);
        }
    }

    // ─── ACCIONES PÚBLICAS ────────────────────────────────────────────────────

    /**
     * Acción: listar_perros
     * Retorna el catálogo de perros disponibles en formato JSON.
     * No requiere sesión (es pública).
     */
    private function listarPerros(): void
    {
        $perros = $this->perroModel->obtenerDisponibles();

        // Enriquecer datos para el frontend (formateo de edad)
        $perros = array_map(function (array $perro) {
            $perro['edad_formateada'] = Perro::formatearEdad((int)$perro['edad_meses']);
            // Sanitizar para output HTML (defensa en profundidad)
            $perro['nombre']      = htmlspecialchars($perro['nombre'],      ENT_QUOTES, 'UTF-8');
            $perro['raza']        = htmlspecialchars($perro['raza'],        ENT_QUOTES, 'UTF-8');
            $perro['descripcion'] = htmlspecialchars($perro['descripcion'], ENT_QUOTES, 'UTF-8');
            return $perro;
        }, $perros);

        $this->responderExito([
            'perros' => $perros,
            'total'  => count($perros),
        ]);
    }

    /**
     * Acción: solicitar_adopcion
     * Procesa una solicitud de adopción con todas las validaciones.
     *
     * Flujo:
     * 1. Verificar sesión activa
     * 2. Validar y sanitizar entrada
     * 3. Verificar estado del perro
     * 4. Verificar solicitudes duplicadas
     * 5. Registrar en transacción SQL
     */
    private function solicitarAdopcion(): void
    {
        // ── 1. Verificar sesión activa ─────────────────────────────────────
        if (!$this->sesionActiva()) {
            $this->responderError(
                'Debes iniciar sesión para solicitar una adopción.',
                401
            );
            return;
        }

        // ── 2. Solo aceptar POST ───────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responderError('Método no permitido.', 405);
            return;
        }

        // ── 3. Leer y validar body JSON ────────────────────────────────────
        $body = $this->leerBodyJson();

        if ($body === null) {
            $this->responderError('Datos de solicitud inválidos.', 400);
            return;
        }

        // Sanitizar y validar perro_id
        $perroId = filter_var($body['perro_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($perroId === false || $perroId === null) {
            $this->responderError('ID de perro inválido.', 400);
            return;
        }

        // Sanitizar mensaje opcional
        $mensaje = isset($body['mensaje'])
            ? substr(trim((string)$body['mensaje']), 0, 500) // máx 500 chars
            : '';

        $usuarioId = (int)$_SESSION['usuario_id'];

        // ── 4. Verificar que el perro existe y está disponible ─────────────
        $perro = $this->perroModel->buscarPorId($perroId);

        if ($perro === null) {
            $this->responderError('El perro solicitado no existe.', 404);
            return;
        }

        if ($perro['estado'] !== Perro::ESTADO_DISPONIBLE) {
            $this->responderError(
                'Este perro ya no está disponible para adopción.',
                409
            );
            return;
        }

        // ── 5. Verificar solicitud duplicada ───────────────────────────────
        if ($this->solicitudModel->existeSolicitudActiva($usuarioId, $perroId)) {
            $this->responderError(
                'Ya tienes una solicitud activa para este perro.',
                409
            );
            return;
        }

        // ── 6. Registrar en transacción SQL ────────────────────────────────
        $resultado = $this->solicitudModel->registrarConTransaccion(
            $usuarioId,
            $perroId,
            $mensaje
        );

        $this->responderExito([
            'mensaje'      => '¡Solicitud enviada correctamente! Pronto nos pondremos en contacto contigo.',
            'solicitud_id' => $resultado['solicitudId'],
            'perro_nombre' => htmlspecialchars($perro['nombre'], ENT_QUOTES, 'UTF-8'),
        ], 201);
    }

    /**
     * Acción: mis_solicitudes
     * Retorna el historial de solicitudes del usuario en sesión.
     */
    private function misSolicitudes(): void
    {
        if (!$this->sesionActiva()) {
            $this->responderError('Sesión requerida.', 401);
            return;
        }

        $solicitudes = $this->solicitudModel->obtenerPorUsuario(
            (int)$_SESSION['usuario_id']
        );

        $this->responderExito(['solicitudes' => $solicitudes]);
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    /**
     * Verifica si existe una sesión válida con usuario autenticado.
     *
     * @return bool
     */
    private function sesionActiva(): bool
    {
        return isset($_SESSION['usuario_id'])
            && is_numeric($_SESSION['usuario_id'])
            && (int)$_SESSION['usuario_id'] > 0;
    }

    /**
     * Lee y decodifica el body JSON del request.
     *
     * @return array<string, mixed>|null
     */
    private function leerBodyJson(): ?array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return null;

        $data = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($data))
            ? $data
            : null;
    }

    /**
     * Envía respuesta JSON de éxito estandarizada.
     *
     * @param array<string, mixed> $datos
     * @param int                  $httpStatus
     */
    private function responderExito(array $datos, int $httpStatus = 200): void
    {
        http_response_code($httpStatus);
        echo json_encode([
            'ok'   => true,
            'data' => $datos,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Envía respuesta JSON de error estandarizada.
     *
     * @param string $mensaje
     * @param int    $httpStatus
     */
    private function responderError(string $mensaje, int $httpStatus = 400): void
    {
        http_response_code($httpStatus);
        echo json_encode([
            'ok'      => false,
            'mensaje' => $mensaje,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
