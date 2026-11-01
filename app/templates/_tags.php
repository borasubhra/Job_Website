<?php
/** @var array $j */
$d = fn($s) => $s ? date('j M Y', strtotime($s)) : '';
?>
<div class="tags">
  <?php if ($j['organisation']): ?><span class="tag"><?= h($j['organisation']) ?></span><?php endif ?>
  <?php if ($j['location']): ?><span class="tag">📍 <?= h($j['location']) ?></span><?php endif ?>
  <?php if ($j['salary_min'] || $j['salary_max']): ?><span class="tag"><?= inr($j['salary_min']) ?><?= $j['salary_max'] ? ' – ' . inr($j['salary_max']) : '' ?></span><?php endif ?>
  <?php if ($j['last_date']): ?><span class="tag deadline">Last date: <?= $d($j['last_date']) ?></span><?php endif ?>
  <span class="tag"><?= $d($j['published_at']) ?></span>
</div>
