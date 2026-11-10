<?php

declare(strict_types=1);

namespace OCA\SmartCook\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final class ImportRepository extends AbstractRepository {
    public function __construct(IDBConnection $db) {
        parent::__construct($db);
    }

    public function createJob(string $userId, string $kind, ?string $sourceRef, bool $useAi, ?string $provider, array $payload): array {
        $now = time();
        $id = $this->insert('smartcook_imports', [
            'user_id' => $userId,
            'kind' => $kind,
            'source_ref' => $sourceRef,
            'status' => 'queued',
            'progress' => 0,
            'stage' => 'Queued',
            'use_ai' => $useAi,
            'provider' => $provider,
            'payload' => $this->encode($payload),
            'result' => null,
            'error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->getJob($id, $userId) ?? throw new \RuntimeException('Import job could not be reloaded');
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(string $userId, int $limit = 50): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('smartcook_imports')
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('created_at', 'DESC')
            ->setMaxResults(max(1, min(200, $limit)));
        return array_map(fn (array $row): array => $this->map($row), $this->fetchAll($qb));
    }

    public function getJob(int $id, ?string $userId = null): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('smartcook_imports')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        if ($userId !== null) {
            $qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        }
        $qb->setMaxResults(1);
        $row = $this->fetchOne($qb);
        return $row === null ? null : $this->map($row);
    }

    public function markRunning(int $id, string $stage = 'Processing import', int $progress = 5): void {
        $this->update('smartcook_imports', $id, ['status' => 'running', 'stage' => $stage, 'progress' => max(0, min(99, $progress)), 'updated_at' => time(), 'error' => null]);
    }

    public function updateProgress(int $id, string $stage, int $progress): void {
        $this->update('smartcook_imports', $id, ['stage' => $stage, 'progress' => max(0, min(99, $progress)), 'updated_at' => time()]);
    }

    public function markComplete(int $id, array $result): void {
        $this->update('smartcook_imports', $id, [
            'status' => 'complete',
            'stage' => 'Complete',
            'progress' => 100,
            'result' => $this->encode($result),
            'error' => null,
            'updated_at' => time(),
        ]);
    }

    public function markFailed(int $id, string $error): void {
        $this->update('smartcook_imports', $id, [
            'status' => 'failed',
            'stage' => 'Failed',
            'error' => mb_substr($error, 0, 4000),
            'updated_at' => time(),
        ]);
    }

    public function deleteForUser(int $id, string $userId): bool {
        $qb = $this->db->getQueryBuilder();
        return $qb->delete('smartcook_imports')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->executeStatement() > 0;
    }

    public function deleteOlderThan(int $timestamp): int {
        $qb = $this->db->getQueryBuilder();
        return $qb->delete('smartcook_imports')
            ->where($qb->expr()->lt('updated_at', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    /** @return array<string, mixed> */
    private function map(array $row): array {
        return [
            'id' => (int)$row['id'],
            'userId' => (string)$row['user_id'],
            'kind' => (string)$row['kind'],
            'sourceRef' => $row['source_ref'],
            'status' => (string)$row['status'],
            'progress' => isset($row['progress']) ? (int)$row['progress'] : 0,
            'stage' => isset($row['stage']) ? (string)$row['stage'] : '',
            'useAi' => (bool)$row['use_ai'],
            'provider' => $row['provider'],
            'payload' => $this->decode($row['payload'], []),
            'result' => $this->decode($row['result'], null),
            'error' => $row['error'],
            'createdAt' => (int)$row['created_at'],
            'updatedAt' => (int)$row['updated_at'],
        ];
    }
}
