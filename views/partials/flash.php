<?php if (!empty($flashSuccess)): ?>
    <div class="flash flash-success" role="status"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if (!empty($flashError)): ?>
    <div class="flash flash-error" role="alert"><?= e($flashError) ?></div>
<?php endif; ?>
