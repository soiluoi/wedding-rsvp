<?php

class Wedding_RSVP_Response_Repo {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'wedding_responses';
    }

    public static function normalize(array $input, array $guest, array $settings, $source = 'guest', $now = null) {
        if ($source !== 'admin') {
            $source = 'guest';
        }
        if ($source === 'guest' && Wedding_RSVP_Settings::is_past_deadline($settings, $now)) {
            $contact = trim((string) (isset($settings['contact_info']) ? $settings['contact_info'] : ''));
            if ($contact !== '') {
                throw new Wedding_RSVP_Validation_Exception('Đã hết hạn chỉnh sửa, cần thay đổi bạn nhắn ' . $contact . ' nhé.');
            }
            throw new Wedding_RSVP_Validation_Exception('Đã hết hạn chỉnh sửa. Bạn nhắn cho tụi mình nếu cần thay đổi nhé.');
        }

        $attending = strtolower(trim(self::str_value($input, 'attending')));
        if (!in_array($attending, array('yes', 'no', 'maybe'), true)) {
            throw new Wedding_RSVP_Validation_Exception('Bạn chọn giúp mình có tham dự được không nhé.');
        }

        $message = self::clean_multiline(self::str_value($input, 'message'), 2000);
        $data = array(
            'attending' => $attending,
            'ride_bus' => null,
            'phone' => null,
            'diet_note' => null,
            'has_companion' => 0,
            'companion_name' => null,
            'companion_type' => null,
            'companion_age' => null,
            'message' => $message === '' ? null : $message,
            'source' => $source,
        );
        if ($attending === 'no') {
            return $data;
        }

        if (!self::has_ride_answer($input)) {
            throw new Wedding_RSVP_Validation_Exception('Bạn chọn giúp mình cách đi lại nhé.');
        }
        $ride_yes = self::is_truthy($input['ride_bus']);
        $ride_no = self::is_falsey($input['ride_bus']);
        if (!$ride_yes && !$ride_no) {
            throw new Wedding_RSVP_Validation_Exception('Bạn chọn giúp mình cách đi lại nhé.');
        }
        $data['ride_bus'] = $ride_yes ? 1 : 0;
        if ($ride_yes) {
            $data['phone'] = self::normalize_phone(self::str_value($input, 'phone'), true);
        }

        $diet = self::clean_line(self::str_value($input, 'diet_note'), 255);
        $data['diet_note'] = $diet === '' ? null : $diet;

        $allow = Wedding_Stats::flag($settings, 'allow_plus_one_all', true)
            || (int) (isset($guest['invited_count']) ? $guest['invited_count'] : 1) === 2;
        if ($allow && self::is_truthy(isset($input['has_companion']) ? $input['has_companion'] : 0)) {
            $type = strtolower(trim(self::str_value($input, 'companion_type')));
            if ($type !== 'adult' && $type !== 'child') {
                throw new Wedding_RSVP_Validation_Exception('Bạn chọn giúp mình người đi cùng là người lớn hay trẻ em nhé.');
            }
            $companion_name = self::clean_line(self::str_value($input, 'companion_name'), 191);
            $data['has_companion'] = 1;
            $data['companion_name'] = $companion_name === '' ? null : $companion_name;
            $data['companion_type'] = $type;
            if ($type === 'child') {
                $age_raw = trim(self::str_value($input, 'companion_age'));
                if (!preg_match('/^\d+$/', $age_raw) || (int) $age_raw > 15) {
                    throw new Wedding_RSVP_Validation_Exception('Bạn nhập tuổi của bé (từ 0 đến 15) giúp mình nhé.');
                }
                $data['companion_age'] = (int) $age_raw;
            }
        }
        return $data;
    }

    public static function normalize_phone($raw, $required) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            if ($required) {
                throw new Wedding_RSVP_Validation_Exception('Bạn để lại số điện thoại để nhà xe tiện liên hệ nhé.');
            }
            return null;
        }
        $compact = preg_replace('/[\s.\-]/', '', $raw);
        if (strpos($compact, '+84') === 0) {
            $compact = '0' . substr($compact, 3);
        }
        $digits = preg_replace('/\D/', '', $compact);
        if (strpos($digits, '84') === 0 && strlen($digits) === 11) {
            $digits = '0' . substr($digits, 2);
        }
        if (!preg_match('/^0\d{9}$/', $digits)) {
            throw new Wedding_RSVP_Validation_Exception('Số điện thoại chưa đúng. Bạn nhập 10 số bắt đầu bằng 0 giúp mình nhé.');
        }
        return $digits;
    }

    public function save($guest_id, array $data) {
        global $wpdb;
        $guest_id = (int) $guest_id;
        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT id, update_count, submitted_at FROM ' . self::table() . ' WHERE guest_id = %d',
            $guest_id
        ), ARRAY_A);
        $now = current_time('mysql');
        $payload = $data;
        $payload['guest_id'] = $guest_id;
        $payload['updated_at'] = $now;
        if ($existing) {
            $payload['update_count'] = (int) $existing['update_count'] + 1;
            $payload['submitted_at'] = $existing['submitted_at'];
            $this->update_row((int) $existing['id'], $payload);
        } else {
            $payload['update_count'] = 0;
            $payload['submitted_at'] = $now;
            $this->insert_row($payload);
        }
        return $this->find_by_guest($guest_id);
    }

    public function find_by_guest($guest_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE guest_id = %d',
            (int) $guest_id
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        return array(
            'attending' => $row['attending'],
            'ride_bus' => $row['ride_bus'] === null ? null : (int) $row['ride_bus'],
            'phone' => $row['phone'] !== null && $row['phone'] !== '' ? $row['phone'] : null,
            'diet_note' => $row['diet_note'] !== null && $row['diet_note'] !== '' ? $row['diet_note'] : null,
            'has_companion' => (int) $row['has_companion'] === 1 ? 1 : 0,
            'companion_name' => $row['companion_name'] !== null && $row['companion_name'] !== '' ? $row['companion_name'] : null,
            'companion_type' => $row['companion_type'] !== null && $row['companion_type'] !== '' ? $row['companion_type'] : null,
            'companion_age' => $row['companion_age'] === null || $row['companion_age'] === '' ? null : (int) $row['companion_age'],
            'message' => $row['message'] !== null && $row['message'] !== '' ? $row['message'] : null,
            'source' => $row['source'],
            'update_count' => (int) $row['update_count'],
            'submitted_at' => $row['submitted_at'],
            'updated_at' => $row['updated_at'],
        );
    }

    public function delete_for_guest($guest_id) {
        global $wpdb;
        $wpdb->delete(self::table(), array('guest_id' => (int) $guest_id), array('%d'));
        if ($wpdb->last_error) {
            throw new RuntimeException($wpdb->last_error);
        }
    }

    private function insert_row(array $payload) {
        global $wpdb;
        $columns = array(
            'guest_id', 'attending', 'ride_bus', 'phone', 'diet_note', 'has_companion',
            'companion_name', 'companion_type', 'companion_age', 'message', 'source',
            'update_count', 'submitted_at', 'updated_at',
        );
        list($sql, $values) = $this->write_sql('INSERT INTO ' . self::table() . ' (' . implode(', ', $columns) . ') VALUES (', $columns, $payload, ')');
        $wpdb->query($wpdb->prepare($sql, $values));
        if ($wpdb->last_error) {
            throw new RuntimeException($wpdb->last_error);
        }
    }

    private function update_row($id, array $payload) {
        global $wpdb;
        $columns = array(
            'attending', 'ride_bus', 'phone', 'diet_note', 'has_companion',
            'companion_name', 'companion_type', 'companion_age', 'message', 'source',
            'update_count', 'updated_at',
        );
        list($sql, $values) = $this->write_sql('UPDATE ' . self::table() . ' SET ', $columns, $payload, ' WHERE id = %d', true);
        $values[] = (int) $id;
        $wpdb->query($wpdb->prepare($sql, $values));
        if ($wpdb->last_error) {
            throw new RuntimeException($wpdb->last_error);
        }
    }

    private function write_sql($prefix, array $columns, array $payload, $suffix, $is_update = false) {
        $parts = array();
        $values = array();
        $ints = array('guest_id' => true, 'ride_bus' => true, 'has_companion' => true, 'companion_age' => true, 'update_count' => true);
        foreach ($columns as $column) {
            $value = array_key_exists($column, $payload) ? $payload[$column] : null;
            if ($column === 'message' && $value === null) {
                $value = '';
            }
            $token = $value === null ? 'NULL' : (isset($ints[$column]) ? '%d' : '%s');
            if ($is_update) {
                $parts[] = $column . ' = ' . $token;
            } else {
                $parts[] = $token;
            }
            if ($value !== null) {
                $values[] = isset($ints[$column]) ? (int) $value : (string) $value;
            }
        }
        $glue = $is_update ? ', ' : ', ';
        return array($prefix . implode($glue, $parts) . $suffix, $values);
    }

    private static function has_ride_answer(array $input) {
        if (!array_key_exists('ride_bus', $input) || $input['ride_bus'] === null || $input['ride_bus'] === '') {
            return false;
        }
        return true;
    }

    private static function is_truthy($value) {
        return $value === true || $value === 1 || $value === '1' || $value === 'on' || $value === 'yes' || $value === 'true';
    }

    private static function is_falsey($value) {
        return $value === false || $value === 0 || $value === '0' || $value === 'no' || $value === 'false';
    }

    private static function str_value(array $input, $key) {
        if (!isset($input[$key]) || is_array($input[$key])) {
            return '';
        }
        return (string) $input[$key];
    }

    private static function clean_line($value, $max) {
        $value = trim(preg_replace('/[\r\n\t]+/', ' ', (string) $value));
        $value = strip_tags($value);
        if (function_exists('sanitize_text_field')) {
            $value = sanitize_text_field($value);
        }
        if (mb_strlen($value, 'UTF-8') > $max) {
            $value = mb_substr($value, 0, $max, 'UTF-8');
        }
        return $value;
    }

    private static function clean_multiline($value, $max) {
        $value = str_replace("\r\n", "\n", (string) $value);
        $value = str_replace("\r", "\n", $value);
        $value = strip_tags($value);
        if (function_exists('sanitize_textarea_field')) {
            $value = sanitize_textarea_field($value);
        }
        if (mb_strlen($value, 'UTF-8') > $max) {
            $value = mb_substr($value, 0, $max, 'UTF-8');
        }
        return trim($value);
    }
}
