<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = require __DIR__ . '/../includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';

// Normalize path: strip up to /api/
if (preg_match('#/api(?:/index\.php)?(.*)$#', $uri, $m)) {
    $path = trim($m[1], '/');
} else {
    $path = trim($uri, '/');
}

$segments = $path !== '' ? explode('/', $path) : [];
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    match ($resource) {
        'health'        => handle_health($pdo),
        'organizations' => handle_organizations($pdo, $method, $id),
        'tasks'         => handle_tasks($pdo, $method, $id),
        'incidents'     => handle_incidents($pdo, $method, $id),
        'assets'        => handle_assets($pdo, $method, $id),
        'activity'      => handle_activity($pdo, $method, $id),
        'search'        => handle_search($pdo, $method),
        default         => json_error('Not found', 404),
    };
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}

function handle_health(PDO $pdo): void
{
    $pdo->query('SELECT 1');
    $counts = [
        'organizations' => (int) $pdo->query('SELECT COUNT(*) FROM organizations')->fetchColumn(),
        'tasks'         => (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn(),
        'incidents'     => (int) $pdo->query('SELECT COUNT(*) FROM incidents')->fetchColumn(),
        'assets'        => (int) $pdo->query('SELECT COUNT(*) FROM assets')->fetchColumn(),
        'activity'      => (int) $pdo->query('SELECT COUNT(*) FROM activity')->fetchColumn(),
    ];
    json_response(['status' => 'ok', 'counts' => $counts]);
}

function handle_organizations(PDO $pdo, string $method, ?string $id): void
{
    if ($method === 'GET' && !$id) {
        $rows = $pdo->query('SELECT * FROM organizations ORDER BY name')->fetchAll();
        json_response(array_map('map_organization', $rows));
    }

    if ($method === 'GET' && $id) {
        $stmt = $pdo->prepare('SELECT * FROM organizations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_error('Organization not found', 404);
        json_response(map_organization($row));
    }

    if ($method === 'POST') {
        $data = read_json_body();
        require_fields($data, ['name', 'shortName', 'color', 'contact']);
        $orgId = $data['id'] ?? slug_id('org');
        $stmt = $pdo->prepare(
            'INSERT INTO organizations (id, name, short_name, color, contact, site_count)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $orgId,
            $data['name'],
            $data['shortName'],
            $data['color'],
            $data['contact'],
            (int) ($data['siteCount'] ?? 1),
        ]);
        $stmt = $pdo->prepare('SELECT * FROM organizations WHERE id = ?');
        $stmt->execute([$orgId]);
        json_response(map_organization($stmt->fetch()), 201);
    }

    if ($method === 'PUT' && $id) {
        $data = read_json_body();
        require_fields($data, ['name', 'shortName', 'color', 'contact']);
        $stmt = $pdo->prepare(
            'UPDATE organizations SET name=?, short_name=?, color=?, contact=?, site_count=?
             WHERE id=?'
        );
        $stmt->execute([
            $data['name'],
            $data['shortName'],
            $data['color'],
            $data['contact'],
            (int) ($data['siteCount'] ?? 1),
            $id,
        ]);
        if ($stmt->rowCount() === 0) json_error('Organization not found', 404);
        $stmt = $pdo->prepare('SELECT * FROM organizations WHERE id = ?');
        $stmt->execute([$id]);
        json_response(map_organization($stmt->fetch()));
    }

    if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare('DELETE FROM organizations WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) json_error('Organization not found', 404);
        json_response(['deleted' => true]);
    }

    json_error('Method not allowed', 405);
}

function handle_tasks(PDO $pdo, string $method, ?string $id): void
{
    if ($method === 'GET' && !$id) {
        $orgId = $_GET['orgId'] ?? null;
        if ($orgId) {
            $stmt = $pdo->prepare('SELECT * FROM tasks WHERE org_id = ? ORDER BY updated_at DESC');
            $stmt->execute([$orgId]);
            $rows = $stmt->fetchAll();
        } else {
            $rows = $pdo->query('SELECT * FROM tasks ORDER BY updated_at DESC')->fetchAll();
        }
        json_response(array_map('map_task', $rows));
    }

    if ($method === 'GET' && $id) {
        $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_error('Task not found', 404);
        json_response(map_task($row));
    }

    if ($method === 'POST') {
        $data = read_json_body();
        require_fields($data, ['orgId', 'title']);
        $taskId = $data['id'] ?? slug_id('T');
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO tasks (id, org_id, title, description, category, priority, status,
             assignee, requester, due_date, created_at, updated_at, tags)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $taskId,
            $data['orgId'],
            $data['title'],
            $data['description'] ?? '',
            $data['category'] ?? 'other',
            $data['priority'] ?? 'medium',
            $data['status'] ?? 'open',
            $data['assignee'] ?? '',
            $data['requester'] ?? '',
            to_mysql_date($data['dueDate'] ?? null),
            to_mysql_datetime($data['createdAt'] ?? $now) ?? $now,
            to_mysql_datetime($data['updatedAt'] ?? $now) ?? $now,
            json_encode($data['tags'] ?? []),
        ]);
        $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $stmt->execute([$taskId]);
        json_response(map_task($stmt->fetch()), 201);
    }

    if ($method === 'PUT' && $id) {
        $data = read_json_body();
        require_fields($data, ['orgId', 'title']);
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'UPDATE tasks SET org_id=?, title=?, description=?, category=?, priority=?, status=?,
             assignee=?, requester=?, due_date=?, updated_at=?, tags=? WHERE id=?'
        );
        $stmt->execute([
            $data['orgId'],
            $data['title'],
            $data['description'] ?? '',
            $data['category'] ?? 'other',
            $data['priority'] ?? 'medium',
            $data['status'] ?? 'open',
            $data['assignee'] ?? '',
            $data['requester'] ?? '',
            to_mysql_date($data['dueDate'] ?? null),
            to_mysql_datetime($data['updatedAt'] ?? $now) ?? $now,
            json_encode($data['tags'] ?? []),
            $id,
        ]);
        if ($stmt->rowCount() === 0) json_error('Task not found', 404);
        $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
        json_response(map_task($stmt->fetch()));
    }

    if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) json_error('Task not found', 404);
        json_response(['deleted' => true]);
    }

    json_error('Method not allowed', 405);
}

function handle_incidents(PDO $pdo, string $method, ?string $id): void
{
    if ($method === 'GET' && !$id) {
        $orgId = $_GET['orgId'] ?? null;
        if ($orgId) {
            $stmt = $pdo->prepare('SELECT * FROM incidents WHERE org_id = ? ORDER BY started_at DESC');
            $stmt->execute([$orgId]);
            $rows = $stmt->fetchAll();
        } else {
            $rows = $pdo->query('SELECT * FROM incidents ORDER BY started_at DESC')->fetchAll();
        }
        json_response(array_map('map_incident', $rows));
    }

    if ($method === 'GET' && $id) {
        $stmt = $pdo->prepare('SELECT * FROM incidents WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_error('Incident not found', 404);
        json_response(map_incident($row));
    }

    if ($method === 'POST') {
        $data = read_json_body();
        require_fields($data, ['orgId', 'title']);
        $incId = $data['id'] ?? slug_id('INC');
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO incidents (id, org_id, title, summary, severity, status,
             impacted_service, reported_by, started_at, resolved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $incId,
            $data['orgId'],
            $data['title'],
            $data['summary'] ?? '',
            $data['severity'] ?? 'minor',
            $data['status'] ?? 'investigating',
            $data['impactedService'] ?? '',
            $data['reportedBy'] ?? '',
            to_mysql_datetime($data['startedAt'] ?? $now) ?? $now,
            to_mysql_datetime($data['resolvedAt'] ?? null),
        ]);
        $stmt = $pdo->prepare('SELECT * FROM incidents WHERE id = ?');
        $stmt->execute([$incId]);
        json_response(map_incident($stmt->fetch()), 201);
    }

    if ($method === 'PUT' && $id) {
        $data = read_json_body();
        require_fields($data, ['orgId', 'title']);
        $stmt = $pdo->prepare(
            'UPDATE incidents SET org_id=?, title=?, summary=?, severity=?, status=?,
             impacted_service=?, reported_by=?, started_at=?, resolved_at=? WHERE id=?'
        );
        $stmt->execute([
            $data['orgId'],
            $data['title'],
            $data['summary'] ?? '',
            $data['severity'] ?? 'minor',
            $data['status'] ?? 'investigating',
            $data['impactedService'] ?? '',
            $data['reportedBy'] ?? '',
            to_mysql_datetime($data['startedAt'] ?? date('Y-m-d H:i:s')),
            to_mysql_datetime($data['resolvedAt'] ?? null),
            $id,
        ]);
        if ($stmt->rowCount() === 0) json_error('Incident not found', 404);
        $stmt = $pdo->prepare('SELECT * FROM incidents WHERE id = ?');
        $stmt->execute([$id]);
        json_response(map_incident($stmt->fetch()));
    }

    if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare('DELETE FROM incidents WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) json_error('Incident not found', 404);
        json_response(['deleted' => true]);
    }

    json_error('Method not allowed', 405);
}

function handle_assets(PDO $pdo, string $method, ?string $id): void
{
    if ($method === 'GET' && !$id) {
        $orgId = $_GET['orgId'] ?? null;
        if ($orgId) {
            $stmt = $pdo->prepare('SELECT * FROM assets WHERE org_id = ? ORDER BY name');
            $stmt->execute([$orgId]);
            $rows = $stmt->fetchAll();
        } else {
            $rows = $pdo->query('SELECT * FROM assets ORDER BY name')->fetchAll();
        }
        json_response(array_map('map_asset', $rows));
    }

    if ($method === 'GET' && $id) {
        $stmt = $pdo->prepare('SELECT * FROM assets WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_error('Asset not found', 404);
        json_response(map_asset($row));
    }

    if ($method === 'POST') {
        $data = read_json_body();
        require_fields($data, ['orgId', 'name']);
        $assetId = $data['id'] ?? slug_id('AST');
        $stmt = $pdo->prepare(
            'INSERT INTO assets (id, org_id, name, type, serial_number, location, assigned_to,
             status, purchase_date, last_maintenance, next_maintenance)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $assetId,
            $data['orgId'],
            $data['name'],
            $data['type'] ?? 'other',
            $data['serialNumber'] ?? '',
            $data['location'] ?? '',
            $data['assignedTo'] ?? '',
            $data['status'] ?? 'operational',
            to_mysql_date($data['purchaseDate'] ?? null),
            to_mysql_date($data['lastMaintenance'] ?? null),
            $data['nextMaintenance'] ?? 'N/A',
        ]);
        $stmt = $pdo->prepare('SELECT * FROM assets WHERE id = ?');
        $stmt->execute([$assetId]);
        json_response(map_asset($stmt->fetch()), 201);
    }

    if ($method === 'PUT' && $id) {
        $data = read_json_body();
        require_fields($data, ['orgId', 'name']);
        $stmt = $pdo->prepare(
            'UPDATE assets SET org_id=?, name=?, type=?, serial_number=?, location=?,
             assigned_to=?, status=?, purchase_date=?, last_maintenance=?, next_maintenance=?
             WHERE id=?'
        );
        $stmt->execute([
            $data['orgId'],
            $data['name'],
            $data['type'] ?? 'other',
            $data['serialNumber'] ?? '',
            $data['location'] ?? '',
            $data['assignedTo'] ?? '',
            $data['status'] ?? 'operational',
            to_mysql_date($data['purchaseDate'] ?? null),
            to_mysql_date($data['lastMaintenance'] ?? null),
            $data['nextMaintenance'] ?? 'N/A',
            $id,
        ]);
        if ($stmt->rowCount() === 0) json_error('Asset not found', 404);
        $stmt = $pdo->prepare('SELECT * FROM assets WHERE id = ?');
        $stmt->execute([$id]);
        json_response(map_asset($stmt->fetch()));
    }

    if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare('DELETE FROM assets WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) json_error('Asset not found', 404);
        json_response(['deleted' => true]);
    }

    json_error('Method not allowed', 405);
}

function handle_activity(PDO $pdo, string $method, ?string $id): void
{
    if ($method === 'GET' && !$id) {
        $orgId = $_GET['orgId'] ?? null;
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 80)));
        if ($orgId) {
            $stmt = $pdo->prepare(
                "SELECT * FROM activity WHERE org_id = ? ORDER BY timestamp DESC LIMIT {$limit}"
            );
            $stmt->execute([$orgId]);
            $rows = $stmt->fetchAll();
        } else {
            $rows = $pdo->query(
                "SELECT * FROM activity ORDER BY timestamp DESC LIMIT {$limit}"
            )->fetchAll();
        }
        json_response(array_map('map_activity', $rows));
    }

    if ($method === 'POST') {
        $data = read_json_body();
        require_fields($data, ['type', 'action', 'entityTitle', 'orgId']);
        $actId = $data['id'] ?? slug_id('a');
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO activity (id, type, action, entity_title, org_id, actor, timestamp)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $actId,
            $data['type'],
            $data['action'],
            $data['entityTitle'],
            $data['orgId'],
            $data['actor'] ?? 'System',
            to_mysql_datetime($data['timestamp'] ?? $now) ?? $now,
        ]);
        $stmt = $pdo->prepare('SELECT * FROM activity WHERE id = ?');
        $stmt->execute([$actId]);
        json_response(map_activity($stmt->fetch()), 201);
    }

    json_error('Method not allowed', 405);
}

function handle_search(PDO $pdo, string $method): void
{
    if ($method !== 'GET') {
        json_error('Method not allowed', 405);
    }

    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        json_response(['tasks' => [], 'incidents' => [], 'assets' => []]);
    }

    $like = '%' . $q . '%';

    $tasks = $pdo->prepare(
        'SELECT * FROM tasks WHERE title LIKE ? OR id LIKE ? OR assignee LIKE ? OR requester LIKE ?
         ORDER BY updated_at DESC LIMIT 20'
    );
    $tasks->execute([$like, $like, $like, $like]);

    $incidents = $pdo->prepare(
        'SELECT * FROM incidents WHERE title LIKE ? OR id LIKE ? OR impacted_service LIKE ?
         ORDER BY started_at DESC LIMIT 20'
    );
    $incidents->execute([$like, $like, $like]);

    $assets = $pdo->prepare(
        'SELECT * FROM assets WHERE name LIKE ? OR id LIKE ? OR serial_number LIKE ? OR location LIKE ?
         ORDER BY name LIMIT 20'
    );
    $assets->execute([$like, $like, $like, $like]);

    json_response([
        'tasks'     => array_map('map_task', $tasks->fetchAll()),
        'incidents' => array_map('map_incident', $incidents->fetchAll()),
        'assets'    => array_map('map_asset', $assets->fetchAll()),
    ]);
}
