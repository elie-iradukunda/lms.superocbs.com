<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php $attachments = $attachments ?? []; $choices = $choices ?? null; $showCc = $showCc ?? false; ?>
<div class="container-fluid lms-form-page">

  <div class="lms-page-head">
    <div>
      <p class="lms-kicker"><?= e($kicker) ?></p>
      <h2 class="lms-page-title"><i class="fe fe-send mr-2"></i><?= e($pageHeading ?? 'Email to the customer') ?></h2>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= $backUrl ?>">Cancel</a>
      <button class="btn btn-primary" type="submit" form="lms-mail-form"><i class="fe fe-send fe-12 mr-1"></i>Send email</button>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger lms-error-block">
      <ul class="mb-0"><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form id="lms-mail-form" method="post" action="<?= e($formAction) ?>" class="lms-mail-compose" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <div class="form-group row mb-2">
          <label class="col-sm-2 col-form-label" for="mail-to">To</label>
          <div class="col-sm-10">
            <input class="form-control" id="mail-to" type="email" name="to" value="<?= e($to) ?>" required>
          </div>
        </div>
        <?php if ($showCc): ?>
        <div class="form-group row mb-2">
          <label class="col-sm-2 col-form-label" for="mail-cc">Cc</label>
          <div class="col-sm-10">
            <input class="form-control" id="mail-cc" type="text" name="cc" value="<?= e($cc ?? '') ?>" placeholder="Other people to copy, separated by commas">
          </div>
        </div>
        <?php endif; ?>
        <div class="form-group row mb-0">
          <label class="col-sm-2 col-form-label" for="mail-subject">Subject</label>
          <div class="col-sm-10">
            <input class="form-control" id="mail-subject" type="text" name="subject" maxlength="200" value="<?= e($subject) ?>" required>
          </div>
        </div>
      </div>
    </div>

    <div class="lms-mail-toolbar" role="toolbar" aria-label="Formatting">
      <button type="button" data-cmd="bold" title="Bold"><strong>B</strong></button>
      <button type="button" data-cmd="italic" title="Italic"><em>I</em></button>
      <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
      <span class="lms-mail-sep"></span>
      <button type="button" data-cmd="formatBlock" data-value="p" title="Normal text">Text</button>
      <button type="button" data-cmd="formatBlock" data-value="h3" title="Heading">Heading</button>
      <span class="lms-mail-sep"></span>
      <button type="button" data-cmd="insertUnorderedList" title="Bulleted list"><i class="fe fe-list"></i></button>
      <button type="button" data-cmd="insertOrderedList" title="Numbered list">1.</button>
      <button type="button" data-cmd="createLink" title="Link"><i class="fe fe-link"></i></button>
      <button type="button" data-cmd="removeFormat" title="Clear formatting"><i class="fe fe-x"></i></button>
      <span class="lms-mail-sep"></span>
      <button type="button" data-cmd="undo" title="Undo"><i class="fe fe-corner-up-left"></i></button>
      <button type="button" data-cmd="redo" title="Redo"><i class="fe fe-corner-up-right"></i></button>
      <button type="button" data-reset title="Put back the letter the system wrote" class="ml-auto">Start again</button>
    </div>

    <?php // The email as the customer will see it: the bar and the footer are fixed, the letter is yours to change. ?>
    <div class="lms-mail-frame">
      <div class="lms-mail-brand" style="background: <?= e($colour) ?>">
        <strong><?= e($company) ?></strong>
        <span>Logistics Management System</span>
      </div>
      <div class="lms-mail-body">
        <h1><?= e($letter['heading']) ?></h1>
        <div id="lms-mail-editor" class="lms-mail-editor" contenteditable="true" spellcheck="true"><?= $body ?></div>
      </div>
      <?php if ($attachments !== []): ?>
        <div class="lms-mail-attachments">
          <?php foreach ($attachments as $file): ?>
            <a class="lms-mail-attachment" href="<?= e($file['url']) ?>" target="_blank" rel="noopener" title="Open to check it">
              <i class="fe fe-paperclip"></i><span><?= e($file['filename']) ?></span><small>PDF · attached</small>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="lms-mail-foot"><?= e($letter['signature']) ?></div>
    </div>

    <?php if ($choices !== null): ?>
      <?php // The papers already on record, to tick, and a file from the computer if the agent needs something else. ?>
      <section class="card shadow-sm lms-mail-choices">
        <div class="card-body">
          <h3 class="h6 mb-3"><i class="fe fe-paperclip mr-1"></i>Documents to attach</h3>
          <?php if ($choices === []): ?>
            <p class="text-muted small mb-2">No scanned papers on record yet for this load. Attach them on the shipment, the driver or the truck, or add a file below.</p>
          <?php endif; ?>
          <?php $group = null; ?>
          <?php foreach ($choices as $doc): ?>
            <?php if ($doc['group'] !== $group): $group = $doc['group']; ?><div class="lms-mail-choice-group"><?= e($group) ?></div><?php endif; ?>
            <div class="custom-control custom-checkbox lms-mail-choice">
              <input type="checkbox" class="custom-control-input" id="attach-<?= e(str_replace(':', '-', $doc['key'])) ?>" name="attach[]" value="<?= e($doc['key']) ?>"<?= $doc['ticked'] ? ' checked' : '' ?>>
              <label class="custom-control-label" for="attach-<?= e(str_replace(':', '-', $doc['key'])) ?>"><?= e($doc['label']) ?></label>
              <a href="<?= e(url(array_merge(['files'], explode('/', $doc['path'])))) ?>" target="_blank" rel="noopener" class="small ml-2">open</a>
            </div>
          <?php endforeach; ?>
          <div class="form-group mt-3 mb-0">
            <label for="mail-extra" class="small font-weight-bold">Add a file from your computer</label>
            <input type="file" class="form-control-file" id="mail-extra" name="extra_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
          </div>
        </div>
      </section>
    <?php endif; ?>
    <textarea name="body" id="lms-mail-body" hidden></textarea>
    <template id="lms-mail-original"><?= $original ?></template>

    <div class="lms-form-bar">
      <span class="small text-muted"><?= e($footNote ?? '') ?></span>
      <div>
        <a class="btn btn-light" href="<?= $backUrl ?>">Cancel</a>
        <button class="btn btn-primary" type="submit"><i class="fe fe-send fe-12 mr-1"></i>Send email</button>
      </div>
    </div>
  </form>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var editor = document.getElementById('lms-mail-editor');
    var form = document.getElementById('lms-mail-form');
    var field = document.getElementById('lms-mail-body');
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

    document.querySelectorAll('.lms-mail-toolbar [data-cmd]').forEach(function (button) {
      // mousedown keeps the selection in the letter while the button is pressed.
      button.addEventListener('mousedown', function (event) { event.preventDefault(); });
      button.addEventListener('click', function () {
        var cmd = button.getAttribute('data-cmd');
        var value = button.getAttribute('data-value');
        if (cmd === 'createLink') {
          value = window.prompt('Link address (https://…)', 'https://');
          if (!value || value === 'https://') { return; }
        }
        editor.focus();
        document.execCommand(cmd, false, value ? (cmd === 'formatBlock' ? '<' + value + '>' : value) : null);
      });
    });

    var reset = document.querySelector('.lms-mail-toolbar [data-reset]');
    reset.addEventListener('click', function () {
      if (window.confirm('Put back the letter the system wrote? Your changes will be lost.')) {
        editor.innerHTML = document.getElementById('lms-mail-original').innerHTML;
      }
    });

    // Pasted text comes in as plain text, so a copy from Word or a web page
    // does not bring its fonts and colours into the email.
    editor.addEventListener('paste', function (event) {
      var text = (event.clipboardData || window.clipboardData).getData('text/plain');
      if (text) {
        event.preventDefault();
        document.execCommand('insertText', false, text);
      }
    });

    var sending = false;
    form.addEventListener('submit', function (event) {
      if (sending) { event.preventDefault(); return; }
      field.value = editor.innerHTML;
      if (editor.textContent.trim() === '') {
        event.preventDefault();
        window.alert('The email is empty.');
        return;
      }
      sending = true;
      form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
      document.querySelectorAll('button[form="lms-mail-form"]').forEach(function (b) { b.disabled = true; });
    });
  });
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
