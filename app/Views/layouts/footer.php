    <?php /* The company using the system is named all over its own pages; the
             footer credits whoever built it, which is not the same company. */ ?>
    <footer class="lms-footer">Powered by <?= e(vendor_name()) ?> &copy; <?= date('Y') ?></footer>
  </main>
</div>
<script src="<?= $baseUrl ?>/assets/js/jquery.min.js"></script>
<script src="<?= $baseUrl ?>/assets/js/popper.min.js"></script>
<script src="<?= $baseUrl ?>/assets/js/moment.min.js"></script>
<script src="<?= $baseUrl ?>/assets/js/bootstrap.min.js"></script>
<script src="<?= $baseUrl ?>/assets/js/simplebar.min.js"></script>
<script src="<?= $baseUrl ?>/assets/js/tinycolor-min.js"></script>
<script src="<?= $baseUrl ?>/assets/js/config.js"></script>
<script src="<?= $baseUrl ?>/assets/js/apps.js"></script>
<script src="<?= $baseUrl ?>/assets/js/select2.min.js"></script>
<?php foreach (($pageScripts ?? []) as $pageScript): ?><script src="<?= $baseUrl . e($pageScript) ?>"></script>
<?php endforeach; ?>

<div class="modal fade" id="statusConfirmModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Change status</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
  <div class="modal-body"><p class="mb-0">Switch this record <strong class="status-target">on</strong>?</p></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="confirmStatusButton">Confirm</button></div>
</div></div></div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Delete record</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
  <div class="modal-body">
    <p>The record is removed from the lists but kept in the audit trail. Enter a reason to continue.</p>
    <label for="deleteReason">Reason</label>
    <textarea class="form-control" id="deleteReason" rows="3" required></textarea>
    <div class="invalid-feedback">A reason is required.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmDeleteButton">Delete record</button></div>
</div></div></div>

<div class="modal fade" id="reasonModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="reasonModalTitle">Reason</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
  <div class="modal-body">
    <p class="small text-muted">This reason is stored on the record and in the audit trail.</p>
    <label for="reasonText">Reason</label>
    <textarea class="form-control" id="reasonText" rows="3" required></textarea>
    <div class="invalid-feedback">A reason is required.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="confirmReasonButton">Confirm</button></div>
</div></div></div>

<script>
$(function () {
  // Searchable dropdowns everywhere a relation or a long option list is shown.
  $('.lms-select').each(function () {
    var $select = $(this);
    if ($select.find('option').length > 8) {
      $select.select2({theme: 'bootstrap4', width: '100%', placeholder: $select.find('option:first').text()});
    }
  });

  // File inputs show the chosen file name instead of staying blank.
  $(document).on('change', '.custom-file-input', function () {
    var name = this.files && this.files.length ? this.files[0].name : 'Choose file';
    $(this).next('.custom-file-label').text(name);
  });

  // Status switch: confirm before posting, and never leave the switch showing a
  // state the server has not accepted.
  var pendingStatusForm = null, pendingStatusValue = null;
  $(document).on('change', '.status-switch', function (event) {
    event.preventDefault();
    var $input = $(this);
    pendingStatusValue = $input.is(':checked') ? 1 : 0;
    $input.prop('checked', !$input.is(':checked'));
    pendingStatusForm = $input.closest('form');
    $('.status-target').text(pendingStatusValue ? 'on' : 'off');
    $('#statusConfirmModal').modal('show');
  });
  $('#confirmStatusButton').on('click', function () {
    if (!pendingStatusForm) { return; }
    pendingStatusForm.find('input[name=status]').val(pendingStatusValue);
    pendingStatusForm[0].submit();
  });
  $('#statusConfirmModal').on('hidden.bs.modal', function () { pendingStatusForm = null; pendingStatusValue = null; });

  // Delete always asks for a reason.
  var pendingDeleteForm = null;
  $(document).on('submit', '.delete-form', function (event) {
    event.preventDefault();
    pendingDeleteForm = this;
    $('#deleteReason').val('').removeClass('is-invalid');
    $('#deleteConfirmModal').modal('show');
  });
  $('#confirmDeleteButton').on('click', function () {
    var reason = $.trim($('#deleteReason').val());
    if (!reason) { $('#deleteReason').addClass('is-invalid'); return; }
    $(pendingDeleteForm).find('input[name=reason]').val(reason);
    pendingDeleteForm.submit();
  });

  // Workflow actions that need a reason (reject, cancel, record failure).
  var pendingReasonForm = null;
  $(document).on('submit', '.reason-form', function (event) {
    event.preventDefault();
    pendingReasonForm = this;
    $('#reasonModalTitle').text($(this).data('reason-title') || 'Reason');
    $('#reasonText').val('').removeClass('is-invalid');
    $('#reasonModal').modal('show');
  });
  $('#confirmReasonButton').on('click', function () {
    var reason = $.trim($('#reasonText').val());
    if (!reason) { $('#reasonText').addClass('is-invalid'); return; }
    $(pendingReasonForm).find('input[name=reason]').val(reason);
    pendingReasonForm.submit();
  });

  // Line item tables: add a row, clear a row, keep the running total honest.
  $(document).on('click', '.lms-line-add', function () {
    var $form = $(this).closest('.lms-lines');
    var $body = $form.find('tbody');
    var index = parseInt($form.attr('data-next-index'), 10) || $body.find('tr').length;
    var $row = $body.find('tr').last().clone();
    $row.find('input, select').each(function () {
      var name = $(this).attr('name');
      if (name) { $(this).attr('name', name.replace(/lines\[\d+\]/, 'lines[' + index + ']')); }
      if ($(this).is('input')) { $(this).val(''); }
    });
    $row.find('.lms-line-total').text('');
    $body.append($row);
    $form.attr('data-next-index', index + 1);
  });

  $(document).on('click', '.lms-line-clear', function () {
    var $row = $(this).closest('tr');
    $row.find('input').val('');
    $row.find('.lms-line-total').text('');
  });

  $(document).on('input', '.lms-line-number', function () {
    var $row = $(this).closest('tr');
    var numbers = $row.find('.lms-line-number').map(function () { return parseFloat(this.value) || 0; }).get();
    if (numbers.length >= 2) {
      var total = numbers[0] * numbers[numbers.length - 1];
      $row.find('.lms-line-total').text(total ? total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '');
    }
  });

  // A drop-down that hangs off another one only offers what belongs to the
  // parent already chosen: the shipments on this trip, not every shipment in
  // the company. Picking a different parent clears a choice that no longer fits.
  function lmsNarrow($select) {
    var all = $select.data('lmsOptions');
    if (!all) {
      all = $select.find('option').toArray();
      $select.data('lmsOptions', all);
    }

    var parent = $('#' + $select.attr('data-depends-on')).val() || '';
    var current = $select.val();
    var keptCurrent = false;

    $select.empty();
    $.each(all, function (i, option) {
      var $option = $(option);
      var owner = $option.attr('data-parent');
      if (owner === undefined || parent === '' || owner === parent) {
        $select.append($option);
        if ($option.attr('value') === current) { keptCurrent = true; }
      }
    });

    $select.val(keptCurrent ? current : '');
    if ($select.hasClass('select2-hidden-accessible')) { $select.trigger('change.select2'); }
  }

  function lmsFill($select) {
    var answers = $select.find('option:selected').attr('data-fills');
    answers = answers ? JSON.parse(answers) : {};

    $.each(answers, function (target, answer) {
      var $target = $('#f_' + target);
      if (!$target.length) { return; }

      var typed = $target.val();
      var mine = $target.attr('data-filled-by') === $select.attr('id');
      if (typed !== '' && typed !== undefined && !mine) { return; }

      $target.val(answer).attr('data-filled-by', answer === '' ? null : $select.attr('id'));
      if ($target.is('select') && $target.hasClass('select2-hidden-accessible')) { $target.trigger('change.select2'); }
    });
  }

  $('select[data-depends-on]').each(function () {
    var $select = $(this);
    lmsNarrow($select);
    $('#' + $select.attr('data-depends-on')).on('change', function () { lmsNarrow($select); lmsFill($select); });
  });

  $('select').filter(function () { return $(this).find('option[data-fills]').length > 0; }).each(function () {
    var $select = $(this);
    lmsFill($select);
    $select.on('change', function () { lmsFill($select); });
  });

  // Anything the user edits by hand is theirs from then on.
  $(document).on('input', '[data-filled-by]', function () { $(this).removeAttr('data-filled-by'); });
  // Choosing a photo shows it straight away, so nobody saves and then wonders
  // whether the right file went up.
  $('#avatar').on('change', function () {
    var file = this.files && this.files[0];
    if (!file) { return; }

    $('#photoChosen').text(file.name);

    var reader = new FileReader();
    reader.onload = function (event) {
      $('#photoInitials').addClass('d-none');
      $('#photoPreview').attr('src', event.target.result).removeClass('d-none');
    };
    reader.readAsDataURL(file);
  });

  // Percentage cells in reports get a subtle bar so a column can be read at a glance.
  $('.lms-pct').each(function () {
    var value = parseFloat($(this).attr('data-value'));
    if (!isNaN(value)) {
      $(this).css('background', 'linear-gradient(to right, rgba(94,114,228,0.16) ' + Math.max(0, Math.min(100, value)) + '%, transparent ' + Math.max(0, Math.min(100, value)) + '%)');
    }
  });

  // Dismiss flash messages on their own after a while.
  setTimeout(function () { $('.lms-flash').not('.alert-danger').alert('close'); }, 6000);
});
</script>

<script>
(function () {
  var box = document.querySelector('.lms-search');
  if (!box) { return; }
  var input = box.querySelector('input'), list = box.querySelector('.lms-search-results');
  var pages = JSON.parse(box.getAttribute('data-pages') || '[]'), shown = [], active = -1;
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
  function paint() {
    list.innerHTML = shown.length ? shown.map(function (p, i) {
      return '<li><a class="lms-search-item' + (i === active ? ' active' : '') + '" href="' + esc(p.url) + '"><strong>' + esc(p.label) + '</strong><small>' + esc(p.group) + '</small></a></li>';
    }).join('') : '<li class="lms-search-empty">No matching pages</li>';
    list.hidden = false;
  }
  function search() {
    var q = input.value.trim().toLowerCase();
    shown = pages.filter(function (p) { return !q || (p.label + ' ' + p.group + ' ' + p.keywords).toLowerCase().indexOf(q) !== -1; });
    active = q && shown.length ? 0 : -1;
    paint();
  }
  input.addEventListener('focus', search);
  input.addEventListener('input', search);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (!shown.length) { return; }
      active = (active + (e.key === 'ArrowDown' ? 1 : -1) + shown.length) % shown.length;
      paint();
      var el = list.querySelector('.active'); if (el) { el.scrollIntoView({block: 'nearest'}); }
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (shown[active >= 0 ? active : 0]) { window.location.href = shown[active >= 0 ? active : 0].url; }
    } else if (e.key === 'Escape') {
      list.hidden = true; input.blur();
    }
  });
  document.addEventListener('click', function (e) { if (!box.contains(e.target)) { list.hidden = true; } });
  document.addEventListener('keydown', function (e) {
    var tag = (e.target.tagName || '').toLowerCase();
    if (e.key === '/' && tag !== 'input' && tag !== 'textarea' && tag !== 'select' && !e.target.isContentEditable) { e.preventDefault(); input.focus(); }
  });
})();
</script>
</body>
</html>
