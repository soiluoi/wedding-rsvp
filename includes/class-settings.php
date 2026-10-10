<?php

class Wedding_RSVP_Settings {
    const OPTION = 'wedding_settings';

    public static function defaults() {
        return array(
            'bride_name' => 'Trúc Anh',
            'groom_name' => '',
            'wedding_date' => '',
            'venue_name' => '',
            'venue_address' => '',
            'deadline' => '',
            'allow_plus_one_all' => true,
            'seats_per_bus' => 43,
            'child_takes_bus_seat' => true,
            'people_per_table' => 10,
            'child_counts_for_table' => true,
            'bus_notice_text' => "🚌 **Về xe đưa đón:** Vì khách mời phần lớn là anh chị em đồng nghiệp ở công ty của hai đứa, tụi mình sẽ dựa vào số lượng đăng ký để chọn điểm đón thuận tiện nhất cho mọi người. Điểm đón và giờ xuất phát sẽ được nhắn riêng cho bạn trước ngày cưới nha. Bạn chỉ cần để lại số điện thoại để nhà xe tiện liên hệ hôm đó thôi!",
            'invite_message_template' => "Chào {danh_xung} {ten}! {chu_re} & {co_dau} sẽ tổ chức đám cưới vào {ngay_cuoi} tại {dia_diem}. Tụi mình rất mong {danh_xung} đến chung vui 💕\n{danh_xung} xác nhận giúp tụi mình trước {han_chot} tại link này nhé: {link}",
            'pickup_message_template' => "Chào {danh_xung} {ten}! Xe đưa đón đám cưới sẽ đón {danh_xung} tại {diem_don} ({dia_chi_don}) lúc {gio_don}. Hẹn gặp {danh_xung} nha! 🚌",
            'contact_info' => '',
            'og_image_url' => '',
            'delete_data_on_uninstall' => false,
        );
    }

    public static function get() {
        $stored = array();
        if (function_exists('get_option')) {
            $raw = get_option(self::OPTION, array());
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $stored = $decoded;
                }
            } elseif (is_array($raw)) {
                $stored = $raw;
            }
        }
        return self::merge($stored);
    }

    public static function merge(array $stored) {
        $settings = self::defaults();
        foreach ($settings as $key => $default) {
            if (!array_key_exists($key, $stored)) {
                continue;
            }
            $settings[$key] = $stored[$key];
        }
        return self::cast($settings);
    }

    public static function update(array $input) {
        $clean = self::sanitize($input);
        if (function_exists('update_option')) {
            update_option(self::OPTION, wp_json_encode($clean), true);
        }
        return $clean;
    }

    public static function sanitize(array $input) {
        $defaults = self::defaults();
        $clean = $defaults;
        $text_keys = array('bride_name', 'groom_name', 'venue_name', 'venue_address');
        foreach ($text_keys as $key) {
            if (isset($input[$key])) {
                $clean[$key] = self::line($input[$key], 191);
            }
        }
        $clean['contact_info'] = isset($input['contact_info']) ? self::block($input['contact_info'], 500) : '';
        $clean['bus_notice_text'] = isset($input['bus_notice_text']) ? self::block($input['bus_notice_text'], 2000) : $defaults['bus_notice_text'];
        $clean['invite_message_template'] = isset($input['invite_message_template']) ? self::block($input['invite_message_template'], 2000) : $defaults['invite_message_template'];
        $clean['pickup_message_template'] = isset($input['pickup_message_template']) ? self::block($input['pickup_message_template'], 2000) : $defaults['pickup_message_template'];
        $clean['og_image_url'] = isset($input['og_image_url']) ? self::url($input['og_image_url']) : '';
        $clean['wedding_date'] = self::datetime_or_empty(isset($input['wedding_date']) ? $input['wedding_date'] : '');
        $clean['deadline'] = self::datetime_or_empty(isset($input['deadline']) ? $input['deadline'] : '');
        $clean['allow_plus_one_all'] = self::bool_value(isset($input['allow_plus_one_all']) ? $input['allow_plus_one_all'] : false);
        $clean['child_takes_bus_seat'] = self::bool_value(isset($input['child_takes_bus_seat']) ? $input['child_takes_bus_seat'] : false);
        $clean['child_counts_for_table'] = self::bool_value(isset($input['child_counts_for_table']) ? $input['child_counts_for_table'] : false);
        $clean['delete_data_on_uninstall'] = self::bool_value(isset($input['delete_data_on_uninstall']) ? $input['delete_data_on_uninstall'] : false);
        $seats = isset($input['seats_per_bus']) ? (int) $input['seats_per_bus'] : 43;
        $tables = isset($input['people_per_table']) ? (int) $input['people_per_table'] : 10;
        $clean['seats_per_bus'] = $seats > 0 ? $seats : 43;
        $clean['people_per_table'] = $tables > 0 ? $tables : 10;
        return $clean;
    }

    public static function is_past_deadline(array $settings, $now = null) {
        $deadline = trim((string) (isset($settings['deadline']) ? $settings['deadline'] : ''));
        if ($deadline === '') {
            return false;
        }
        $tz = self::timezone();
        $deadline_at = date_create_immutable($deadline, $tz);
        if (!$deadline_at) {
            return false;
        }
        if ($now === null) {
            $now = new DateTimeImmutable('now', $tz);
        } elseif (is_int($now)) {
            $now = (new DateTimeImmutable('@' . $now))->setTimezone($tz);
        } elseif (is_string($now)) {
            $now = new DateTimeImmutable($now, $tz);
        }
        return $now > $deadline_at;
    }

    public static function format_datetime($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $tz = self::timezone();
        $dt = date_create_immutable($value, $tz);
        if (!$dt) {
            return '';
        }
        return $dt->format('d/m/Y H:i');
    }

    public static function datetime_local_value($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $dt = date_create_immutable($value, self::timezone());
        if (!$dt) {
            return '';
        }
        return $dt->format('Y-m-d\TH:i');
    }

    public static function place_label(array $settings) {
        $name = trim((string) $settings['venue_name']);
        $address = trim((string) $settings['venue_address']);
        if ($name !== '' && $address !== '') {
            return $name . ' — ' . $address;
        }
        return $name !== '' ? $name : $address;
    }

    public static function couple_label(array $settings) {
        $groom = trim((string) $settings['groom_name']);
        $bride = trim((string) $settings['bride_name']);
        if ($groom !== '' && $bride !== '') {
            return $groom . ' & ' . $bride;
        }
        return $groom !== '' ? $groom : $bride;
    }

    public static function timezone() {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }
        $name = @date_default_timezone_get();
        try {
            return new DateTimeZone($name ? $name : 'Asia/Ho_Chi_Minh');
        } catch (Exception $e) {
            return new DateTimeZone('Asia/Ho_Chi_Minh');
        }
    }

    private static function cast(array $settings) {
        $settings['allow_plus_one_all'] = self::bool_value($settings['allow_plus_one_all']);
        $settings['child_takes_bus_seat'] = self::bool_value($settings['child_takes_bus_seat']);
        $settings['child_counts_for_table'] = self::bool_value($settings['child_counts_for_table']);
        $settings['delete_data_on_uninstall'] = self::bool_value($settings['delete_data_on_uninstall']);
        $settings['seats_per_bus'] = max(1, (int) $settings['seats_per_bus']);
        $settings['people_per_table'] = max(1, (int) $settings['people_per_table']);
        foreach (array('bride_name', 'groom_name', 'wedding_date', 'venue_name', 'venue_address', 'deadline', 'bus_notice_text', 'invite_message_template', 'pickup_message_template', 'contact_info', 'og_image_url') as $key) {
            $settings[$key] = (string) $settings[$key];
        }
        return $settings;
    }

    private static function bool_value($value) {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), array('1', 'true', 'yes', 'on'), true);
        }
        return (bool) $value;
    }

    private static function line($value, $max) {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
        if (function_exists('sanitize_text_field')) {
            $value = sanitize_text_field($value);
        }
        if (mb_strlen($value, 'UTF-8') > $max) {
            $value = mb_substr($value, 0, $max, 'UTF-8');
        }
        return $value;
    }

    private static function block($value, $max) {
        $value = str_replace("\r\n", "\n", (string) $value);
        $value = str_replace("\r", "\n", $value);
        if (function_exists('sanitize_textarea_field')) {
            $value = sanitize_textarea_field($value);
        } else {
            $value = strip_tags($value);
        }
        if (mb_strlen($value, 'UTF-8') > $max) {
            $value = mb_substr($value, 0, $max, 'UTF-8');
        }
        return trim($value);
    }

    private static function url($value) {
        $value = trim((string) $value);
        if (function_exists('esc_url_raw')) {
            return esc_url_raw($value);
        }
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }

    private static function datetime_or_empty($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return '';
        }
        $dt = date_create_immutable($value, self::timezone());
        if (!$dt) {
            return '';
        }
        return $dt->format('Y-m-d H:i:s');
    }
}
