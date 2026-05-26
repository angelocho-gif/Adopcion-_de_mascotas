<?php

/**
 * Solicitud.php — Modelo de Dominio: Solicitud de Adopción
 *
 * Gestiona la lógica de negocio y acceso a datos de las solicitudes.
 * Contiene la verificación de duplicados y el registro transaccional.
 *
 * Principios SOLID aplicados:
 *   S — Responsabilidad única: gestión de solicitudes de adopción.
 *   D — Inversión de dependencias: PDO inyectado por constructor.
 *
 * @arquitectura  Capa de Modelos / Dominio
 * @seguridad     Transacciones SQL, prepared statements, validaciones
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Perro.php';

/**
 * Clase Solicitud — Repositorio de solicitudes de adopción.
 */
class Solicitud
{
    // ─── Estados válidos de una solicitud ────────────────────────────────────
    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_APROBADA   = 'aprobada';
    public const ESTADO_RECHAZADA  = 'rechazada';

    /** @var PDO Conexión a base de datos */
    private PDO $db;

    /**
     * @param PDO $db Conexión activa
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Verifica si ya existe una solicitud ACTIVA (pendiente o aprobada)
     * para un usuario y perro específicos.
     *
     * Regla de negocio: no se permite duplicar solicitudes activas.
     * Una solicitud "rechazada" sí permite volver a postularse.
     *
     * @param int $usuarioId ID del usuario
     * @param int $perroId   ID del perro
     * @return bool True si ya existe una solicitud activa
     * @throws RuntimeException si falla la consulta
     */
    public function existeSolicitudActiva(int $usuarioId, int $perroId): bool
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) AS total
                 FROM solicitudes
                 WHERE usuario_id = :usuario_id
                   AND perro_id   = :perro_id
                   AND estado IN (:pendiente, :aprobada)
                 LIMIT 1'
            );

            $stmt->bindValue(':usuario_id', $usuarioId,             PDO::PARAM_INT);
            $stmt->bindValue(':perro_id',   $perroId,               PDO::PARAM_INT);
            $stmt->bindValue(':pendiente',  self::ESTADO_PENDIENTE, PDO::PARAM_STR);
            $stmt->bindValue(':aprobada',   self::ESTADO_APROBADA,  PDO::PARAM_STR);
            $stmt->execute();

            $fila = $stmt->fetch();
            return (int)($fila['total'] ?? 0) > 0;
        } catch (PDOException $e) {
            error_log('[SOLICITUD_ERROR] existeSolicitudActiva: ' . $e->getMessage());
            throw new RuntimeException('Error al verificar solicitudes existentes.');
        }
    }

    /**
     * Registra una solicitud y cambia el estado del perro de forma atómica.
     *
     * TRANSACCIÓN SQL: ambas operaciones (INSERT solicitud + UPDATE perro)
     * se ejecutan como una unidad indivisible. Si cualquiera falla,
     * se hace ROLLBACK completo — nunca quedan datos inconsistentes.
     *
     * @param int    $usuarioId ID del usuario solicitante
     * @param int    $perroId   ID del perro solicitado
     * @param string $mensaje   Mensaje opcional del usuario
     * @return array{ok: bool, solicitudId: int} Resultado de la operación
     * @throws RuntimeException si la transacción falla
     */
    public function registrarConTransaccion(
        int    $usuarioId,
        int    $perroId,
        string $mensaje = ''
    ): array {
        // Sanitizar mensaje antes de almacenar
        $mensajeLimpio = trim(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'));

        try {
            // ── INICIO DE TRANSACCIÓN ────────────────────────────────────────
            $this->db->beginTransaction();

            // ── Paso 1: Insertar la solicitud ────────────────────────────────
            $stmtSolicitud = $this->db->prepare(
                'INSERT INTO solicitudes (usuario_id, perro_id, estado, mensaje)
                 VALUES (:usuario_id, :perro_id, :estado, :mensaje)'
            );

            $stmtSolicitud->bindValue(':usuario_id', $usuarioId,             PDO::PARAM_INT);
            $stmtSolicitud->bindValue(':perro_id',   $perroId,               PDO::PARAM_INT);
            $stmtSolicitud->bindValue(':estado',     self::ESTADO_PENDIENTE, PDO::PARAM_STR);
            $stmtSolicitud->bindValue(':mensaje',    $mensajeLimpio,         PDO::PARAM_STR);
            $stmtSolicitud->execute();

            $solicitudId = (int)$this->db->lastInsertId();

            // ── Paso 2: Cambiar estado del perro a "en_proceso" ──────────────
            $stmtPerro = $this->db->prepare(
                'UPDATE perros
                 SET estado = :estado
                 WHERE id   = :id
                   AND estado = :disponible
                 LIMIT 1'
            );

            $stmtPerro->bindValue(':estado',     Perro::ESTADO_EN_PROCESO,  PDO::PARAM_STR);
            $stmtPerro->bindValue(':id',         $perroId,                  PDO::PARAM_INT);
            $stmtPerro->bindValue(':disponible', Perro::ESTADO_DISPONIBLE,  PDO::PARAM_STR);
            $stmtPerro->execute();

            // Verificar que el perro fue actualizado (evita race conditions)
            if ($stmtPerro->rowCount() === 0) {
                // El perro ya no estaba disponible — alguien más llegó primero
                $this->db->rollBack();
                throw new RuntimeException(
                    'Este perro ya no está disponible. Alguien más acaba de solicitarlo.'
                );
            }

            // ── COMMIT: confirmar ambas operaciones ──────────────────────────
            $this->db->commit();

            error_log(sprintf(
                '[SOLICITUD_OK] usuario=%d, perro=%d, solicitud=%d — %s',
                $usuarioId,
                $perroId,
                $solicitudId,
                date('Y-m-d H:i:s')
            ));

            return [
                'ok'          => true,
                'solicitudId' => $solicitudId,
            ];

        } catch (PDOException $e) {
            // ── ROLLBACK: revertir todo si algo falla ────────────────────────
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('[SOLICITUD_ERROR] registrarConTransaccion: ' . $e->getMessage());
            throw new RuntimeException('Error al procesar la solicitud de adopción.');
        }
    }

    /**
     * Obtiene las solicitudes de un usuario (historial personal).
     *
     * @param int $usuarioId ID del usuario
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     */
    public function obtenerPorUsuario(int $usuarioId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT
                    s.id,
                    s.estado,
                    s.creado_en,
                    p.nombre  AS perro_nombre,
                    p.raza    AS perro_raza,
                    p.imagen_url
                 FROM solicitudes s
                 INNER JOIN perros p ON p.id = s.perro_id
                 WHERE s.usuario_id = :usuario_id
                 ORDER BY s.creado_en DESC'
            );

            $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('[SOLICITUD_ERROR] obtenerPorUsuario: ' . $e->getMessage());
            throw new RuntimeException('Error al obtener el historial de solicitudes.');
        }
    }
}
