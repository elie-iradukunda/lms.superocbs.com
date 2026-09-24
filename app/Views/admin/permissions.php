<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$selectedKey = (string) ($_GET['role'] ?? 'logistics_manager');
$selected = null;
foreach ($roles as $role) {
    if ($role['role_key'] === $selectedKey) {
        $selected = $role;
        break;
    }
}
$selected ??= $roles[1] ?? $roles[0];
$grants = $matrix[$selected['role_key']] ?? [];
$isSuperAdmin = $selected['role_key'] === 'super_admin';

$grouped = [];
foreach ($permissions as $permission) {
    $grouped[$permission['permission_group']][] = $permission;
}
?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Access control</p>
      <h2 class="lms-page-title"><i class="fe fe-shield mr-2"></i>Role permissions</h2>
      <p class="lms-page-sub">What each role may see, create, edit, delete and approve. These are read on every request, so a change takes effect immediately.</p>
    </div>
    <div class="lms-page-actions"><a class="btn btn-outline-secondary" href="<?= url('users') ?>"><i class="fe fe-users fe-12 mr-1"></i>Users</a></div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body lms-role-tabs">
      <?php foreach ($roles as $role): ?>
        <a class="lms-role-tab<?= $role['role_key'] === $selected['role_key'] ? ' is-current' : '' ?>" href="<?= url('permissions', ['role' => $role['role_key']]) ?>">
          <?= e($role['role_name']) ?>
          <small><?= count(array_filter($matrix[$role['role_key']] ?? [])) ?> modules</small>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($isSuperAdmin): ?>
    <div class="alert alert-info"><i class="fe fe-info mr-2"></i>Super Admin always holds every permission and cannot be restricted, so that an installation can never lock itself out.</div>
  <?php endif; ?>

  <form method="post" action="<?= url('permissions') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="role_id" value="<?= (int) $selected['id'] ?>">

    <?php foreach ($grouped as $groupName => $groupPermissions): ?>
      <section class="card shadow-sm lms-section">
        <header class="lms-section-head"><div><h3><i class="fe fe-folder fe-16 mr-2"></i><?= e($groupName) ?></h3></div></header>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0 lms-permission-table">
              <thead>
                <tr>
                  <th>Module</th>
                  <?php foreach ($abilities as $ability): ?><th class="text-center"><?= e(ucfirst($ability)) ?></th><?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($groupPermissions as $permission): ?>
                  <?php $key = $permission['permission_key']; ?>
                  <tr>
                    <td><strong><?= e($permission['permission_label']) ?></strong><small class="d-block text-muted"><?= e($key) ?></small></td>
                    <?php foreach ($abilities as $ability): ?>
                      <?php $checked = !empty($grants[$key][$ability]); ?>
                      <td class="text-center">
                        <div class="custom-control custom-checkbox d-inline-block">
                          <input type="checkbox"
                                 class="custom-control-input lms-grant"
                                 id="g-<?= e($key . '-' . $ability) ?>"
                                 name="grants[<?= e($key) ?>][<?= e($ability) ?>]"
                                 value="1"
                                 data-permission="<?= e($key) ?>"
                                 data-ability="<?= e($ability) ?>"
                                 <?= $checked ? 'checked' : '' ?>
                                 <?= $isSuperAdmin || !$canEdit ? 'disabled' : '' ?>>
                          <label class="custom-control-label" for="g-<?= e($key . '-' . $ability) ?>"></label>
                        </div>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    <?php endforeach; ?>

    <?php if ($canEdit && !$isSuperAdmin): ?>
      <div class="lms-form-bar">
        <span class="small text-muted">Ticking create, edit, delete or approve automatically grants view for that module.</span>
        <button class="btn btn-primary" type="submit"><i class="fe fe-save fe-12 mr-1"></i>Save permissions for <?= e($selected['role_name']) ?></button>
      </div>
    <?php endif; ?>
  </form>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
