<?php

class Db
{
    private static ?PDO $connection = null;

    /**
     * Obtiene o crea la conexión única a SQLite (Singleton en memoria)
     */
    public static function connect(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        try {
            self::$connection = new PDO(
                DB_HOST,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            // Optimizaciones agresivas de rendimiento para SQLite3
            self::$connection->exec('PRAGMA journal_mode = WAL');     // Write-Ahead Logging
            self::$connection->exec('PRAGMA synchronous = NORMAL');   // Balance entre velocidad y seguridad
            self::$connection->exec('PRAGMA cache_size = -64000');   // 64MB de caché en RAM
            self::$connection->exec('PRAGMA temp_store = MEMORY');    // Tablas temporales en RAM
            self::$connection->exec('PRAGMA mmap_size = 30000000');   // Memory-mapped I/O
            self::$connection->exec('PRAGMA page_size = 4096');       // Tamaño de página óptimo
            self::$connection->exec('PRAGMA busy_timeout = 5000');     // Timeout de 5s para escrituras concurrentes

            return self::$connection;
        } catch (PDOException $e) {
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }

    /**
     * Prepara y ejecuta una sentencia SQL
     */
    public static function exec(string $sql, ?array $params = null)
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params ?? []);
        return $stmt;
    }

    /**
     * Retorna todos los registros que coincidan con la consulta
     */
    public static function findAll(string $sql, ?array $params = null): array
    {
        return self::exec($sql, $params)->fetchAll();
    }

    /**
     * Retorna únicamente el primer registro o null
     */
    public static function findFirst(string $sql, ?array $params = null): ?array
    {
        $stmt = self::exec($sql, $params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Obtiene un único valor escalar (ej: un COUNT(*))
     */
    public static function getScalar(string $sql, ?array $params = null): mixed
    {
        $stmt = self::exec($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_NUM);
        return $result ? $result[0] : null;
    }

    /**
     * Inserta un registro a partir de un array asociativo y retorna el ID insertado
     */
    public static function insert(string $table, array $data): string
    {
        $keys = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));
        $sql = "INSERT INTO {$table} ({$keys}) VALUES ({$placeholders})";

        self::exec($sql, $data);
        return self::connect()->lastInsertId();
    }

    /**
     * Actualiza registros en la tabla
     */
    public static function update(string $table, array $data, string $condition, ?array $params = null): int
    {
        if (empty(trim($condition))) {
            throw new Exception("Se requiere una condición WHERE para evitar actualizaciones accidentales masivas.");
        }

        $sets = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $sql = "UPDATE {$table} SET {$sets} {$condition}";

        // Combinar datos a actualizar con los parámetros del WHERE si los hay
        $mergedParams = array_merge($data, $params ?? []);

        $stmt = self::exec($sql, $mergedParams);
        return $stmt->rowCount();
    }

    /**
     * Elimina registros de la tabla
     */
    public static function delete(string $table, string $condition, ?array $params = null): int
    {
        if (empty(trim($condition))) {
            throw new Exception("Se requiere una condición WHERE para evitar eliminaciones accidentales masivas.");
        }

        $sql = "DELETE FROM {$table} {$condition}";

        $stmt = self::exec($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Inicia una transacción
     */
    public static function beginTransaction(): void
    {
        self::connect()->beginTransaction();
    }

    /**
     * Confirma la transacción
     */
    public static function commit(): void
    {
        self::connect()->commit();
    }

    /**
     * Revierte la transacción
     */
    public static function rollback(): void
    {
        self::connect()->rollback();
    }
}