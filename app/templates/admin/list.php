<?php /** @var array $jobs @var string $show @var array $counts @var ?string $flash */
$tabs = ['manual' => 'Added by you', 'auto' => 'Fetched automatically', 'hidden' => 'Hidden / expired'];
$csrf = App\Auth::csrf();
?>
<main class="wrap">
  <?php require __DIR__ . '/_nav.php' ?>
  <?php if ($flash): ?><p class="notice"><?= h($flash) ?></p><?php endif ?>

  <nav class="subtabs">
    <?php foreach ($tabs as $k => $label): ?>
      <a href="/admin?show=<?= $k ?>" class="<?= $show === $k ? 'active' : '' ?>"><?= h($label) ?> (<?= (int) $counts[$k] ?>)</a>
    <?php endforeach ?>
  </nav>

  <?php if (!$jobs): ?>
    <p class="muted">No jobs here. <?php if ($show === 'manual'): ?><a href="/admin/new">Add your first job →</a><?php endif ?></p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="admin-table">
    <thead><tr><th>Job</th><th>Section</th><th>Last date</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($jobs as $j): ?>
      <tr>
        <td><a href="/job/<?= $j['id'] ?>" target="_blank"><?= h($j['title']) ?></a>
            <div class="muted small"><?= h($j['organisation']) ?><?= $j['location'] ? ' · ' . h($j['location']) : '' ?> · <?= h($j['source_name']) ?></div></td>
        <td><?= $j['section'] === 'india' ? 'All India' : 'North East' ?></td>
        <td><?= $j['last_date'] ? h(date('j M Y', strtotime($j['last_date']))) : '–' ?></td>
        <td><?= $j['is_active'] ? '<span class="tag">Live</span>' : '<span class="tag deadline">Hidden</span>' ?></td>
        <td class="actions">
          <?php if ($j['source_id'] === 'manual'): ?><a href="/admin/edit/<?= $j['id'] ?>">Edit</a><?php endif ?>
          <form method="post" action="/admin/toggle/<?= $j['id'] ?>" class="inline">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="back" value="/admin?show=<?= h($show) ?>">
            <button class="link"><?= $j['is_active'] ? 'Hide' : 'Show' ?></button>
          </form>
          <?php if ($j['source_id'] === 'manual'): ?>
          <form method="post" action="/admin/delete/<?= $j['id'] ?>" class="inline" onsubmit="return confirm('Delete this job permanently?')">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="back" value="/admin?show=<?= h($show) ?>">
            <button class="link danger">Delete</button>
          </form>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  </div>
  <?php endif ?>
</main>
