<?php
if (!defined('ABSPATH')) {
    exit;
}
$view = Wedding_RSVP_Frontend::view();
$settings = $view['settings'];
$guest = $view['guest'];
$response = $view['response'];
$coming = $response && $response['attending'] === 'yes';
$ride = ($response && $response['ride_bus'] !== null) ? (int) $response['ride_bus'] : null;
$show_bus = $coming && $ride === 1;
$companions = $response ? Wedding_RSVP_Response_Repo::companion_list($response) : array();
$companion_count = count($companions);
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
    'maxCompanions' => (int) $view['max_companions'],
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
    <meta property="og:description" content="Mời bạn điền form khảo sát.">
    <meta property="og:type" content="website">
    <?php if ($settings['og_image_url'] !== '') : ?>
        <meta property="og:image" content="<?php echo esc_url($settings['og_image_url']); ?>">
    <?php endif; ?>
    <title><?php echo esc_html($view['og_title']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(WEDDING_RSVP_URL . 'assets/css/form.css?ver=' . rawurlencode(wedding_rsvp_asset_version('assets/css/form.css'))); ?>">
</head>
<body>
<main class="invite">
    <p class="eyebrow">Form khảo sát</p>
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
        <?php if (trim((string) $settings['opening_text']) !== '') : ?>
            <p class="opening"><?php echo nl2br(esc_html($settings['opening_text'])); ?></p>
        <?php endif; ?>
        <?php if ($view['when'] !== '' || $view['place'] !== '' || $view['maps_url'] !== '') : ?>
            <ul class="facts">
                <?php if ($view['when'] !== '') : ?><li><span>Thời gian</span><?php echo esc_html($view['when']); ?></li><?php endif; ?>
                <?php if ($view['place'] !== '' || $view['maps_url'] !== '') : ?>
                    <li><span>Địa điểm</span><?php if ($view['maps_url'] !== '') : ?><a class="place-link" href="<?php echo esc_url($view['maps_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($view['place'] !== '' ? $view['place'] : 'Mở Google Maps'); ?></a><?php else : ?><?php echo esc_html($view['place']); ?><?php endif; ?></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
        <?php if ($view['remaining'] !== '' && !$view['locked']) : ?>
            <p class="deadline">Hạn xác nhận: <?php echo esc_html($view['remaining']); ?></p>
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
                        <li><span>Người đi cùng</span><?php echo esc_html($companions ? Wedding_RSVP_Templates::companion_label($response) : 'Không'); ?></li>
                        <?php if (!empty($response['diet_note'])) : ?><li><span>Ăn uống</span><?php echo nl2br(esc_html($response['diet_note'])); ?></li><?php endif; ?>
                    <?php endif; ?>
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
                    <legend>Bạn có tham dự được không? <span class="req" aria-hidden="true">*</span></legend>
                    <label class="choice"><input type="radio" name="attending" value="yes" <?php checked($response && $response['attending'] === 'yes'); ?>><span>Có, mình sẽ đến</span></label>
                    <label class="choice"><input type="radio" name="attending" value="no" <?php checked($response && $response['attending'] === 'no'); ?>><span>Rất tiếc, mình không đến được</span></label>
                </fieldset>
                <div data-show="coming" <?php echo $coming ? '' : 'hidden'; ?>>
                    <fieldset>
                        <legend>Bạn sẽ di chuyển cùng xe đưa đón chung chứ? <span class="req" aria-hidden="true">*</span></legend>
                        <label class="choice"><input type="radio" name="ride_bus" value="1" <?php checked($ride === 1); ?>><span>Di chuyển xe chung</span></label>
                        <label class="choice"><input type="radio" name="ride_bus" value="0" <?php checked($ride === 0); ?>><span>Mình tự di chuyển</span></label>
                    </fieldset>
                    <div class="bus" data-show="bus" <?php echo $show_bus ? '' : 'hidden'; ?>>
                        <?php echo Wedding_RSVP_Templates::format_plain($settings['bus_notice_text']); ?>
                    </div>
                    <label class="field" data-show="bus" <?php echo $show_bus ? '' : 'hidden'; ?>>Số điện thoại <span class="req" aria-hidden="true">*</span>
                        <input type="tel" name="phone" inputmode="numeric" autocomplete="tel" value="<?php echo esc_attr($response && $response['phone'] ? $response['phone'] : ''); ?>" placeholder="(----) --- ---">
                    </label>
                    <?php if ($view['max_companions'] > 0) : ?>
                        <label class="check"><input type="checkbox" name="has_companion" value="1" <?php checked($companion_count > 0); ?>> Có người đi cùng</label>
                        <div data-show="companions" <?php echo $companion_count > 0 ? '' : 'hidden'; ?>>
                            <label class="field">Số người đi cùng <span class="req" aria-hidden="true">*</span>
                                <input type="number" name="companion_count" min="1" max="<?php echo (int) $view['max_companions']; ?>" inputmode="numeric" value="<?php echo $companion_count > 0 ? (int) $companion_count : 1; ?>">
                            </label>
                            <p class="hint">Người đi cùng sẽ đi chung xe với bạn.</p>
                            <div id="companion-list">
                                <?php foreach ($companions as $index => $person) : ?>
                                    <div class="companion">
                                        <p class="companion-title">Người đi cùng <?php echo (int) $index + 1; ?> <span class="req" aria-hidden="true">*</span></p>
                                        <input class="companion-name" type="text" name="companion_name_<?php echo (int) $index; ?>" value="<?php echo esc_attr($person['name']); ?>" placeholder="Tên" aria-label="Tên">
                                        <div class="choice-row">
                                            <label class="choice"><input type="radio" name="companion_type_<?php echo (int) $index; ?>" value="adult" <?php checked($person['type'] === 'adult'); ?>><span>Người lớn</span></label>
                                            <label class="choice"><input type="radio" name="companion_type_<?php echo (int) $index; ?>" value="child" <?php checked($person['type'] === 'child'); ?>><span>Trẻ em</span></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <label class="field">Ghi chú ăn uống
                        <textarea name="diet_note" rows="4" placeholder="<?php echo esc_attr($companion_count > 0 ? 'Nếu bạn hay người đi cùng ăn chay hay đặc biệt dị ứng với món nào thì hãy note thêm thông tin nhé' : 'Nếu bạn ăn chay hay đặc biệt dị ứng với món nào thì hãy note thêm thông tin nhé'); ?>"><?php echo esc_textarea($response && $response['diet_note'] ? $response['diet_note'] : ''); ?></textarea>
                    </label>
                </div>
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
