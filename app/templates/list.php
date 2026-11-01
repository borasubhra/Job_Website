<?php /** @var array $meta @var array $result @var string $q @var string $location @var string $section */
$link = fn(int $p) => $meta['url'] . '?' . http_build_query(array_filter(['q' => $q, 'location' => $location, 'page' => $p > 1 ? $p : null]));
?>
<section class="hero">
  <div class="wrap">
    <h1><?= h($meta['title']) ?></h1>
    <p><?= h($meta['sub']) ?></p>
    <form method="get" action="<?= h($meta['url']) ?>">
      <input name="q" value="<?= h($q) ?>" placeholder="Post, department or keyword">
      <select name="location" onchange="this.form.submit()">
        <option value="">All locations</option>
        <?php foreach ($meta['locations'] as $l): ?>
          <option <?= $l === $location ? 'selected' : '' ?>><?= h($l) ?></option>
        <?php endforeach ?>
      </select>
      <button>Search</button>
    </form>
  </div>
</section>

<main class="wrap">
  <?php if ($result['total']): ?><p class="muted"><?= $result['total'] ?> jobs found</p><?php endif ?>
  <div class="list">
    <?php foreach ($result['items'] as $j): ?>
      <a class="card" href="/job/<?= $j['id'] ?>-<?= h(slug($j['title'])) ?>">
        <?php if ($j['image_url']): ?><img src="<?= h($j['image_url']) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif ?>
        <div>
          <h3><?= h($j['title']) ?></h3>
          <?php require __DIR__ . '/_tags.php' ?>
          <p><?= h($j['summary']) ?></p>
        </div>
      </a>
    <?php endforeach ?>
    <?php if (!$result['items']): ?><p class="muted">No jobs yet. New jobs are added automatically every day.</p><?php endif ?>
  </div>

  <?php if ($result['pages'] > 1): ?>
    <nav class="pager">
      <?php if ($result['page'] > 1): ?><a href="<?= h($link($result['page'] - 1)) ?>">← Prev</a><?php endif ?>
      <span class="muted">Page <?= $result['page'] ?> of <?= $result['pages'] ?></span>
      <?php if ($result['page'] < $result['pages']): ?><a href="<?= h($link($result['page'] + 1)) ?>">Next →</a><?php endif ?>
    </nav>
  <?php endif ?>
</main>
