<?php
// ── Henry @ MGS — API backend (PHP + SQLite) ───────────────────────
// Endpoints:
//   GET    ?col=x            → return all items in collection
//   POST   ?col=x            → add item (auto-generated id)
//   PUT    ?col=x&id=y       → set/replace item by id
//   PATCH  ?col=x&id=y       → merge-update item by id
//   DELETE ?col=x&id=y       → delete item
//   POST   ?action=import    → proxy to Claude API (X-Claude-Key header)
// Auth: X-Api-Key header (or ?key= query param for simplicity)

define('API_KEY', 'henry-mgs-2026-xK9mP');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, X-Claude-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Auth ────────────────────────────────────────────────────────────
$key = '';
if (isset($_SERVER['HTTP_X_API_KEY'])) {
    $key = $_SERVER['HTTP_X_API_KEY'];
} elseif (isset($_GET['key'])) {
    $key = $_GET['key'];
}
if ($key !== API_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// ── Claude AI proxy ──────────────────────────────────────────────────
// Proxies to Anthropic so the browser never makes cross-origin requests.
if (isset($_GET['action']) && $_GET['action'] === 'import') {
    $claudeKey = isset($_SERVER['HTTP_X_CLAUDE_KEY']) ? trim($_SERVER['HTTP_X_CLAUDE_KEY']) : '';
    if (!$claudeKey) {
        http_response_code(400);
        echo json_encode(['error' => 'X-Claude-Key header required']);
        exit;
    }
    if (!function_exists('curl_init')) {
        http_response_code(500);
        echo json_encode(['error' => 'cURL not available on this server']);
        exit;
    }
    $payload = file_get_contents('php://input');
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $claudeKey,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    if ($curlErr) {
        http_response_code(502);
        echo json_encode(['error' => 'cURL error: ' . $curlErr]);
        exit;
    }
    http_response_code($httpCode ?: 502);
    echo $result ?: json_encode(['error' => 'Empty response from Claude API']);
    exit;
}

// ── Database ─────────────────────────────────────────────────────────
$dbPath = __DIR__ . '/henry.db';
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA journal_mode=WAL");
    $pdo->exec("CREATE TABLE IF NOT EXISTS items (
        id   TEXT NOT NULL,
        col  TEXT NOT NULL,
        data TEXT NOT NULL DEFAULT '{}',
        PRIMARY KEY (col, id)
    )");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// ── Request ──────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$col    = isset($_GET['col']) ? $_GET['col'] : '';
$id     = isset($_GET['id'])  ? $_GET['id']  : '';
$body   = file_get_contents('php://input');
$data   = ($body !== '') ? json_decode($body, true) : [];
if ($data === null) $data = [];

// Whitelist collections
$allowed = ['homework', 'fixtures', 'keyDates', 'wraparound', 'books', 'forms'];
if (!in_array($col, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown collection']);
    exit;
}

// ── Handlers ─────────────────────────────────────────────────────────
try {

    if ($method === 'GET') {
        $stmt = $pdo->prepare("SELECT id, data FROM items WHERE col = ? ORDER BY rowid");
        $stmt->execute([$col]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = array_map(function ($r) {
            $d = json_decode($r['data'], true);
            if (!is_array($d)) $d = [];
            $d['id'] = $r['id'];
            return $d;
        }, $rows);
        echo json_encode(array_values($out));

    } elseif ($method === 'POST') {
        // Auto-generate ID
        $newId = uniqid('', true);
        $stmt  = $pdo->prepare("INSERT INTO items (id, col, data) VALUES (?, ?, ?)");
        $stmt->execute([$newId, $col, json_encode($data)]);
        echo json_encode(['id' => $newId]);

    } elseif ($method === 'PUT') {
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'id required']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO items (id, col, data) VALUES (?, ?, ?)");
        $stmt->execute([$id, $col, json_encode($data)]);
        echo json_encode(['ok' => true]);

    } elseif ($method === 'PATCH') {
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'id required']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT data FROM items WHERE col = ? AND id = ?");
        $stmt->execute([$col, $id]);
        $row      = $stmt->fetch(PDO::FETCH_ASSOC);
        $existing = ($row && $row['data']) ? json_decode($row['data'], true) : [];
        if (!is_array($existing)) $existing = [];
        $merged = array_merge($existing, $data);
        $stmt2  = $pdo->prepare("INSERT OR REPLACE INTO items (id, col, data) VALUES (?, ?, ?)");
        $stmt2->execute([$id, $col, json_encode($merged)]);
        echo json_encode(['ok' => true]);

    } elseif ($method === 'DELETE') {
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'id required']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM items WHERE col = ? AND id = ?");
        $stmt->execute([$col, $id]);
        echo json_encode(['ok' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
