<?php
if (!defined('ABSPATH')) {
    exit;
}
$settings = Wedding_RSVP_Settings::get();
?>
<div class="wrap wedding-admin">
    <h1>Tổng quan</h1>
    <?php if (isset($_GET['place'])) : ?>
        <div class="notice notice-success is-dismissible"><p>Đã lưu địa điểm.</p></div>
    <?php endif; ?>
    <form class="wedding-card wedding-place" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wedding_rsvp_place'); ?>
        <input type="hidden" name="action" value="wedding_rsvp_save_place">
        <h2>Địa điểm</h2>
        <label>Tên địa điểm
            <input class="regular-text" name="venue_name" value="<?php echo esc_attr($settings['venue_name']); ?>">
        </label>
        <label>Địa chỉ
            <input class="regular-text" name="venue_address" value="<?php echo esc_attr($settings['venue_address']); ?>">
        </label>
        <label>Link Google Maps
            <input class="large-text" name="maps_url" value="<?php echo esc_attr($settings['maps_url']); ?>" placeholder="https://maps.app.goo.gl/...">
        </label>
        <p><button type="submit" class="button button-primary">Lưu địa điểm</button></p>
    </form>
    <div id="wedding-dashboard">Đang tải số liệu...</div>
</div>
