<?php
/**
 * ランバムみくじ
 * Googleスプレッドシートの「ランバムみくじ_出力」をCSV公開して使います。
 *
 * 1. シートを「ファイル > 共有 > ウェブに公開」で公開
 * 2. 「ランバムみくじ_出力」シートをCSV形式で公開
 * 3. 下のURLを、発行されたCSV URLへ貼り替えてください
 */
const GOOGLE_SHEET_CSV_URL = 'https://docs.google.com/spreadsheets/d/1VJE0MBF16O9nwwt0eOD-M_Rk7V12NDXGFKlc-ZvgtuI/export?format=csv&gid=583072315';

date_default_timezone_set('Asia/Tokyo');

function h($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalize_header($value) {
  return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$value));
}

function get_display_date_code($item) {
  $code = trim((string)($item['表示日コード'] ?? ''));
  if (preg_match('/^\d{4}$/', $code)) return $code;

  $dateLabel = trim((string)($item['整理番号用日付'] ?? ''));
  if ($dateLabel === '会期中ずっと' || $dateLabel === '0000') return '0000';
  if (preg_match('/(?:^|\D)(\d{2})(\d{2})(?:\D|$)/', $dateLabel, $matches)) {
    return $matches[1] . $matches[2];
  }

  $source = (string)($item['元シート名'] ?? $item['id'] ?? '');
  if (preg_match('/^(\d{4})(?:_|$)/', $source, $matches)) return $matches[1];
  return '';
}

function get_programs() {
  if (GOOGLE_SHEET_CSV_URL === '') {
    return ['programs' => [], 'error' => 'みくじの札を、ただいま準備しています。もう少しだけお待ちください。'];
  }

  $context = stream_context_create([
    'http' => ['timeout' => 10],
    'https' => ['timeout' => 10],
  ]);
  $csv = @file_get_contents(GOOGLE_SHEET_CSV_URL, false, $context);

  if ($csv === false) {
    return ['programs' => [], 'error' => 'まちから企画の便りが届いていないようです。少し時間をおいて、もう一度のぞいてみてください。'];
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
    $item['表示日コード'] = get_display_date_code($item);
    if ($item['表示日コード'] === '') continue;
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
    :root { --red:#e43b2f; --ink:#181817; --paper:#f4f0e6; --white:#fffdf7; --yellow:#ffd52e; --line:3px; }
    * { box-sizing:border-box; }
    html { scroll-behavior:smooth; }
    body { margin:0; min-height:100vh; color:var(--ink); background:var(--paper); font-family:-apple-system,BlinkMacSystemFont,"Hiragino Kaku Gothic ProN","Hiragino Sans","Noto Sans JP",sans-serif; }
    body::before { content:""; position:fixed; inset:0; pointer-events:none; opacity:.22; background-image:linear-gradient(rgba(24,24,23,.14) 1px,transparent 1px),linear-gradient(90deg,rgba(24,24,23,.14) 1px,transparent 1px); background-size:24px 24px; mask-image:linear-gradient(to bottom,#000,transparent 44%); }
    .page { position:relative; width:min(100%,820px); margin:0 auto; padding:18px 16px 48px; overflow:hidden; }
    .masthead { position:relative; min-height:440px; margin-bottom:24px; padding:18px 16px 28px; border:var(--line) solid var(--ink); background:var(--red); box-shadow:8px 8px 0 var(--ink); color:var(--white); overflow:hidden; }
    .masthead::before { content:"↘"; position:absolute; right:-12px; bottom:-50px; color:var(--yellow); font-size:clamp(180px,55vw,360px); font-weight:900; line-height:1; transform:rotate(-8deg); }
    .masthead-top { position:relative; z-index:1; display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
    .brand { max-width:14em; font-size:11px; font-weight:900; letter-spacing:.14em; line-height:1.5; }
    .issue { flex:none; border:2px solid var(--ink); background:var(--yellow); color:var(--ink); padding:5px 8px; font-size:11px; font-weight:900; transform:rotate(2deg); }
    h1 { position:relative; z-index:1; margin:58px 0 0; max-width:600px; color:var(--white); font-size:clamp(60px,17vw,122px); line-height:.78; letter-spacing:-.095em; font-weight:950; }
    h1 span { display:block; margin-left:.66em; }
    .lead { position:relative; z-index:1; width:max-content; max-width:90%; margin:42px 0 0; padding:8px 10px; background:var(--white); color:var(--ink); font-size:13px; font-weight:900; line-height:1.65; transform:rotate(-1deg); }
    .fate-strip { display:grid; grid-template-columns:1fr auto; align-items:stretch; margin:0 0 18px; border:var(--line) solid var(--ink); background:var(--ink); color:var(--white); }
    .fate-label { padding:14px 16px; font-size:clamp(22px,7vw,40px); font-weight:950; letter-spacing:-.05em; }
    .date { display:grid; place-items:center; min-width:104px; padding:8px 12px; background:var(--yellow); color:var(--ink); font-size:13px; font-weight:900; line-height:1.35; text-align:center; }
    .card { position:relative; border:var(--line) solid var(--ink); background:var(--white); box-shadow:8px 8px 0 var(--ink); transition:opacity .16s ease,transform .16s ease; }
    .card::before { content:"LUCKY DRAW / 2026"; position:absolute; top:50%; right:-51px; padding:5px 10px; background:var(--yellow); border:2px solid var(--ink); font-size:9px; font-weight:900; letter-spacing:.12em; transform:rotate(90deg) translateY(-50%); }
    .card.is-changing { opacity:.12; transform:translateY(9px) rotate(.35deg); }
    .card-head { display:flex; justify-content:space-between; gap:12px; padding:11px 34px 10px 14px; border-bottom:var(--line) solid var(--ink); background:var(--red); color:#fff; }
    .eyebrow { margin:0; font-size:12px; font-weight:900; letter-spacing:.08em; }
    .serial { font-size:11px; font-weight:900; }
    .card-body { padding:clamp(24px,7vw,46px) clamp(20px,6vw,44px) 30px; }
    #program-title { margin:0; max-width:13em; font-size:clamp(34px,10vw,68px); letter-spacing:-.065em; line-height:1.12; overflow-wrap:anywhere; }
    .catchcopy { margin:24px 0 0; padding-left:15px; border-left:8px solid var(--yellow); font-size:clamp(16px,4.5vw,22px); font-weight:900; line-height:1.55; }
    .description { margin:22px 0 0; font-size:15px; line-height:1.9; white-space:pre-wrap; }
    .info { margin:28px 0 0; border-top:var(--line) solid var(--ink); font-size:14px; line-height:1.55; }
    .info div { display:grid; grid-template-columns:5.2em 1fr; gap:10px; padding:11px 4px; border-bottom:1px solid var(--ink); }
    .info b { color:var(--red); font-size:11px; letter-spacing:.08em; }
    .actions { padding:0 clamp(20px,6vw,44px) 38px; }
    .button { appearance:none; display:block; width:100%; border:var(--line) solid var(--ink); border-radius:0; margin-top:16px; padding:17px 20px; color:var(--ink); background:var(--yellow); box-shadow:5px 5px 0 var(--ink); font:inherit; font-weight:950; font-size:18px; text-align:center; text-decoration:none; cursor:pointer; transition:transform .1s,box-shadow .1s; }
    .button::after { content:"  ↗"; }
    #draw-button { color:#fff; background:var(--red); font-size:clamp(20px,6vw,28px); }
    #draw-button::after { content:"  ↻"; }
    .button:hover { transform:translate(2px,2px); box-shadow:3px 3px 0 var(--ink); }
    .button:active { transform:translate(5px,5px); box-shadow:none; }
    .button:focus-visible, footer a:focus-visible { outline:4px solid #2773e8; outline-offset:4px; }
    .notice { position:relative; border:var(--line) solid var(--ink); background:var(--yellow); box-shadow:8px 8px 0 var(--ink); padding:28px 24px; font-weight:800; line-height:1.8; }
    .notice::before { content:"! NOTICE"; display:block; width:max-content; margin:-42px 0 18px -12px; border:2px solid var(--ink); background:var(--red); color:#fff; padding:4px 10px; font-size:11px; letter-spacing:.1em; transform:rotate(-2deg); }
    .hidden { display:none; }
    footer { margin-top:52px; border-top:var(--line) solid var(--ink); padding-top:18px; font-size:12px; font-weight:700; line-height:1.7; }
    footer a { display:flex; justify-content:space-between; align-items:center; gap:16px; color:var(--ink); font-size:clamp(18px,5vw,26px); font-weight:950; text-decoration:none; }
    footer a::after { content:"→"; color:var(--red); font-size:1.5em; }
    footer p { margin:8px 0 0; }
    @media (min-width:640px) { .page { padding:30px 28px 64px; } .masthead { min-height:520px; padding:26px 28px 36px; } .masthead h1 { margin-top:76px; } .card-head { padding-left:24px; } }
    @media (prefers-reduced-motion:reduce) { html { scroll-behavior:auto; } *,*::before,*::after { transition-duration:.01ms !important; animation-duration:.01ms !important; } }
  </style>
</head>
<body>
  <main class="page">
    <header class="masthead">
      <div class="masthead-top"><div class="brand">BEPPU ART MONTH<br>まちじゅう文化祭 2026</div><div class="issue">NO. <?= h($today->format('md')) ?></div></div>
      <h1>ランバム<span>みくじ</span></h1>
      <p class="lead">今日、出会うはずのなかった<br>プログラムに出かけてみよう。</p>
    </header>
    <div class="fate-strip"><div class="fate-label">今日の運命</div><div class="date"><?= h($today->format('n月j日')) ?><br>MON / DAY</div></div>
    <?php if ($data['error'] !== ''): ?>
      <div class="notice"><?= h($data['error']) ?></div>
    <?php elseif (!$isFestivalPeriod): ?>
      <div class="notice">ランバムみくじは、2026年10月10日（土）〜11月29日（日）の会期中にお楽しみいただけます。</div>
    <?php elseif (!$initialProgram): ?>
      <div class="notice">本日のプログラムを準備中です。少し時間をおいて、もう一度のぞいてみてください。</div>
    <?php else: ?>
      <section class="card" id="result-card" aria-live="polite" aria-atomic="true">
        <div class="card-head"><p class="eyebrow">TODAY'S BAM PROGRAM</p><span class="serial"># RANDOM</span></div>
        <div class="card-body">
          <h2 id="program-title"><?= h($initialProgram['タイトル'] ?? '') ?></h2>
          <p class="catchcopy" id="program-catchcopy"><?= h($initialProgram['キャッチコピー'] ?? '') ?></p>
          <p class="description" id="program-description"><?= h($initialProgram['紹介文'] ?? '') ?></p>
          <div class="info"><div id="program-date"><b>日時 / DATE</b><span><?= h($initialProgram['開催日'] ?? '') ?> <?= h($initialProgram['開催時間'] ?? '') ?></span></div><div id="program-venue"><b>会場 / PLACE</b><span><?= h($initialProgram['会場名'] ?? '') ?></span></div><div id="program-fee"><b>料金 / FEE</b><span><?= h($initialProgram['料金'] ?? '') ?></span></div><div id="program-organizer"><b>企画者 / BY</b><span><?= h($initialProgram['企画者名'] ?? '') ?></span></div></div>
        </div>
        <div class="actions"><a class="button hidden" id="program-link" href="#" target="_blank" rel="noopener">詳細を見る</a><button class="button" id="draw-button" type="button">もう一度ひく！</button></div>
      </section>
    <?php endif; ?>
    <footer><a href="https://beppuartmonth.com/" target="_blank" rel="noopener">ベップ・アート・マンス 2026</a><p>「つくろう会」がお届けする、今日のおすすめ。</p></footer>
  </main>
  <?php if ($initialProgram): ?>
  <script>
    const candidates = <?= json_encode($candidates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const byId = id => document.getElementById(id);
    const setText = (id, label, value) => {
      const row = byId(id);
      row.querySelector('b').textContent = label;
      row.querySelector('span').textContent = value || '未定';
    };
    const draw = () => {
      const program = candidates[Math.floor(Math.random() * candidates.length)];
      const card = byId('result-card');
      const updateCard = () => {
        byId('program-title').textContent = program['タイトル'] || '';
        byId('program-catchcopy').textContent = program['キャッチコピー'] || '';
        byId('program-description').textContent = program['紹介文'] || '';
        setText('program-date', '日時 / DATE', [program['開催日'], program['開催時間']].filter(Boolean).join(' '));
        setText('program-venue', '会場 / PLACE', program['会場名']); setText('program-fee', '料金 / FEE', program['料金']); setText('program-organizer', '企画者 / BY', program['企画者名']);
        const link = byId('program-link');
        if (program['URL']) { link.href = program['URL']; link.classList.remove('hidden'); } else { link.classList.add('hidden'); }
        card.classList.remove('is-changing');
      };
      card.classList.add('is-changing');
      window.setTimeout(updateCard, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 160);
      card.scrollIntoView({ behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block:'start' });
    };
    byId('draw-button').addEventListener('click', draw); draw();
  </script>
  <?php endif; ?>
</body>
</html>
