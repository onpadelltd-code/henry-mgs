<?php
/* ============================================================
   Owl Academy — progress sync + daily email report
   POST  {token, snapshot}          store today's snapshot
   GET   ?ping=1&token=...          send today's report (after 5pm, once)
   GET   ?ping=1&token=...&test=1   send immediately (doesn't mark day done)
   GET   ?ping=1&token=...&debug=1  return the HTML instead of emailing
   ============================================================ */
date_default_timezone_set('Europe/London');
$TOKEN = 'owl-1515-hoot';
$TO    = 'timsharrock@me.com, laura.hutton_88@live.co.uk';
$FROM  = 'Owl Academy <owl@cell-tech.co.uk>';
$DATA  = __DIR__ . '/owl-data.json';
$SEND_HOUR = 17; // 5pm London

function load_db($f){ return file_exists($f) ? json_decode(file_get_contents($f), true) : ['days'=>[], 'lastEmail'=>'']; }
function store_db($f,$d){ file_put_contents($f, json_encode($d), LOCK_EX); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$today = date('Y-m-d');
$db = load_db($DATA);
if(!is_array($db)) $db = ['days'=>[], 'lastEmail'=>''];

/* ---------- POST: store snapshot or full synced state ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $raw = file_get_contents('php://input');
  if (strlen($raw) > 400000) { http_response_code(413); exit('too big'); }
  $body = json_decode($raw, true);
  if (!$body || ($body['token'] ?? '') !== $TOKEN) { http_response_code(403); exit('no'); }

  // full app-state sync (cross-device: iPad <-> fridge <-> phones)
  if (isset($body['state'])) {
    $state = $body['state'];
    if (!is_array($state) || !isset($state['skills'])) { http_response_code(400); exit('bad'); }
    if (isset($state['coach'])) $state['coach']['apiKey'] = ''; // never store the API key server-side
    file_put_contents(__DIR__ . '/owl-state.json', json_encode($state), LOCK_EX);
    exit('ok');
  }

  $snap = $body['snapshot'] ?? null;
  if (!is_array($snap)) { http_response_code(400); exit('bad'); }
  $snap['at'] = date('H:i');
  $db['days'][$today] = $snap;
  ksort($db['days']);
  if (count($db['days']) > 60) $db['days'] = array_slice($db['days'], -60, null, true);
  store_db($DATA, $db);
  exit('ok');
}

/* ---------- GET ?state=1 : serve the synced state ---------- */
if (isset($_GET['state'])) {
  if (($_GET['token'] ?? '') !== $TOKEN) { http_response_code(403); exit('no'); }
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  $f = __DIR__ . '/owl-state.json';
  echo file_exists($f) ? file_get_contents($f) : '{}';
  exit;
}

/* ---------- GET: maybe send the daily report ---------- */
if (($_GET['token'] ?? '') !== $TOKEN) { http_response_code(403); exit('no'); }
$test  = isset($_GET['test']);
$debug = isset($_GET['debug']);
$hour  = (int)date('G');
if (!$test && !$debug && (($db['lastEmail'] ?? '') === $today || $hour < $SEND_HOUR)) exit('idle');

$snap = $db['days'][$today] ?? null;
$prev = null;
foreach (array_reverse(array_keys($db['days'])) as $k) { if ($k < $today) { $prev = $db['days'][$k]; break; } }

$dateNice = date('D j M');
$G = '#1d9e55'; $R = '#d64560'; $B = '#0e1f42'; $GOLD = '#b8860b';
$CHILD = h($snap['name'] ?? 'Your owl');   // name comes from the app's own settings

if ($snap && !empty($snap['today']['done'])) {
  $c = (int)($snap['today']['correct'] ?? 0); $t = max(1,(int)($snap['today']['total'] ?? 1));
  $pct = round($c / $t * 100);
  $subject = "🦉 {$CHILD}'s Owl Academy — $dateNice: $pct% accuracy, streak {$snap['streak']}" . (!empty($snap['earned']) ? ", +{$snap['earned']} min earned" : "");
  $headline = "<span style='color:$G'>✅ Mission completed</span> at " . h($snap['at'] ?? '?');
} elseif ($snap) {
  $subject = "🦉 {$CHILD}'s Owl Academy — $dateNice: no mission completed yet";
  $headline = "<span style='color:$R'>⏳ No mission completed today (yet)</span>";
} else {
  $subject = "🦉 Owl Academy — $dateNice: no activity recorded";
  $headline = "<span style='color:$R'>⏳ No activity recorded today</span>";
}

$rows = '';
if ($snap && !empty($snap['ratings'])) {
  foreach ($snap['ratings'] as $name => $r) {
    $pr = $prev['ratings'][$name] ?? null;
    $delta = $pr !== null ? $r - $pr : 0;
    $dTxt = $delta > 0 ? "<span style='color:$G'>▲ +$delta</span>" : ($delta < 0 ? "<span style='color:$R'>▼ $delta</span>" : "<span style='color:#999'>–</span>");
    $rows .= "<tr><td style='padding:6px 10px;border-bottom:1px solid #eee'>" . h($name) . "</td>
              <td style='padding:6px 10px;border-bottom:1px solid #eee;text-align:right'><b>" . h($r) . "</b></td>
              <td style='padding:6px 10px;border-bottom:1px solid #eee;text-align:right'>$dTxt</td></tr>";
  }
}
$weak = '';
if ($snap && !empty($snap['ratings'])) {
  $rt = $snap['ratings']; asort($rt);
  $weak = implode(' and ', array_map('h', array_slice(array_keys($rt), 0, 2)));
}
$pendHtml = '';
if ($snap && !empty($snap['pending'])) {
  foreach ($snap['pending'] as $p) {
    $pendHtml .= "<li style='margin:4px 0;color:#444'><b>" . h($p['n'] ?? '') . "</b> — " . h($p['c'] ?? '') . " owl-min</li>";
  }
}
$slipsHtml = '';
if ($snap && !empty($snap['slips'])) {
  foreach (array_slice($snap['slips'],0,5) as $s) {
    $slipsHtml .= "<li style='margin:4px 0;color:#444'>" . h($s['q'] ?? '') . " — answered <b style='color:$R'>" . h($s['g'] ?? '') . "</b>, correct: <b style='color:$G'>" . h($s['a'] ?? '') . "</b></li>";
  }
}

$statCell = function($v,$l) use ($B) {
  return "<td align='center' style='padding:10px;background:#f4f6fb;border-radius:8px'><div style='font-size:22px;font-weight:800;color:$B'>" . h($v) . "</div><div style='font-size:11px;color:#888;text-transform:uppercase'>$l</div></td>";
};
$stats = '';
if ($snap) {
  $accTxt = !empty($snap['today']['done']) ? round(($snap['today']['correct']??0)/max(1,$snap['today']['total']??1)*100)."%" : "—";
  $stats = "<table width='100%' cellspacing='6'><tr>"
    . $statCell($accTxt, "today's accuracy")
    . $statCell(($snap['streak'] ?? 0) . " 🔥", "streak")
    . $statCell(($snap['earned'] ?? 0) . " min", "earned today")
    . $statCell(($snap['bank'] ?? 0) . " min", "iPad bank")
    . "</tr></table>";
}

$html = "<div style='font-family:-apple-system,Segoe UI,Arial,sans-serif;max-width:560px;margin:0 auto;color:#222'>
  <div style='background:$B;border-radius:12px 12px 0 0;padding:18px 22px'>
    <div style='color:#ffc845;font-size:20px;font-weight:800'>🦉 Owl Academy — Daily Report</div>
    <div style='color:#9db1d9;font-size:13px'>$CHILD · $dateNice</div>
  </div>
  <div style='border:1px solid #e5e8f0;border-top:none;border-radius:0 0 12px 12px;padding:20px 22px'>
    <p style='font-size:16px;font-weight:700;margin:0 0 14px'>$headline</p>
    $stats"
  . ($rows ? "<h3 style='font-size:13px;color:#888;text-transform:uppercase;letter-spacing:1px;margin:18px 0 6px'>Skill power (vs last report)</h3>
    <table width='100%' style='border-collapse:collapse;font-size:14px'>$rows</table>" : "")
  . ($pendHtml ? "<h3 style='font-size:13px;color:$GOLD;text-transform:uppercase;letter-spacing:1px;margin:18px 0 6px'>🎁 Reward requests awaiting your approval</h3><ul style='font-size:13px;padding-left:18px'>$pendHtml</ul>" : "")
  . ($weak ? "<p style='font-size:13px;color:#555;margin:14px 0'><b>Focus areas:</b> tomorrow's mission will target <b>$weak</b>.</p>" : "")
  . ($slipsHtml ? "<h3 style='font-size:13px;color:#888;text-transform:uppercase;letter-spacing:1px;margin:18px 0 6px'>Recent slips (dinner-table chat)</h3><ul style='font-size:13px;padding-left:18px'>$slipsHtml</ul>" : "")
  . ($snap && !empty($snap['coach']) ? "<p style='font-size:12px;color:#999'>Auto-Coach has added " . h($snap['coach']) . " questions to date.</p>" : "")
  . (!$snap || empty($snap['today']['done']) ? "<p style='font-size:14px;color:$R;font-weight:700'>💡 A gentle nudge at bedtime keeps the streak alive!</p>" : "")
  . "<p style='font-size:11px;color:#bbb;margin-top:18px'>Sent automatically by Owl Academy · cell-tech.co.uk/henry/owl</p>
  </div></div>";

if ($debug) { header('Content-Type: text/html; charset=utf-8'); echo $html; exit; }

$headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: $FROM\r\nReply-To: timsharrock@me.com\r\n";
$ok = @mail($TO, $subject, $html, $headers);
if ($ok && !$test) { $db['lastEmail'] = $today; store_db($DATA, $db); }
exit($ok ? 'sent' : 'mailfail');
