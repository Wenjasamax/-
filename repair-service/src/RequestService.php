<?php

declare(strict_types=1);

namespace App;

use PDO;

final class RequestService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO requests (client_name, phone, address, problem_text, status, assigned_to) VALUES (:client_name, :phone, :address, :problem_text, "new", NULL)'
        );
        $stmt->execute([
            'client_name' => $payload['clientName'],
            'phone' => $payload['phone'],
            'address' => $payload['address'],
            'problem_text' => $payload['problemText'],
        ]);

        $requestId = (int) $this->pdo->lastInsertId();
        $this->logEvent($requestId, null, 'created', 'Заявка создана');
        return $requestId;
    }

    public function listForDispatcher(?string $status = null): array
    {
        $sql = 'SELECT r.id, r.client_name, r.phone, r.address, r.problem_text, r.status, r.assigned_to, r.created_at, r.updated_at, u.name AS assigned_master
                FROM requests r
                LEFT JOIN users u ON u.id = r.assigned_to
                WHERE (:status IS NULL OR r.status = :status)
                ORDER BY r.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['status' => $status]);

        return $stmt->fetchAll();
    }

    public function listForMaster(int $masterId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, client_name, phone, address, problem_text, status, created_at, updated_at
             FROM requests
             WHERE assigned_to = :master_id
             ORDER BY updated_at DESC'
        );
        $stmt->execute(['master_id' => $masterId]);

        return $stmt->fetchAll();
    }

    public function assign(int $requestId, int $masterId, int $dispatcherId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE requests SET assigned_to = :master_id, status = "assigned", updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = "new"'
        );
        $stmt->execute([
            'master_id' => $masterId,
            'id' => $requestId,
        ]);

        $updated = $stmt->rowCount() === 1;
        if ($updated) {
            $this->logEvent($requestId, $dispatcherId, 'assigned', sprintf('Назначен мастер ID=%d', $masterId));
        }

        return $updated;
    }

    public function cancel(int $requestId, int $dispatcherId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE requests SET status = "canceled", updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status IN ("new", "assigned")'
        );
        $stmt->execute(['id' => $requestId]);

        $updated = $stmt->rowCount() === 1;
        if ($updated) {
            $this->logEvent($requestId, $dispatcherId, 'canceled', 'Заявка отменена диспетчером');
        }

        return $updated;
    }

    public function takeInWork(int $requestId, int $masterId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE requests SET status = "in_progress", updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND assigned_to = :master_id AND status = "assigned"'
        );
        $stmt->execute([
            'id' => $requestId,
            'master_id' => $masterId,
        ]);

        $updated = $stmt->rowCount() === 1;
        if ($updated) {
            $this->logEvent($requestId, $masterId, 'in_progress', 'Мастер взял заявку в работу');
        }

        return $updated;
    }

    public function complete(int $requestId, int $masterId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE requests SET status = "done", updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND assigned_to = :master_id AND status = "in_progress"'
        );
        $stmt->execute([
            'id' => $requestId,
            'master_id' => $masterId,
        ]);

        $updated = $stmt->rowCount() === 1;
        if ($updated) {
            $this->logEvent($requestId, $masterId, 'done', 'Заявка завершена мастером');
        }

        return $updated;
    }

    public function events(int $requestId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.action, e.note, e.created_at, u.name AS actor_name
             FROM request_events e
             LEFT JOIN users u ON u.id = e.actor_id
             WHERE e.request_id = :request_id
             ORDER BY e.id DESC'
        );
        $stmt->execute(['request_id' => $requestId]);

        return $stmt->fetchAll();
    }

    private function logEvent(int $requestId, ?int $actorId, string $action, string $note): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO request_events (request_id, actor_id, action, note) VALUES (:request_id, :actor_id, :action, :note)'
        );
        $stmt->execute([
            'request_id' => $requestId,
            'actor_id' => $actorId,
            'action' => $action,
            'note' => $note,
        ]);
    }
}
