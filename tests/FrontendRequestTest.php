<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/class-frontend.php';

class FrontendRequestTest extends TestCase {
    public function test_guest_url_overrides_the_catch_all_invalid_flag() {
        $vars = Wedding_RSVP_Frontend::resolve_request(
            array('wedding_invalid' => '1'),
            '/wedding/hoai-phuong-k7x9q2m/'
        );

        $this->assertSame('k7x9q2m', $vars['wedding_code']);
        $this->assertArrayNotHasKey('wedding_invalid', $vars);
    }

    public function test_preview_url_overrides_the_catch_all_invalid_flag() {
        $vars = Wedding_RSVP_Frontend::resolve_request(
            array('wedding_invalid' => '1'),
            '/wedding/xem-thu/'
        );

        $this->assertSame(1, $vars['wedding_preview']);
        $this->assertArrayNotHasKey('wedding_invalid', $vars);
        $this->assertArrayNotHasKey('wedding_code', $vars);
    }

    public function test_unknown_wedding_path_stays_invalid() {
        $vars = Wedding_RSVP_Frontend::resolve_request(array(), '/wedding/khong-dung');

        $this->assertSame(1, $vars['wedding_invalid']);
        $this->assertArrayNotHasKey('wedding_code', $vars);
    }
}
