<?php

use PHPUnit\Framework\TestCase;

class CodesTest extends TestCase {
    public function test_generated_codes_are_seven_chars_and_include_a_letter() {
        for ($i = 0; $i < 300; $i++) {
            $code = Wedding_RSVP_Codes::generate();
            $this->assertTrue(Wedding_RSVP_Codes::is_valid_code($code));
            $this->assertTrue(Wedding_RSVP_Codes::has_letter($code));
            $this->assertDoesNotMatchRegularExpression('/[01oli]/', $code);
        }
    }

    public function test_unique_code_skips_codes_that_already_exist() {
        $blocked = array();
        $code = Wedding_RSVP_Codes::unique_code(function ($candidate) use (&$blocked) {
            $blocked[] = $candidate;
            return count($blocked) < 3;
        });
        $this->assertCount(3, $blocked);
        $this->assertSame($blocked[2], $code);
    }

    public function test_slug_strips_accents_and_cuts_on_a_word_boundary() {
        $this->assertSame('hoai-phuong', Wedding_RSVP_Codes::slug_from_name('Hoài Phương'));
        $this->assertSame('tien-tien-cung-su-giau-co', Wedding_RSVP_Codes::slug_from_name('Tiên Tiên cùng sự giàu có'));
        $this->assertSame('nguyen-thi-hoang-bich-phuong', Wedding_RSVP_Codes::slug_from_name('Nguyễn Thị Hoàng Bích Phương Thảo'));
        $this->assertLessThanOrEqual(30, strlen(Wedding_RSVP_Codes::slug_from_name('Nguyễn Thị Hoàng Bích Phương Thảo')));
    }

    public function test_name_normalization_folds_accents_and_spaces() {
        $this->assertSame('hoai phuong', Wedding_RSVP_Text::normalize_name('  Hoài   Phương '));
        $this->assertSame('tien tien cung su giau co', Wedding_RSVP_Text::normalize_name('Tiên Tiên cùng sự giàu có'));
    }

    public function test_request_path_extracts_code_and_rejects_bad_shapes() {
        $this->assertSame(
            array('type' => 'guest', 'code' => 'k7x9q2m'),
            Wedding_RSVP_Codes::match_request_path('/wedding/hoai-phuong-k7x9q2m')
        );
        $this->assertSame(
            array('type' => 'guest', 'code' => 'k7x9q2m'),
            Wedding_RSVP_Codes::match_request_path('/wedding/k7x9q2m/')
        );
        $this->assertSame(array('type' => 'preview'), Wedding_RSVP_Codes::match_request_path('/wedding/xem-thu'));
        $this->assertSame(array('type' => 'invalid'), Wedding_RSVP_Codes::match_request_path('/wedding/khong-dung'));
        $this->assertSame(array('type' => 'invalid'), Wedding_RSVP_Codes::match_request_path('/wedding/abc'));
        $this->assertNull(Wedding_RSVP_Codes::match_request_path('/about'));
    }

    public function test_old_slug_needs_redirect_and_current_slug_does_not() {
        $this->assertTrue(Wedding_RSVP_Codes::needs_redirect('/wedding/ten-cu-k7x9q2m', 'Hoài Phương', 'k7x9q2m'));
        $this->assertTrue(Wedding_RSVP_Codes::needs_redirect('/wedding/k7x9q2m', 'Hoài Phương', 'k7x9q2m'));
        $this->assertFalse(Wedding_RSVP_Codes::needs_redirect('/wedding/hoai-phuong-k7x9q2m/', 'Hoài Phương', 'k7x9q2m'));
        $this->assertSame(
            'http://kienit.com/wedding/hoai-phuong-k7x9q2m',
            Wedding_RSVP_Codes::url('Hoài Phương', 'k7x9q2m', 'http://kienit.com')
        );
    }
}
