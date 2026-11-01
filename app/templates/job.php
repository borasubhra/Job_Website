<?php /** @var array $job */ $j = $job; ?>
<main class="wrap">
  <article class="detail">
    <h1><?= h($j['title']) ?></h1>
    <?php require __DIR__ . '/_tags.php' ?>
    <?php if ($j['image_url']): ?><img src="<?= h($j['image_url']) ?>" alt="" onerror="this.remove()"><?php endif ?>
    <?php if ($j['category']): ?><p class="muted small">Category: <?= h($j['category']) ?></p><?php endif ?>
    <p><?= nl2br(h($j['summary'])) ?></p>
    <?php $official = $j['source_id'] === 'manual' || preg_match('/\.pdf($|\?)/i', $j['source_url']); ?>
    <?php if ($j['source_url'] !== ''): ?>
    <a class="btn" href="<?= h($j['source_url']) ?>" target="_blank" rel="noopener nofollow"><?= $official ? 'View official notification / apply →' : 'View full details &amp; apply →' ?></a>
    <?php endif ?>
    <p class="muted small">Source: <?= h($j['source_name']) ?>. Never pay money to get a job. Always check the official notification before you apply.</p>
  </article>
</main>
