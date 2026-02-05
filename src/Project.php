<?php

declare(strict_types=1);

namespace App;

class Project
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        return $this->db->get_results("SELECT * FROM projects ORDER BY name ASC");
    }

    public function getById(int $id)
    {
        return $this->db->get_row("SELECT * FROM projects WHERE id = ?", [$id]);
    }

    public function create(array $data): int|bool
    {
        return $this->db->insert('projects', [
            'name' => $data['name'],
            'webserver_path' => $data['webserver_path'] ?? null,
            'php_path' => $data['php_path'] ?? null,
            'webserver_format' => $data['webserver_format'] ?? null,
            'php_format' => $data['php_format'] ?? null
        ]);
    }

    public function update(int $id, array $data): bool
    {
        return $this->db->update('projects', [
            'name' => $data['name'],
            'webserver_path' => $data['webserver_path'] ?? null,
            'php_path' => $data['php_path'] ?? null,
            'webserver_format' => $data['webserver_format'] ?? null,
            'php_format' => $data['php_format'] ?? null
        ], ['id' => $id]);
    }

    public function delete(int $id): bool
    {
        // Also delete access records
        $this->db->delete('project_access', ['project_id' => $id]);
        return $this->db->delete('projects', ['id' => $id]);
    }

    public function getUserProjects(int $userId): array
    {
        return $this->db->get_results("
            SELECT p.* FROM projects p
            JOIN project_access pa ON p.id = pa.project_id
            WHERE pa.user_id = ?
            ORDER BY p.name ASC
        ", [$userId]);
    }

    public function grantAccess(int $userId, int $projectId): bool
    {
        return $this->db->insert('project_access', [
            'user_id' => $userId,
            'project_id' => $projectId
        ]) !== false;
    }

    public function setUserProjects(int $userId, array $projectIds): bool
    {
        $this->db->delete('project_access', ['user_id' => $userId]);
        foreach ($projectIds as $projectId) {
            $this->grantAccess($userId, (int)$projectId);
        }
        return true;
    }

    public function getUserProjectIds(int $userId): array
    {
        $results = $this->db->get_results("SELECT project_id FROM project_access WHERE user_id = ?", [$userId]);
        return array_map(fn($r) => (int)$r->project_id, $results);
    }
}
