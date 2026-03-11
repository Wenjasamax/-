<?php

declare(strict_types=1);

namespace Tests;

use App\RequestService;
use PDO;
use PHPUnit\Framework\TestCase;

final class RequestServiceTest extends TestCase
{
    private PDO $pdo;
    private RequestService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, role TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_name TEXT NOT NULL,
            phone TEXT NOT NULL,
            address TEXT NOT NULL,
            problem_text TEXT NOT NULL,
            status TEXT NOT NULL,
            assigned_to INTEGER NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');
        $this->pdo->exec('CREATE TABLE request_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            request_id INTEGER NOT NULL,
            actor_id INTEGER NULL,
            action TEXT NOT NULL,
            note TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $this->pdo->exec("INSERT INTO users (id, name, role) VALUES (1, 'Dispatcher', 'dispatcher'), (2, 'Master', 'master')");
        $this->service = new RequestService($this->pdo);
    }

    public function testTakeInWorkIsAtomicByStatusGuard(): void
    {
        $this->pdo->exec("INSERT INTO requests (id, client_name, phone, address, problem_text, status, assigned_to) VALUES (1, 'Client', '1', 'Addr', 'Text', 'assigned', 2)");

        $first = $this->service->takeInWork(1, 2);
        $second = $this->service->takeInWork(1, 2);

        $this->assertTrue($first);
        $this->assertFalse($second);
    }

    public function testCompleteWorksOnlyFromInProgress(): void
    {
        $this->pdo->exec("INSERT INTO requests (id, client_name, phone, address, problem_text, status, assigned_to) VALUES (2, 'Client', '1', 'Addr', 'Text', 'assigned', 2)");

        $directComplete = $this->service->complete(2, 2);
        $this->service->takeInWork(2, 2);
        $fromInProgress = $this->service->complete(2, 2);

        $this->assertFalse($directComplete);
        $this->assertTrue($fromInProgress);
    }
}
