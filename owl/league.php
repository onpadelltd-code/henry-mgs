<?php
/* ============================================================
   Owl League — shared leaderboard across pilot children.
   POST {token, child:{id,name,xp,week,streak,missions}}  upsert one child
   GET  ?board=1&token=...                                ranked board (weekly XP)
   Stores only: first name + score numbers. No progress detail crosses families.
   ============================================================ */
date_default_timezone_set('Europe/London');
$TOKEN = 'owl-league-9k4t';
$F = __DIR__ . '/league.json';

$db = file_exists($F) ? json_decode(file_get_contents($F), true) : [];
if (!is_array($db)) $db = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $b = json_decode(file_get_contents('php://input'), true);
  if (!$b || ($b['token'] ?? '') !== $TOKEN) { http_response_code(403); exit('no'); }
  $c = $b['child'] ?? null;
  if (!is_array($c) || empty($c['id']) || empty($c['name'])) { http_response_code(400); exit('bad'); }
  $id = preg_replace('/[^a-z0-9_-]/i', '', substr($c['id'], 0, 24));
  $db[$id] = [
    'name'     => mb_substr(strip_tags($c['name']), 0, 16),
    'xp'       => max(0, (int)($c['xp'] ?? 0)),
    'week'     => max(0, (int)($c['week'] ?? 0)),
    'streak'   => max(0, (int)($c['streak'] ?? 0)),
    'missions' => max(0, (int)($c['missions'] ?? 0)),
    'up'       => date('c'),
  ];
  file_put_contents($F, json_encode($db), LOCK_EX);
  exit('ok');
}

if (($_GET['token'] ?? '') !== $TOKEN) { http_response_code(403); exit('no'); }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$rows = [];
foreach ($db as $id => $r) { $r['id'] = $id; $rows[] = $r; }
usort($rows, function($a, $b) { return ($b['week'] <=> $a['week']) ?: ($b['xp'] <=> $a['xp']); });
echo json_encode($rows);
