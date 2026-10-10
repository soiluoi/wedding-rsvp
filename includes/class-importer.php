<?php

class Wedding_RSVP_Importer {
    public function build_diff(array $parsed_rows, array $existing_guests, $mode) {
        if ($mode !== 'sync' && $mode !== 'upsert') {
            throw new Wedding_RSVP_Import_Exception('Chế độ import không hợp lệ.');
        }

        $existing_by_code = array();
        $existing_by_id = array();
        foreach ($existing_guests as $guest) {
            $guest['id'] = (int) $guest['id'];
            $guest['code'] = strtolower((string) $guest['code']);
            $guest['name_normalized'] = isset($guest['name_normalized']) && $guest['name_normalized'] !== ''
                ? $guest['name_normalized']
                : Wedding_RSVP_Text::normalize_name($guest['name']);
            $guest['host_normalized'] = Wedding_RSVP_Text::normalize_name(isset($guest['host']) ? $guest['host'] : '');
            $existing_by_id[$guest['id']] = $guest;
            if ($guest['code'] !== '') {
                $existing_by_code[$guest['code']] = $guest;
            }
        }

        $code_lines = array();
        foreach ($parsed_rows as $row) {
            $code = strtolower(trim((string) $row['code']));
            if ($code !== '' && Wedding_RSVP_Codes::is_valid_code($code)) {
                $code_lines[$code][] = (int) $row['line'];
            }
        }

        $claimed = array();
        $result_rows = array();
        $position = 0;
        foreach ($parsed_rows as $row) {
            $position++;
            $built = $this->prepare_row($row);
            $built['incoming']['sort_order'] = $position;
            $code = $built['incoming']['code'];
            if ($code !== '' && isset($code_lines[$code]) && count($code_lines[$code]) > 1) {
                $built['status'] = 'error';
                $built['errors'][] = 'Mã bị trùng trong file.';
                $result_rows[] = $built;
                continue;
            }
            if ($built['errors']) {
                $built['status'] = 'error';
                $result_rows[] = $built;
                continue;
            }
            if ($code !== '') {
                if (isset($existing_by_code[$code])) {
                    $guest = $existing_by_code[$code];
                    if (isset($claimed[$guest['id']])) {
                        $built['status'] = 'error';
                        $built['errors'][] = 'Mã này đã được gắn với một dòng khác.';
                        $result_rows[] = $built;
                        continue;
                    }
                    $claimed[$guest['id']] = true;
                    $result_rows[] = $this->match_existing($built, $guest, 'code');
                    continue;
                }
                $built['status'] = 'new';
                $built['match'] = 'preserved_code';
                $built['warnings'][] = 'Mã chưa có trong danh sách. Sẽ tạo khách mới và giữ nguyên mã này.';
                $result_rows[] = $built;
                continue;
            }
            $result_rows[] = $built;
        }

        foreach ($result_rows as $index => $built) {
            if ($built['status'] !== 'pending') {
                continue;
            }
            $name_key = Wedding_RSVP_Text::normalize_name($built['incoming']['name']);
            $host_key = Wedding_RSVP_Text::normalize_name($built['incoming']['host']);
            $candidates = array();
            foreach ($existing_by_id as $guest) {
                if (isset($claimed[$guest['id']])) {
                    continue;
                }
                if ($guest['name_normalized'] === $name_key && $guest['host_normalized'] === $host_key) {
                    $candidates[] = $guest;
                }
            }
            if (count($candidates) === 1) {
                $guest = $candidates[0];
                $claimed[$guest['id']] = true;
                $result_rows[$index] = $this->match_existing($built, $guest, 'name');
                continue;
            }
            if (count($candidates) > 1) {
                $built['status'] = 'conflict';
                $built['candidates'] = array();
                foreach ($candidates as $guest) {
                    $built['candidates'][] = array(
                        'id' => (int) $guest['id'],
                        'code' => $guest['code'],
                        'name' => $guest['name'],
                        'salutation' => isset($guest['salutation']) ? $guest['salutation'] : '',
                        'host' => isset($guest['host']) ? $guest['host'] : '',
                        'group_name' => isset($guest['group_name']) ? $guest['group_name'] : '',
                        'status' => isset($guest['status']) ? $guest['status'] : 'active',
                    );
                }
                $result_rows[$index] = $built;
                continue;
            }
            $built['status'] = 'new';
            $built['match'] = 'none';
            $result_rows[$index] = $built;
        }

        $conflict_ids = array();
        foreach ($result_rows as $built) {
            if ($built['status'] !== 'conflict') {
                continue;
            }
            foreach ($built['candidates'] as $candidate) {
                $conflict_ids[(int) $candidate['id']] = true;
            }
        }

        $to_inactivate = array();
        if ($mode === 'sync') {
            foreach ($existing_by_id as $guest) {
                if ((isset($guest['status']) ? $guest['status'] : 'active') !== 'active') {
                    continue;
                }
                if (isset($claimed[$guest['id']]) || isset($conflict_ids[$guest['id']])) {
                    continue;
                }
                $to_inactivate[] = array(
                    'id' => (int) $guest['id'],
                    'code' => $guest['code'],
                    'name' => $guest['name'],
                    'host' => isset($guest['host']) ? $guest['host'] : '',
                );
            }
        }

        $counts = array(
            'new' => 0,
            'update' => 0,
            'unchanged' => 0,
            'reactivated' => 0,
            'conflict' => 0,
            'error' => 0,
            'inactivate' => count($to_inactivate),
        );
        foreach ($result_rows as $built) {
            if (isset($counts[$built['status']])) {
                $counts[$built['status']]++;
            }
        }

        return array(
            'mode' => $mode,
            'rows' => $result_rows,
            'to_inactivate' => $to_inactivate,
            'has_errors' => $counts['error'] > 0,
            'has_conflicts' => $counts['conflict'] > 0,
            'counts' => $counts,
        );
    }

    public function plan(array $diff, array $resolutions, callable $generate_code, array $existing_guests) {
        if (!empty($diff['has_errors'])) {
            throw new Wedding_RSVP_Import_Exception('File còn dòng lỗi, chưa áp dụng được.');
        }

        $existing_by_id = array();
        foreach ($existing_guests as $guest) {
            $existing_by_id[(int) $guest['id']] = $guest;
        }

        $claimed = array();
        $ops = array();
        foreach ($diff['rows'] as $row) {
            if ($row['status'] === 'error') {
                throw new Wedding_RSVP_Import_Exception('File còn dòng lỗi, chưa áp dụng được.');
            }
            if (in_array($row['status'], array('update', 'unchanged', 'reactivated'), true)) {
                $claimed[(int) $row['guest_id']] = true;
            }
        }

        foreach ($diff['rows'] as $row) {
            if ($row['status'] === 'unchanged') {
                continue;
            }
            if ($row['status'] === 'new') {
                $code = $row['match'] === 'preserved_code'
                    ? $row['incoming']['code']
                    : call_user_func($generate_code);
                if (!Wedding_RSVP_Codes::is_valid_code($code) || !Wedding_RSVP_Codes::has_letter($code)) {
                    throw new Wedding_RSVP_Import_Exception('Không sinh được mã hợp lệ.');
                }
                $ops[] = array(
                    'op' => 'insert',
                    'line' => (int) $row['line'],
                    'code' => $code,
                    'data' => Wedding_RSVP_Guest_Repo::sanitize_import_payload($row['incoming'], 'active'),
                );
                continue;
            }
            if ($row['status'] === 'update' || $row['status'] === 'reactivated') {
                $ops[] = array(
                    'op' => 'update',
                    'line' => (int) $row['line'],
                    'id' => (int) $row['guest_id'],
                    'status' => 'active',
                    'reactivated' => $row['status'] === 'reactivated',
                    'data' => Wedding_RSVP_Guest_Repo::sanitize_import_payload($row['incoming'], 'active'),
                );
                continue;
            }
            if ($row['status'] !== 'conflict') {
                continue;
            }
            $line = (int) $row['line'];
            $resolution = $this->resolution_for($resolutions, $line);
            if ($resolution === null) {
                throw new Wedding_RSVP_Import_Exception('Dòng ' . $line . ' còn xung đột, bạn chọn khách hoặc tạo mới giúp mình.');
            }
            if ($resolution['action'] === 'create') {
                $code = call_user_func($generate_code);
                if (!Wedding_RSVP_Codes::is_valid_code($code) || !Wedding_RSVP_Codes::has_letter($code)) {
                    throw new Wedding_RSVP_Import_Exception('Không sinh được mã hợp lệ.');
                }
                $ops[] = array(
                    'op' => 'insert',
                    'line' => $line,
                    'code' => $code,
                    'data' => Wedding_RSVP_Guest_Repo::sanitize_import_payload($row['incoming'], 'active'),
                );
                continue;
            }
            if ($resolution['action'] !== 'match') {
                throw new Wedding_RSVP_Import_Exception('Cách xử lý xung đột ở dòng ' . $line . ' không hợp lệ.');
            }
            $guest_id = (int) $resolution['guest_id'];
            $allowed = array();
            foreach ($row['candidates'] as $candidate) {
                $allowed[(int) $candidate['id']] = true;
            }
            if (empty($allowed[$guest_id])) {
                throw new Wedding_RSVP_Import_Exception('Khách được chọn ở dòng ' . $line . ' không nằm trong danh sách trùng.');
            }
            if (isset($claimed[$guest_id])) {
                throw new Wedding_RSVP_Import_Exception('Một khách bị gắn vào hai dòng. Bạn chọn lại giúp mình.');
            }
            $claimed[$guest_id] = true;
            $guest = $existing_by_id[$guest_id];
            $reactivated = (isset($guest['status']) ? $guest['status'] : 'active') !== 'active';
            $ops[] = array(
                'op' => 'update',
                'line' => $line,
                'id' => $guest_id,
                'status' => 'active',
                'reactivated' => $reactivated,
                'data' => Wedding_RSVP_Guest_Repo::sanitize_import_payload($row['incoming'], 'active'),
            );
        }

        if ($diff['mode'] === 'sync') {
            foreach ($existing_guests as $guest) {
                $id = (int) $guest['id'];
                if ((isset($guest['status']) ? $guest['status'] : 'active') !== 'active') {
                    continue;
                }
                if (isset($claimed[$id])) {
                    continue;
                }
                $ops[] = array(
                    'op' => 'inactivate',
                    'id' => $id,
                    'name' => isset($guest['name']) ? $guest['name'] : '',
                    'data' => Wedding_RSVP_Guest_Repo::sanitize_import_payload(array(), 'inactive'),
                );
            }
        }

        return $ops;
    }

    private function resolution_for(array $resolutions, $line) {
        if (isset($resolutions[$line]) && is_array($resolutions[$line])) {
            return $resolutions[$line];
        }
        $key = (string) $line;
        if (isset($resolutions[$key]) && is_array($resolutions[$key])) {
            return $resolutions[$key];
        }
        return null;
    }

    private function prepare_row(array $row) {
        $warnings = array();
        $errors = array();
        $salutation = trim((string) $row['salutation']);
        $name = trim((string) $row['name']);
        $host = trim((string) $row['host']);
        $note = trim((string) (isset($row['note']) ? $row['note'] : ''));
        $group = trim((string) (isset($row['group_name']) ? $row['group_name'] : ''));
        $code = strtolower(trim((string) (isset($row['code']) ? $row['code'] : '')));
        if ($salutation === '') {
            $errors[] = 'Thiếu danh xưng.';
        }
        if ($name === '') {
            $errors[] = 'Thiếu tên trên thiệp.';
        }
        if ($host === '') {
            $errors[] = 'Thiếu khách của.';
        }
        if ($code !== '' && !Wedding_RSVP_Codes::is_valid_code($code)) {
            $errors[] = 'Mã không đúng định dạng.';
        }
        $invited = $this->normalize_count(isset($row['invited_count']) ? $row['invited_count'] : '', $warnings);
        return array(
            'line' => (int) $row['line'],
            'status' => $errors ? 'error' : 'pending',
            'match' => null,
            'guest_id' => null,
            'incoming' => array(
                'code' => $code,
                'salutation' => $salutation,
                'name' => $name,
                'note' => $note,
                'invited_count' => $invited,
                'host' => $host,
                'group_name' => $group,
                'sort_order' => (int) $row['line'],
            ),
            'changes' => array(),
            'warnings' => $warnings,
            'errors' => $errors,
            'candidates' => array(),
        );
    }

    private function match_existing(array $built, array $guest, $via) {
        $built['match'] = $via;
        $built['guest_id'] = (int) $guest['id'];
        $labels = array(
            'salutation' => 'Danh xưng',
            'name' => 'Tên trên thiệp',
            'note' => 'Note',
            'invited_count' => 'Số người',
            'host' => 'Khách của',
            'group_name' => 'Nhóm khách',
            'sort_order' => 'Thứ tự',
        );
        $changes = array();
        foreach (Wedding_RSVP_Guest_Repo::IMPORTABLE_FIELDS as $field) {
            $from = isset($guest[$field]) ? $guest[$field] : '';
            $to = $built['incoming'][$field];
            if ($field === 'invited_count' || $field === 'sort_order') {
                $same = (int) $from === (int) $to;
            } else {
                $same = trim((string) $from) === trim((string) $to);
            }
            if (!$same) {
                $changes[$field] = array(
                    'label' => $labels[$field],
                    'from' => (string) $from,
                    'to' => (string) $to,
                );
            }
        }
        $old_count = (int) (isset($guest['invited_count']) ? $guest['invited_count'] : 1);
        $new_count = (int) $built['incoming']['invited_count'];
        if ($old_count === 2 && $new_count === 1 && (int) (isset($guest['has_companion']) ? $guest['has_companion'] : 0) === 1) {
            $built['warnings'][] = 'Số người giảm từ 2 xuống 1 nhưng khách đã đăng ký người đi cùng. Câu trả lời sẽ được giữ nguyên.';
        }
        $built['changes'] = $changes;
        $inactive = (isset($guest['status']) ? $guest['status'] : 'active') !== 'active';
        if ($inactive) {
            $built['status'] = 'reactivated';
            return $built;
        }
        $built['status'] = $changes ? 'update' : 'unchanged';
        return $built;
    }

    private function normalize_count($raw, array &$warnings) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return 1;
        }
        $normalized = str_replace(',', '.', $raw);
        if (!is_numeric($normalized)) {
            $warnings[] = 'Số người không hợp lệ, đã đưa về 1.';
            return 1;
        }
        $number = (float) $normalized;
        if ($number == 1) {
            return 1;
        }
        if ($number == 2) {
            return 2;
        }
        if ($number < 1) {
            $warnings[] = 'Số người không hợp lệ, đã đưa về 1.';
            return 1;
        }
        $warnings[] = 'Số người nằm ngoài 1–2, đã đưa về 2.';
        return 2;
    }
}
