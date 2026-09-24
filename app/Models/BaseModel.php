<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

abstract class BaseModel
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    protected function fetchAll(string $sql, array $parameters = []): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    protected function fetchOne(string $sql, array $parameters = []): ?array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        $result = $statement->fetch();
        return $result ?: null;
    }
}
