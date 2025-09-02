<?php

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$config_path = __DIR__ . '/../../config.json';

function load_config($path)
{
    return json_decode(file_get_contents($path), true);
}

function save_config($path, $data)
{
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
if (!$action) {
    echo json_encode(["success" => false, "message" => "No action specified."]);
    exit;
}

$config = load_config($config_path);

switch ($action) {
    case 'refresh':
        // Only update lastupdated
        $dt = new DateTime('now', new DateTimeZone('UTC'));
        $millis = (int)($dt->format('u') / 1000);
        $iso8601 = $dt->format('Y-m-d\TH:i:s') . '.' . str_pad($millis, 3, '0', STR_PAD_LEFT) . 'Z';
        $config['lastupdated'] = $iso8601;
        save_config($config_path, $config);
        echo json_encode(["success" => true, "lastupdated" => $iso8601]);
        break;
    case 'save':
        // Save all config fields, update lastupdated
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            echo json_encode(["success" => false, "message" => "No data provided."]);
            exit;
        }
        // Only allow known fields to be updated
        $fields = [
            'name', 'version', 'author', 'email', 'link', 'collector', 'gid',
            'offline', 'appdata', 'showads', 'modules', 'debugmode'
        ];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $config[$field] = $data[$field];
            }
        }
        // Update lastupdated
        $dt = new DateTime('now', new DateTimeZone('UTC'));
        $millis = (int)($dt->format('u') / 1000);
        $iso8601 = $dt->format('Y-m-d\TH:i:s') . '.' . str_pad($millis, 3, '0', STR_PAD_LEFT) . 'Z';
        $config['lastupdated'] = $iso8601;
        save_config($config_path, $config);
        echo json_encode(["success" => true, "lastupdated" => $iso8601, "config" => $config]);
        break;
    default:
        echo json_encode(["success" => false, "message" => "Unknown action."]);
        break;
}
