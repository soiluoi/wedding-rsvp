<?php

use PHPUnit\Framework\TestCase;

class ImporterTest extends TestCase {
    private function row($line, $name, $host = 'Trúc Anh', $extra = array()) {
        return array_merge(array(
            'line' => $line,
            'code' => '',
            'salutation' => 'Bạn',
            'name' => $name,
            'note' => '',
            'invited_count' => '1',
            'host' => $host,
            'group_name' => 'Bạn bè',
        ), $extra);
    }

    private function guest($id, $name, $extra = array()) {
        return array_merge(array(
            'id' => $id,
            'code' => 'abc2345',
            'salutation' => 'Bạn',
            'name' => $name,
            'note' => '',
            'invited_count' => 1,
            'host' => 'Trúc Anh',
            'group_name' => 'Bạn bè',
            'sort_order' => $id,
            'status' => 'active',
            'has_companion' => 0,
        ), $extra);
    }

    private function codes() {
        $queue = array('abc2345', 'def6789', 'ghjkmnp', 'qrstuvw');
        return function () use (&$queue) {
            return array_shift($queue);
        };
    }

    public function test_first_import_creates_three_guests_with_letter_codes() {
        $rows = array(
            $this->row(2, 'Tiên Tiên'),
            $this->row(3, 'Hoài Phương'),
            $this->row(4, 'Quân Lê', 'Trúc Anh', array('invited_count' => '2')),
        );
        $importer = new Wedding_RSVP_Importer();
        $diff = $importer->build_diff($rows, array(), 'upsert');
        $this->assertSame(3, $diff['counts']['new']);
        $ops = $importer->plan($diff, array(), $this->codes(), array());
        $this->assertCount(3, $ops);
        foreach ($ops as $op) {
            $this->assertSame('insert', $op['op']);
            $this->assertTrue(Wedding_RSVP_Codes::is_valid_code($op['code']));
            $this->assertTrue(Wedding_RSVP_Codes::has_letter($op['code']));
            $this->assertArrayNotHasKey('code', $op['data']);
            $this->assertArrayNotHasKey('pickup_id', $op['data']);
            $this->assertArrayNotHasKey('link_sent_at', $op['data']);
            $this->assertArrayNotHasKey('pickup_notified_at', $op['data']);
        }
        $this->assertSame(2, $ops[2]['data']['invited_count']);
        $this->assertSame(array(1, 2, 3), array(
            $ops[0]['data']['sort_order'],
            $ops[1]['data']['sort_order'],
            $ops[2]['data']['sort_order'],
        ));
    }

    public function test_reimport_by_code_changes_name_but_not_code_or_response() {
        $existing = array($this->guest(2, 'Hoài Phương', array(
            'code' => 'k7x9q2m',
            'sort_order' => 1,
            'has_companion' => 1,
        )));
        $rows = array($this->row(2, 'Hoài Phương mới', 'Trúc Anh', array('code' => 'k7x9q2m')));
        $importer = new Wedding_RSVP_Importer();
        $diff = $importer->build_diff($rows, $existing, 'upsert');
        $this->assertSame('update', $diff['rows'][0]['status']);
        $this->assertSame(2, $diff['rows'][0]['guest_id']);
        $this->assertSame('code', $diff['rows'][0]['match']);
        $ops = $importer->plan($diff, array(), $this->codes(), $existing);
        $this->assertSame('update', $ops[0]['op']);
        $this->assertSame(2, $ops[0]['id']);
        $this->assertSame('Hoài Phương mới', $ops[0]['data']['name']);
        $this->assertArrayNotHasKey('code', $ops[0]['data']);
        $this->assertArrayNotHasKey('pickup_id', $ops[0]['data']);
        $this->assertStringNotContainsString('response', implode(',', array_keys($ops[0]['data'])));
    }

    public function test_reimport_without_codes_matches_names_and_appends_a_new_guest() {
        $existing = array(
            $this->guest(1, 'Tiên Tiên', array('code' => 'abc2345', 'sort_order' => 1)),
            $this->guest(2, 'Hoài Phương', array('code' => 'def6789', 'sort_order' => 2)),
            $this->guest(3, 'Quân Lê', array('code' => 'ghjkmnp', 'sort_order' => 3, 'invited_count' => 2)),
        );
        $rows = array(
            $this->row(2, 'Tiên Tiên'),
            $this->row(3, 'Hoài Phương'),
            $this->row(4, 'Quân Lê', 'Trúc Anh', array('invited_count' => '2')),
            $this->row(5, 'Minh An'),
        );
        $diff = (new Wedding_RSVP_Importer())->build_diff($rows, $existing, 'upsert');
        $this->assertSame('unchanged', $diff['rows'][0]['status']);
        $this->assertSame('name', $diff['rows'][0]['match']);
        $this->assertSame('unchanged', $diff['rows'][1]['status']);
        $this->assertSame('unchanged', $diff['rows'][2]['status']);
        $this->assertSame('new', $diff['rows'][3]['status']);
        $this->assertSame(3, $diff['counts']['unchanged']);
        $this->assertSame(1, $diff['counts']['new']);
    }

    public function test_sync_mode_inactivates_guests_missing_from_the_file() {
        $existing = array(
            $this->guest(1, 'Tiên Tiên', array('code' => 'abc2345', 'sort_order' => 1)),
            $this->guest(2, 'Hoài Phương', array('code' => 'def6789', 'sort_order' => 2)),
            $this->guest(3, 'Quân Lê', array('code' => 'ghjkmnp', 'sort_order' => 3)),
        );
        $rows = array(
            $this->row(2, 'Tiên Tiên'),
            $this->row(3, 'Hoài Phương'),
        );
        $importer = new Wedding_RSVP_Importer();
        $diff = $importer->build_diff($rows, $existing, 'sync');
        $this->assertSame(3, $diff['to_inactivate'][0]['id']);
        $ops = $importer->plan($diff, array(), $this->codes(), $existing);
        $inactive = array_values(array_filter($ops, function ($op) {
            return $op['op'] === 'inactivate';
        }));
        $this->assertCount(1, $inactive);
        $this->assertSame(3, $inactive[0]['id']);
        $this->assertSame(array('status' => 'inactive'), $inactive[0]['data']);
    }

    public function test_inactive_guest_is_reactivated_when_they_return() {
        $existing = array($this->guest(3, 'Quân Lê', array(
            'code' => 'ghjkmnp',
            'sort_order' => 1,
            'status' => 'inactive',
        )));
        $rows = array($this->row(2, 'Quân Lê'));
        $importer = new Wedding_RSVP_Importer();
        $diff = $importer->build_diff($rows, $existing, 'upsert');
        $this->assertSame('reactivated', $diff['rows'][0]['status']);
        $ops = $importer->plan($diff, array(), $this->codes(), $existing);
        $this->assertTrue($ops[0]['reactivated']);
        $this->assertSame('active', $ops[0]['data']['status']);
        $this->assertSame(3, $ops[0]['id']);
    }

    public function test_duplicate_names_without_codes_are_conflicts() {
        $existing = array(
            $this->guest(1, 'Minh', array('code' => 'abc2345', 'sort_order' => 1)),
            $this->guest(2, 'Minh', array('code' => 'def6789', 'sort_order' => 2)),
        );
        $rows = array(
            $this->row(2, 'Minh'),
            $this->row(3, 'Minh'),
        );
        $importer = new Wedding_RSVP_Importer();
        $diff = $importer->build_diff($rows, $existing, 'sync');
        $this->assertSame('conflict', $diff['rows'][0]['status']);
        $this->assertSame('conflict', $diff['rows'][1]['status']);
        $this->assertCount(2, $diff['rows'][0]['candidates']);
        $this->expectException(Wedding_RSVP_Import_Exception::class);
        $importer->plan($diff, array(), $this->codes(), $existing);
    }

    public function test_conflict_resolution_cannot_attach_one_guest_twice() {
        $existing = array(
            $this->guest(1, 'Minh', array('code' => 'abc2345', 'sort_order' => 1)),
            $this->guest(2, 'Minh', array('code' => 'def6789', 'sort_order' => 2)),
        );
        $rows = array($this->row(2, 'Minh'), $this->row(3, 'Minh'));
        $importer = new Wedding_RSVP_Importer();
        $diff = $importer->build_diff($rows, $existing, 'upsert');
        $this->expectException(Wedding_RSVP_Import_Exception::class);
        $importer->plan($diff, array(
            2 => array('action' => 'match', 'guest_id' => 1),
            3 => array('action' => 'match', 'guest_id' => 1),
        ), $this->codes(), $existing);
    }

    public function test_lowering_invited_count_warns_and_does_not_touch_the_response() {
        $existing = array($this->guest(1, 'Quân Lê', array(
            'code' => 'ghjkmnp',
            'invited_count' => 2,
            'sort_order' => 1,
            'has_companion' => 1,
        )));
        $rows = array($this->row(2, 'Quân Lê', 'Trúc Anh', array('code' => 'ghjkmnp', 'invited_count' => '1')));
        $importer = new Wedding_RSVP_Importer();
        $diff = $importer->build_diff($rows, $existing, 'upsert');
        $this->assertNotEmpty($diff['rows'][0]['warnings']);
        $this->assertStringContainsString('người đi cùng', $diff['rows'][0]['warnings'][0]);
        $ops = $importer->plan($diff, array(), $this->codes(), $existing);
        $this->assertSame(1, $ops[0]['data']['invited_count']);
        $this->assertArrayNotHasKey('has_companion', $ops[0]['data']);
    }

    public function test_unknown_code_is_kept_for_a_new_guest() {
        $rows = array($this->row(2, 'Khách cũ', 'Trúc Anh', array('code' => 'k7x9q2m')));
        $importer = new Wedding_RSVP_Importer();
        $diff = $importer->build_diff($rows, array(), 'upsert');
        $this->assertSame('new', $diff['rows'][0]['status']);
        $this->assertSame('preserved_code', $diff['rows'][0]['match']);
        $ops = $importer->plan($diff, array(), $this->codes(), array());
        $this->assertSame('k7x9q2m', $ops[0]['code']);
    }

    public function test_invalid_code_and_duplicate_codes_are_errors_and_block_apply() {
        $rows = array(
            $this->row(2, 'Một', 'Trúc Anh', array('code' => '1111111')),
            $this->row(3, 'Hai', 'Trúc Anh', array('code' => 'abc2345')),
            $this->row(4, 'Ba', 'Trúc Anh', array('code' => 'abc2345')),
        );
        $importer = new Wedding_RSVP_Importer();
        $diff = $importer->build_diff($rows, array(), 'sync');
        $this->assertSame('error', $diff['rows'][0]['status']);
        $this->assertSame('error', $diff['rows'][1]['status']);
        $this->assertSame('error', $diff['rows'][2]['status']);
        $this->expectException(Wedding_RSVP_Import_Exception::class);
        $importer->plan($diff, array(), $this->codes(), array());
    }

    public function test_out_of_range_invited_count_is_clamped_with_a_warning() {
        $rows = array($this->row(2, 'An', 'Trúc Anh', array('invited_count' => '9')));
        $diff = (new Wedding_RSVP_Importer())->build_diff($rows, array(), 'upsert');
        $this->assertSame('new', $diff['rows'][0]['status']);
        $this->assertSame(2, $diff['rows'][0]['incoming']['invited_count']);
        $this->assertNotEmpty($diff['rows'][0]['warnings']);
    }

    public function test_import_payload_whitelist_drops_protected_fields() {
        $payload = Wedding_RSVP_Guest_Repo::sanitize_import_payload(array(
            'name' => 'An',
            'code' => 'abc2345',
            'pickup_id' => 4,
            'link_sent_at' => '2026-01-01 00:00:00',
            'pickup_notified_at' => '2026-01-01 00:00:00',
            'salutation' => 'Bạn',
        ), 'active');
        $this->assertArrayNotHasKey('code', $payload);
        $this->assertArrayNotHasKey('pickup_id', $payload);
        $this->assertArrayNotHasKey('link_sent_at', $payload);
        $this->assertArrayNotHasKey('pickup_notified_at', $payload);
        $this->assertSame('an', $payload['name_normalized']);
        $this->assertSame('active', $payload['status']);
        $this->assertNotContains('code', Wedding_RSVP_Guest_Repo::IMPORTABLE_FIELDS);
    }
}
