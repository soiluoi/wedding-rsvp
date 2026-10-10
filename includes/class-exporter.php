<?php

class Wedding_RSVP_Exporter {
    public static function presets() {
        return array(
            'invite' => array(
                'label' => 'Danh sách mời',
                'columns' => array(
                    array('key' => 'sort_order', 'header' => '#'),
                    array('key' => 'code', 'header' => 'Mã'),
                    array('key' => 'salutation', 'header' => 'Danh xưng'),
                    array('key' => 'name', 'header' => 'Tên trên thiệp'),
                    array('key' => 'note', 'header' => 'Note'),
                    array('key' => 'invited_count', 'header' => 'Số người'),
                    array('key' => 'host', 'header' => 'Khách của'),
                    array('key' => 'group_name', 'header' => 'Nhóm khách'),
                    array('key' => 'link', 'header' => 'Link'),
                ),
            ),
            'full' => array(
                'label' => 'Đầy đủ',
                'columns' => array(
                    array('key' => 'sort_order', 'header' => '#'),
                    array('key' => 'code', 'header' => 'Mã'),
                    array('key' => 'salutation', 'header' => 'Danh xưng'),
                    array('key' => 'name', 'header' => 'Tên trên thiệp'),
                    array('key' => 'note', 'header' => 'Note'),
                    array('key' => 'invited_count', 'header' => 'Số người'),
                    array('key' => 'host', 'header' => 'Khách của'),
                    array('key' => 'group_name', 'header' => 'Nhóm khách'),
                    array('key' => 'link', 'header' => 'Link'),
                    array('key' => 'attending', 'header' => 'Tham dự'),
                    array('key' => 'ride_bus', 'header' => 'Đi xe'),
                    array('key' => 'phone', 'header' => 'SĐT'),
                    array('key' => 'companion_name', 'header' => 'Tên người đi cùng'),
                    array('key' => 'companion_type', 'header' => 'Người đi cùng là'),
                    array('key' => 'companion_age', 'header' => 'Tuổi'),
                    array('key' => 'diet_note', 'header' => 'Ghi chú ăn uống'),
                    array('key' => 'message', 'header' => 'Lời chúc'),
                    array('key' => 'pickup_name', 'header' => 'Điểm đón'),
                    array('key' => 'pickup_address', 'header' => 'Địa chỉ đón'),
                    array('key' => 'pickup_time', 'header' => 'Giờ đón'),
                    array('key' => 'link_sent_at', 'header' => 'Đã gửi link'),
                    array('key' => 'pickup_notified_at', 'header' => 'Đã báo điểm đón'),
                    array('key' => 'response_updated_at', 'header' => 'Cập nhật lúc'),
                ),
            ),
            'bus' => array(
                'label' => 'Đi xe',
                'columns' => array(
                    array('key' => 'name', 'header' => 'Tên'),
                    array('key' => 'host', 'header' => 'Khách của'),
                    array('key' => 'group_name', 'header' => 'Nhóm'),
                    array('key' => 'phone', 'header' => 'SĐT'),
                    array('key' => 'companion', 'header' => 'Người đi cùng'),
                    array('key' => 'bus_seats', 'header' => 'Số ghế'),
                    array('key' => 'pickup_name', 'header' => 'Điểm đón'),
                ),
            ),
            'party' => array(
                'label' => 'Tiệc',
                'columns' => array(
                    array('key' => 'name', 'header' => 'Tên'),
                    array('key' => 'host', 'header' => 'Khách của'),
                    array('key' => 'group_name', 'header' => 'Nhóm'),
                    array('key' => 'adults', 'header' => 'Người lớn'),
                    array('key' => 'children', 'header' => 'Trẻ em'),
                    array('key' => 'diet_note', 'header' => 'Ghi chú ăn uống'),
                ),
            ),
        );
    }

    public static function matrix(array $guests, $preset, array $settings) {
        $presets = self::presets();
        if (!isset($presets[$preset])) {
            $preset = 'full';
        }
        $columns = $presets[$preset]['columns'];
        $header = array();
        foreach ($columns as $column) {
            $header[] = $column['header'];
        }
        $matrix = array($header);
        foreach ($guests as $guest) {
            $line = array();
            foreach ($columns as $column) {
                $line[] = Wedding_RSVP_Text::clean_cell(self::value($guest, $column['key'], $settings));
            }
            $matrix[] = $line;
        }
        return $matrix;
    }

    public static function to_csv(array $matrix) {
        $handle = fopen('php://temp', 'r+');
        foreach ($matrix as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        $csv = str_replace("\r\n", "\n", $csv);
        $csv = str_replace("\n", "\r\n", $csv);
        return "\xEF\xBB\xBF" . $csv;
    }

    public static function format_phone($digits) {
        $digits = preg_replace('/\D/', '', (string) $digits);
        if (strlen($digits) !== 10) {
            return (string) $digits;
        }
        return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 3);
    }

    public static function value(array $guest, $key, array $settings) {
        $response = (isset($guest['response']) && is_array($guest['response'])) ? $guest['response'] : null;
        $attending = $response && isset($response['attending']) ? $response['attending'] : '';
        $units = array('adults' => 0, 'children' => 0, 'bus' => 0);
        if ($attending === 'yes' && $response) {
            $units = Wedding_Stats::units($response, $settings);
        }
        switch ($key) {
            case 'sort_order':
                return (string) (isset($guest['sort_order']) ? $guest['sort_order'] : '');
            case 'code':
                return (string) $guest['code'];
            case 'salutation':
                return (string) $guest['salutation'];
            case 'name':
                return (string) $guest['name'];
            case 'note':
                return (string) (isset($guest['note']) ? $guest['note'] : '');
            case 'invited_count':
                return (string) (isset($guest['invited_count']) ? $guest['invited_count'] : 1);
            case 'host':
                return (string) $guest['host'];
            case 'group_name':
                return (string) (isset($guest['group_name']) ? $guest['group_name'] : '');
            case 'link':
                return (string) (isset($guest['link']) ? $guest['link'] : '');
            case 'attending':
                return Wedding_RSVP_Templates::attending_label($attending);
            case 'ride_bus':
                $ride = $response && array_key_exists('ride_bus', $response) ? $response['ride_bus'] : null;
                return Wedding_RSVP_Templates::ride_label($attending === 'no' ? null : $ride);
            case 'phone':
                $phone = $response && !empty($response['phone']) ? $response['phone'] : '';
                return $phone === '' ? '' : self::format_phone($phone);
            case 'companion_name':
                return $response && !empty($response['companion_name']) ? (string) $response['companion_name'] : '';
            case 'companion_type':
                return Wedding_RSVP_Templates::companion_type_label($response);
            case 'companion_age':
                if (!$response || (isset($response['companion_type']) ? $response['companion_type'] : '') !== 'child' || !isset($response['companion_age']) || $response['companion_age'] === null) {
                    return '';
                }
                return (string) $response['companion_age'];
            case 'diet_note':
                return $response && !empty($response['diet_note']) ? (string) $response['diet_note'] : '';
            case 'message':
                return $response && !empty($response['message']) ? (string) $response['message'] : '';
            case 'pickup_name':
                return (string) (isset($guest['pickup_name']) ? $guest['pickup_name'] : '');
            case 'pickup_address':
                return (string) (isset($guest['pickup_address']) ? $guest['pickup_address'] : '');
            case 'pickup_time':
                return (string) (isset($guest['pickup_time']) ? $guest['pickup_time'] : '');
            case 'link_sent_at':
                return Wedding_RSVP_Settings::format_datetime(isset($guest['link_sent_at']) ? $guest['link_sent_at'] : '');
            case 'pickup_notified_at':
                return Wedding_RSVP_Settings::format_datetime(isset($guest['pickup_notified_at']) ? $guest['pickup_notified_at'] : '');
            case 'response_updated_at':
                $updated = $response && isset($response['updated_at']) ? $response['updated_at'] : '';
                return Wedding_RSVP_Settings::format_datetime($updated);
            case 'companion':
                return Wedding_RSVP_Templates::companion_label($response);
            case 'bus_seats':
                return (string) $units['bus'];
            case 'adults':
                return (string) $units['adults'];
            case 'children':
                return (string) $units['children'];
            default:
                return '';
        }
    }
}
