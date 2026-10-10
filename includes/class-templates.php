<?php

class Wedding_RSVP_Templates {
    public static function fill($template, array $vars) {
        $replace = array();
        foreach ($vars as $key => $value) {
            $replace['{' . $key . '}'] = (string) $value;
        }
        return strtr((string) $template, $replace);
    }

    public static function guest_vars(array $guest, array $settings, array $pickup = array()) {
        return array(
            'danh_xung' => isset($guest['salutation']) ? $guest['salutation'] : '',
            'ten' => isset($guest['name']) ? $guest['name'] : '',
            'link' => isset($guest['link']) ? $guest['link'] : '',
            'co_dau' => isset($settings['bride_name']) ? $settings['bride_name'] : '',
            'chu_re' => isset($settings['groom_name']) ? $settings['groom_name'] : '',
            'ngay_cuoi' => Wedding_RSVP_Settings::format_datetime(isset($settings['wedding_date']) ? $settings['wedding_date'] : ''),
            'dia_diem' => Wedding_RSVP_Settings::place_label($settings),
            'han_chot' => Wedding_RSVP_Settings::format_datetime(isset($settings['deadline']) ? $settings['deadline'] : ''),
            'diem_don' => isset($pickup['name']) ? $pickup['name'] : (isset($guest['pickup_name']) ? $guest['pickup_name'] : ''),
            'dia_chi_don' => isset($pickup['address']) ? $pickup['address'] : (isset($guest['pickup_address']) ? $guest['pickup_address'] : ''),
            'gio_don' => isset($pickup['pickup_time']) ? $pickup['pickup_time'] : (isset($guest['pickup_time']) ? $guest['pickup_time'] : ''),
        );
    }

    public static function attending_label($value) {
        if ($value === 'yes') {
            return 'Có, mình sẽ đến';
        }
        if ($value === 'maybe') {
            return 'Chưa chắc';
        }
        if ($value === 'no') {
            return 'Rất tiếc, mình không đến được';
        }
        return 'Chưa trả lời';
    }

    public static function ride_label($value) {
        if ($value === null || $value === '') {
            return '';
        }
        return (int) $value === 1 ? 'Đi xe chung' : 'Mình tự đi';
    }

    public static function companion_type_label($response) {
        if (!is_array($response) || (int) (isset($response['has_companion']) ? $response['has_companion'] : 0) !== 1) {
            return '';
        }
        if ((isset($response['companion_type']) ? $response['companion_type'] : '') === 'child') {
            return 'Trẻ em';
        }
        if ((isset($response['companion_type']) ? $response['companion_type'] : '') === 'adult') {
            return 'Người lớn';
        }
        return '';
    }

    public static function companion_label($response) {
        if (!is_array($response) || (int) (isset($response['has_companion']) ? $response['has_companion'] : 0) !== 1) {
            return '';
        }
        $parts = array();
        if (!empty($response['companion_name'])) {
            $parts[] = $response['companion_name'];
        }
        $type = self::companion_type_label($response);
        if ($type !== '') {
            $parts[] = $type;
        }
        if ((isset($response['companion_type']) ? $response['companion_type'] : '') === 'child' && isset($response['companion_age']) && $response['companion_age'] !== null && $response['companion_age'] !== '') {
            $parts[] = $response['companion_age'] . ' tuổi';
        }
        return implode(' · ', $parts);
    }

    public static function format_plain($text) {
        $safe = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        $safe = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $safe);
        return nl2br($safe, false);
    }
}
