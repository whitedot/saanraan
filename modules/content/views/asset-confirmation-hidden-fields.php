<?php

if ($assetConfirmationId > 0) {
    ?>
    <input type="hidden" name="id" value="<?php echo sr_e((string) $assetConfirmationId); ?>">
    <?php
}
?>
<input type="hidden" name="asset_confirm" value="1">
<?php echo sr_content_asset_settlement_exchange_hidden_inputs_html($assetConfirmationExchangeSuggestion); ?>
<input type="hidden" name="asset_request_token" value="<?php echo sr_e($assetConfirmationRequestToken); ?>">
<?php if ($assetConfirmationContentId > 0) { ?>
    <input type="hidden" name="content_id" value="<?php echo sr_e((string) $assetConfirmationContentId); ?>">
<?php } ?>
