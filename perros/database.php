<?php

/**
 * database.php — Configuración de Conexión a Base de Datos
 *
 * Módulo de configuración que establece la conexión PDO de forma segura.
 * Centraliza los parámetros de conexión para facilitar el mantenimiento.
 *
 * Principio SOLID aplicado: Single Responsibility (S)
 * Este archivo tiene una única responsabilidad: gestionar la conexión DB.
 *
 * @arquitectura  Capa de Configuración / Infraestructura
 * @seguridad     PDO con excepciones activadas, charset UTF-8 forzado
 */

declare(strict_types=1);

// ─── Parámetros de conexión ───────────────────────────────────────────────────
// En producción, estos valores deben venir de variables de entorno (.env)
// y NUNCA estar hardcodeados en el repositorio.
define('DB_HOST',    'localhost');
define('DB_NAME',    'adopcion_perros');
define('DB_USER',    'root');
define('DB_PASS',    '');          // Cambiar en producción
define('DB_CHARSET', 'utf8mb4');

/**
 * Clase Database — Patrón Singleton para gestión de conexión PDO.
 *
 * Garantiza que exista una única instancia de conexión durante
 * el ciclo de vida de cada request (eficiencia de recursos).
 */
class Database
{
    /** @var Database|null Instancia única (Singleton) */
    private static ?Database $instance = null;

    /** @var PDO Objeto de conexión PDO */
    private PDO $connection;

    /**
     * Constructor privado — impide instanciación directa.
     * Establece la conexión PDO con opciones de seguridad.
     *
     * @throws RuntimeException si la conexión falla
     */
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $opciones = [
            // Lanzar excepciones en errores (nunca silenciarlos)
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Retornar filas como arrays asociativos por defecto
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Desactivar emulación de prepared statements (seguridad)
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Persistencia desactivada (evita problemas de estado)
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        } catch (PDOException $e) {
            // Loguear el error real pero NO exponerlo al cliente
            error_log('[DB_ERROR] ' . date('Y-m-d H:i:s') . ' — ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar a la base de datos.');
        }
    }

    /**
     * Obtiene la instancia única de Database (Singleton).
     *
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Expone el objeto PDO para uso en modelos y repositorios.
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    // Prevenir clonación y deserialización del Singleton
    private function __clone() {}
    public function __wakeup(): void
    {
        throw new RuntimeException('No se puede deserializar un Singleton.');
    }
}
