<?php /** @var string $content @var string $pageTitle @var string $section */ $site = env('SITE_NAME', 'JobPortal'); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($pageTitle) ?> – <?= h($site) ?></title>
<?php if ($section === 'admin'): ?><meta name="robots" content="noindex, nofollow"><?php endif ?>
<meta name="description" content="Latest jobs in Assam, North East India and across India – updated automatically every day.">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="top">
  <div class="wrap bar">
    <a class="logo" href="/"><?= h(substr($site, 0, 3)) ?><span><?= h(substr($site, 3)) ?></span></a>
    <nav class="tabs">
      <a href="/" class="<?= $section === 'northeast' ? 'active' : '' ?>">North East Jobs</a>
      <a href="/india-jobs" class="<?= $section === 'india' ? 'active' : '' ?>">All India Jobs</a>
    </nav>
  </div>
</header>
<?= $content ?>
<footer class="wrap muted small">
  Jobs are gathered automatically from public sources. Each post links to its original source.
  Always check the official notification before you apply.
  <?php if ($section === 'admin'): ?><br>Version <?= h(APP_VERSION) ?><?php endif ?>
</footer>
</body>
</html>
