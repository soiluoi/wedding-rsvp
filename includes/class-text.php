<?php

class Wedding_RSVP_Text {
    public static function remove_accents($text) {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }
        return strtr($text, self::accent_map());
    }

    public static function normalize_name($name) {
        $name = self::remove_accents((string) $name);
        $name = mb_strtolower($name, 'UTF-8');
        $name = preg_replace('/\s+/u', ' ', trim($name));
        return $name;
    }

    public static function normalize_header($header) {
        $header = self::remove_accents((string) $header);
        $header = mb_strtolower($header, 'UTF-8');
        $header = str_replace(array('_', '-'), ' ', $header);
        $header = preg_replace('/\s+/u', ' ', trim($header));
        return $header;
    }

    public static function clean_cell($value) {
        $value = (string) $value;
        $value = str_replace(array("\t", "\r\n", "\r", "\n"), ' ', $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
        return trim($value);
    }

    public static function slugify($name) {
        $slug = self::remove_accents((string) $name);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        if (strlen($slug) <= 30) {
            return $slug;
        }
        $cut = substr($slug, 0, 30);
        $pos = strrpos($cut, '-');
        if ($pos !== false && $pos > 0) {
            return substr($cut, 0, $pos);
        }
        return rtrim($cut, '-');
    }

    private static function accent_map() {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $groups = array(
            'A' => 'ÀÁẢÃẠĂẰẮẲẴẶÂẦẤẨẪẬ',
            'a' => 'àáảãạăằắẳẵặâầấẩẫậ',
            'E' => 'ÈÉẺẼẸÊỀẾỂỄỆ',
            'e' => 'èéẻẽẹêềếểễệ',
            'I' => 'ÌÍỈĨỊ',
            'i' => 'ìíỉĩị',
            'O' => 'ÒÓỎÕỌÔỒỐỔỖỘƠỜỚỞỠỢ',
            'o' => 'òóỏõọôồốổỗộơờớởỡợ',
            'U' => 'ÙÚỦŨỤƯỪỨỬỮỰ',
            'u' => 'ùúủũụưừứửữự',
            'Y' => 'ỲÝỶỸỴ',
            'y' => 'ỳýỷỹỵ',
            'D' => 'Đ',
            'd' => 'đ',
        );
        $map = array();
        foreach ($groups as $ascii => $chars) {
            $length = mb_strlen($chars, 'UTF-8');
            for ($i = 0; $i < $length; $i++) {
                $map[mb_substr($chars, $i, 1, 'UTF-8')] = $ascii;
            }
        }
        return $map;
    }
}
