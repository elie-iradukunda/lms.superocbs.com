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
$locked = $isSuperAdmin || !$canEdit;

// Only the abilities a permission's code really checks get a tick box; a
// permission nothing reads any more is left out altogether.
$grouped = [];
foreach ($permissions as $permission) {
    $permission['abilities'] = \Models\Permission::applicable((string) $permission['permission_key']);
    if ($permission['abilities'] !== []) {
        $grouped[$permission['permission_group']][] = $permission;
    }
}

// Nine permission groups are too many tabs to read at a glance, so they are
// gathered into the three areas of the business. A group added later that is
// not named here still shows, under "Other".
$areas = [
    'Operations' => ['icon' => 'truck', 'groups' => ['Overview', 'Fleet', 'Transport', 'Warehouse']],
    'Commercial and finance' => ['icon' => 'dollar-sign', 'groups' => ['Commercial', 'Finance', 'Accounting']],
    'Administration' => ['icon' => 'shield', 'groups' => ['Insights', 'Administration']],
];
$placed = array_merge(...array_column($areas, 'groups'));
$leftover = array_diff(array_keys($grouped), $placed);
if ($leftover !== []) {
    $areas['Other'] = ['icon' => 'grid', 'groups' => array_values($leftover)];
}
$groupIcons = [
    'Overview' => 'home', 'Fleet' => 'truck', 'Transport' => 'map-pin', 'Warehouse' => 'package',
    'Commercial' => 'briefcase', 'Finance' => 'credit-card', 'Accounting' => 'book',
    'Insights' => 'bar-chart-2', 'Administration' => 'shield',
];
$moduleCount = static fn (string $roleKey): int => count(array_filter($matrix[$roleKey] ?? [], static fn (array $a): bool => !empty($a['view'])));
?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Access control</p>
      <h2 class="lms-page-title"><i class="fe fe-shield mr-2"></i>Role permissions</h2>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('users') ?>"><i class="fe fe-users fe-12 mr-1"></i>Users</a>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-3 mb-3">
      <aside class="card shadow-sm lms-role-list">
        <header class="lms-role-list-head">
          <span>Roles</span>
        </header>
        <ul class="list-unstyled mb-0">
          <?php foreach ($roles as $role): ?>
            <?php
            $isCurrent = $role['role_key'] === $selected['role_key'];
            $users = (int) $role['users'];
            ?>
            <li class="lms-role-item<?= $isCurrent ? ' is-current' : '' ?>">
              <a class="lms-role-link" href="<?= url('permissions', ['role' => $role['role_key']]) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>>
                <span class="lms-role-name"><?= e($role['role_name']) ?></span>
                <small><?= $moduleCount((string) $role['role_key']) ?> modules &middot; <?= $users ?> user<?= $users === 1 ? '' : 's' ?></small>
              </a>
              <?php if ($canEdit): ?>
                <div class="dropdown">
                  <button type="button" class="lms-role-more" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Actions for <?= e($role['role_name']) ?>"><i class="fe fe-more-horizontal"></i></button>
                  <div class="dropdown-menu dropdown-menu-right">
                    <button type="button" class="dropdown-item" data-role-rename data-id="<?= (int) $role['id'] ?>" data-name="<?= e($role['role_name']) ?>"><i class="fe fe-edit-2 fe-12 mr-2"></i>Rename</button>
                  </div>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </aside>
    </div>

    <div class="col-lg-9">
      <form id="lms-permissions-form" method="post" action="<?= url('permissions') ?>" class="lms-perm">
        <?= csrf_field() ?>
        <input type="hidden" name="role_id" value="<?= (int) $selected['id'] ?>">

        <section class="card shadow-sm lms-section lms-form-tabs">
          <header class="lms-perm-head">
            <div>
              <h3>Feature permissions</h3>
              <p>Role: <?= e($selected['role_name']) ?></p>
            </div>
            <?php if (!$locked): ?>
              <button class="btn btn-primary" type="submit"><i class="fe fe-check fe-14 mr-1"></i>Save</button>
            <?php endif; ?>
          </header>

          <ul class="nav nav-tabs lms-tabs" role="tablist">
            <?php $index = 0; foreach ($areas as $areaName => $area): ?>
              <li class="nav-item">
                <a class="nav-link<?= $index === 0 ? ' active' : '' ?>" id="tab-area-<?= $index ?>" data-toggle="tab" href="#area-<?= $index ?>" role="tab" aria-controls="area-<?= $index ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                  <i class="fe fe-<?= e($area['icon']) ?> fe-14 mr-2"></i><?= e($areaName) ?>
                </a>
              </li>
            <?php $index++; endforeach; ?>
          </ul>

          <div class="tab-content">
            <?php $index = 0; foreach ($areas as $areaName => $area): ?>
              <div class="tab-pane fade<?= $index === 0 ? ' show active' : '' ?>" id="area-<?= $index ?>" role="tabpanel" aria-labelledby="tab-area-<?= $index ?>">
                <div class="card-body">
                  <div class="table-responsive lms-perm-frame">
                    <table class="table mb-0 lms-perm-table">
                      <thead>
                        <tr>
                          <th class="lms-perm-module">
                            <button type="button" class="lms-perm-toggle" data-perm-toggle title="Tick or clear everything on this tab"<?= $locked ? ' disabled' : '' ?>><i class="fe fe-square"></i></button>Module
                          </th>
                          <th class="lms-perm-function">
                            <button type="button" class="lms-perm-toggle" data-perm-toggle title="Tick or clear everything on this tab"<?= $locked ? ' disabled' : '' ?>><i class="fe fe-square"></i></button>Function
                          </th>
                          <th>Operation authorization</th>
                        </tr>
                      </thead>
                      <?php foreach ($area['groups'] as $groupName): ?>
                        <?php if (empty($grouped[$groupName])) { continue; } ?>
                        <tbody>
                          <?php foreach ($grouped[$groupName] as $row => $permission): ?>
                            <?php $key = $permission['permission_key']; ?>
                            <tr>
                              <?php if ($row === 0): ?>
                                <th scope="rowgroup" class="lms-perm-module" rowspan="<?= count($grouped[$groupName]) ?>">
                                  <button type="button" class="lms-perm-toggle" data-perm-toggle data-perm-target="tbody" title="Tick or clear all of <?= e($groupName) ?>"<?= $locked ? ' disabled' : '' ?>><i class="fe fe-square"></i></button>
                                  <i class="fe fe-<?= e($groupIcons[$groupName] ?? 'folder') ?> fe-14 mr-1 text-muted"></i><?= e($groupName) ?>
                                </th>
                              <?php endif; ?>
                              <td class="lms-perm-function">
                                <button type="button" class="lms-perm-toggle" data-perm-toggle data-perm-target="tr" title="Tick or clear all of <?= e($permission['permission_label']) ?>"<?= $locked ? ' disabled' : '' ?>><i class="fe fe-square"></i></button>
                                <span>
                                  <?= e($permission['permission_label']) ?>
                                  <small class="d-block text-muted"><?= e($key) ?></small>
                                </span>
                              </td>
                              <td>
                                <div class="lms-perm-ops">
                                  <?php foreach ($permission['abilities'] as $ability): ?>
                                    <?php $checked = !empty($grants[$key][$ability]); $id = 'g-' . $key . '-' . $ability; ?>
                                    <div class="custom-control custom-checkbox">
                                      <input type="checkbox"
                                             class="custom-control-input lms-grant"
                                             id="<?= e($id) ?>"
                                             name="grants[<?= e($key) ?>][<?= e($ability) ?>]"
                                             value="1"
                                             data-ability="<?= e($ability) ?>"
                                             <?= $checked ? 'checked' : '' ?>
                                             <?= $locked ? 'disabled' : '' ?>>
                                      <label class="custom-control-label" for="<?= e($id) ?>"><?= e(ucfirst($ability)) ?></label>
                                    </div>
                                  <?php endforeach; ?>
                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      <?php endforeach; ?>
                    </table>
                  </div>
                </div>
              </div>
            <?php $index++; endforeach; ?>
          </div>
        </section>

        <?php if (!$locked): ?>
          <p class="small text-muted mt-n2">Ticking create, edit, delete or approve also ticks view for that function. Saving keeps the ticks on every tab.</p>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<?php if ($canEdit): ?>
  <div class="modal fade" id="roleRenameModal" tabindex="-1" role="dialog" aria-labelledby="roleRenameTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <form class="modal-content" method="post" action="" data-action="<?= url(['permissions', 'roles', '__ID__', 'rename']) ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title" id="roleRenameTitle">Rename role</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group lms-field is-required mb-0">
            <label for="roleRenameName">Role name <span class="lms-required">*</span></label>
            <input class="form-control" type="text" id="roleRenameName" name="role_name" maxlength="100" required>
            <small class="form-text text-muted">Only the name shown on screen changes. Users keep this role and its permissions.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fe fe-check fe-12 mr-1"></i>Rename</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<script>
// The square beside a module or a function ticks everything under it, or clears
// it when it is all ticked already. Ticking any other ability ticks view, which
// the server would grant anyway, so the screen shows what will be saved.
(function () {
  var form = document.getElementById('lms-permissions-form');
  if (!form) { return; }

  function boxes(scope) { return Array.prototype.slice.call(scope.querySelectorAll('.lms-grant:not(:disabled)')); }
  function scopeOf(button) { return button.closest(button.getAttribute('data-perm-target') || '.lms-perm-frame'); }

  function paint() {
    Array.prototype.forEach.call(form.querySelectorAll('[data-perm-toggle]'), function (button) {
      var all = boxes(scopeOf(button));
      var on = all.filter(function (b) { return b.checked; }).length;
      button.querySelector('i').className = 'fe ' + (all.length && on === all.length ? 'fe-check-square' : (on ? 'fe-minus-square' : 'fe-square'));
    });
  }

  form.addEventListener('click', function (event) {
    var button = event.target.closest('[data-perm-toggle]');
    if (!button || button.disabled) { return; }
    var all = boxes(scopeOf(button));
    var turnOn = all.some(function (b) { return !b.checked; });
    all.forEach(function (b) { b.checked = turnOn; });
    paint();
  });

  form.addEventListener('change', function (event) {
    var box = event.target;
    if (!box.classList || !box.classList.contains('lms-grant')) { return; }
    if (box.checked && box.getAttribute('data-ability') !== 'view') {
      var view = box.closest('tr').querySelector('.lms-grant[data-ability="view"]');
      if (view) { view.checked = true; }
    }
    paint();
  });

  paint();
})();

// Rename on the role list. Waits for the page to load because the modal needs
// jQuery and Bootstrap, which the footer brings.
document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('roleRenameModal');
  if (!modal) { return; }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-role-rename]');
    if (!button) { return; }
    var renameForm = modal.querySelector('form');
    renameForm.action = renameForm.getAttribute('data-action').replace('__ID__', button.getAttribute('data-id'));
    document.getElementById('roleRenameName').value = button.getAttribute('data-name');
    $(modal).modal('show');
  });

  $(modal).on('shown.bs.modal', function () { $(this).find('input[name="role_name"]').trigger('focus').trigger('select'); });
});
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
