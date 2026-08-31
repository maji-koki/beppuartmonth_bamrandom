<?php
/**
 * ランバムみくじ
 * Googleスプレッドシートの「ランバムみくじ_出力」をCSV公開して使います。
 *
 * 1. シートを「ファイル > 共有 > ウェブに公開」で公開
 * 2. 「ランバムみくじ_出力」シートをCSV形式で公開
 * 3. 下のURLを、発行されたCSV URLへ貼り替えてください
 */
const GOOGLE_SHEET_CSV_URL = '';

date_default_timezone_set('Asia/Tokyo');

function h($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalize_header($value) {
  return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$value));
}

function get_programs() {
  if (GOOGLE_SHEET_CSV_URL === '') {
    return ['programs' => [], 'error' => 'データURLが未設定です。index.php上部の GOOGLE_SHEET_CSV_URL に、公開したCSVのURLを入力してください。'];
  }

  $context = stream_context_create([
    'http' => ['timeout' => 10],
    'https' => ['timeout' => 10],
  ]);
  $csv = @file_get_contents(GOOGLE_SHEET_CSV_URL, false, $context);

  if ($csv === false) {
    return ['programs' => [], 'error' => '企画データを読み込めませんでした。公開設定とCSV URLを確認してください。'];
  }

  $rows = preg_split('/\r\n|\r|\n/', trim($csv));
  if (count($rows) < 2) return ['programs' => [], 'error' => '企画データがまだありません。'];

  $headers = array_map('normalize_header', str_getcsv(array_shift($rows)));
  $programs = [];
  foreach ($rows as $row) {
    if (trim($row) === '') continue;
    $values = str_getcsv($row);
    $item = [];
    foreach ($headers as $index => $header) $item[$header] = isset($values[$index]) ? trim($values[$index]) : '';
    if (($item['type'] ?? '') !== 'program') continue;
    if (strtoupper($item['表示する'] ?? 'TRUE') !== 'TRUE') continue;
    if (($item['表示日コード'] ?? '') === '') continue;
    $programs[] = $item;
  }
  return ['programs' => $programs, 'error' => ''];
}

function is_valid_date_string($date) {
  return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
}

// 通常は今日。?date=2026-10-10 を付けると公開前にも動作確認できます。
$requestedDate = $_GET['date'] ?? date('Y-m-d');
if (!is_valid_date_string($requestedDate)) $requestedDate = date('Y-m-d');
$today = new DateTimeImmutable($requestedDate, new DateTimeZone('Asia/Tokyo'));
$festivalStart = new DateTimeImmutable('2026-10-10', new DateTimeZone('Asia/Tokyo'));
$festivalEnd = new DateTimeImmutable('2026-11-29 23:59:59', new DateTimeZone('Asia/Tokyo'));
$isFestivalPeriod = $today >= $festivalStart && $today <= $festivalEnd;
$todayCode = $today->format('md');
$data = get_programs();
$candidates = [];
if ($isFestivalPeriod) {
  foreach ($data['programs'] as $program) {
    $code = $program['表示日コード'] ?? '';
    if ($code === '0000' || $code === $todayCode) {
      $weight = max(1, (int)($program['おすすめ度'] ?? 1));
      for ($i = 0; $i < $weight; $i++) $candidates[] = $program;
    }
  }
}
$initialProgram = count($candidates) ? $candidates[array_rand($candidates)] : null;
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#ef4b3e">
  <title>ランバムみくじ｜ベップ・アート・マンス 2026</title>
  <style>
    :root { --red:#ef4b3e; --ink:#22211f; --paper:#fffdf8; --yellow:#f8cc42; --line:#22211f; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; color:var(--ink); background:radial-gradient(circle at 12% 10%,rgba(248,204,66,.55) 0 4px,transparent 5px) 0 0/48px 48px,var(--paper); font-family:-apple-system,BlinkMacSystemFont,"Hiragino Kaku Gothic ProN","Hiragino Sans","Noto Sans JP",sans-serif; }
    .page { width:min(100%,720px); margin:0 auto; padding:28px 20px 48px; }
    header { display:flex; justify-content:space-between; gap:18px; align-items:flex-start; margin-bottom:26px; }
    .brand { font-size:12px; font-weight:800; letter-spacing:.08em; line-height:1.5; }
    h1 { margin:0; color:var(--red); font-size:clamp(43px,11vw,74px); line-height:.95; letter-spacing:-.08em; font-weight:900; }
    .lead { margin:12px 0 0; font-weight:700; line-height:1.7; }
    .date { flex:0 0 auto; margin-top:6px; border:2px solid var(--line); background:var(--yellow); padding:7px 10px; font-size:12px; font-weight:800; text-align:center; }
    .card { border:3px solid var(--line); background:#fff; box-shadow:7px 7px 0 var(--line); padding:clamp(24px,7vw,44px); }
    .eyebrow { margin:0 0 14px; font-size:13px; font-weight:800; color:var(--red); letter-spacing:.07em; }
    #program-title { margin:0; font-size:clamp(28px,7vw,48px); letter-spacing:-.04em; line-height:1.25; }
    .catchcopy { margin:20px 0 0; font-weight:800; line-height:1.7; }
    .description { margin:16px 0 0; font-size:15px; line-height:1.9; white-space:pre-wrap; }
    .info { border-top:1px solid #bdb8ad; margin:25px 0 0; padding:19px 0 0; display:grid; gap:9px; font-size:14px; line-height:1.6; }
    .info b { display:inline-block; min-width:4.5em; color:var(--red); }
    .button { appearance:none; display:block; width:100%; border:3px solid var(--line); border-radius:0; margin-top:30px; padding:17px 20px; color:white; background:var(--red); box-shadow:5px 5px 0 var(--line); font:inherit; font-weight:900; font-size:18px; text-align:center; text-decoration:none; cursor:pointer; }
    .button:hover { transform:translate(2px,2px); box-shadow:3px 3px 0 var(--line); }
    .button:active { transform:translate(5px,5px); box-shadow:none; }
    .notice { border:2px solid var(--line); background:#fff3c5; padding:20px; font-weight:700; line-height:1.8; }
    .hidden { display:none; }
    footer { margin-top:36px; text-align:center; font-size:12px; font-weight:700; line-height:1.7; }
    footer a { color:var(--ink); text-underline-offset:3px; }
  </style>
</head>
<body>
  <main class="page">
    <header>
      <div><div class="brand">BEPPU ART MONTH 2026</div><h1>ランバム<br>みくじ</h1><p class="lead">今日、出会うはずのなかった<br>プログラムに出かけてみよう。</p></div>
      <div class="date"><?= h($today->format('n月j日')) ?><br>の運命</div>
    </header>
    <?php if ($data['error'] !== ''): ?>
      <div class="notice"><?= h($data['error']) ?></div>
    <?php elseif (!$isFestivalPeriod): ?>
      <div class="notice">ランバムみくじは、2026年10月10日（土）〜11月29日（日）の会期中にお楽しみいただけます。</div>
    <?php elseif (!$initialProgram): ?>
      <div class="notice">本日のプログラムを準備中です。少し時間をおいて、もう一度のぞいてみてください。</div>
    <?php else: ?>
      <section class="card" id="result-card" aria-live="polite">
        <p class="eyebrow">TODAY'S BAM PROGRAM</p>
        <h2 id="program-title"><?= h($initialProgram['タイトル'] ?? '') ?></h2>
        <p class="catchcopy" id="program-catchcopy"><?= h($initialProgram['キャッチコピー'] ?? '') ?></p>
        <p class="description" id="program-description"><?= h($initialProgram['紹介文'] ?? '') ?></p>
        <div class="info"><div id="program-date"><b>日時</b><?= h($initialProgram['開催日'] ?? '') ?> <?= h($initialProgram['開催時間'] ?? '') ?></div><div id="program-venue"><b>会場</b><?= h($initialProgram['会場名'] ?? '') ?></div><div id="program-fee"><b>料金</b><?= h($initialProgram['料金'] ?? '') ?></div><div id="program-organizer"><b>企画者</b><?= h($initialProgram['企画者名'] ?? '') ?></div></div>
        <a class="button hidden" id="program-link" href="#" target="_blank" rel="noopener">詳細を見る</a>
        <button class="button" id="draw-button" type="button">もう一度ひく！</button>
      </section>
    <?php endif; ?>
    <footer><a href="https://beppuartmonth.com/" target="_blank" rel="noopener">ベップ・アート・マンス 2026</a><br>「つくろう会」がお届けする、今日のおすすめ。</footer>
  </main>
  <?php if ($initialProgram): ?>
  <script>
    const candidates = <?= json_encode($candidates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const byId = id => document.getElementById(id);
    const setText = (id, label, value) => { byId(id).innerHTML = '<b>' + label + '</b>' + (value || '未定'); };
    const draw = () => {
      const program = candidates[Math.floor(Math.random() * candidates.length)];
      byId('program-title').textContent = program['タイトル'] || '';
      byId('program-catchcopy').textContent = program['キャッチコピー'] || '';
      byId('program-description').textContent = program['紹介文'] || '';
      setText('program-date', '日時', [program['開催日'], program['開催時間']].filter(Boolean).join(' '));
      setText('program-venue', '会場', program['会場名']); setText('program-fee', '料金', program['料金']); setText('program-organizer', '企画者', program['企画者名']);
      const link = byId('program-link');
      if (program['URL']) { link.href = program['URL']; link.classList.remove('hidden'); } else { link.classList.add('hidden'); }
      byId('result-card').scrollIntoView({ behavior:'smooth', block:'start' });
    };
    byId('draw-button').addEventListener('click', draw); draw();
  </script>
  <?php endif; ?>
</body>
</html>
