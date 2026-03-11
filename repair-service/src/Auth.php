<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listUsers(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, role FROM users ORDER BY FIELD(role, "dispatcher", "master"), id');
        return $stmt->fetchAll();
    }

    public function userById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, role FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function masters(): array
    {
        $stmt = $this->pdo->query('SELECT id, name FROM users WHERE role = "master" ORDER BY id');
        return $stmt->fetchAll();
    }
}
