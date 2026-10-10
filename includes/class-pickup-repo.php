<?php

class Wedding_RSVP_Pickup_Repo {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'wedding_pickups';
    }

    public function all() {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY sort_order ASC, id ASC', ARRAY_A);
        if (!is_array($rows)) {
            return array();
        }
        $out = array();
        foreach ($rows as $row) {
            $out[] = $this->cast($row);
        }
        return $out;
    }

    public function find($id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id), ARRAY_A);
        return $row ? $this->cast($row) : null;
    }

    public function create(array $data) {
        global $wpdb;
        $clean = $this->sanitize($data);
        if ($clean['name'] === '') {
            throw new Wedding_RSVP_Validation_Exception('Bạn nhập tên điểm đón giúp mình.');
        }
        $wpdb->insert(self::table(), $clean, array('%s', '%s', '%s', '%s', '%d'));
        if ($wpdb->last_error) {
            throw new RuntimeException($wpdb->last_error);
        }
        return $this->find((int) $wpdb->insert_id);
    }

    public function update($id, array $data) {
        global $wpdb;
        $clean = $this->sanitize($data);
        if ($clean['name'] === '') {
            throw new Wedding_RSVP_Validation_Exception('Bạn nhập tên điểm đón giúp mình.');
        }
        $wpdb->update(self::table(), $clean, array('id' => (int) $id), array('%s', '%s', '%s', '%s', '%d'), array('%d'));
        if ($wpdb->last_error) {
            throw new RuntimeException($wpdb->last_error);
        }
        return $this->find((int) $id);
    }

    public function delete($id, $reassign_to = null) {
        $id = (int) $id;
        $guests = new Wedding_RSVP_Guest_Repo();
        $count = $guests->count_by_pickup($id);
        if ($count > 0) {
            $reassign_to = $reassign_to ? (int) $reassign_to : 0;
            if ($reassign_to <= 0) {
                throw new Wedding_RSVP_Validation_Exception('Điểm đón đang có khách. Chọn điểm khác để chuyển khách trước khi xóa.');
            }
            if ($reassign_to === $id || !$this->find($reassign_to)) {
                throw new Wedding_RSVP_Validation_Exception('Điểm đón để chuyển khách không hợp lệ.');
            }
            $guests->reassign_pickup($id, $reassign_to);
        }
        global $wpdb;
        $wpdb->delete(self::table(), array('id' => $id), array('%d'));
        if ($wpdb->last_error) {
            throw new RuntimeException($wpdb->last_error);
        }
    }

    private function sanitize(array $data) {
        $name = $this->line(isset($data['name']) ? $data['name'] : '', 191);
        $address = $this->line(isset($data['address']) ? $data['address'] : '', 255);
        $time = $this->line(isset($data['pickup_time']) ? $data['pickup_time'] : '', 50);
        $note = isset($data['note']) ? (string) $data['note'] : '';
        $note = strip_tags(str_replace(array("\r\n", "\r"), "\n", $note));
        if (mb_strlen($note, 'UTF-8') > 2000) {
            $note = mb_substr($note, 0, 2000, 'UTF-8');
        }
        return array(
            'name' => $name,
            'address' => $address,
            'pickup_time' => $time,
            'note' => trim($note),
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
        );
    }

    private function line($value, $max) {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
        if (function_exists('sanitize_text_field')) {
            $value = sanitize_text_field($value);
        }
        if (mb_strlen($value, 'UTF-8') > $max) {
            $value = mb_substr($value, 0, $max, 'UTF-8');
        }
        return $value;
    }

    private function cast(array $row) {
        return array(
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'address' => $row['address'],
            'pickup_time' => $row['pickup_time'],
            'note' => $row['note'],
            'sort_order' => (int) $row['sort_order'],
        );
    }
}
