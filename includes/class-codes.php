<?php

class Wedding_RSVP_Codes {
    const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    const LETTERS = 'abcdefghjkmnpqrstuvwxyz';
    const LENGTH = 7;

    public static function generate() {
        $alphabet = self::ALPHABET;
        $letters = self::LETTERS;
        $max = strlen($alphabet) - 1;
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }
        if (!preg_match('/[a-z]/', $code)) {
            $pos = random_int(0, self::LENGTH - 1);
            $code[$pos] = $letters[random_int(0, strlen($letters) - 1)];
        }
        return $code;
    }

    public static function is_valid_code($code) {
        return is_string($code) && preg_match('/^[abcdefghjkmnpqrstuvwxyz23456789]{7}$/', $code) === 1;
    }

    public static function has_letter($code) {
        return is_string($code) && preg_match('/[a-z]/', $code) === 1;
    }

    public static function unique_code(callable $exists) {
        for ($i = 0; $i < 20; $i++) {
            $code = self::generate();
            if (!$exists($code)) {
                return $code;
            }
        }
        throw new RuntimeException('Could not generate a unique guest code.');
    }

    public static function slug_from_name($name) {
        return Wedding_RSVP_Text::slugify($name);
    }

    public static function canonical_path($name, $code) {
        $slug = self::slug_from_name($name);
        $tail = $slug === '' ? $code : $slug . '-' . $code;
        return 'wedding/' . $tail;
    }

    public static function url($name, $code, $base = null) {
        if ($base === null) {
            $base = function_exists('home_url') ? home_url('/') : '';
        }
        return rtrim((string) $base, '/') . '/' . self::canonical_path($name, $code);
    }

    public static function match_request_path($path) {
        $path = self::normalize_request_path($path);
        if ($path === null) {
            return null;
        }
        if (preg_match('#(?:^|/)wedding/xem-thu/?$#', $path)) {
            return array('type' => 'preview');
        }
        if (preg_match('#(?:^|/)wedding/(?:[a-z0-9-]+-)?([a-z0-9]{7})/?$#', $path, $matches)) {
            return array('type' => 'guest', 'code' => $matches[1]);
        }
        if (preg_match('#(?:^|/)wedding/.+#', $path)) {
            return array('type' => 'invalid');
        }
        return null;
    }

    public static function needs_redirect($request_uri, $name, $code) {
        $path = self::normalize_request_path($request_uri);
        if ($path === null) {
            return false;
        }
        $canonical = self::canonical_path($name, $code);
        if (substr($path, -strlen($canonical)) === $canonical) {
            $prefix = substr($path, 0, -strlen($canonical));
            return $prefix !== '' && substr($prefix, -1) !== '/';
        }
        return true;
    }

    public static function normalize_request_path($path) {
        if (!is_string($path) || $path === '') {
            return null;
        }
        $parsed = parse_url($path, PHP_URL_PATH);
        if (!is_string($parsed) || $parsed === '') {
            $parsed = $path;
        }
        $parsed = strtolower(rawurldecode($parsed));
        $parsed = trim($parsed, '/');
        if (strpos($parsed, 'index.php/') === 0) {
            $parsed = substr($parsed, strlen('index.php/'));
        } elseif ($parsed === 'index.php') {
            $parsed = '';
        }
        return $parsed;
    }
}
