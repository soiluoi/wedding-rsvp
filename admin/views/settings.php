<?php
if (!defined('ABSPATH')) {
    exit;
}
$settings = Wedding_RSVP_Settings::get();
$updated = isset($_GET['updated']);
$permalinks = (string) get_option('permalink_structure');
?>
<div class="wrap wedding-admin">
    <h1>Cài đặt</h1>
    <?php if ($updated) : ?>
        <div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt.</p></div>
    <?php endif; ?>
    <?php if ($permalinks === '') : ?>
        <div class="notice notice-warning"><p>Đường dẫn tĩnh đang để kiểu mặc định. Link khách vẫn được plugin nhận, nhưng nên chọn một kiểu đường dẫn có tên bài viết rồi bấm Lưu.</p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wedding_rsvp_settings'); ?>
        <input type="hidden" name="action" value="wedding_rsvp_save_settings">
        <h2>Cô dâu, chú rể và tiệc</h2>
        <table class="form-table">
            <tr><th><label for="bride_name">Cô dâu</label></th><td><input class="regular-text" id="bride_name" name="bride_name" value="<?php echo esc_attr($settings['bride_name']); ?>"></td></tr>
            <tr><th><label for="groom_name">Chú rể</label></th><td><input class="regular-text" id="groom_name" name="groom_name" value="<?php echo esc_attr($settings['groom_name']); ?>"></td></tr>
            <tr><th><label for="wedding_date">Ngày giờ tiệc</label></th><td><input type="datetime-local" id="wedding_date" name="wedding_date" value="<?php echo esc_attr(Wedding_RSVP_Settings::datetime_local_value($settings['wedding_date'])); ?>"></td></tr>
            <tr><th><label for="venue_name">Tên địa điểm</label></th><td><input class="regular-text" id="venue_name" name="venue_name" value="<?php echo esc_attr($settings['venue_name']); ?>"></td></tr>
            <tr><th><label for="venue_address">Địa chỉ</label></th><td><input class="regular-text" id="venue_address" name="venue_address" value="<?php echo esc_attr($settings['venue_address']); ?>"></td></tr>
            <tr><th><label for="maps_url">Link Google Maps</label></th><td><input class="large-text" id="maps_url" name="maps_url" value="<?php echo esc_attr($settings['maps_url']); ?>" placeholder="https://maps.app.goo.gl/..."></td></tr>
            <tr><th><label for="deadline">Hạn xác nhận</label></th><td><input type="datetime-local" id="deadline" name="deadline" value="<?php echo esc_attr(Wedding_RSVP_Settings::datetime_local_value($settings['deadline'])); ?>"><p class="description">Sau hạn này khách chỉ xem, không sửa được.</p></td></tr>
            <tr><th><label for="contact_info">Liên hệ</label></th><td><textarea id="contact_info" name="contact_info" rows="3" class="large-text"><?php echo esc_textarea($settings['contact_info']); ?></textarea></td></tr>
            <tr><th><label for="og_image_url">Ảnh chia sẻ link</label></th><td><input class="large-text" id="og_image_url" name="og_image_url" value="<?php echo esc_attr($settings['og_image_url']); ?>" placeholder="https://"></td></tr>
        </table>
        <h2>Form và cách tính</h2>
        <table class="form-table">
            <tr><th><label for="opening_text">Lời ngỏ</label></th><td><textarea id="opening_text" name="opening_text" rows="4" class="large-text"><?php echo esc_textarea($settings['opening_text']); ?></textarea><p class="description">Hiện ngay sau câu chào trên form.</p></td></tr>
            <tr><th>Người đi cùng</th><td><p class="description">Mỗi khách khai tối đa 10 người đi cùng. Cột Số người trên sheet chỉ là số giả định, không giới hạn form.</p></td></tr>
            <tr><th><label for="people_per_table">Số người mỗi bàn</label></th><td><input type="number" min="1" id="people_per_table" name="people_per_table" value="<?php echo esc_attr((string) $settings['people_per_table']); ?>"></td></tr>
            <tr><th>Trẻ em ở bàn</th><td><label><input type="checkbox" name="child_counts_for_table" value="1" <?php checked($settings['child_counts_for_table']); ?>> Trẻ em tính một suất</label></td></tr>
            <tr><th><label for="seats_per_bus">Ghế khách mỗi xe</label></th><td><input type="number" min="1" id="seats_per_bus" name="seats_per_bus" value="<?php echo esc_attr((string) $settings['seats_per_bus']); ?>"><p class="description">Xe 45 chỗ, trừ tài xế và phụ xe, thường là 43.</p></td></tr>
            <tr><th>Trẻ em trên xe</th><td><label><input type="checkbox" name="child_takes_bus_seat" value="1" <?php checked($settings['child_takes_bus_seat']); ?>> Trẻ em chiếm một ghế</label></td></tr>
            <tr><th><label for="bus_notice_text">Thông báo về xe</label></th><td><textarea id="bus_notice_text" name="bus_notice_text" rows="5" class="large-text"><?php echo esc_textarea($settings['bus_notice_text']); ?></textarea><p class="description">Có thể bọc chữ bằng **đậm**.</p></td></tr>
        </table>
        <h2>Mẫu tin nhắn</h2>
        <p class="description">Chỗ trống: {danh_xung} {ten} {link} {co_dau} {chu_re} {ngay_cuoi} {dia_diem} {han_chot} {diem_don} {dia_chi_don} {gio_don}</p>
        <table class="form-table">
            <tr><th><label for="invite_message_template">Tin mời</label></th><td><textarea id="invite_message_template" name="invite_message_template" rows="5" class="large-text"><?php echo esc_textarea($settings['invite_message_template']); ?></textarea></td></tr>
            <tr><th><label for="pickup_message_template">Tin báo điểm đón</label></th><td><textarea id="pickup_message_template" name="pickup_message_template" rows="4" class="large-text"><?php echo esc_textarea($settings['pickup_message_template']); ?></textarea></td></tr>
        </table>
        <h2>Gỡ plugin</h2>
        <p><label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked($settings['delete_data_on_uninstall']); ?>> Xóa dữ liệu khi gỡ plugin</label></p>
        <p>
            <button type="submit" class="button button-primary">Lưu cài đặt</button>
            <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/wedding/xem-thu/')); ?>">Xem thử form</a>
        </p>
    </form>
</div>
