<?php
if (!defined('ABSPATH')) {
    exit;
}
$presets = Wedding_RSVP_Exporter::presets();
?>
<div class="wrap wedding-admin">
    <h1>Export CSV</h1>
    <p>File UTF-8 có BOM, dấu phẩy, để Excel mở đúng tiếng Việt. Các cột câu trả lời sẽ bị bỏ qua nếu bạn import lại file này.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wedding_rsvp_export'); ?>
        <input type="hidden" name="action" value="wedding_rsvp_export">
        <fieldset>
            <?php foreach ($presets as $id => $preset) : ?>
                <label class="wedding-block">
                    <input type="radio" name="preset" value="<?php echo esc_attr($id); ?>" <?php checked($id, 'full'); ?>>
                    <?php echo esc_html($preset['label']); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p><label><input type="checkbox" name="include_inactive" value="1"> Gồm cả khách đã ẩn</label></p>
        <p><button type="submit" class="button button-primary">Tải CSV</button></p>
    </form>
</div>
