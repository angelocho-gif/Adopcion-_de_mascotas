<?php

/**
 * Perro.php — Modelo de Dominio + Repositorio
 *
 * Encapsula la lógica de negocio y acceso a datos relacionada
 * con la entidad "Perro". Sigue el patrón Repository para
 * desacoplar la lógica de negocio del acceso a datos.
 *
 * Principios SOLID aplicados:
 *   S — Una sola responsabilidad: gestión de datos de perros.
 *   O — Abierto/Cerrado: extendible sin modificar la clase base.
 *   D — Inversión de dependencias: recibe PDO por constructor.
 *
 * @arquitectura  Capa de Modelos / Dominio
 * @seguridad     Todas las consultas usan prepared statements (PDO)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Clase Perro — Repositorio de entidad Perro.
 */
class Perro
{
    // ─── Estados válidos del ciclo de vida de un perro ────────────────────────
    public const ESTADO_DISPONIBLE = 'disponible';
    public const ESTADO_EN_PROCESO = 'en_proceso';
    public const ESTADO_ADOPTADO   = 'adoptado';

    /** @var PDO Conexión a base de datos inyectada */
    private PDO $db;

    /**
     * Constructor — Inyección de dependencia de PDO.
     *
     * @param PDO $db Conexión activa a la base de datos
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Obtiene todos los perros con estado "disponible".
     *
     * Solo expone perros que el público puede adoptar.
     * Perros en_proceso o adoptados se excluyen automáticamente.
     *
     * @return array<int, array<string, mixed>> Lista de perros disponibles
     * @throws RuntimeException si falla la consulta
     */
    public function obtenerDisponibles(): array
    {
        try {
            // Prepared statement — nunca interpolación directa
            $stmt = $this->db->prepare(
                'SELECT
                    id,
                    nombre,
                    raza,
                    edad_meses,
                    descripcion,
                    imagen_url,
                    estado,
                    creado_en
                FROM perros
                WHERE estado = :estado
                ORDER BY creado_en DESC'
            );

            $stmt->bindValue(':estado', self::ESTADO_DISPONIBLE, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('[PERRO_ERROR] obtenerDisponibles: ' . $e->getMessage());
            throw new RuntimeException('Error al obtener el catálogo de perros.');
        }
    }

    /**
     * Busca un perro por su ID.
     *
     * @param int $id Identificador del perro
     * @return array<string, mixed>|null Datos del perro o null si no existe
     * @throws RuntimeException si falla la consulta
     */
    public function buscarPorId(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT id, nombre, raza, edad_meses, descripcion, imagen_url, estado
                 FROM perros
                 WHERE id = :id
                 LIMIT 1'
            );

            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch();
            return $resultado !== false ? $resultado : null;
        } catch (PDOException $e) {
            error_log('[PERRO_ERROR] buscarPorId(' . $id . '): ' . $e->getMessage());
            throw new RuntimeException('Error al buscar el perro solicitado.');
        }
    }

    /**
     * Actualiza el estado de un perro.
     *
     * Debe ejecutarse dentro de una transacción SQL cuando
     * se combina con otras operaciones (ej. crear solicitud).
     *
     * @param int    $id     ID del perro
     * @param string $estado Nuevo estado (usar constantes de clase)
     * @return bool True si se actualizó correctamente
     * @throws InvalidArgumentException si el estado no es válido
     * @throws RuntimeException si falla la consulta
     */
    public function actualizarEstado(int $id, string $estado): bool
    {
        // Validar que el estado sea uno de los permitidos
        $estadosValidos = [
            self::ESTADO_DISPONIBLE,
            self::ESTADO_EN_PROCESO,
            self::ESTADO_ADOPTADO,
        ];

        if (!in_array($estado, $estadosValidos, true)) {
            throw new InvalidArgumentException(
                "Estado inválido: '{$estado}'. Estados permitidos: " .
                implode(', ', $estadosValidos)
            );
        }

        try {
            $stmt = $this->db->prepare(
                'UPDATE perros
                 SET estado = :estado
                 WHERE id   = :id
                 LIMIT 1'
            );

            $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindValue(':id',     $id,     PDO::PARAM_INT);
            $stmt->execute();

            // Verificar que realmente se modificó una fila
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('[PERRO_ERROR] actualizarEstado(' . $id . ', ' . $estado . '): ' . $e->getMessage());
            throw new RuntimeException('Error al actualizar el estado del perro.');
        }
    }

    /**
     * Formatea la edad en meses a texto legible.
     *
     * Método utilitario de presentación.
     *
     * @param int $meses Edad en meses
     * @return string Texto formateado (ej. "1 año 3 meses")
     */
    public static function formatearEdad(int $meses): string
    {
        if ($meses < 1)  return 'Menos de 1 mes';
        if ($meses < 12) return $meses . ' ' . ($meses === 1 ? 'mes' : 'meses');

        $años   = intdiv($meses, 12);
        $resto  = $meses % 12;
        $texto  = $años . ' ' . ($años === 1 ? 'año' : 'años');

        if ($resto > 0) {
            $texto .= ' ' . $resto . ' ' . ($resto === 1 ? 'mes' : 'meses');
        }

        return $texto;
    }
}
