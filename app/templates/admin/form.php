<?php /** @var array $data @var array $errors @var ?int $id */
$err = fn($k) => isset($errors[$k]) ? '<span class="field-error">' . h($errors[$k]) . '</span>' : '';
?>
<main class="wrap narrow">
  <?php require __DIR__ . '/_nav.php' ?>
  <article class="detail">
    <h1><?= $id ? 'Edit job' : 'Add a job' ?></h1>
    <p class="muted small">Write the key facts in your own words. Don't paste the newspaper's text or images.
       Link to the official notification or apply page.</p>
    <?php if ($errors): ?><p class="alert">Please fix the highlighted fields.</p><?php endif ?>

    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?= h(App\Auth::csrf()) ?>">

      <label>Section
        <select name="section">
          <option value="northeast" <?= $data['section'] === 'northeast' ? 'selected' : '' ?>>North East Jobs</option>
          <option value="india" <?= $data['section'] === 'india' ? 'selected' : '' ?>>All India Jobs</option>
        </select>
      </label>

      <label>Job title * <?= $err('title') ?>
        <input name="title" value="<?= h($data['title']) ?>" maxlength="250" required
               placeholder="e.g. Junior Assistant Recruitment 2026 – 120 posts">
      </label>

      <label>Organisation / department * <?= $err('organisation') ?>
        <input name="organisation" value="<?= h($data['organisation']) ?>" maxlength="200" required placeholder="e.g. Directorate of Health Services, Assam">
      </label>

      <div class="row2">
        <label>Location <?= $err('location') ?>
          <input name="location" value="<?= h($data['location']) ?>" list="locations" maxlength="200" placeholder="e.g. Assam / Guwahati">
          <datalist id="locations"><?php foreach (App\Admin::LOCATIONS as $l): ?><option value="<?= h($l) ?>"><?php endforeach ?></datalist>
        </label>
        <label>Last date to apply <?= $err('last_date') ?>
          <input type="date" name="last_date" value="<?= h($data['last_date']) ?>">
        </label>
      </div>

      <label>Category <?= $err('category') ?>
        <input name="category" value="<?= h($data['category']) ?>" maxlength="120" placeholder="e.g. Government, Teaching, Health, Police">
      </label>

      <label>Official link (notification PDF or apply page) <?= $err('source_url') ?>
        <input type="url" name="source_url" value="<?= h($data['source_url']) ?>" placeholder="https://...  (leave empty if there is none)">
      </label>

      <label>Short description <?= $err('summary') ?>
        <textarea name="summary" rows="6" maxlength="1500" placeholder="Number of posts, qualification, age limit, pay, how to apply (email / phone / address)…"><?= h($data['summary']) ?></textarea>
      </label>

      <label class="check"><input type="checkbox" name="is_active" value="1" <?= $data['is_active'] ? 'checked' : '' ?>> Show on the website</label>

      <div><button><?= $id ? 'Save changes' : 'Publish job' ?></button> <a href="/admin" class="muted">Cancel</a></div>
    </form>
  </article>
</main>
