<?php

use PHPUnit\Framework\TestCase;

class StatsAndResponseTest extends TestCase {
    private function settings($overrides = array()) {
        return array_merge(Wedding_RSVP_Settings::defaults(), $overrides);
    }

    private function guest($response, $extra = array()) {
        return array_merge(array(
            'status' => 'active',
            'host' => 'Trúc Anh',
            'group_name' => 'Công ty B',
            'pickup_id' => null,
            'pickup_notified_at' => null,
            'response' => $response,
        ), $extra);
    }

    public function test_declining_clears_bus_and_companion_and_lowers_the_counts() {
        $coming = array(
            'attending' => 'yes',
            'ride_bus' => 1,
            'has_companion' => 1,
            'companion_type' => 'child',
            'companion_age' => 5,
        );
        $before = Wedding_Stats::summarize(array($this->guest($coming)), $this->settings());
        $this->assertSame(1, $before['adults']);
        $this->assertSame(1, $before['children']);
        $this->assertSame(2, $before['party_total']);
        $this->assertSame(2, $before['bus_seats']);

        $normalized = Wedding_RSVP_Response_Repo::normalize(array(
            'attending' => 'no',
            'ride_bus' => '1',
            'phone' => '0901 234 567',
            'has_companion' => '1',
            'companion_type' => 'child',
            'companion_age' => '5',
            'diet_note' => 'ăn chay',
            'message' => 'Chúc mừng',
        ), array('invited_count' => 2), $this->settings(), 'guest');
        $this->assertSame('no', $normalized['attending']);
        $this->assertNull($normalized['ride_bus']);
        $this->assertSame(0, $normalized['has_companion']);
        $this->assertNull($normalized['phone']);
        $this->assertNull($normalized['diet_note']);
        $this->assertNull($normalized['companion_type']);
        $this->assertNull($normalized['companion_age']);
        $this->assertSame('Chúc mừng', $normalized['message']);

        $after = Wedding_Stats::summarize(array(
            $this->guest(array(
                'attending' => 'no',
                'ride_bus' => null,
                'has_companion' => 0,
            )),
        ), $this->settings());
        $this->assertSame(0, $after['adults']);
        $this->assertSame(0, $after['children']);
        $this->assertSame(0, $after['bus_seats']);
        $this->assertSame(1, $after['no']);
    }

    public function test_child_does_not_take_a_bus_seat_when_the_setting_is_off() {
        $response = array(
            'attending' => 'yes',
            'ride_bus' => '1',
            'has_companion' => '1',
            'companion_type' => 'child',
        );
        $off = Wedding_Stats::units($response, $this->settings(array('child_takes_bus_seat' => false)));
        $on = Wedding_Stats::units($response, $this->settings(array('child_takes_bus_seat' => true)));
        $this->assertSame(1, $off['bus']);
        $this->assertSame(2, $on['bus']);
        $this->assertSame(1, $off['children']);
    }

    public function test_tables_buses_maybe_and_inactive_guests() {
        $guests = array();
        for ($i = 0; $i < 10; $i++) {
            $guests[] = $this->guest(array(
                'attending' => 'yes',
                'ride_bus' => 1,
                'has_companion' => 0,
            ), array('host' => 'Trúc Anh', 'group_name' => 'Công ty B'));
        }
        $guests[] = $this->guest(array(
            'attending' => 'yes',
            'ride_bus' => 1,
            'has_companion' => 1,
            'companion_type' => 'child',
        ), array('host' => 'Minh', 'group_name' => 'Công ty A'));
        $guests[] = $this->guest(array(
            'attending' => 'maybe',
            'ride_bus' => 1,
            'has_companion' => 0,
        ));
        $guests[] = $this->guest(null, array('status' => 'inactive'));
        $guests[] = $this->guest(null);

        $summary = Wedding_Stats::summarize($guests, $this->settings(array(
            'people_per_table' => 10,
            'seats_per_bus' => 43,
            'child_counts_for_table' => false,
            'child_takes_bus_seat' => false,
        )));

        $this->assertSame(13, $summary['invited']);
        $this->assertSame(12, $summary['responded']);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(11, $summary['adults']);
        $this->assertSame(1, $summary['children']);
        $this->assertSame(2, $summary['tables']);
        $this->assertSame(11, $summary['bus_seats']);
        $this->assertSame(12, $summary['max_adults']);
        $this->assertSame(12, $summary['max_bus_seats']);
        $this->assertSame(1, $summary['by_source']['groups'][0]['subtotal']['maybe_bus_seats'] + $summary['by_source']['groups'][1]['subtotal']['maybe_bus_seats']);

        $with_child_seat = Wedding_Stats::summarize($guests, $this->settings(array(
            'people_per_table' => 10,
            'child_counts_for_table' => true,
            'child_takes_bus_seat' => true,
        )));
        $this->assertSame(2, $with_child_seat['tables']);
        $this->assertSame(12, $with_child_seat['bus_seats']);

        $edge = array($this->guest(array(
            'attending' => 'yes',
            'ride_bus' => 0,
            'has_companion' => 1,
            'companion_type' => 'child',
        )));
        for ($i = 0; $i < 9; $i++) {
            $edge[] = $this->guest(array(
                'attending' => 'yes',
                'ride_bus' => 0,
                'has_companion' => 0,
            ));
        }
        $hide_child = Wedding_Stats::summarize($edge, $this->settings(array(
            'people_per_table' => 10,
            'child_counts_for_table' => false,
        )));
        $count_child = Wedding_Stats::summarize($edge, $this->settings(array(
            'people_per_table' => 10,
            'child_counts_for_table' => true,
        )));
        $this->assertSame(1, $hide_child['tables']);
        $this->assertSame(2, $count_child['tables']);

        $bus = Wedding_Stats::summarize(array(), $this->settings(array('seats_per_bus' => 43)));
        $this->assertSame(0, $bus['buses']);
        $fifty_eight = array();
        for ($i = 0; $i < 58; $i++) {
            $fifty_eight[] = $this->guest(array(
                'attending' => 'yes',
                'ride_bus' => 1,
                'has_companion' => 0,
            ));
        }
        $fleet = Wedding_Stats::summarize($fifty_eight, $this->settings(array('seats_per_bus' => 43)));
        $this->assertSame(58, $fleet['bus_seats']);
        $this->assertSame(2, $fleet['buses']);
        $this->assertSame(28, $fleet['bus_empty_seats']);
    }

    public function test_several_companions_are_counted_and_guests_cannot_choose_maybe() {
        $normalized = Wedding_RSVP_Response_Repo::normalize(array(
            'attending' => 'yes',
            'ride_bus' => '1',
            'phone' => '0901234567',
            'companions' => array(
                array('name' => 'An', 'type' => 'adult'),
                array('name' => 'Bé Na', 'type' => 'child'),
            ),
            'diet_note' => "ăn chay\nkhông đậu phộng",
        ), array('invited_count' => 4), $this->settings(), 'guest');
        $this->assertSame(1, $normalized['has_companion']);
        $this->assertNull($normalized['companion_age']);
        $this->assertSame('An, Bé Na', $normalized['companion_name']);
        $this->assertNull($normalized['companion_type']);
        $decoded = json_decode($normalized['companions'], true);
        $this->assertCount(2, $decoded);
        $this->assertStringContainsString('không đậu phộng', $normalized['diet_note']);
        $units = Wedding_Stats::units(array(
            'attending' => 'yes',
            'ride_bus' => 1,
            'companions' => $decoded,
        ), $this->settings());
        $this->assertSame(2, $units['adults']);
        $this->assertSame(1, $units['children']);
        $this->assertSame(3, $units['bus']);
        $this->assertSame('An · Người lớn, Bé Na · Trẻ em', Wedding_RSVP_Templates::companion_label(array('companions' => $decoded)));

        try {
            Wedding_RSVP_Response_Repo::normalize(
                array('attending' => 'maybe', 'ride_bus' => '0'),
                array('invited_count' => 1),
                $this->settings(),
                'guest'
            );
            $this->fail('Guests should not be able to answer maybe.');
        } catch (Wedding_RSVP_Validation_Exception $e) {
            $this->assertStringContainsString('tham dự', $e->getMessage());
        }
    }

    public function test_remaining_time_is_relative() {
        $tz = Wedding_RSVP_Settings::timezone();
        $this->assertSame('Còn 2 ngày 3 giờ', Wedding_RSVP_Settings::remaining_label(
            '2026-10-13 15:00:00',
            new DateTimeImmutable('2026-10-11 12:00:00', $tz)
        ));
        $this->assertSame('Còn 40 phút', Wedding_RSVP_Settings::remaining_label(
            '2026-10-11 12:40:00',
            new DateTimeImmutable('2026-10-11 12:00:00', $tz)
        ));
        $this->assertSame('', Wedding_RSVP_Settings::remaining_label(
            '2026-10-11 11:00:00',
            new DateTimeImmutable('2026-10-11 12:00:00', $tz)
        ));
    }

    public function test_phone_accepts_spaces_dots_and_country_code() {
        $this->assertSame('0901234567', Wedding_RSVP_Response_Repo::normalize_phone('0901.234.567', true));
        $this->assertSame('0901234567', Wedding_RSVP_Response_Repo::normalize_phone('+84 901 234 567', true));
        $this->assertSame('0901234567', Wedding_RSVP_Response_Repo::normalize_phone('84901234567', true));
        $this->assertSame('0901 234 567', Wedding_RSVP_Exporter::format_phone('0901234567'));
    }

    public function test_deadline_blocks_guest_edits_and_allows_admin() {
        $settings = $this->settings(array(
            'deadline' => '2026-10-01 12:00:00',
            'contact_info' => '0909000111',
        ));
        $tz = Wedding_RSVP_Settings::timezone();
        $this->assertFalse(Wedding_RSVP_Settings::is_past_deadline(
            $settings,
            new DateTimeImmutable('2026-10-01 12:00:00', $tz)
        ));
        $this->assertTrue(Wedding_RSVP_Settings::is_past_deadline(
            $settings,
            new DateTimeImmutable('2026-10-01 12:00:01', $tz)
        ));
        try {
            Wedding_RSVP_Response_Repo::normalize(
                array('attending' => 'yes', 'ride_bus' => '0'),
                array('invited_count' => 1),
                $settings,
                'guest',
                new DateTimeImmutable('2026-10-02 08:00:00', $tz)
            );
            $this->fail('Guest edit after the deadline should be rejected.');
        } catch (Wedding_RSVP_Validation_Exception $e) {
            $this->assertStringContainsString('0909000111', $e->getMessage());
        }
        $admin = Wedding_RSVP_Response_Repo::normalize(
            array('attending' => 'yes', 'ride_bus' => '0'),
            array('invited_count' => 1),
            $settings,
            'admin',
            new DateTimeImmutable('2026-10-02 08:00:00', $tz)
        );
        $this->assertSame('yes', $admin['attending']);
        $this->assertSame(0, $admin['ride_bus']);
    }

    public function test_invite_csv_round_trip_keeps_codes_and_vietnamese_names() {
        $settings = $this->settings();
        $guest = array(
            'sort_order' => 1,
            'code' => 'k7x9q2m',
            'salutation' => 'Bạn',
            'name' => 'Hoài Phương',
            'note' => 'Nhà cô dâu',
            'invited_count' => 1,
            'host' => 'Trúc Anh',
            'group_name' => 'Bạn bè',
            'link' => 'http://kienit.com/wedding/hoai-phuong-k7x9q2m',
            'response' => array(
                'attending' => 'yes',
                'ride_bus' => 1,
                'phone' => '0901234567',
                'has_companion' => 0,
            ),
            'link_sent_at' => '2026-10-02 09:30:00',
        );
        $csv = Wedding_RSVP_Exporter::to_csv(Wedding_RSVP_Exporter::matrix(array($guest), 'invite', $settings));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $parsed = (new Wedding_RSVP_Tabular_Parser())->parse($csv, 'auto');
        $this->assertSame('k7x9q2m', $parsed[0]['code']);
        $this->assertSame('Hoài Phương', $parsed[0]['name']);
        $this->assertSame('Trúc Anh', $parsed[0]['host']);

        $full = Wedding_RSVP_Exporter::matrix(array($guest), 'full', $settings);
        $phone_index = array_search('SĐT', $full[0], true);
        $this->assertSame('0901 234 567', $full[1][$phone_index]);
        $sent_index = array_search('Đã gửi link', $full[0], true);
        $this->assertSame('02/10/2026 09:30', $full[1][$sent_index]);
    }

    public function test_message_template_replaces_placeholders() {
        $text = Wedding_RSVP_Templates::fill('Chào {danh_xung} {ten} {link}', array(
            'danh_xung' => 'Bạn',
            'ten' => 'An',
            'link' => 'http://kienit.com/wedding/an-abc2345',
        ));
        $this->assertSame('Chào Bạn An http://kienit.com/wedding/an-abc2345', $text);
    }
}
