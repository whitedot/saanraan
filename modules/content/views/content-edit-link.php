<?php

$contentEditUrl = (string) ($contentEditUrl ?? '');
if ($contentEditUrl !== '') {
    ?>
    <a class="btn btn-sm btn-outline-default content-edit-link" href="<?php echo sr_e(sr_url($contentEditUrl)); ?>"><?php echo sr_e(sr_t('content::ui.edit.3537f0cc')); ?></a>
    <?php
}
