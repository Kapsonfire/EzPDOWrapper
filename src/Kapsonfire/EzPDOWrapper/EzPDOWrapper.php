<?php

namespace Kapsonfire\EzPDOWrapper;

class EzPDOWrapper
{
    /**
     * @var \PDO $db
     */
    private $db;

    private function __construct(\PDO $pdoInstance)
    {
        $this->db = $pdoInstance;
    }


    /**
     * Get the native PDO Instance
     * @return \PDO
     */
    public function getPDO(): \PDO
    {
        return $this->db;
    }


    public function __destruct()
    {
        $this->db = null;
    }


    /**
     * @param string $database
     * @param array $connectionInfo
     * @param array $options
     * @return EzPDOWrapper
     */
    public static function create(string $database, array $connectionInfo = [], array $options = []): EzPDOWrapper
    {
        $dsn = '';
        try {
            $instance = null;

            $host = $connectionInfo['host'] ?? 'localhost';
            $port = $connectionInfo['port'] ?? 3306;
            $unix = $connectionInfo['socket'] ?? null;
            $user = $connectionInfo['user'] ?? 'root';
            $pass = $connectionInfo['pass'] ?? '';
            $charset = $connectionInfo['charset'] ?? 'utf8';


            $dsn = 'mysql:';
            if ($unix === null) {
                $dsn .= 'host = ' . $host . ';port = ' . $port . ';';
            } else {
                $dsn .= 'unix_socket=' . $unix . ';';
            }
            $dsn .= 'dbname=' . $database . ';charset=' . $charset;
            $pdo = new \PDO($dsn, $user, $pass, $options);

            $instance = new EzPDOWrapper($pdo);

            return $instance;
        } catch (\Exception $ex) {
            throw new \PDOException('Can\'t connect to mysql database.');
        }
    }


    public function escapeIdentifier(string $identifier, bool $outerTicks = true): string
    {
        $identifier = str_replace(
            ['\\\\', '`', '\'', '"'],
            ['\\', '``', '\\\'', '\\"'],
            $identifier);
        if (!$outerTicks) return $identifier;
        return '`' . $identifier . '`';
    }


    protected function createWhere(array $conditions, $connector = 'OR'): ?array
    {
        if (empty($conditions)) return null;

        foreach ($conditions as $key => $condition) {
            if (is_array($condition)) {
                $conditions[$key] = $this->createWhere($condition, $connector === 'OR' ? 'AND' : 'OR');
            }
        }

        $clause = '';
        $values = [];
        foreach ($conditions as $identifier => $value) {
            if (strlen($clause) > 0) {
                $clause .= ' ' . $connector . ' ';
            }
            if (!is_array($value)) {
                $clause .= $this->escapeIdentifier($identifier) . ' = ?';
                $values[] = $value;
            } else {
                $clause .= '(' . $value['sql'] . ')';
                $values = array_merge($values, $value['params']);
            }
        }


        return [
            'sql' => $clause,
            'params' => $values
        ];

    }


    protected function createSelectColumns($columns): string
    {
        return implode(', ', array_map(function ($column) {
            if ($column === '*') return $column;

            return $this->escapeIdentifier($column);
        }, $columns));
    }

    protected function createOrderBy($order) : string {
        if(is_string($order)) {
            return $this->escapeIdentifier($order);
        }
        return implode(', ', array_map(function ($orderData) {
            $column = $this->escapeIdentifier($orderData[0]);
            $direction = $orderData[1] ?? 'ASC'; if($direction !== 'DESC' && $direction !== 'ASC') {
                $direction = 'ASC';
            }

            return $column.' '.$direction;
        }, $order));
    }

    public function createSelectStatement(string $table, $columns = ['*'], $where = [], $options = [], &$params = []) {
        $sql = 'SELECT ';
        $sql .= $this->createSelectColumns($columns);
        $sql .= ' FROM ' . $this->escapeIdentifier($table);

        $where = $this->createWhere($where);
        if ($where !== null) {
            $sql .= ' WHERE ' . $where['sql'];
            $params = array_merge($params, $where['params']);
        }

        if(isset($options['order'])) {
            $sql .= ' ORDER BY '.$this->createOrderBy($options['order']);
        }

        $limit = '';
        if(isset($options['limit'])) {
            if(is_array($options['limit'])) {
                $limit = intval($options['limit'][0]).','.intval($options['limit'][1]);
            }
            $limit = intval($options['limit']);
        }
        if(strlen($limit) > 0) {
            $sql .= ' LIMIT '.$limit;
        }


        return $sql;
    }

    public function select(string $table, $columns = ['*'], $where = [], $options = [])
    {
        $fullBuffered = boolval($options['buffered'] ?? true);
        $fetchStyle = intval($options['fetchstyle'] ?? \PDO::FETCH_ASSOC);

        $params = [];


        $sql = $this->createSelectStatement($table, $columns, $where, $options, $params);


        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);


        return $fullBuffered ? $stmt->fetchAll($fetchStyle) : $stmt;
    }
}