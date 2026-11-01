<?php /** @var ?string $error */ ?>
<main class="wrap narrow">
  <article class="detail">
    <h1>Admin login</h1>
    <?php if ($error): ?><p class="alert"><?= h($error) ?></p><?php endif ?>
    <?php if (!App\Auth::setupProblem()): ?>
    <form method="post" action="/admin/login" class="stack">
      <label>Username <input name="user" autocomplete="username" required autofocus></label>
      <label>Password <input name="password" type="password" autocomplete="current-password" required></label>
      <button>Log in</button>
    </form>
    <?php endif ?>
  </article>
</main>
