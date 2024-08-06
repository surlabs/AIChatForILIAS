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
            return (int) $this->db->nextId($table);
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
}