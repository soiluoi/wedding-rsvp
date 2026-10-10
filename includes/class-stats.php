<?php

class Wedding_Stats {
    public static function summarize(array $guests, array $settings, array $pickups = array()) {
        $people_per_table = max(1, (int) (isset($settings['people_per_table']) ? $settings['people_per_table'] : 10));
        $seats_per_bus = max(1, (int) (isset($settings['seats_per_bus']) ? $settings['seats_per_bus'] : 43));

        $invited = 0;
        $responded = 0;
        $pending = 0;
        $yes = 0;
        $no = 0;
        $maybe = 0;
        $adults = 0;
        $children = 0;
        $bus_seats = 0;
        $maybe_adults = 0;
        $maybe_children = 0;
        $maybe_bus_seats = 0;
        $groups = array();
        $pickup_seats = array();
        $pickup_unnotified = array();
        $unassigned_guests = 0;
        $unassigned_seats = 0;

        foreach ($guests as $guest) {
            if ((isset($guest['status']) ? $guest['status'] : 'active') !== 'active') {
                continue;
            }
            $invited++;
            $host = (string) (isset($guest['host']) ? $guest['host'] : '');
            $group_name = (string) (isset($guest['group_name']) ? $guest['group_name'] : '');
            if (!isset($groups[$host])) {
                $groups[$host] = array();
            }
            if (!isset($groups[$host][$group_name])) {
                $groups[$host][$group_name] = self::empty_source_row($group_name);
            }
            $groups[$host][$group_name]['invited']++;

            $response = (isset($guest['response']) && is_array($guest['response'])) ? $guest['response'] : null;
            $attending = $response && isset($response['attending']) ? (string) $response['attending'] : '';
            if ($response && $attending !== '') {
                $responded++;
                $groups[$host][$group_name]['responded']++;
            } else {
                $pending++;
            }

            $units = null;
            if ($attending === 'yes' || $attending === 'maybe') {
                $units = self::units($response, $settings);
            }

            if ($attending === 'yes') {
                $yes++;
                $adults += $units['adults'];
                $children += $units['children'];
                $bus_seats += $units['bus'];
                $groups[$host][$group_name]['attending_people'] += $units['adults'] + $units['children'];
                $groups[$host][$group_name]['bus_seats'] += $units['bus'];
            } elseif ($attending === 'maybe') {
                $maybe++;
                $maybe_adults += $units['adults'];
                $maybe_children += $units['children'];
                $maybe_bus_seats += $units['bus'];
                $groups[$host][$group_name]['maybe_bus_seats'] += $units['bus'];
            } elseif ($attending === 'no') {
                $no++;
            }

            $pickup_id = isset($guest['pickup_id']) ? (int) $guest['pickup_id'] : 0;
            if ($pickup_id > 0) {
                if (!isset($pickup_seats[$pickup_id])) {
                    $pickup_seats[$pickup_id] = 0;
                    $pickup_unnotified[$pickup_id] = 0;
                }
                if (empty($guest['pickup_notified_at'])) {
                    $pickup_unnotified[$pickup_id]++;
                }
                if ($attending === 'yes' && $units) {
                    $pickup_seats[$pickup_id] += $units['bus'];
                }
            } elseif ($units && (int) (isset($response['ride_bus']) ? $response['ride_bus'] : 0) === 1) {
                $unassigned_guests++;
                if ($attending === 'yes') {
                    $unassigned_seats += $units['bus'];
                }
            }
        }

        $max_adults = $adults + $maybe_adults;
        $max_children = $children + $maybe_children;
        $max_bus_seats = $bus_seats + $maybe_bus_seats;
        $child_table = self::flag($settings, 'child_counts_for_table', true);
        $table_people = $adults + ($child_table ? $children : 0);
        $max_table_people = $max_adults + ($child_table ? $max_children : 0);

        ksort($groups);
        $source_groups = array();
        $source_total = self::empty_source_row('');
        unset($source_total['group_name']);
        foreach ($groups as $host => $rows) {
            ksort($rows);
            $subtotal = self::empty_source_row('');
            unset($subtotal['group_name']);
            $list = array();
            foreach ($rows as $row) {
                $list[] = $row;
                foreach (array('invited', 'responded', 'attending_people', 'bus_seats', 'maybe_bus_seats') as $key) {
                    $subtotal[$key] += $row[$key];
                    $source_total[$key] += $row[$key];
                }
            }
            $source_groups[] = array(
                'host' => $host === '' ? 'Không rõ' : $host,
                'rows' => $list,
                'subtotal' => $subtotal,
            );
        }

        $pickup_rows = array();
        $seen = array();
        foreach ($pickups as $pickup) {
            $id = (int) $pickup['id'];
            $seen[$id] = true;
            $pickup_rows[] = array(
                'id' => $id,
                'name' => (string) $pickup['name'],
                'seats' => isset($pickup_seats[$id]) ? $pickup_seats[$id] : 0,
                'unnotified_guests' => isset($pickup_unnotified[$id]) ? $pickup_unnotified[$id] : 0,
            );
        }
        foreach ($pickup_seats as $id => $seats) {
            if (isset($seen[$id])) {
                continue;
            }
            $pickup_rows[] = array(
                'id' => (int) $id,
                'name' => 'Điểm #' . $id,
                'seats' => $seats,
                'unnotified_guests' => isset($pickup_unnotified[$id]) ? $pickup_unnotified[$id] : 0,
            );
        }

        return array(
            'invited' => $invited,
            'responded' => $responded,
            'pending' => $pending,
            'yes' => $yes,
            'no' => $no,
            'maybe' => $maybe,
            'response_rate' => $invited === 0 ? 0 : (int) round(($responded / $invited) * 100),
            'adults' => $adults,
            'children' => $children,
            'party_total' => $adults + $children,
            'tables' => (int) ceil($table_people / $people_per_table),
            'max_adults' => $max_adults,
            'max_children' => $max_children,
            'max_party_total' => $max_adults + $max_children,
            'max_tables' => (int) ceil($max_table_people / $people_per_table),
            'bus_seats' => $bus_seats,
            'buses' => $bus_seats === 0 ? 0 : (int) ceil($bus_seats / $seats_per_bus),
            'bus_empty_seats' => self::empty_seats($bus_seats, $seats_per_bus),
            'max_bus_seats' => $max_bus_seats,
            'max_buses' => $max_bus_seats === 0 ? 0 : (int) ceil($max_bus_seats / $seats_per_bus),
            'max_bus_empty_seats' => self::empty_seats($max_bus_seats, $seats_per_bus),
            'seats_per_bus' => $seats_per_bus,
            'people_per_table' => $people_per_table,
            'by_source' => array(
                'groups' => $source_groups,
                'total' => $source_total,
            ),
            'by_pickup' => array(
                'pickups' => $pickup_rows,
                'unassigned_guests' => $unassigned_guests,
                'unassigned_seats' => $unassigned_seats,
            ),
        );
    }

    public static function units(array $response, array $settings) {
        $has_companion = (int) (isset($response['has_companion']) ? $response['has_companion'] : 0) === 1;
        $type = isset($response['companion_type']) ? (string) $response['companion_type'] : '';
        $adults = 1;
        $children = 0;
        if ($has_companion && $type === 'adult') {
            $adults = 2;
        } elseif ($has_companion && $type === 'child') {
            $children = 1;
        }

        $bus = 0;
        if ((int) (isset($response['ride_bus']) ? $response['ride_bus'] : 0) === 1) {
            $bus = 1;
            if ($has_companion && $type === 'adult') {
                $bus++;
            } elseif ($has_companion && $type === 'child' && self::flag($settings, 'child_takes_bus_seat', true)) {
                $bus++;
            }
        }

        return array(
            'adults' => $adults,
            'children' => $children,
            'bus' => $bus,
        );
    }

    public static function flag(array $settings, $key, $default) {
        if (!array_key_exists($key, $settings)) {
            return $default;
        }
        $value = $settings[$key];
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

    private static function empty_seats($seats, $seats_per_bus) {
        if ($seats <= 0) {
            return 0;
        }
        $buses = (int) ceil($seats / $seats_per_bus);
        return ($buses * $seats_per_bus) - $seats;
    }

    private static function empty_source_row($group_name) {
        return array(
            'group_name' => $group_name === '' ? 'Không nhóm' : $group_name,
            'invited' => 0,
            'responded' => 0,
            'attending_people' => 0,
            'bus_seats' => 0,
            'maybe_bus_seats' => 0,
        );
    }
}
