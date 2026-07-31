<?php
// core/Db.php

class Db
{
    private ?PDO $connection = null;

    /**
     * Obtiene o crea la conexión única a SQLite (Singleton de instancia)
     */
    public function connect(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        try {
            $this->connection = new PDO(
                DB_HOST,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            // Optimizaciones agresivas de rendimiento para SQLite3
            $this->connection->exec('PRAGMA journal_mode = WAL');     // Write-Ahead Logging
            $this->connection->exec('PRAGMA synchronous = NORMAL');   // Balance entre velocidad y seguridad
            $this->connection->exec('PRAGMA cache_size = -64000');   // 64MB de caché en RAM
            $this->connection->exec('PRAGMA temp_store = MEMORY');    // Tablas temporales en RAM
            $this->connection->exec('PRAGMA mmap_size = 30000000');   // Memory-mapped I/O
            $this->connection->exec('PRAGMA page_size = 4096');       // Tamaño de página óptimo
            $this->connection->exec('PRAGMA busy_timeout = 5000');     // Timeout de 5s para escrituras concurrentes

            return $this->connection;
        } catch (PDOException $e) {
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }

    /**
     * Prepara y ejecuta una sentencia SQL
     */
    public function exec(string $sql, ?array $params = null)
    {
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params ?? []);
        return $stmt;
    }

    /**
     * Retorna todos los registros que coincidan con la consulta
     */
    public function findAll(string $sql, ?array $params = null): array
    {
        return $this->exec($sql, $params)->fetchAll();
    }

    /**
     * Retorna únicamente el primer registro o null
     */
    public function findFirst(string $sql, ?array $params = null): ?array
    {
        $stmt = $this->exec($sql, $params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Obtiene un único valor escalar (ej: un COUNT(*))
     */
    public function getScalar(string $sql, ?array $params = null): mixed
    {
        $stmt = $this->exec($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_NUM);
        return $result ? $result[0] : null;
    }

    /**
     * Inserta un registro a partir de un array asociativo y retorna el ID insertado
     */
    public function insert(string $table, array $data): string
    {
        $keys = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));
        $sql = "INSERT INTO {$table} ({$keys}) VALUES ({$placeholders})";

        $this->exec($sql, $data);
        return $this->connect()->lastInsertId();
    }

    /**
     * Actualiza registros en la tabla
     */
    public function update(string $table, array $data, string $condition, ?array $params = null): int
    {
        if (empty(trim($condition))) {
            throw new Exception("Se requiere una condición WHERE para evitar actualizaciones accidentales masivas.");
        }

        $sets = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $sql = "UPDATE {$table} SET {$sets} {$condition}";

        // Combinar datos a actualizar con los parámetros del WHERE si los hay
        $mergedParams = array_merge($data, $params ?? []);

        $stmt = $this->exec($sql, $mergedParams);
        return $stmt->rowCount();
    }

    /**
     * Elimina registros de la tabla
     */
    public function delete(string $table, string $condition, ?array $params = null): int
    {
        if (empty(trim($condition))) {
            throw new Exception("Se requiere una condición WHERE para evitar eliminaciones accidentales masivas.");
        }

        $sql = "DELETE FROM {$table} {$condition}";

        $stmt = $this->exec($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Inicia una transacción
     */
    public function beginTransaction(): void
    {
        $this->connect()->beginTransaction();
    }

    /**
     * Confirma la transacción
     */
    public function commit(): void
    {
        $this->connect()->commit();
    }

    /**
     * Revierte la transacción
     */
    public function rollback(): void
    {
        $this->connect()->rollback();
    }
}