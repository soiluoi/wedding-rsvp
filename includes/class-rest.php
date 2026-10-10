<?php

class Wedding_RSVP_REST {
    public function __construct() {
        add_action('rest_api_init', array($this, 'routes'));
    }

    public function routes() {
        register_rest_route('wedding/v1', '/rsvp/(?P<code>[a-z0-9]{7})', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_rsvp'),
            'permission_callback' => '__return_true',
        ));

        $admin = array($this, 'can_manage');
        register_rest_route('wedding/v1', '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'stats'),
            'permission_callback' => $admin,
        ));
        register_rest_route('wedding/v1', '/guests', array(
            'methods' => 'GET',
            'callback' => array($this, 'guests'),
            'permission_callback' => $admin,
        ));
        register_rest_route('wedding/v1', '/guests/bulk', array(
            'methods' => 'POST',
            'callback' => array($this, 'bulk_guests'),
            'permission_callback' => $admin,
        ));
        register_rest_route('wedding/v1', '/guests/(?P<id>\d+)', array(
            'methods' => 'PATCH',
            'callback' => array($this, 'patch_guest'),
            'permission_callback' => $admin,
        ));
        register_rest_route('wedding/v1', '/guests/(?P<id>\d+)/response', array(
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'save_admin_response'),
                'permission_callback' => $admin,
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_response'),
                'permission_callback' => $admin,
            ),
        ));
        register_rest_route('wedding/v1', '/import/preview', array(
            'methods' => 'POST',
            'callback' => array($this, 'import_preview'),
            'permission_callback' => $admin,
        ));
        register_rest_route('wedding/v1', '/import/apply', array(
            'methods' => 'POST',
            'callback' => array($this, 'import_apply'),
            'permission_callback' => $admin,
        ));
        register_rest_route('wedding/v1', '/pickups', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'list_pickups'),
                'permission_callback' => $admin,
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_pickup'),
                'permission_callback' => $admin,
            ),
        ));
        register_rest_route('wedding/v1', '/pickups/(?P<id>\d+)', array(
            array(
                'methods' => 'PATCH',
                'callback' => array($this, 'update_pickup'),
                'permission_callback' => $admin,
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_pickup'),
                'permission_callback' => $admin,
            ),
        ));
        register_rest_route('wedding/v1', '/settings', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_settings'),
                'permission_callback' => $admin,
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'save_settings'),
                'permission_callback' => $admin,
            ),
        ));
    }

    public function can_manage() {
        return current_user_can('manage_options');
    }

    public function submit_rsvp(WP_REST_Request $request) {
        $code = strtolower((string) $request['code']);
        if (!Wedding_RSVP_Codes::is_valid_code($code)) {
            return new WP_Error('wedding_bad_code', 'Link không đúng, bạn kiểm tra lại giúp mình nhé.', array('status' => 404));
        }
        $limited = $this->rate_limited($code);
        if ($limited) {
            return $limited;
        }
        $guest_repo = new Wedding_RSVP_Guest_Repo();
        $guest = $guest_repo->find_by_code($code);
        if (!$guest) {
            return new WP_Error('wedding_bad_code', 'Link không đúng, bạn kiểm tra lại giúp mình nhé.', array('status' => 404));
        }
        if ($guest['status'] !== 'active') {
            return new WP_Error('wedding_inactive', 'Link này không còn hiệu lực.', array('status' => 410));
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        if (!empty($params['website'])) {
            return new WP_Error('wedding_rejected', 'Không gửi được, bạn thử lại nhé.', array('status' => 400));
        }
        $nonce = isset($params['nonce']) ? (string) $params['nonce'] : '';
        if (!wp_verify_nonce($nonce, 'wedding_rsvp_' . $code)) {
            return new WP_Error('wedding_nonce', 'Phiên đã hết hạn, bạn tải lại trang giúp mình nhé.', array('status' => 403));
        }

        $settings = Wedding_RSVP_Settings::get();
        try {
            $data = Wedding_RSVP_Response_Repo::normalize($params, $guest, $settings, 'guest');
        } catch (Wedding_RSVP_Validation_Exception $e) {
            return new WP_Error('wedding_invalid', $e->getMessage(), array('status' => 400));
        }

        $saved = (new Wedding_RSVP_Response_Repo())->save((int) $guest['id'], $data);
        $fresh = $guest_repo->find((int) $guest['id']);
        return rest_ensure_response(array(
            'response' => $saved,
            'pickup' => $this->visible_pickup($fresh),
            'updated_text' => Wedding_RSVP_Settings::format_datetime($saved['updated_at']),
        ));
    }

    public function stats() {
        $guests = (new Wedding_RSVP_Guest_Repo())->all_detailed();
        $pickups = (new Wedding_RSVP_Pickup_Repo())->all();
        $summary = Wedding_Stats::summarize($guests, Wedding_RSVP_Settings::get(), $pickups);
        $summary['recent'] = $this->recent($guests);
        return rest_ensure_response($summary);
    }

    public function guests() {
        return rest_ensure_response((new Wedding_RSVP_Guest_Repo())->all_detailed());
    }

    public function patch_guest(WP_REST_Request $request) {
        $repo = new Wedding_RSVP_Guest_Repo();
        $guest = $repo->find((int) $request['id']);
        if (!$guest) {
            return new WP_Error('wedding_missing', 'Không tìm thấy khách.', array('status' => 404));
        }
        $fields = array();
        if ($request->has_param('pickup_id')) {
            $pickup = $this->pickup_value($request->get_param('pickup_id'));
            if (is_wp_error($pickup)) {
                return $pickup;
            }
            $fields['pickup_id'] = $pickup;
        }
        if ($request->has_param('link_sent')) {
            $fields['link_sent_at'] = $this->is_on($request->get_param('link_sent')) ? current_time('mysql') : null;
        }
        if ($request->has_param('pickup_notified')) {
            $fields['pickup_notified_at'] = $this->is_on($request->get_param('pickup_notified')) ? current_time('mysql') : null;
        }
        if ($fields) {
            $repo->update_admin((int) $guest['id'], $fields);
        }
        $rows = $repo->all_detailed();
        foreach ($rows as $row) {
            if ((int) $row['id'] === (int) $guest['id']) {
                return rest_ensure_response($row);
            }
        }
        return rest_ensure_response(array('updated' => true));
    }

    public function bulk_guests(WP_REST_Request $request) {
        $action = sanitize_key((string) $request->get_param('action'));
        $ids = $request->get_param('ids');
        if (!is_array($ids) || !$ids) {
            return new WP_Error('wedding_bulk', 'Bạn chưa chọn khách.', array('status' => 400));
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $repo = new Wedding_RSVP_Guest_Repo();
        $fields = array();
        if ($action === 'assign_pickup') {
            $pickup = $this->pickup_value($request->get_param('pickup_id'));
            if (is_wp_error($pickup)) {
                return $pickup;
            }
            $fields['pickup_id'] = $pickup;
        } elseif ($action === 'mark_link_sent') {
            $fields['link_sent_at'] = current_time('mysql');
        } elseif ($action === 'mark_pickup_notified') {
            $fields['pickup_notified_at'] = current_time('mysql');
        } else {
            return new WP_Error('wedding_bulk', 'Hành động không hợp lệ.', array('status' => 400));
        }
        $updated = 0;
        foreach ($ids as $id) {
            if ($id <= 0 || !$repo->find($id)) {
                continue;
            }
            $repo->update_admin($id, $fields);
            $updated++;
        }
        return rest_ensure_response(array('updated' => $updated));
    }

    public function save_admin_response(WP_REST_Request $request) {
        $repo = new Wedding_RSVP_Guest_Repo();
        $guest = $repo->find((int) $request['id']);
        if (!$guest) {
            return new WP_Error('wedding_missing', 'Không tìm thấy khách.', array('status' => 404));
        }
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        try {
            $data = Wedding_RSVP_Response_Repo::normalize($params, $guest, Wedding_RSVP_Settings::get(), 'admin');
        } catch (Wedding_RSVP_Validation_Exception $e) {
            return new WP_Error('wedding_invalid', $e->getMessage(), array('status' => 400));
        }
        $saved = (new Wedding_RSVP_Response_Repo())->save((int) $guest['id'], $data);
        return rest_ensure_response($saved);
    }

    public function delete_response(WP_REST_Request $request) {
        $guest = (new Wedding_RSVP_Guest_Repo())->find((int) $request['id']);
        if (!$guest) {
            return new WP_Error('wedding_missing', 'Không tìm thấy khách.', array('status' => 404));
        }
        (new Wedding_RSVP_Response_Repo())->delete_for_guest((int) $guest['id']);
        return rest_ensure_response(array('deleted' => true));
    }

    public function import_preview(WP_REST_Request $request) {
        $text = (string) $request->get_param('text');
        $format = (string) $request->get_param('format');
        $mode = (string) $request->get_param('mode');
        if (!in_array($format, array('csv', 'tsv', 'auto'), true)) {
            $format = 'auto';
        }
        if (!in_array($mode, array('sync', 'upsert'), true)) {
            return new WP_Error('wedding_import', 'Chế độ import không hợp lệ.', array('status' => 400));
        }
        if (trim($text) === '') {
            return new WP_Error('wedding_import', 'Bạn chưa dán dữ liệu.', array('status' => 400));
        }
        if (strlen($text) > 2000000) {
            return new WP_Error('wedding_import', 'Dữ liệu quá lớn.', array('status' => 400));
        }
        try {
            $parsed = (new Wedding_RSVP_Tabular_Parser())->parse($text, $format);
            $existing = (new Wedding_RSVP_Guest_Repo())->list_for_import();
            $diff = (new Wedding_RSVP_Importer())->build_diff($parsed, $existing, $mode);
        } catch (Wedding_RSVP_Parse_Exception $e) {
            return new WP_Error('wedding_import', $e->getMessage(), array('status' => 400));
        } catch (Wedding_RSVP_Import_Exception $e) {
            return new WP_Error('wedding_import', $e->getMessage(), array('status' => 400));
        }
        $token = bin2hex(random_bytes(16));
        set_transient('wedding_imp_' . $token, array(
            'diff' => $diff,
            'existing' => $existing,
        ), 30 * MINUTE_IN_SECONDS);
        return rest_ensure_response(array(
            'import_token' => $token,
            'diff' => $diff,
        ));
    }

    public function import_apply(WP_REST_Request $request) {
        $token = (string) $request->get_param('import_token');
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return new WP_Error('wedding_import', 'Phiên import không hợp lệ.', array('status' => 400));
        }
        $stored = get_transient('wedding_imp_' . $token);
        if (!is_array($stored) || empty($stored['diff']) || !isset($stored['existing'])) {
            return new WP_Error('wedding_import', 'Phiên import đã hết hạn, bạn xem lại bản xem trước nhé.', array('status' => 400));
        }
        $resolutions = $request->get_param('resolutions');
        if (!is_array($resolutions)) {
            $resolutions = array();
        }
        $repo = new Wedding_RSVP_Guest_Repo();
        try {
            $ops = (new Wedding_RSVP_Importer())->plan($stored['diff'], $resolutions, function () use ($repo) {
                return Wedding_RSVP_Codes::unique_code(function ($code) use ($repo) {
                    return $repo->code_exists($code);
                });
            }, $stored['existing']);
            $result = $repo->run_import($ops);
        } catch (Wedding_RSVP_Import_Exception $e) {
            return new WP_Error('wedding_import', $e->getMessage(), array('status' => 400));
        } catch (Exception $e) {
            return new WP_Error('wedding_import', 'Không lưu được dữ liệu. Chưa có thay đổi nào được ghi.', array('status' => 500));
        }
        delete_transient('wedding_imp_' . $token);
        $result['unchanged'] = (int) $stored['diff']['counts']['unchanged'];
        return rest_ensure_response($result);
    }

    public function list_pickups() {
        $repo = new Wedding_RSVP_Pickup_Repo();
        $guests = new Wedding_RSVP_Guest_Repo();
        $rows = $repo->all();
        foreach ($rows as &$row) {
            $row['guest_count'] = $guests->count_by_pickup($row['id']);
        }
        unset($row);
        return rest_ensure_response($rows);
    }

    public function create_pickup(WP_REST_Request $request) {
        try {
            $pickup = (new Wedding_RSVP_Pickup_Repo())->create($this->pickup_input($request));
        } catch (Wedding_RSVP_Validation_Exception $e) {
            return new WP_Error('wedding_pickup', $e->getMessage(), array('status' => 400));
        }
        $pickup['guest_count'] = 0;
        return rest_ensure_response($pickup);
    }

    public function update_pickup(WP_REST_Request $request) {
        $repo = new Wedding_RSVP_Pickup_Repo();
        if (!$repo->find((int) $request['id'])) {
            return new WP_Error('wedding_pickup', 'Không tìm thấy điểm đón.', array('status' => 404));
        }
        try {
            $pickup = $repo->update((int) $request['id'], $this->pickup_input($request));
        } catch (Wedding_RSVP_Validation_Exception $e) {
            return new WP_Error('wedding_pickup', $e->getMessage(), array('status' => 400));
        }
        $pickup['guest_count'] = (new Wedding_RSVP_Guest_Repo())->count_by_pickup((int) $pickup['id']);
        return rest_ensure_response($pickup);
    }

    public function delete_pickup(WP_REST_Request $request) {
        try {
            (new Wedding_RSVP_Pickup_Repo())->delete((int) $request['id'], $request->get_param('reassign_to'));
        } catch (Wedding_RSVP_Validation_Exception $e) {
            return new WP_Error('wedding_pickup', $e->getMessage(), array('status' => 400));
        }
        return rest_ensure_response(array('deleted' => true));
    }

    public function get_settings() {
        return rest_ensure_response(Wedding_RSVP_Settings::get());
    }

    public function save_settings(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        return rest_ensure_response(Wedding_RSVP_Settings::update($params));
    }

    private function pickup_input(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        return is_array($params) ? $params : array();
    }

    private function pickup_value($value) {
        if ($value === null || $value === '' || $value === 0 || $value === '0' || $value === false) {
            return null;
        }
        $id = (int) $value;
        if ($id <= 0 || !(new Wedding_RSVP_Pickup_Repo())->find($id)) {
            return new WP_Error('wedding_pickup', 'Điểm đón không hợp lệ.', array('status' => 400));
        }
        return $id;
    }

    private function is_on($value) {
        return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes';
    }

    private function visible_pickup($guest) {
        if (!$guest || empty($guest['pickup_id']) || empty($guest['pickup_notified_at'])) {
            return null;
        }
        $pickup = (new Wedding_RSVP_Pickup_Repo())->find($guest['pickup_id']);
        if (!$pickup) {
            return null;
        }
        return array(
            'name' => $pickup['name'],
            'address' => $pickup['address'],
            'pickup_time' => $pickup['pickup_time'],
        );
    }

    private function rate_limited($code) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0';
        $key = 'wrsvp_' . md5($ip . '|' . $code);
        $count = (int) get_transient($key);
        if ($count >= 20) {
            return new WP_Error('wedding_rate', 'Bạn gửi hơi nhiều lần. Đợi vài phút rồi thử lại nhé.', array('status' => 429));
        }
        set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return null;
    }

    private function recent(array $guests) {
        $rows = array();
        foreach ($guests as $guest) {
            if (empty($guest['response']['updated_at'])) {
                continue;
            }
            $rows[] = $guest;
        }
        usort($rows, function ($a, $b) {
            return strcmp($b['response']['updated_at'], $a['response']['updated_at']);
        });
        $rows = array_slice($rows, 0, 10);
        $out = array();
        foreach ($rows as $guest) {
            $out[] = array(
                'id' => (int) $guest['id'],
                'name' => trim($guest['salutation'] . ' ' . $guest['name']),
                'updated_at' => Wedding_RSVP_Settings::format_datetime($guest['response']['updated_at']),
                'kind' => (int) $guest['response']['update_count'] > 0 ? 'Sửa' : 'Mới',
                'attending' => Wedding_RSVP_Templates::attending_label($guest['response']['attending']),
            );
        }
        return $out;
    }
}
