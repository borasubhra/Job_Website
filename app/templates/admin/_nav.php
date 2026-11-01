<div class="admin-bar">
  <strong>Admin</strong>
  <a href="/admin">Jobs</a>
  <a href="/admin/new">+ Add job</a>
  <a href="/admin/imports">Import</a>
  <a href="/" target="_blank">View site ↗</a>
  <form method="post" action="/admin/logout" class="inline">
    <input type="hidden" name="csrf" value="<?= h(App\Auth::csrf()) ?>">
    <button class="link">Log out</button>
  </form>
</div>
