<?php /** @var array $files @var ?array $results @var array $sources @var string $dir */
$csrf = App\Auth::csrf();
$pending = array_filter($files, fn($f) => $f['imported_at'] === null && !$f['error']);
?>
<main class="wrap">
  <?php require __DIR__ . '/_nav.php' ?>
  <article class="detail">
    <h1>Import jobs from files</h1>
    <p class="muted small">Job files (for example jobs taken from a newspaper) are in the project folder <code>app\imports</code>.
       New files arrive with <code>git pull</code> / <code>.\update.ps1</code>.</p>

    <?php if ($results !== null): ?>
      <?php if (!$results): ?><p class="notice">Nothing new to import.</p><?php endif ?>
      <?php foreach ($results as $r): ?>
        <p class="<?= $r['added'] || !$r['messages'] ? 'notice' : 'alert' ?>">
          <strong><?= h($r['file']) ?>:</strong> <?= $r['added'] ?> jobs added, <?= $r['skipped'] ?> skipped (already on the site).
          <?php foreach ($r['messages'] as $m): ?><br><?= h($m) ?><?php endforeach ?>
        </p>
      <?php endforeach ?>
    <?php endif ?>

    <?php if (!$files): ?>
      <p class="alert">No import files found in <code><?= h($dir) ?></code>.<br>
        Your copy of the project is out of date. In PowerShell run: <code>git pull</code>, then reload this page.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>File</th><th>Jobs in file</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($files as $f): ?>
          <tr>
            <td><?= h($f['file']) ?></td>
            <td><?= $f['error'] ? '<span class="field-error">' . h($f['error']) . '</span>' : (int) $f['jobs'] ?></td>
            <td><?= $f['imported_at'] ? 'Imported ' . h(date('j M Y, H:i', strtotime($f['imported_at']))) . ' (' . (int) $f['jobs_added'] . ' added)' : '<span class="tag deadline">Not imported yet</span>' ?></td>
            <td>
              <?php if (!$f['error']): ?>
              <form method="post" class="inline">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="file" value="<?= h($f['file']) ?>">
                <button class="link"><?= $f['imported_at'] ? 'Import again (adds missing jobs only)' : 'Import' ?></button>
              </form>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
      </div>
      <?php if ($pending): ?>
      <form method="post" style="margin-top:14px">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <button class="btn" style="border:none">Import all new files (<?= count($pending) ?>)</button>
      </form>
      <?php endif ?>
    <?php endif ?>

    <h2 style="font-size:18px;margin-top:28px">Jobs on the website now</h2>
    <div class="table-wrap">
    <table class="admin-table">
      <thead><tr><th>Section</th><th>Source</th><th>Live</th><th>Hidden</th></tr></thead>
      <tbody>
      <?php foreach ($sources as $s): ?>
        <tr><td><?= $s['section'] === 'india' ? 'All India' : 'North East' ?></td><td><?= h($s['source_name']) ?></td><td><?= (int) $s['live'] ?></td><td><?= (int) $s['hidden'] ?></td></tr>
      <?php endforeach ?>
      <?php if (!$sources): ?><tr><td colspan="4" class="muted">No jobs yet.</td></tr><?php endif ?>
      </tbody>
    </table>
    </div>
  </article>
</main>
