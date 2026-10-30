<?php

use App\Core\Session;

$success = Session::getFlash('success');
$error = Session::getFlash('error');

if (!is_string($success) && !is_string($error)) {
    return;
}

?>
<div class="container flash-stack" role="status" aria-live="polite">
    <?php if (is_string($success)) : ?>
        <p class="flash flash--ok"><?= e($success) ?></p>
    <?php endif; ?>
    <?php if (is_string($error)) : ?>
        <p class="flash flash--bad"><?= e($error) ?></p>
    <?php endif; ?>
</div>
