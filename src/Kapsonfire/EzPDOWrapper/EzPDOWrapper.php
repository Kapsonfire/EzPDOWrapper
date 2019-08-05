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
    public function getPDO() : \PDO {
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

            $host       = $connectionInfo['host'] ?? 'localhost';
            $port       = $connectionInfo['port'] ?? 3306;
            $unix       = $connectionInfo['socket'] ?? null;
            $user       = $connectionInfo['user'] ?? 'root';
            $pass       = $connectionInfo['pass'] ?? '';
            $charset    = $connectionInfo['charset'] ?? 'utf8';


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
}