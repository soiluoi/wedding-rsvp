<?php

class Wedding_RSVP_Tabular_Parser {
    const HEADER_MAP = array(
        'ma' => 'code',
        'danh xung' => 'salutation',
        'ten tren thiep' => 'name',
        'note' => 'note',
        'so nguoi' => 'invited_count',
        'khach cua' => 'host',
        'nhom khach' => 'group_name',
    );

    const REQUIRED = array('salutation', 'name', 'host');

    public function parse($text, $format = 'auto') {
        $text = $this->normalize_encoding((string) $text);
        if (trim($text) === '') {
            throw new Wedding_RSVP_Parse_Exception('Không có dữ liệu để đọc.');
        }
        $forced = null;
        if (preg_match('/^sep=(.)\n/i', $text, $sep)) {
            $forced = $sep[1];
            $text = substr($text, strlen($sep[0]));
        }
        $delimiter = $forced !== null ? $forced : $this->detect_delimiter($text, $format);
        $records = $this->parse_records($text, $delimiter);
        $records = array_values(array_filter($records, array($this, 'record_has_content')));
        if (count($records) < 2) {
            throw new Wedding_RSVP_Parse_Exception('Cần có dòng tiêu đề và ít nhất một dòng khách.');
        }
        if (count($records) > 5001) {
            throw new Wedding_RSVP_Parse_Exception('File có quá nhiều dòng.');
        }

        $map = $this->map_headers($records[0]['cells']);
        $missing = array();
        foreach (self::REQUIRED as $field) {
            if (!isset($map[$field])) {
                $missing[] = $this->header_label($field);
            }
        }
        if ($missing) {
            throw new Wedding_RSVP_Parse_Exception('Thiếu cột bắt buộc: ' . implode(', ', $missing) . '.');
        }

        $rows = array();
        for ($i = 1; $i < count($records); $i++) {
            $record = $records[$i];
            $row = array(
                'line' => $record['line'],
                'code' => '',
                'salutation' => '',
                'name' => '',
                'note' => '',
                'invited_count' => '',
                'host' => '',
                'group_name' => '',
            );
            foreach ($map as $field => $index) {
                $row[$field] = isset($record['cells'][$index]) ? trim((string) $record['cells'][$index]) : '';
            }
            $row['code'] = strtolower(trim($row['code']));
            $rows[] = $row;
        }
        if (!$rows) {
            throw new Wedding_RSVP_Parse_Exception('Không có dòng khách nào sau tiêu đề.');
        }
        return $rows;
    }

    private function header_label($field) {
        $labels = array(
            'salutation' => 'Danh xưng',
            'name' => 'Tên trên thiệp',
            'host' => 'Khách của',
        );
        return isset($labels[$field]) ? $labels[$field] : $field;
    }

    private function map_headers(array $cells) {
        $map = array();
        foreach ($cells as $index => $cell) {
            $key = Wedding_RSVP_Text::normalize_header($cell);
            if ($key === '' || $key === '#' || !isset(self::HEADER_MAP[$key])) {
                continue;
            }
            $field = self::HEADER_MAP[$key];
            if (!isset($map[$field])) {
                $map[$field] = $index;
            }
        }
        return $map;
    }

    private function record_has_content($record) {
        foreach ($record['cells'] as $cell) {
            if (trim((string) $cell) !== '') {
                return true;
            }
        }
        return false;
    }

    private function normalize_encoding($text) {
        if (substr($text, 0, 3) === "\xEF\xBB\xBF") {
            $text = substr($text, 3);
        } elseif (substr($text, 0, 2) === "\xFF\xFE") {
            $text = mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16LE');
        } elseif (substr($text, 0, 2) === "\xFE\xFF") {
            $text = mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16BE');
        }
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        return $text;
    }

    private function detect_delimiter($text, $format) {
        if ($format === 'tsv') {
            return "\t";
        }
        $sample = $this->first_record_text($text);
        $counts = array(
            "\t" => substr_count($sample, "\t"),
            ';' => substr_count($sample, ';'),
            ',' => substr_count($sample, ','),
        );
        if ($format === 'csv') {
            $counts["\t"] = -1;
        }
        $best = ',';
        $best_count = -1;
        $priority = array("\t" => 3, ';' => 2, ',' => 1);
        foreach ($counts as $candidate => $count) {
            if ($count > $best_count || ($count === $best_count && $priority[$candidate] > $priority[$best])) {
                $best = $candidate;
                $best_count = $count;
            }
        }
        if ($best_count < 1) {
            return ',';
        }
        return $best;
    }

    private function first_record_text($text) {
        $length = strlen($text);
        $in_quotes = false;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === '"') {
                if ($in_quotes && $i + 1 < $length && $text[$i + 1] === '"') {
                    $out .= '""';
                    $i++;
                    continue;
                }
                $in_quotes = !$in_quotes;
                $out .= $char;
                continue;
            }
            if ($char === "\n" && !$in_quotes) {
                break;
            }
            $out .= $char;
        }
        return $out;
    }

    private function parse_records($text, $delimiter) {
        $rows = array();
        $row = array();
        $field = '';
        $in_quotes = false;
        $length = strlen($text);
        $line = 1;
        $start_line = 1;
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($in_quotes) {
                if ($char === '"') {
                    if ($i + 1 < $length && $text[$i + 1] === '"') {
                        $field .= '"';
                        $i++;
                    } else {
                        $in_quotes = false;
                    }
                } else {
                    if ($char === "\n") {
                        $line++;
                    }
                    $field .= $char;
                }
                continue;
            }
            if ($char === '"') {
                $in_quotes = true;
                continue;
            }
            if ($char === $delimiter) {
                $row[] = $field;
                $field = '';
                continue;
            }
            if ($char === "\n") {
                $row[] = $field;
                $rows[] = array('line' => $start_line, 'cells' => $row);
                $row = array();
                $field = '';
                $line++;
                $start_line = $line;
                continue;
            }
            $field .= $char;
        }
        if ($field !== '' || $row) {
            $row[] = $field;
            $rows[] = array('line' => $start_line, 'cells' => $row);
        }
        return $rows;
    }
}
