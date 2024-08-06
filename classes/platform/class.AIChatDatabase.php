<?php
declare(strict_types=1);
/**
 *  This file is part of the AI Chat Repository Object plugin for ILIAS, which allows your platform's users
 *  To connect with an external LLM service
 *  This plugin is created and maintained by SURLABS.
 *
 *  The AI Chat Repository Object plugin for ILIAS is open-source and licensed under GPL-3.0.
 *  For license details, visit https://www.gnu.org/licenses/gpl-3.0.en.html.
 *
 *  To report bugs or participate in discussions, visit the Mantis system and filter by
 *  the category "AI Chat" at https://mantis.ilias.de.
 *
 *  More information and source code are available at:
 *  https://github.com/surlabs/AIChat
 *
 *  If you need support, please contact the maintainer of this software at:
 *  info@surlabs.es
 *
 */

namespace platform;

use Exception;
use ilDBInterface;

/**
 * Class AIChatDatabase
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class AIChatDatabase
{
    const ALLOWED_TABLES = [
        'xaic_config',
        'xaic_objects',
        'xaic_chats',
        'xaic_messages'
    ];

    private ilDBInterface $db;

    public function __construct()
    {
        global $DIC;

        $this->db = $DIC->database();
    }

    /**
     * Inserts a new row in the database
     *
     * Usage: AIChatDatabase::insert('table_name', ['column1' => 'value1', 'column2' => 'value2']);
     *
     * @param string $table
     * @param array $data
     * @return void
     * @throws AIChatException
     */
    public function insert(string $table, array $data): void
    {
        $data = $this->sanitizeData($data);
        if (!$this->validateTableName($table)) {
            throw new AIChatException("Invalid table name: " . $table);
        }

        try {
            $this->db->query("INSERT INTO " . $table . " (" . implode(", ", array_keys($data)) . ") VALUES (" . implode(", ", array_map(function ($value) {
                    return $this->db->quote($value);
                }, array_values($data))) . ")");
        } catch (Exception $e) {
            throw new AIChatException($e->getMessage());
        }
    }

    /**
     * Inserts a new row in the database, if the row already exists, updates it
     *
     * Usage: AIChatDatabase::insertOnDuplicatedKey('table_name', ['column1' => 'value1', 'column2' => 'value2']);
     *
     * @param string $table
     * @param array $data
     * @return void
     * @throws AIChatException
     */
    public function insertOnDuplicatedKey(string $table, array $data): void
    {
        $data = $this->sanitizeData($data);
        if (!$this->validateTableName($table)) {
            throw new AIChatException("Invalid table name: " . $table);
        }

        try {
            $this->db->query("INSERT INTO " . $table . " (" . implode(", ", array_keys($data)) . ") VALUES (" . implode(", ", array_map(function ($value) {
                    return $this->db->quote($value);
                }, array_values($data))) . ") ON DUPLICATE KEY UPDATE " . implode(", ", array_map(function ($key, $value) {
                    return $key . " = " . $value;
                }, array_keys($data), array_map(function ($value) {
                    return $this->db->quote($value);
                }, array_values($data)))));
        } catch (Exception $e) {
            throw new AIChatException($e->getMessage());
        }
    }

    /**
     * Updates a row/s in the database
     *
     * Usage: AIChatDatabase::update('table_name', ['column1' => 'value1', 'column2' => 'value2'], ['id' => 1]);
     *
     * @param string $table
     * @param array $data
     * @param array $where
     * @return void
     * @throws AIChatException
     */
    public function update(string $table, array $data, array $where): void
    {
        $data = $this->sanitizeData($data);
        $where = $this->sanitizeData($where);

        if (!$this->validateTableName($table)) {
            throw new AIChatException("Invalid table name: " . $table);
        }

        try {
            $this->db->query("UPDATE " . $table . " SET " . implode(", ", array_map(function ($key, $value) {
                    return $key . " = " . $value;
                }, array_keys($data), array_map(function ($value) {
                    return $this->db->quote($value);
                }, array_values($data)))) . " WHERE " . implode(" AND ", array_map(function ($key, $value) {
                    return $key . " = " . $value;
                }, array_keys($where), array_map(function ($value) {
                    return $this->db->quote($value);
                }, array_values($where)))));
        } catch (Exception $e) {
            throw new AIChatException($e->getMessage());
        }
    }

    /**
     * Deletes a row/s in the database
     *
     * Usage: AIChatDatabase::delete('table_name', ['id' => 1]);
     *
     * @param string $table
     * @param array $where
     * @return void
     * @throws AIChatException
     */
    public function delete(string $table, array $where): void
    {
        $where = $this->sanitizeData($where);

        if (!$this->validateTableName($table)) {
            throw new AIChatException("Invalid table name: " . $table);
        }

        try {
            $this->db->query("DELETE FROM " . $table . " WHERE " . implode(" AND ", array_map(function ($key, $value) {
                    return $key . " = " . $value;
                }, array_keys($where), array_map(function ($value) {
                    return $this->db->quote($value);
                }, array_values($where)))));
        } catch (Exception $e) {
            throw new AIChatException($e->getMessage());
        }
    }

    /**
     * Selects a row/s in the database
     *
     * Usage: AIChatDatabase::select('table_name', ['id' => 1]);
     *
     * @param string $table
     * @param array|null $where
     * @param array|null $columns
     * @param string|null $extra
     * @return array
     * @throws AIChatException
     */
    public function select(string $table, ?array $where = null, ?array $columns = null, ?string $extra = ""): array
    {
        if (is_array($where)) {
            $where = $this->sanitizeData($where);
        }
        if (is_array($columns)) {
            $columns = $this->sanitizeData($columns);
        }

        if (!$this->validateTableName($table)) {
            throw new AIChatException("Invalid table name: " . $table);
        }

        try {
            $query = "SELECT " . (isset($columns) ? implode(", ", $columns) : "*") . " FROM " . $table;

            if (isset($where)) {
                $query .= " WHERE " . implode(" AND ", array_map(function ($key, $value) {
                        return $key . " = " . $value;
                    }, array_keys($where), array_map(function ($value) {
                        return $this->db->quote($value);
                    }, array_values($where))));
            }

            if (is_string($extra)) {
                $extra = strip_tags($extra);
                $query .= " " . $extra;
            }

            $result = $this->db->query($query);

            $rows = [];

            while ($row = $this->db->fetchAssoc($result)) {
                $rows[] = $row;
            }

            return $rows;
        } catch (Exception $e) {
            throw new AIChatException($e->getMessage());
        }
    }

    /**
     * Returns the next id for a table
     *
     * Usage: AIChatDatabase::nextId('table_name');
     *
     * @param string $table
     * @return int
     * @throws AIChatException
     */
    public function nextId(string $table): int
    {
        try {
            return $this->db->nextId($table);
        } catch (Exception $e) {
            throw new AIChatException($e->getMessage());
        }
    }

    /**
     * Utility function to validate table names and column names against a list of allowed names.
     * This helps prevent SQL injection through malicious SQL segments being passed as table or column names.
     */
    private function validateTableName(string $identifier): bool
    {
        return in_array($identifier, self::ALLOWED_TABLES, true);
    }

    /**
     * Utility function to sanitize and validate data before it's processed in SQL queries.
     * @throws AIChatException
     */
    private function sanitizeData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {

            // Validate the key to prevent SQL injection via column names
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                throw new AIChatException("Invalid key '$key' in data array.");
            }

            // Depending on the type of the value, different sanitization might be needed
            if (is_string($value)) {
                // Strip tags and escape other potential HTML or SQL injection vectors
                $sanitizedValue = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
                // Optionally, trim the value to remove unwanted white spaces
                $sanitized[$key] = $sanitizedValue;
            } elseif (is_int($value) || is_float($value)) {
                // For numeric values, ensure they are indeed numeric
                if (!is_numeric($value)) {
                    throw new AIChatException("Non-numeric value provided for a numeric field '$key'.");
                }
                $sanitized[$key] = $value;
            } elseif (is_bool($value)) {
                // For booleans, explicitly cast to ensure boolean type integrity
                $sanitized[$key] = (bool) $value;
            } elseif (is_null($value)) {
                // Null values are accepted as is
                $sanitized[$key] = $value;
            } else {
                // For other types or complex types like arrays or objects, consider your specific needs
                // Here, we might decide to throw an error or handle them specifically
                throw new AIChatException("Unsupported data type for key '$key'.");
            }
        }
        return $sanitized;
    }

}