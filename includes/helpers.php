<?php

declare(strict_types=1);

function json_response(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_response(['error' => $message], $status);
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error('Invalid JSON body', 400);
    }
    return $data;
}

function require_fields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
            json_error("Missing required field: {$field}", 422);
        }
    }
}

function slug_id(string $prefix = 'id'): string
{
    return $prefix . '-' . bin2hex(random_bytes(4));
}

function map_organization(array $row): array
{
    return [
        'id'        => $row['id'],
        'name'      => $row['name'],
        'shortName' => $row['short_name'],
        'color'     => $row['color'],
        'contact'   => $row['contact'],
        'siteCount' => (int) $row['site_count'],
    ];
}

function map_task(array $row): array
{
    $tags = $row['tags'] ?? '[]';
    if (is_string($tags)) {
        $tags = json_decode($tags, true) ?: [];
    }

    return [
        'id'          => $row['id'],
        'orgId'       => $row['org_id'],
        'title'       => $row['title'],
        'description' => $row['description'] ?? '',
        'category'    => $row['category'],
        'priority'    => $row['priority'],
        'status'      => $row['status'],
        'assignee'    => $row['assignee'],
        'requester'   => $row['requester'],
        'dueDate'     => $row['due_date'] ? date('c', strtotime($row['due_date'])) : '',
        'createdAt'   => date('c', strtotime($row['created_at'])),
        'updatedAt'   => date('c', strtotime($row['updated_at'])),
        'tags'        => $tags,
    ];
}

function map_incident(array $row): array
{
    return [
        'id'              => $row['id'],
        'orgId'           => $row['org_id'],
        'title'           => $row['title'],
        'summary'         => $row['summary'] ?? '',
        'severity'        => $row['severity'],
        'status'          => $row['status'],
        'impactedService' => $row['impacted_service'],
        'reportedBy'      => $row['reported_by'],
        'startedAt'       => date('c', strtotime($row['started_at'])),
        'resolvedAt'      => $row['resolved_at'] ? date('c', strtotime($row['resolved_at'])) : null,
    ];
}

function map_asset(array $row): array
{
    return [
        'id'              => $row['id'],
        'orgId'           => $row['org_id'],
        'name'            => $row['name'],
        'type'            => $row['type'],
        'serialNumber'    => $row['serial_number'],
        'location'        => $row['location'],
        'assignedTo'      => $row['assigned_to'],
        'status'          => $row['status'],
        'purchaseDate'    => $row['purchase_date'] ?? '',
        'lastMaintenance' => $row['last_maintenance'] ?? '',
        'nextMaintenance' => $row['next_maintenance'] ?? 'N/A',
    ];
}

function map_activity(array $row): array
{
    return [
        'id'          => $row['id'],
        'type'        => $row['type'],
        'action'      => $row['action'],
        'entityTitle' => $row['entity_title'],
        'orgId'       => $row['org_id'],
        'actor'       => $row['actor'],
        'timestamp'   => date('c', strtotime($row['timestamp'])),
    ];
}

function to_mysql_datetime(?string $iso): ?string
{
    if (!$iso) {
        return null;
    }
    $ts = strtotime($iso);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function to_mysql_date(?string $iso): ?string
{
    if (!$iso) {
        return null;
    }
    $ts = strtotime($iso);
    return $ts ? date('Y-m-d', $ts) : null;
}
