<?php /** @var array $job */ $j = $job; ?>
<main class="wrap">
  <article class="detail">
    <h1><?= h($j['title']) ?></h1>
    <?php require __DIR__ . '/_tags.php' ?>
    <?php if ($j['image_url']): ?><img src="<?= h($j['image_url']) ?>" alt="" onerror="this.remove()"><?php endif ?>
    <p><?= h($j['summary']) ?></p>
    <a class="btn" href="<?= h($j['source_url']) ?>" target="_blank" rel="noopener nofollow">View full details &amp; apply →</a>
    <p class="muted small">Source: <?= h($j['source_name']) ?>. Always check the official notification before you apply.</p>
  </article>
</main>
