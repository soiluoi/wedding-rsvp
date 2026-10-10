<?php

use PHPUnit\Framework\TestCase;

class TabularParserTest extends TestCase {
    public function test_maps_headers_by_name_not_position_and_ignores_extra_columns() {
        $csv = "Link,Số người,Khách của,Note,Tên trên thiệp,Danh xưng,Nhóm khách\n"
            . "http://example,2,Trúc Anh,ghi chú,Hoài Phương,Bạn,Bạn bè\n";
        $rows = (new Wedding_RSVP_Tabular_Parser())->parse($csv, 'csv');
        $this->assertCount(1, $rows);
        $this->assertSame('Hoài Phương', $rows[0]['name']);
        $this->assertSame('Bạn', $rows[0]['salutation']);
        $this->assertSame('Trúc Anh', $rows[0]['host']);
        $this->assertSame('Bạn bè', $rows[0]['group_name']);
        $this->assertSame('2', $rows[0]['invited_count']);
        $this->assertSame('', $rows[0]['code']);
    }

    public function test_accent_insensitive_headers_and_example_sheet() {
        $csv = "#,Ma,Danh xung,Ten tren thiep,Note,So nguoi,Khach cua,Nhom khach\n"
            . "1,,Bạn,Tiên Tiên cùng sự giàu có,,1,Trúc Anh,Bạn bè\n"
            . "2,,Bạn,Hoài Phương,,1,Trúc Anh,Bạn bè\n";
        $rows = (new Wedding_RSVP_Tabular_Parser())->parse($csv, 'auto');
        $this->assertCount(2, $rows);
        $this->assertSame('Tiên Tiên cùng sự giàu có', $rows[0]['name']);
        $this->assertSame('Hoài Phương', $rows[1]['name']);
    }

    public function test_excel_semicolon_with_bom_and_sheet_tsv() {
        $csv = "\xEF\xBB\xBFDanh xưng;Tên trên thiệp;Khách của;Note\nBạn;Hoài Phương;Trúc Anh;Nhà cô dâu\n";
        $rows = (new Wedding_RSVP_Tabular_Parser())->parse($csv, 'auto');
        $this->assertSame('Hoài Phương', $rows[0]['name']);
        $this->assertSame('Nhà cô dâu', $rows[0]['note']);

        $tsv = "Danh xưng\tTên trên thiệp\tKhách của\nBạn\tQuân Lê\tTrúc Anh\n";
        $sheet = (new Wedding_RSVP_Tabular_Parser())->parse($tsv, 'tsv');
        $this->assertSame('Quân Lê', $sheet[0]['name']);
        $this->assertSame('Trúc Anh', $sheet[0]['host']);
    }

    public function test_quoted_commas_and_newlines_and_blank_rows() {
        $csv = "Danh xưng,Tên trên thiệp,Khách của,Note\n\nBạn,\"Tiên, Tiên\ncon\",Trúc Anh,\"dòng 1\ndòng 2\"\n\n";
        $rows = (new Wedding_RSVP_Tabular_Parser())->parse($csv, 'csv');
        $this->assertCount(1, $rows);
        $this->assertSame("Tiên, Tiên\ncon", $rows[0]['name']);
        $this->assertSame("dòng 1\ndòng 2", $rows[0]['note']);
    }

    public function test_missing_required_header_is_rejected() {
        $this->expectException(Wedding_RSVP_Parse_Exception::class);
        (new Wedding_RSVP_Tabular_Parser())->parse("Tên trên thiệp,Note\nAn,x\n", 'csv');
    }
}
