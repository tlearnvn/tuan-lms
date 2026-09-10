<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
$primary = Settings::get('primary_color', '#6C5CE7');
$accent  = Settings::get('accent_color', '#FF7675');
$theme   = Auth::check() ? (Auth::user()['theme'] ?: 'light') : 'light';
$hex = function ($h) { $h = ltrim((string)$h, '#'); if (strlen($h) === 3) $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2]; return $h; };
$shade = function ($h, $pct) use ($hex) {
    $h = $hex($h);
    $r = hexdec(substr($h,0,2)); $g = hexdec(substr($h,2,2)); $b = hexdec(substr($h,4,2));
    $mix = function ($c) use ($pct) { return $pct < 0 ? max(0, (int)round($c * (1 + $pct))) : min(255, (int)round($c + (255 - $c) * $pct)); };
    return sprintf('#%02X%02X%02X', $mix($r), $mix($g), $mix($b));
};
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?= e($primary) ?>">
<meta name="description" content="<?= e(Settings::get('site_tagline', '')) ?>">
<title><?= e(full_title(isset($pageTitle) ? $pageTitle : '')) ?></title>
<link rel="icon" href="<?= e(favicon_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=<?= LMS_VERSION ?>">
<style>
:root{
  --primary: <?= e($primary) ?>;
  --primary-dark: <?= e($shade($primary, -0.18)) ?>;
  --primary-light: <?= e($shade($primary, 0.34)) ?>;
  --accent: <?= e($accent) ?>;
}
</style>
<script>
(function(){
  try {
    var t = localStorage.getItem('lms-theme') || <?= json_encode($theme) ?>;
    if (t === 'dark') document.documentElement.setAttribute('data-theme','dark');
  } catch(e) {}
})();
</script>
<?php if (Settings::bool('enable_math', true)):
    $dollar = Settings::bool('math_dollar', true); ?>
<script>
/* Cấu hình MathJax: hiển thị công thức LaTeX trong bài giảng, học liệu và bài tập */
window.MathJax = {
  tex: {
    inlineMath: <?= $dollar ? '[["$","$"],["\\\\(","\\\\)"]]' : '[["\\\\(","\\\\)"]]' ?>,
    displayMath: [["$$","$$"],["\\[","\\]"]],
    processEscapes: true,
    processEnvironments: true,
    tags: 'ams'
  },
  options: {
    skipHtmlTags: ['script','noscript','style','textarea','pre','code'],
    ignoreHtmlClass: 'no-math',
    processHtmlClass: 'has-math'
  },
  chtml: { scale: 1.02 },
  startup: {
    ready: function () {
      MathJax.startup.defaultReady();
      window.LMS_MATH_READY = true;
    }
  }
};
window.LMS_MATHJAX_URL = <?= json_encode(Settings::get('mathjax_url', 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js')) ?>;
</script>
<script async id="MathJax-script" src="<?= e(Settings::get('mathjax_url', 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js')) ?>"></script>
<?php endif; ?>
