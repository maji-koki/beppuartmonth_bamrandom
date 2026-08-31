<?php
/**
 * ランバムみくじ
 * Googleスプレッドシートの「ランバムみくじ_出力」をCSV公開して使います。
 *
 * 1. シートを「ファイル > 共有 > ウェブに公開」で公開
 * 2. 「ランバムみくじ_出力」シートをCSV形式で公開
 * 3. 下のURLを、発行されたCSV URLへ貼り替えてください
 */
const GOOGLE_SHEET_CSV_URL = 'https://docs.google.com/spreadsheets/d/1keCoXNB6wLdFJOyBmLMn69tccXrc4P4FAJJdawetjr8/export?format=csv&gid=1589021911';
// 公開前の確認用。会期開始時には false に戻してください。
const ENABLE_DRAW_OUTSIDE_FESTIVAL = true;

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

function get_program_date_display($item) {
  $date = trim((string)($item['開催日'] ?? ''));
  if ($date !== '') return $date;
  $isAllPeriod = trim((string)($item['整理番号用日付'] ?? '')) === '会期中ずっと'
    || trim((string)($item['表示日コード'] ?? '')) === '0000';
  if ($isAllPeriod) return '10/10（土）〜11/29（日）';
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
$isFestivalPeriod = ENABLE_DRAW_OUTSIDE_FESTIVAL || ($today >= $festivalStart && $today <= $festivalEnd);
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
$assetBase = function_exists('get_template_directory_uri') ? rtrim(get_template_directory_uri(), '/') . '/assets' : 'assets';
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#e968a0">
  <title>ランバムみくじ｜ベップ・アート・マンス 2026</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=M+PLUS+1p:wght@400;500;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="<?= h($assetBase) ?>/js/gsap.min.js"></script>
  <script>
    tailwind.config = {
      theme: { extend: {
        colors: { bam: { pink:'#e968a0', red:'#ec4d63', lime:'#91c500', orange:'#f39800', sky:'#5eadd7', teal:'#4db4b1', purple:'#9c68aa', ink:'#4b4b47', cream:'#fffaf5' } },
        fontFamily: { sans:['M PLUS 1p','sans-serif'] }
      } }
    };
  </script>
  <style>
    :root { --pink:#e968a0; --red:#ec4d63; --lime:#91c500; --green:#5eb42b; --orange:#f39800; --sky:#5eadd7; --teal:#4db4b1; --purple:#9c68aa; --blue:#5478b3; --ink:#555; --pale:#f7f7f4; --line:#d8d8d3; }
    * { box-sizing:border-box; }
    html { scroll-behavior:smooth; }
    body { font-family:'M PLUS 1p',sans-serif; }
    .color-dots { display:flex; gap:7px; align-items:center; overflow:hidden; margin:0 0 22px; white-space:nowrap; }
    .color-dots span { flex:0 0 7px; width:7px; height:7px; border-radius:2px; background:var(--pink); }
    .color-dots span:nth-child(8n+2) { background:var(--orange); } .color-dots span:nth-child(8n+3) { background:var(--blue); } .color-dots span:nth-child(8n+4) { background:var(--lime); } .color-dots span:nth-child(8n+5) { background:var(--teal); } .color-dots span:nth-child(8n+6) { background:var(--purple); } .color-dots span:nth-child(8n+7) { background:var(--red); } .color-dots span:nth-child(8n+8) { background:var(--sky); }
    .card { transition:opacity .16s ease,transform .16s ease; }
    .card.is-changing { opacity:.2; transform:translateY(8px); }
    .gacha-machine { filter:drop-shadow(0 16px 20px rgba(75,75,71,.14)); }
    .gacha-handle { transform-box:fill-box; transform-origin:center; }
    .gacha-capsule { transform-box:fill-box; transform-origin:center; opacity:0; }
    .gacha-stage.is-running .gacha-handle { animation:gacha-turn .72s cubic-bezier(.34,1.3,.64,1); }
    .gacha-stage.is-running .gacha-capsule { animation:gacha-drop .9s .28s cubic-bezier(.22,1,.36,1) forwards; }
    .gacha-stage.is-running .gacha-body { animation:gacha-shake .42s ease-in-out; transform-origin:center bottom; }
    @keyframes gacha-turn { to { transform:rotate(360deg); } }
    @keyframes gacha-drop { 0% { opacity:0; transform:translateY(-24px) rotate(-20deg) scale(.7); } 35% { opacity:1; } 75% { transform:translateY(48px) rotate(18deg) scale(1); } 100% { opacity:0; transform:translateY(62px) rotate(26deg) scale(.9); } }
    @keyframes gacha-shake { 25% { transform:rotate(-1.5deg); } 50% { transform:rotate(1.5deg); } 75% { transform:rotate(-.7deg); } }
    .description { margin:22px 0 0; font-size:15px; line-height:1.9; white-space:pre-wrap; }
    .info b { width:max-content; border-radius:99px; background:var(--sky); color:#fff; padding:3px 8px; font-size:10px; letter-spacing:.04em; }
    .info div:nth-child(2) b { background:var(--orange); } .info div:nth-child(3) b { background:var(--teal); } .info div:nth-child(4) b { background:var(--purple); }
    .button::after { content:"  ↗"; }
    #draw-button::after { content:"  ↻"; }
    .button:hover { filter:brightness(1.05); transform:translateY(-2px); }
    .button:active { transform:translateY(1px); }
    .button:focus-visible, footer a:focus-visible { outline:4px solid var(--blue); outline-offset:4px; }
    .notice::before { content:"おしらせ"; display:block; width:max-content; margin:-44px 0 16px -8px; border-radius:99px; background:var(--pink); color:#fff; padding:5px 14px; font-size:11px; letter-spacing:.08em; }
    .hidden { display:none; }
    footer a::after { content:"→"; color:var(--pink); font-size:1.5em; }
    .slider-track { transition:transform .7s cubic-bezier(.22,1,.36,1); }
    @media (prefers-reduced-motion:reduce) { html { scroll-behavior:auto; } .slider-track { transition:none; } *,*::before,*::after { animation-duration:.01ms!important; animation-iteration-count:1!important; } }
  </style>
</head>
<body class="!m-0 min-h-screen !bg-[#fffdf9] !text-bam-ink font-sans antialiased">
  <main class="!w-full !overflow-hidden !pb-16">
    <section id="bam-slider" class="relative h-[100svh] min-h-[640px] w-full overflow-hidden bg-white" aria-roledescription="カルーセル" aria-label="ランバムみくじの紹介">
      <div class="slider-track flex h-full" id="slider-track">
        <article class="slider-slide relative h-full w-full flex-none overflow-hidden bg-[#fffaf5]" aria-label="1 / 3">
          <div class="mx-auto grid h-full w-full max-w-[1440px] grid-rows-[44%_56%] lg:grid-cols-[.9fr_1.1fr] lg:grid-rows-1">
            <div class="relative z-10 order-2 flex flex-col justify-center px-6 pb-28 pt-7 sm:px-12 lg:order-1 lg:pb-20 lg:pt-20 xl:px-20">
              <div class="slide-title-line mb-5 flex items-center gap-3 text-[9px] font-black tracking-[.18em] text-bam-ink sm:text-[11px]"><span class="h-2 w-2 rounded-full bg-bam-lime"></span>BEPPU ART MONTH 2026 <span class="h-px flex-1 bg-bam-ink/20"></span>01</div>
              <h1 class="!m-0 !text-[clamp(3rem,12vw,7.2rem)] !font-black !leading-[.94] !tracking-[-.085em] !text-bam-red">
                <span class="slide-title-line block">ランバム</span><span class="slide-title-line block">みくじ!!</span>
              </h1>
              <p class="slide-title-line mt-3 text-[clamp(1rem,4.5vw,2rem)] font-black tracking-[-.04em] text-bam-ink">（Random BAMみくじ!!）</p>
              <div class="slide-title-line mt-6 flex items-start gap-4 lg:mt-10"><span class="mt-2 h-2 w-12 flex-none rounded-full bg-bam-orange"></span><p class="max-w-[28em] text-xs font-bold leading-6 sm:text-sm sm:leading-7">今日のあなたの運命のプログラムは？<br>偶然をたよりに、別府のまちへ。</p></div>
            </div>
            <div class="relative order-1 overflow-hidden bg-bam-pink lg:order-2">
              <div class="hero-collage-item absolute -left-[3%] -top-[5%] h-[66%] w-[61%] rotate-[-3deg] overflow-hidden border-[5px] border-[#fffaf5] shadow-2xl sm:border-[8px]"><img class="h-full w-full object-cover" src="<?= h($assetBase) ?>/images/beppu-arcade.jpg" alt="別府の商店街" fetchpriority="high"></div>
              <div class="hero-collage-item absolute -right-[4%] -top-[2%] h-[55%] w-[48%] rotate-[3deg] overflow-hidden border-[5px] border-[#fffaf5] shadow-2xl sm:border-[8px]"><img class="h-full w-full object-cover" src="<?= h($assetBase) ?>/images/bam-puppet.jpeg" alt="人形劇を楽しむアートプログラム" fetchpriority="high"></div>
              <div class="hero-collage-item absolute bottom-[-5%] left-[15%] h-[55%] w-[53%] rotate-[2deg] overflow-hidden border-[5px] border-[#fffaf5] shadow-2xl sm:border-[8px]"><img class="h-full w-full object-cover" src="<?= h($assetBase) ?>/images/bam-paper.jpeg" alt="別府のまちで開かれた文化プログラム" fetchpriority="high"></div>
              <div class="hero-collage-item absolute bottom-[3%] right-[-7%] h-[42%] w-[39%] rotate-[-4deg] overflow-hidden border-[5px] border-[#fffaf5] shadow-2xl sm:border-[8px]"><img class="h-full w-full object-cover" src="<?= h($assetBase) ?>/images/takegawara.jpg" alt="別府・竹瓦温泉の風景" fetchpriority="high"></div>
              <div class="hero-collage-item absolute left-[5%] top-[50%] grid h-16 w-16 -translate-y-1/2 place-items-center rounded-full border-[4px] border-bam-red bg-[#fffaf5] text-center text-[9px] font-black leading-3 text-bam-red shadow-lg sm:h-24 sm:w-24 sm:text-xs sm:leading-4">まちじゅう<br>文化祭</div>
            </div>
          </div>
        </article>
        <article class="slider-slide relative flex h-full w-full flex-none items-end overflow-hidden bg-[#251820] px-6 pb-32 pt-28 sm:px-12 sm:pb-36" aria-label="2 / 3" aria-hidden="true">
          <img class="absolute inset-0 h-full w-full object-cover object-center" src="<?= h($assetBase) ?>/images/bam-performance.jpeg" alt="多文化の踊りを披露するアートプログラム" loading="lazy" decoding="async">
          <div class="absolute inset-0 bg-gradient-to-t from-[#251820]/95 via-[#251820]/40 to-black/5"></div>
          <div class="relative z-10 mx-auto w-full max-w-[1120px]"><p class="mb-4 text-[10px] font-black tracking-[.26em] text-pink-200 sm:text-xs">WALK INTO THE UNKNOWN</p><h2 class="max-w-[9em] text-[clamp(2.8rem,12vw,6.5rem)] font-black leading-[1.02] tracking-[-.07em] text-white">知らない別府に、<br><span class="text-[#ff91bd]">会いに行く。</span></h2><p class="mt-8 max-w-[31em] border-l-4 border-bam-orange pl-5 text-sm font-bold leading-8 text-white/90">町のどこかで生まれている表現へ。予定を少しだけ手放して、偶然が選んだ一日に乗ってみる。</p></div>
        </article>
        <article class="slider-slide relative flex h-full w-full flex-none items-center overflow-hidden bg-[#111] px-6 py-28 sm:px-12" aria-label="3 / 3" aria-hidden="true">
          <img class="absolute inset-0 h-full w-full object-cover object-center" src="<?= h($assetBase) ?>/images/bam-gallery.jpeg" alt="作品を鑑賞する人々がいる展示会場" loading="lazy" decoding="async">
          <div class="absolute inset-0 bg-[#35212b]/65 backdrop-blur-[1px]"></div>
          <div class="relative z-10 mx-auto w-full max-w-[820px] rounded-[2rem] border border-white/25 bg-white/90 px-6 py-10 text-center shadow-2xl backdrop-blur-xl sm:px-12 sm:py-14"><p class="mb-5 text-[10px] font-black tracking-[.24em] text-bam-pink sm:text-xs">TODAY'S DESTINY</p><p class="text-[clamp(1.1rem,5vw,1.75rem)] font-black text-bam-ink"><?= h($today->format('Y年n月j日')) ?></p><h2 class="mt-4 text-[clamp(3.3rem,14vw,7.5rem)] font-black leading-none tracking-[-.08em] text-bam-pink">今日の運命</h2><p class="mx-auto mt-7 max-w-[30em] text-sm font-bold leading-8">ボタンの先にあるのは、今日だけの一枚。<br>考えすぎず、まずは引いてみよう。</p><a href="#fortune" class="mt-9 inline-flex items-center gap-3 rounded-full bg-bam-pink px-8 py-5 text-base font-black text-white no-underline shadow-[0_12px_30px_rgba(233,104,160,.28)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_18px_36px_rgba(233,104,160,.36)] focus:outline-none focus:ring-4 focus:ring-pink-200 sm:text-lg">みくじを見る <span aria-hidden="true">↓</span></a></div>
        </article>
      </div>
      <div class="absolute bottom-7 left-1/2 z-20 flex -translate-x-1/2 items-center gap-3 rounded-full bg-white/90 px-4 py-2 shadow-sm backdrop-blur">
        <button class="slider-prev grid h-9 w-9 place-items-center rounded-full text-lg text-bam-ink transition hover:bg-stone-100 focus:outline-none focus:ring-2 focus:ring-bam-pink" type="button" aria-label="前のスライド">←</button>
        <div class="flex gap-2" role="tablist" aria-label="スライドを選択"><button class="slider-dot h-2.5 w-7 rounded-full bg-bam-pink transition-all" type="button" aria-label="スライド1" aria-selected="true"></button><button class="slider-dot h-2.5 w-2.5 rounded-full bg-stone-300 transition-all" type="button" aria-label="スライド2" aria-selected="false"></button><button class="slider-dot h-2.5 w-2.5 rounded-full bg-stone-300 transition-all" type="button" aria-label="スライド3" aria-selected="false"></button></div>
        <button class="slider-next grid h-9 w-9 place-items-center rounded-full text-lg text-bam-ink transition hover:bg-stone-100 focus:outline-none focus:ring-2 focus:ring-bam-pink" type="button" aria-label="次のスライド">→</button>
      </div>
    </section>
    <div id="fortune" class="!mx-auto !w-full !max-w-[820px] !px-4 !pt-12 sm:!px-7 sm:!pt-16">
    <div class="color-dots !mb-5" aria-hidden="true"><?php for ($i = 0; $i < 40; $i++): ?><span></span><?php endfor; ?></div>
    <?php if ($data['error'] !== ''): ?>
      <div class="notice relative before:!content-['おしらせ'] !rounded-2xl !border !border-pink-200 !bg-white !px-6 !pb-6 !pt-8 !font-bold !leading-8 !shadow-[0_12px_36px_rgba(94,67,77,.07)]"><?= h($data['error']) ?></div>
    <?php elseif (!$isFestivalPeriod): ?>
      <div class="notice relative before:!content-['おしらせ'] !rounded-2xl !border !border-pink-200 !bg-white !px-6 !pb-6 !pt-8 !font-bold !leading-8 !shadow-[0_12px_36px_rgba(94,67,77,.07)]">ランバムみくじは、2026年10月10日（土）〜11月29日（日）の会期中にお楽しみいただけます。</div>
    <?php elseif (!$initialProgram): ?>
      <div class="notice relative before:!content-['おしらせ'] !rounded-2xl !border !border-pink-200 !bg-white !px-6 !pb-6 !pt-8 !font-bold !leading-8 !shadow-[0_12px_36px_rgba(94,67,77,.07)]">本日のプログラムを準備中です。少し時間をおいて、もう一度のぞいてみてください。</div>
    <?php else: ?>
      <section class="!rounded-[28px] !border !border-stone-200 !bg-white !p-5 !text-center !shadow-[0_18px_50px_rgba(94,67,77,.1)] sm:!p-8" id="draw-panel">
        <p class="!m-0 !text-[10px] !font-black !tracking-[.2em] !text-bam-pink">TODAY'S BAM PROGRAM</p>
        <h2 class="!mb-2 !mt-3 !text-[clamp(2rem,9vw,3.6rem)] !font-black !leading-none !tracking-[-.06em] !text-bam-ink">今日の運命を<br>引いてみよう。</h2>
        <div class="gacha-stage !mt-7 !grid !grid-cols-[112px_1fr] !items-center !gap-4 !rounded-[22px] !bg-[#fff1d9] !p-4 !text-left sm:!grid-cols-[150px_1fr] sm:!p-6" id="initial-gacha-stage" aria-hidden="true">
          <svg class="gacha-machine !h-auto !w-full" viewBox="0 0 180 190" role="img" aria-label="ランバムみくじのガチャマシン"><g class="gacha-body"><path d="M28 50Q28 22 56 22h68q28 0 28 28v72H28Z" fill="#ec4d63" stroke="#4b4b47" stroke-width="7"/><circle cx="90" cy="68" r="39" fill="#fffaf5" stroke="#4b4b47" stroke-width="7"/><circle cx="70" cy="58" r="12" fill="#91c500"/><circle cx="103" cy="51" r="11" fill="#5eadd7"/><circle cx="105" cy="81" r="13" fill="#f39800"/><circle cx="73" cy="84" r="10" fill="#e968a0"/><path d="M42 121h96l12 47H30Z" fill="#fffaf5" stroke="#4b4b47" stroke-width="7"/><rect x="65" y="137" width="50" height="23" rx="11" fill="#4b4b47"/></g><g class="gacha-handle"><circle cx="146" cy="105" r="17" fill="#f39800" stroke="#4b4b47" stroke-width="7"/><path d="M146 105v-28" stroke="#4b4b47" stroke-width="9" stroke-linecap="round"/><circle cx="146" cy="72" r="9" fill="#fffaf5" stroke="#4b4b47" stroke-width="6"/></g><g class="gacha-capsule"><path d="M72 112a18 18 0 0 1 36 0v7H72Z" fill="#5eadd7" stroke="#4b4b47" stroke-width="5"/><path d="M72 119h36v7a18 18 0 0 1-36 0Z" fill="#e968a0" stroke="#4b4b47" stroke-width="5"/></g></svg>
          <div><p class="!m-0 !text-[10px] !font-black !tracking-[.16em] !text-bam-red">TURN THE HANDLE</p><p class="!mb-0 !mt-2 !text-lg !font-black !leading-7 !text-bam-ink sm:!text-2xl">何が出るかは、<br>まちにおまかせ。</p></div>
        </div>
        <button class="button !mt-5 !block !w-full !cursor-pointer !appearance-none !rounded-full !border-0 !bg-bam-pink !px-5 !py-4 !text-[clamp(1.25rem,6vw,1.7rem)] !font-black !text-white !shadow-none disabled:!cursor-wait disabled:!opacity-60" id="initial-draw-button" type="button"><span id="initial-draw-button-label">ガチャを回す！</span></button>
      </section>
      <div class="hidden" id="result-area">
      <div class="fate-strip !mb-5 !grid !grid-cols-[1fr_auto] !overflow-hidden !rounded-2xl !bg-[#f8e5ee] !text-bam-pink">
        <div class="fate-label !px-5 !py-4 !text-[clamp(1.5rem,7vw,2.35rem)] !font-black !tracking-[-.04em]">今日の運命</div>
        <div class="date !grid !min-w-[104px] !place-items-center !bg-bam-pink !px-4 !py-2 !text-center !text-xs !font-black !leading-5 !text-white"><?= h($today->format('n月j日')) ?><br>MON / DAY</div>
      </div>
      <section class="card !overflow-hidden !rounded-[24px] !border !border-stone-200 !bg-white !shadow-[0_18px_50px_rgba(94,67,77,.1)]" id="result-card" aria-live="polite" aria-atomic="true">
        <div class="card-head !flex !items-center !justify-between !gap-3 !bg-bam-lime !px-5 !py-3 !text-white"><p class="eyebrow !m-0 !text-xs !font-black !tracking-[.12em]">TODAY'S BAM PROGRAM</p><span class="serial !text-[10px] !font-black"># RANDOM</span></div>
        <div class="card-body !px-5 !pb-8 !pt-8 sm:!px-10 sm:!pt-10">
          <h2 class="!m-0 !max-w-[13em] !text-[clamp(2.25rem,10vw,4.25rem)] !font-black !leading-[1.12] !tracking-[-.06em] !text-bam-ink" id="program-title"><?= h($initialProgram['タイトル'] ?? '') ?></h2>
          <p class="catchcopy !mt-6 !rounded-2xl !border-0 !bg-orange-50 !px-4 !py-3 !text-[clamp(1rem,4vw,1.25rem)] !font-black !leading-7 !text-orange-600" id="program-catchcopy"><?= h($initialProgram['キャッチコピー'] ?? '') ?></p>
          <p class="description !mt-6 !text-[15px] !leading-8 !text-bam-ink" id="program-description"><?= h($initialProgram['紹介文'] ?? '') ?></p>
          <div class="info !mt-8 !border-t !border-dashed !border-stone-300 !text-sm !leading-6"><div class="!grid !grid-cols-[6.5rem_1fr] !gap-3 !border-b !border-dashed !border-stone-300 !py-3" id="program-date"><b>日時 / DATE</b><span><?= h(get_program_date_display($initialProgram)) ?> <?= h($initialProgram['開催時間'] ?? '') ?></span></div><div class="!grid !grid-cols-[6.5rem_1fr] !gap-3 !border-b !border-dashed !border-stone-300 !py-3" id="program-venue"><b>会場 / PLACE</b><span><?= h($initialProgram['会場名'] ?? '') ?></span></div><div class="!grid !grid-cols-[6.5rem_1fr] !gap-3 !border-b !border-dashed !border-stone-300 !py-3" id="program-fee"><b>料金 / FEE</b><span><?= h($initialProgram['料金'] ?? '') ?></span></div><div class="!grid !grid-cols-[6.5rem_1fr] !gap-3 !border-b !border-dashed !border-stone-300 !py-3" id="program-organizer"><b>企画者 / BY</b><span><?= h($initialProgram['企画者名'] ?? '') ?></span></div></div>
        </div>
        <div class="actions !px-5 !pb-8 sm:!px-10">
          <a class="button hidden !mt-4 !w-full !rounded-full !border-0 !bg-bam-sky !px-5 !py-4 !text-center !font-black !text-white !no-underline" id="program-link" href="#" target="_blank" rel="noopener">詳細を見る</a>
          <div class="gacha-stage !mt-8 !grid !grid-cols-[112px_1fr] !items-center !gap-4 !rounded-[22px] !bg-[#fff1d9] !p-4 sm:!grid-cols-[150px_1fr] sm:!p-6" id="result-gacha-stage" aria-hidden="true">
            <svg class="gacha-machine !h-auto !w-full" viewBox="0 0 180 190" role="img" aria-label="ランバムみくじのガチャマシン">
              <g class="gacha-body"><path d="M28 50Q28 22 56 22h68q28 0 28 28v72H28Z" fill="#ec4d63" stroke="#4b4b47" stroke-width="7"/><circle cx="90" cy="68" r="39" fill="#fffaf5" stroke="#4b4b47" stroke-width="7"/><circle cx="70" cy="58" r="12" fill="#91c500"/><circle cx="103" cy="51" r="11" fill="#5eadd7"/><circle cx="105" cy="81" r="13" fill="#f39800"/><circle cx="73" cy="84" r="10" fill="#e968a0"/><path d="M42 121h96l12 47H30Z" fill="#fffaf5" stroke="#4b4b47" stroke-width="7"/><rect x="65" y="137" width="50" height="23" rx="11" fill="#4b4b47"/></g>
              <g class="gacha-handle"><circle cx="146" cy="105" r="17" fill="#f39800" stroke="#4b4b47" stroke-width="7"/><path d="M146 105v-28" stroke="#4b4b47" stroke-width="9" stroke-linecap="round"/><circle cx="146" cy="72" r="9" fill="#fffaf5" stroke="#4b4b47" stroke-width="6"/></g>
              <g class="gacha-capsule"><path d="M72 112a18 18 0 0 1 36 0v7H72Z" fill="#5eadd7" stroke="#4b4b47" stroke-width="5"/><path d="M72 119h36v7a18 18 0 0 1-36 0Z" fill="#e968a0" stroke="#4b4b47" stroke-width="5"/></g>
            </svg>
            <div><p class="!m-0 !text-[10px] !font-black !tracking-[.16em] !text-bam-red">TURN THE HANDLE</p><p class="!mb-0 !mt-2 !text-lg !font-black !leading-7 !text-bam-ink sm:!text-2xl">何が出るかは、<br>まちにおまかせ。</p></div>
          </div>
          <button class="button !mt-4 !block !w-full !cursor-pointer !appearance-none !rounded-full !border-0 !bg-bam-pink !px-5 !py-4 !text-[clamp(1.25rem,6vw,1.7rem)] !font-black !text-white !shadow-none disabled:!cursor-wait disabled:!opacity-60" id="draw-again-button" type="button"><span id="draw-again-button-label">もう一度ひく！</span></button>
        </div>
      </section>
      </div>
    <?php endif; ?>
    <footer class="!mt-12 !border-t !border-dashed !border-stone-300 !pt-5 !text-xs !font-bold"><a class="!flex !items-center !justify-between !gap-4 !text-[clamp(1.15rem,5vw,1.6rem)] !font-black !text-bam-ink !no-underline after:!text-bam-pink" href="https://beppuartmonth.com/" target="_blank" rel="noopener">ベップ・アート・マンス 2026</a><p class="!mt-2">「つくろう会」がお届けする、今日のおすすめ。</p></footer>
    </div>
  </main>
  <script>
    (() => {
      const slider = document.getElementById('bam-slider');
      const track = document.getElementById('slider-track');
      const slides = [...slider.querySelectorAll('.slider-slide')];
      const dots = [...slider.querySelectorAll('.slider-dot')];
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      let current = 0;
      let timer = null;
      let touchStartX = 0;
      let touchDeltaX = 0;
      const animateSlide = slide => {
        if (reduceMotion || typeof gsap === 'undefined') return;
        const titleLines = slide.querySelectorAll('.slide-title-line');
        const collageItems = slide.querySelectorAll('.hero-collage-item');
        gsap.killTweensOf([titleLines, collageItems]);
        if (collageItems.length) gsap.fromTo(collageItems, { opacity:0, y:28, scale:.94 }, { opacity:1, y:0, scale:1, duration:.9, stagger:.09, ease:'power3.out', clearProps:'transform' });
        if (titleLines.length) gsap.fromTo(titleLines, { opacity:0, y:24 }, { opacity:1, y:0, duration:.75, stagger:.07, delay:.18, ease:'power3.out', clearProps:'transform' });
      };
      const showSlide = index => {
        current = (index + slides.length) % slides.length;
        track.style.transform = `translateX(-${current * 100}%)`;
        slides.forEach((slide, i) => { const active = i === current; slide.setAttribute('aria-hidden', active ? 'false' : 'true'); slide.inert = !active; });
        dots.forEach((dot, i) => {
          const active = i === current;
          dot.setAttribute('aria-selected', active ? 'true' : 'false');
          dot.classList.toggle('w-7', active); dot.classList.toggle('w-2.5', !active);
          dot.classList.toggle('bg-bam-pink', active); dot.classList.toggle('bg-stone-300', !active);
        });
        window.setTimeout(() => animateSlide(slides[current]), reduceMotion ? 0 : 360);
      };
      const stopAuto = () => { if (timer) window.clearInterval(timer); timer = null; };
      const startAuto = () => { if (!reduceMotion) { stopAuto(); timer = window.setInterval(() => showSlide(current + 1), 5000); } };
      slider.querySelector('.slider-prev').addEventListener('click', () => { showSlide(current - 1); startAuto(); });
      slider.querySelector('.slider-next').addEventListener('click', () => { showSlide(current + 1); startAuto(); });
      dots.forEach((dot, i) => dot.addEventListener('click', () => { showSlide(i); startAuto(); }));
      slider.addEventListener('mouseenter', stopAuto); slider.addEventListener('mouseleave', startAuto);
      slider.addEventListener('focusin', stopAuto); slider.addEventListener('focusout', startAuto);
      slider.addEventListener('touchstart', event => { touchStartX = event.touches[0].clientX; touchDeltaX = 0; stopAuto(); }, { passive:true });
      slider.addEventListener('touchmove', event => { touchDeltaX = event.touches[0].clientX - touchStartX; }, { passive:true });
      slider.addEventListener('touchend', () => { if (Math.abs(touchDeltaX) > 48) showSlide(current + (touchDeltaX < 0 ? 1 : -1)); startAuto(); });
      slider.addEventListener('keydown', event => { if (event.key === 'ArrowLeft') showSlide(current - 1); if (event.key === 'ArrowRight') showSlide(current + 1); });
      showSlide(0); startAuto();
    })();
  </script>
  <?php if ($initialProgram): ?>
  <script>
    const candidates = <?= json_encode($candidates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const byId = id => document.getElementById(id);
    const getDateText = program => {
      if (program['開催日']) return program['開催日'];
      if (program['整理番号用日付'] === '会期中ずっと' || program['表示日コード'] === '0000') return '10/10（土）〜11/29（日）';
      return '';
    };
    const setText = (id, label, value) => {
      const row = byId(id);
      row.querySelector('b').textContent = label;
      row.querySelector('span').textContent = value || '未定';
    };
    const draw = (isInitialDraw = false) => {
      const program = candidates[Math.floor(Math.random() * candidates.length)];
      const card = byId('result-card');
      const drawPanel = byId('draw-panel');
      const resultArea = byId('result-area');
      const button = byId(isInitialDraw ? 'initial-draw-button' : 'draw-again-button');
      const buttonLabel = byId(isInitialDraw ? 'initial-draw-button-label' : 'draw-again-button-label');
      const stage = byId(isInitialDraw ? 'initial-gacha-stage' : 'result-gacha-stage');
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      const wait = reduceMotion ? 0 : 1050;
      const updateCard = () => {
        byId('program-title').textContent = program['タイトル'] || '';
        byId('program-catchcopy').textContent = program['キャッチコピー'] || '';
        byId('program-description').textContent = program['紹介文'] || '';
        setText('program-date', '日時 / DATE', [getDateText(program), program['開催時間']].filter(Boolean).join(' '));
        setText('program-venue', '会場 / PLACE', program['会場名']); setText('program-fee', '料金 / FEE', program['料金']); setText('program-organizer', '企画者 / BY', program['企画者名']);
        const link = byId('program-link');
        if (program['URL']) { link.href = program['URL']; link.classList.remove('hidden'); } else { link.classList.add('hidden'); }
        card.classList.remove('is-changing');
        stage.classList.remove('is-running');
        button.disabled = false;
        buttonLabel.textContent = isInitialDraw ? 'ガチャを回す！' : 'もう一度ひく！';
        if (isInitialDraw) { drawPanel.classList.add('hidden'); resultArea.classList.remove('hidden'); }
        card.scrollIntoView({ behavior:reduceMotion ? 'auto' : 'smooth', block:'start' });
      };
      button.disabled = true;
      buttonLabel.textContent = 'ガチャガチャ…';
      card.classList.add('is-changing');
      stage.classList.remove('is-running');
      void stage.offsetWidth;
      if (!reduceMotion) stage.classList.add('is-running');
      window.setTimeout(updateCard, wait);
    };
    byId('initial-draw-button').addEventListener('click', () => draw(true));
    byId('draw-again-button').addEventListener('click', () => draw(false));
  </script>
  <?php endif; ?>
</body>
</html>
