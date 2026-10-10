<?php
if (!defined('ABSPATH')) {
    exit;
}
$view = Wedding_RSVP_Frontend::view();
$settings = $view['settings'];
$guest = $view['guest'];
$response = $view['response'];
$coming = $response && ($response['attending'] === 'yes' || $response['attending'] === 'maybe');
$ride = ($response && $response['ride_bus'] !== null) ? (int) $response['ride_bus'] : null;
$show_bus = $coming && $ride === 1;
$companion_on = $response ? (int) $response['has_companion'] === 1 : ($guest && (int) $guest['invited_count'] === 2);
$show_plus = $coming && $view['allow_plus_one'] && $companion_on;
$show_child = $show_plus && $response && $response['companion_type'] === 'child';
$show_summary = $response && !$view['preview'];
$show_form = !$view['error'] && !$view['locked'] && !$show_summary;
if ($view['preview']) {
    $show_form = true;
    $show_summary = false;
}
$payload = array(
    'preview' => (bool) $view['preview'],
    'locked' => (bool) $view['locked'],
    'allowPlusOne' => (bool) $view['allow_plus_one'],
    'restUrl' => $view['rest_url'],
    'nonce' => $view['nonce'],
    'contact' => $settings['contact_info'],
    'salutation' => $guest ? $guest['salutation'] : '',
    'response' => $response,
    'pickup' => $view['pickup'],
);
?><!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta property="og:title" content="<?php echo esc_attr($view['og_title']); ?>">
    <meta property="og:description" content="Mời bạn xác nhận tham dự.">
    <meta property="og:type" content="website">
    <?php if ($settings['og_image_url'] !== '') : ?>
        <meta property="og:image" content="<?php echo esc_url($settings['og_image_url']); ?>">
    <?php endif; ?>
    <title><?php echo esc_html($view['og_title']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(WEDDING_RSVP_URL . 'assets/css/form.css?ver=' . rawurlencode(wedding_rsvp_asset_version('assets/css/form.css'))); ?>">
</head>
<body>
<main class="invite">
    <p class="eyebrow">Thiệp mời</p>
    <?php if ($view['couple'] !== '') : ?>
        <h1><?php echo esc_html($view['couple']); ?></h1>
    <?php endif; ?>
    <div class="rule"></div>

    <?php if ($view['error']) : ?>
        <?php include WEDDING_RSVP_DIR . 'templates/rsvp-error.php'; ?>
    <?php else : ?>
        <?php if ($view['preview']) : ?>
            <p class="banner">Đây là bản xem thử. Câu trả lời sẽ không được lưu.<?php echo $view['settings'] && Wedding_RSVP_Settings::is_past_deadline($settings) ? ' Hạn chốt đang ở quá khứ, khách thật sẽ không sửa được.' : ''; ?></p>
        <?php endif; ?>
        <p class="hello">Chào <strong><?php echo esc_html(trim($guest['salutation'] . ' ' . $guest['name'])); ?></strong>!</p>
        <?php if ($view['when'] !== '' || $view['place'] !== '') : ?>
            <ul class="facts">
                <?php if ($view['when'] !== '') : ?><li><span>Khi nào</span><?php echo esc_html($view['when']); ?></li><?php endif; ?>
                <?php if ($view['place'] !== '') : ?><li><span>Ở đâu</span><?php echo esc_html($view['place']); ?></li><?php endif; ?>
                <?php if ($view['deadline'] !== '' && !$view['locked']) : ?><li><span>Hạn xác nhận</span><?php echo esc_html($view['deadline']); ?></li><?php endif; ?>
            </ul>
        <?php endif; ?>

        <section id="summary" class="summary" <?php echo $show_summary ? '' : 'hidden'; ?>>
            <?php if ($show_summary) : ?>
                <h2>Cảm ơn <?php echo esc_html($guest['salutation']); ?> đã phản hồi</h2>
                <ul class="facts">
                    <li><span>Tham dự</span><?php echo esc_html(Wedding_RSVP_Templates::attending_label($response['attending'])); ?></li>
                    <?php if ($response['attending'] !== 'no') : ?>
                        <li><span>Đi lại</span><?php echo esc_html(Wedding_RSVP_Templates::ride_label($response['ride_bus'])); ?></li>
                        <?php if ((int) $response['ride_bus'] === 1 && !empty($response['phone'])) : ?>
                            <li><span>Điện thoại</span><?php echo esc_html(Wedding_RSVP_Exporter::format_phone($response['phone'])); ?></li>
                        <?php endif; ?>
                        <li><span>Người đi cùng</span><?php echo esc_html((int) $response['has_companion'] === 1 ? Wedding_RSVP_Templates::companion_label($response) : 'Không'); ?></li>
                        <?php if (!empty($response['diet_note'])) : ?><li><span>Ăn uống</span><?php echo esc_html($response['diet_note']); ?></li><?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($response['message'])) : ?><li><span>Lời chúc</span><?php echo esc_html($response['message']); ?></li><?php endif; ?>
                </ul>
                <?php if ($view['pickup']) : ?>
                    <div class="pickup">
                        <strong>Điểm đón của bạn</strong>
                        <p><?php echo esc_html($view['pickup']['name']); ?><?php echo $view['pickup']['address'] !== '' ? ' — ' . esc_html($view['pickup']['address']) : ''; ?></p>
                        <?php if ($view['pickup']['pickup_time'] !== '') : ?><p>Giờ đón: <?php echo esc_html($view['pickup']['pickup_time']); ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>
                <p class="meta">Cập nhật lần cuối: <?php echo esc_html(Wedding_RSVP_Settings::format_datetime($response['updated_at'])); ?></p>
                <?php if (!$view['locked']) : ?>
                    <button type="button" class="ghost" id="edit-response">Sửa câu trả lời</button>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if ($view['locked'] && !$response) : ?>
            <p class="locked">Đã hết hạn xác nhận<?php echo $settings['contact_info'] !== '' ? ', bạn nhắn ' . esc_html($settings['contact_info']) . ' nhé' : ''; ?>.</p>
        <?php endif; ?>
        <?php if ($view['locked'] && $response) : ?>
            <p class="locked">Đã hết hạn chỉnh sửa<?php echo $settings['contact_info'] !== '' ? ', cần thay đổi bạn nhắn ' . esc_html($settings['contact_info']) . ' nhé' : ''; ?>.</p>
        <?php endif; ?>

        <?php if (!$view['locked'] || $view['preview']) : ?>
            <form id="rsvp-form" <?php echo $show_form ? '' : 'hidden'; ?> novalidate>
                <div class="wedding-hp" aria-hidden="true">
                    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>
                <fieldset>
                    <legend>Bạn có tham dự được không?</legend>
                    <label class="choice"><input type="radio" name="attending" value="yes" <?php checked($response && $response['attending'] === 'yes'); ?>><span>Có, mình sẽ đến</span></label>
                    <label class="choice"><input type="radio" name="attending" value="maybe" <?php checked($response && $response['attending'] === 'maybe'); ?>><span>Chưa chắc</span></label>
                    <label class="choice"><input type="radio" name="attending" value="no" <?php checked($response && $response['attending'] === 'no'); ?>><span>Rất tiếc, mình không đến được</span></label>
                </fieldset>
                <div data-show="coming" <?php echo $coming ? '' : 'hidden'; ?>>
                    <fieldset>
                        <legend>Bạn sẽ đi xe đưa đón chung chứ?</legend>
                        <label class="choice"><input type="radio" name="ride_bus" value="1" <?php checked($ride === 1); ?>><span>Đi xe chung</span></label>
                        <label class="choice"><input type="radio" name="ride_bus" value="0" <?php checked($ride === 0); ?>><span>Mình tự đi</span></label>
                    </fieldset>
                    <div class="bus" data-show="bus" <?php echo $show_bus ? '' : 'hidden'; ?>>
                        <?php echo Wedding_RSVP_Templates::format_plain($settings['bus_notice_text']); ?>
                    </div>
                    <label class="field" data-show="bus" <?php echo $show_bus ? '' : 'hidden'; ?>>Số điện thoại
                        <input type="tel" name="phone" inputmode="tel" autocomplete="tel" value="<?php echo esc_attr($response && $response['phone'] ? Wedding_RSVP_Exporter::format_phone($response['phone']) : ''); ?>" placeholder="0901 234 567">
                    </label>
                    <?php if ($view['allow_plus_one']) : ?>
                        <label class="check"><input type="checkbox" name="has_companion" value="1" <?php checked($companion_on); ?>> Mình đi cùng 1 người</label>
                        <p class="hint">Người đi cùng sẽ đi chung xe với bạn.</p>
                        <div data-show="plus" <?php echo $show_plus ? '' : 'hidden'; ?>>
                            <label class="field">Tên người đi cùng
                                <input type="text" name="companion_name" value="<?php echo esc_attr($response && $response['companion_name'] ? $response['companion_name'] : ''); ?>">
                            </label>
                            <fieldset>
                                <legend>Người đi cùng là</legend>
                                <label class="choice"><input type="radio" name="companion_type" value="adult" <?php checked($response && $response['companion_type'] === 'adult'); ?>><span>Người lớn</span></label>
                                <label class="choice"><input type="radio" name="companion_type" value="child" <?php checked($response && $response['companion_type'] === 'child'); ?>><span>Trẻ em</span></label>
                            </fieldset>
                            <label class="field" data-show="child" <?php echo $show_child ? '' : 'hidden'; ?>>Bé bao nhiêu tuổi?
                                <input type="number" name="companion_age" min="0" max="15" inputmode="numeric" value="<?php echo esc_attr($response && $response['companion_age'] !== null ? (string) $response['companion_age'] : ''); ?>">
                            </label>
                        </div>
                    <?php endif; ?>
                    <label class="field">Ghi chú ăn uống <span class="optional">không bắt buộc</span>
                        <input type="text" name="diet_note" value="<?php echo esc_attr($response && $response['diet_note'] ? $response['diet_note'] : ''); ?>" placeholder="Ăn chay, dị ứng...">
                    </label>
                </div>
                <label class="field">Lời chúc gửi cô dâu chú rể <span class="optional">không bắt buộc</span>
                    <textarea name="message" rows="4"><?php echo esc_textarea($response && $response['message'] ? $response['message'] : ''); ?></textarea>
                </label>
                <p id="form-error" class="form-error" hidden></p>
                <button type="submit" class="send"><?php echo $response ? 'Cập nhật câu trả lời' : 'Gửi xác nhận'; ?></button>
            </form>
        <?php endif; ?>
        <?php if ($settings['contact_info'] !== '') : ?>
            <p class="contact"><?php echo nl2br(esc_html($settings['contact_info'])); ?></p>
        <?php endif; ?>
        <script type="application/json" id="wedding-rsvp-data"><?php echo wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script src="<?php echo esc_url(WEDDING_RSVP_URL . 'assets/js/form.js?ver=' . rawurlencode(wedding_rsvp_asset_version('assets/js/form.js'))); ?>"></script>
    <?php endif; ?>
</main>
</body>
</html>
