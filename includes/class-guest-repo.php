<?php

class Wedding_RSVP_Guest_Repo {
    const IMPORTABLE_FIELDS = array(
        'salutation',
        'name',
        'note',
        'invited_count',
        'host',
        'group_name',
        'sort_order',
    );

    const PROTECTED_FIELDS = array(
        'code',
        'pickup_id',
        'link_sent_at',
        'pickup_notified_at',
    );

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'wedding_guests';
    }

    public static function sanitize_import_payload(array $data, $status = null) {
        $out = array();
        foreach (self::IMPORTABLE_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if ($field === 'invited_count' || $field === 'sort_order') {
                $out[$field] = (int) $data[$field];
                continue;
            }
            $out[$field] = trim((string) $data[$field]);
        }
        if (isset($out['name'])) {
            $out['name_normalized'] = Wedding_RSVP_Text::normalize_name($out['name']);
        }
        if ($status === 'active' || $status === 'inactive') {
            $out['status'] = $status;
        }
        foreach (self::PROTECTED_FIELDS as $field) {
            unset($out[$field]);
        }
        return $out;
    }

    public function find_by_code($code) {
        global $wpdb;
        $sql = $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE code = %s LIMIT 1',
            strtolower((string) $code)
        );
        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ? $this->cast_guest($row) : null;
    }

    public function find($id) {
        global $wpdb;
        $sql = $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id);
        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ? $this->cast_guest($row) : null;
    }

    public function code_exists($code) {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::table() . ' WHERE code = %s LIMIT 1',
            $code
        ));
        return $found !== null;
    }

    public function list_for_import() {
        global $wpdb;
        $sql = 'SELECT g.id, g.code, g.salutation, g.name, g.name_normalized, g.note, g.invited_count, g.host, g.group_name, g.sort_order, g.status,
            CASE WHEN r.has_companion = 1 THEN 1 ELSE 0 END AS has_companion
            FROM ' . self::table() . ' g
            LEFT JOIN ' . Wedding_RSVP_Response_Repo::table() . ' r ON r.guest_id = g.id
            ORDER BY g.sort_order ASC, g.id ASC';
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return array();
        }
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['invited_count'] = (int) $row['invited_count'];
            $row['sort_order'] = (int) $row['sort_order'];
            $row['has_companion'] = (int) $row['has_companion'];
        }
        unset($row);
        return $rows;
    }

    public function all_detailed() {
        global $wpdb;
        $sql = 'SELECT g.*,
            r.attending, r.ride_bus, r.phone, r.diet_note, r.has_companion, r.companion_name,
            r.companion_type, r.companion_age, r.message AS guest_message, r.source AS response_source,
            r.update_count, r.submitted_at, r.updated_at AS response_updated_at,
            p.name AS pickup_name, p.address AS pickup_address, p.pickup_time
            FROM ' . self::table() . ' g
            LEFT JOIN ' . Wedding_RSVP_Response_Repo::table() . ' r ON r.guest_id = g.id
            LEFT JOIN ' . Wedding_RSVP_Pickup_Repo::table() . ' p ON p.id = g.pickup_id
            ORDER BY g.sort_order ASC, g.id ASC';
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return array();
        }
        $out = array();
        foreach ($rows as $row) {
            $out[] = $this->to_api($row);
        }
        return $out;
    }

    public function insert_imported($code, array $data) {
        global $wpdb;
        if (!Wedding_RSVP_Codes::is_valid_code($code)) {
            throw new RuntimeException('Invalid guest code.');
        }
        $payload = self::sanitize_import_payload($data, 'active');
        $now = current_time('mysql');
        $sql = 'INSERT INTO ' . self::table() . '
            (code, salutation, name, name_normalized, note, invited_count, host, group_name, sort_order, status, pickup_id, link_sent_at, pickup_notified_at, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %d, %s, %s, %d, %s, NULL, NULL, NULL, %s, %s)';
        $wpdb->query($wpdb->prepare($sql, array(
            $code,
            isset($payload['salutation']) ? $payload['salutation'] : '',
            isset($payload['name']) ? $payload['name'] : '',
            isset($payload['name_normalized']) ? $payload['name_normalized'] : '',
            isset($payload['note']) ? $payload['note'] : '',
            isset($payload['invited_count']) ? $payload['invited_count'] : 1,
            isset($payload['host']) ? $payload['host'] : '',
            isset($payload['group_name']) ? $payload['group_name'] : '',
            isset($payload['sort_order']) ? $payload['sort_order'] : 0,
            'active',
            $now,
            $now,
        )));
        $this->check_error();
        return (int) $wpdb->insert_id;
    }

    public function update_imported($id, array $data, $status) {
        global $wpdb;
        $payload = self::sanitize_import_payload($data, $status);
        if (!$payload) {
            return;
        }
        $payload['updated_at'] = current_time('mysql');
        $this->update_columns((int) $id, $payload);
    }

    public function update_admin($id, array $fields) {
        $payload = array();
        if (array_key_exists('pickup_id', $fields)) {
            $payload['pickup_id'] = $fields['pickup_id'] === null ? null : (int) $fields['pickup_id'];
        }
        if (array_key_exists('link_sent_at', $fields)) {
            $payload['link_sent_at'] = $fields['link_sent_at'];
        }
        if (array_key_exists('pickup_notified_at', $fields)) {
            $payload['pickup_notified_at'] = $fields['pickup_notified_at'];
        }
        if (!$payload) {
            return;
        }
        $payload['updated_at'] = current_time('mysql');
        $this->update_columns((int) $id, $payload);
    }

    public function reassign_pickup($from_id, $to_id) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::table() . ' SET pickup_id = %d, updated_at = %s WHERE pickup_id = %d',
            (int) $to_id,
            current_time('mysql'),
            (int) $from_id
        ));
        $this->check_error();
    }

    public function count_by_pickup($pickup_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE pickup_id = %d',
            (int) $pickup_id
        ));
    }

    public function run_import(array $ops) {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $counts = array(
                'created' => 0,
                'updated' => 0,
                'reactivated' => 0,
                'inactivated' => 0,
            );
            $inactivated = array();
            foreach ($ops as $op) {
                if ($op['op'] === 'insert') {
                    $this->insert_imported($op['code'], $op['data']);
                    $counts['created']++;
                    continue;
                }
                if ($op['op'] === 'update') {
                    $this->update_imported($op['id'], $op['data'], 'active');
                    if (!empty($op['reactivated'])) {
                        $counts['reactivated']++;
                    } else {
                        $counts['updated']++;
                    }
                    continue;
                }
                if ($op['op'] === 'inactivate') {
                    $this->update_imported($op['id'], array(), 'inactive');
                    $counts['inactivated']++;
                    $inactivated[] = array(
                        'id' => (int) $op['id'],
                        'name' => isset($op['name']) ? $op['name'] : '',
                    );
                }
            }
            $wpdb->query('COMMIT');
            $counts['inactivated_guests'] = $inactivated;
            return $counts;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            if (function_exists('error_log')) {
                error_log('Wedding RSVP import failed: ' . $e->getMessage());
            }
            throw new Wedding_RSVP_Import_Exception('Không lưu được dữ liệu. Chưa có thay đổi nào được ghi.');
        }
    }

    public function to_api(array $row) {
        $guest = array(
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'salutation' => $row['salutation'],
            'name' => $row['name'],
            'name_normalized' => $row['name_normalized'],
            'note' => $row['note'],
            'invited_count' => (int) $row['invited_count'],
            'host' => $row['host'],
            'group_name' => $row['group_name'],
            'sort_order' => (int) $row['sort_order'],
            'status' => $row['status'],
            'pickup_id' => $row['pickup_id'] === null || $row['pickup_id'] === '' ? null : (int) $row['pickup_id'],
            'pickup_name' => isset($row['pickup_name']) && $row['pickup_name'] !== null ? $row['pickup_name'] : '',
            'pickup_address' => isset($row['pickup_address']) && $row['pickup_address'] !== null ? $row['pickup_address'] : '',
            'pickup_time' => isset($row['pickup_time']) && $row['pickup_time'] !== null ? $row['pickup_time'] : '',
            'link_sent_at' => $row['link_sent_at'] ? $row['link_sent_at'] : null,
            'pickup_notified_at' => $row['pickup_notified_at'] ? $row['pickup_notified_at'] : null,
            'link' => Wedding_RSVP_Codes::url($row['name'], $row['code']),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'response' => null,
        );
        if (!empty($row['attending'])) {
            $guest['response'] = array(
                'attending' => $row['attending'],
                'ride_bus' => $row['ride_bus'] === null || $row['ride_bus'] === '' ? null : (int) $row['ride_bus'],
                'phone' => $row['phone'] !== null && $row['phone'] !== '' ? $row['phone'] : null,
                'diet_note' => $row['diet_note'] !== null && $row['diet_note'] !== '' ? $row['diet_note'] : null,
                'has_companion' => (int) $row['has_companion'] === 1 ? 1 : 0,
                'companion_name' => $row['companion_name'] !== null && $row['companion_name'] !== '' ? $row['companion_name'] : null,
                'companion_type' => $row['companion_type'] !== null && $row['companion_type'] !== '' ? $row['companion_type'] : null,
                'companion_age' => $row['companion_age'] === null || $row['companion_age'] === '' ? null : (int) $row['companion_age'],
                'message' => isset($row['guest_message']) && $row['guest_message'] !== null && $row['guest_message'] !== '' ? $row['guest_message'] : null,
                'source' => isset($row['response_source']) ? $row['response_source'] : 'guest',
                'update_count' => (int) $row['update_count'],
                'submitted_at' => $row['submitted_at'],
                'updated_at' => $row['response_updated_at'],
            );
        }
        return $guest;
    }

    private function cast_guest(array $row) {
        $row['id'] = (int) $row['id'];
        $row['invited_count'] = (int) $row['invited_count'];
        $row['sort_order'] = (int) $row['sort_order'];
        $row['pickup_id'] = $row['pickup_id'] === null || $row['pickup_id'] === '' ? null : (int) $row['pickup_id'];
        return $row;
    }

    private function update_columns($id, array $payload) {
        global $wpdb;
        $sets = array();
        $values = array();
        $int_fields = array('invited_count' => true, 'sort_order' => true, 'pickup_id' => true);
        foreach ($payload as $key => $value) {
            if ($value === null) {
                $sets[] = $key . ' = NULL';
                continue;
            }
            if (isset($int_fields[$key])) {
                $sets[] = $key . ' = %d';
                $values[] = (int) $value;
                continue;
            }
            $sets[] = $key . ' = %s';
            $values[] = $value;
        }
        $values[] = (int) $id;
        $sql = 'UPDATE ' . self::table() . ' SET ' . implode(', ', $sets) . ' WHERE id = %d';
        $wpdb->query($wpdb->prepare($sql, $values));
        $this->check_error();
    }

    private function check_error() {
        global $wpdb;
        if ($wpdb->last_error) {
            throw new RuntimeException($wpdb->last_error);
        }
    }
}
