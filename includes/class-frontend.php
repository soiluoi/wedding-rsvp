<?php

class Wedding_RSVP_Frontend {
    const REWRITE_VERSION = '2';

    public function __construct() {
        add_action('init', array(__CLASS__, 'register_rewrites'));
        add_action('init', array(__CLASS__, 'maybe_flush_rewrites'), 99);
        add_filter('query_vars', array($this, 'query_vars'));
        add_filter('request', array($this, 'map_request'));
        add_filter('redirect_canonical', array($this, 'skip_canonical'));
        add_action('template_redirect', array($this, 'prepare'), 0);
        add_filter('template_include', array($this, 'template'));
        add_filter('show_admin_bar', array($this, 'hide_admin_bar'));
    }

    public static function register_rewrites() {
        // 'top' appends into the high-priority group, and WordPress uses the first match.
        // Specific paths must be registered before the catch-all.
        add_rewrite_rule('^wedding/xem-thu/?$', 'index.php?wedding_preview=1', 'top');
        add_rewrite_rule(
            '^wedding/(?:[a-z0-9-]+-)?([a-z0-9]{7})/?$',
            'index.php?wedding_code=$matches[1]',
            'top'
        );
        add_rewrite_rule('^wedding/.+', 'index.php?wedding_invalid=1', 'top');
    }

    public static function maybe_flush_rewrites() {
        if (get_option('wedding_rewrite_version') === self::REWRITE_VERSION) {
            return;
        }
        flush_rewrite_rules(false);
        update_option('wedding_rewrite_version', self::REWRITE_VERSION, false);
    }

    public function query_vars($vars) {
        $vars[] = 'wedding_code';
        $vars[] = 'wedding_preview';
        $vars[] = 'wedding_invalid';
        return $vars;
    }

    public function map_request($vars) {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        return self::resolve_request($vars, $uri);
    }

    public static function resolve_request(array $query_vars, $request_uri) {
        $match = Wedding_RSVP_Codes::match_request_path((string) $request_uri);
        if ($match && $match['type'] === 'preview') {
            $query_vars['wedding_preview'] = 1;
            unset($query_vars['wedding_code'], $query_vars['wedding_invalid']);
            return $query_vars;
        }
        if ($match && $match['type'] === 'guest') {
            $query_vars['wedding_code'] = $match['code'];
            unset($query_vars['wedding_preview'], $query_vars['wedding_invalid']);
            return $query_vars;
        }
        if (!empty($query_vars['wedding_code'])) {
            $query_vars['wedding_code'] = strtolower((string) $query_vars['wedding_code']);
            unset($query_vars['wedding_invalid']);
            return $query_vars;
        }
        if (($match && $match['type'] === 'invalid') || !empty($query_vars['wedding_invalid'])) {
            $query_vars['wedding_invalid'] = 1;
        }
        return $query_vars;
    }

    public function skip_canonical($redirect) {
        if ($this->is_wedding_request()) {
            return false;
        }
        return $redirect;
    }

    public function hide_admin_bar($show) {
        if ($this->is_wedding_request()) {
            return false;
        }
        return $show;
    }

    public function prepare() {
        if (!$this->is_wedding_request()) {
            return;
        }
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow');
        }
        $code = get_query_var('wedding_code');
        if (!$code) {
            return;
        }
        $code = strtolower(sanitize_text_field((string) $code));
        $guest = (new Wedding_RSVP_Guest_Repo())->find_by_code($code);
        if (!$guest || $guest['status'] !== 'active') {
            return;
        }
        $request = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        if (Wedding_RSVP_Codes::needs_redirect($request, $guest['name'], $guest['code'])) {
            wp_redirect(Wedding_RSVP_Codes::url($guest['name'], $guest['code']), 301);
            exit;
        }
    }

    public function template($template) {
        if (!$this->is_wedding_request()) {
            return $template;
        }
        return WEDDING_RSVP_DIR . 'templates/rsvp-page.php';
    }

    public static function view() {
        $settings = Wedding_RSVP_Settings::get();
        $view = array(
            'settings' => $settings,
            'error' => '',
            'preview' => false,
            'locked' => Wedding_RSVP_Settings::is_past_deadline($settings),
            'guest' => null,
            'response' => null,
            'pickup' => null,
            'allow_plus_one' => false,
            'nonce' => '',
            'rest_url' => '',
            'og_title' => self::og_title($settings),
            'couple' => Wedding_RSVP_Settings::couple_label($settings),
            'when' => Wedding_RSVP_Settings::format_datetime($settings['wedding_date']),
            'place' => Wedding_RSVP_Settings::place_label($settings),
            'maps_url' => isset($settings['maps_url']) ? (string) $settings['maps_url'] : '',
            'remaining' => Wedding_RSVP_Settings::remaining_label(isset($settings['deadline']) ? $settings['deadline'] : ''),
            'max_companions' => 0,
            'deadline' => Wedding_RSVP_Settings::format_datetime($settings['deadline']),
        );

        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $resolved = self::resolve_request(array(
            'wedding_code' => get_query_var('wedding_code'),
            'wedding_preview' => get_query_var('wedding_preview'),
            'wedding_invalid' => get_query_var('wedding_invalid'),
        ), $uri);

        if (!empty($resolved['wedding_preview'])) {
            if (!current_user_can('manage_options')) {
                $view['error'] = 'missing';
                return $view;
            }
            $view['preview'] = true;
            $view['locked'] = false;
            $view['guest'] = array(
                'id' => 0,
                'code' => 'preview',
                'salutation' => 'Bạn',
                'name' => 'Khách mẫu',
                'invited_count' => 4,
                'status' => 'active',
                'link' => home_url('/wedding/xem-thu/'),
            );
            $view['max_companions'] = Wedding_RSVP_Response_Repo::max_companions($view['guest'], $settings);
            $view['allow_plus_one'] = $view['max_companions'] > 0;
            return $view;
        }

        $code = isset($resolved['wedding_code']) ? strtolower(sanitize_text_field((string) $resolved['wedding_code'])) : '';
        if ($code === '') {
            $view['error'] = 'missing';
            return $view;
        }
        $guest = (new Wedding_RSVP_Guest_Repo())->find_by_code($code);
        if (!$guest) {
            $view['error'] = 'missing';
            return $view;
        }
        if ($guest['status'] !== 'active') {
            $view['error'] = 'inactive';
            return $view;
        }
        $guest['link'] = Wedding_RSVP_Codes::url($guest['name'], $guest['code']);
        $response = (new Wedding_RSVP_Response_Repo())->find_by_guest($guest['id']);
        $pickup = null;
        if (!empty($guest['pickup_id']) && !empty($guest['pickup_notified_at'])) {
            $row = (new Wedding_RSVP_Pickup_Repo())->find($guest['pickup_id']);
            if ($row) {
                $pickup = array(
                    'name' => $row['name'],
                    'address' => $row['address'],
                    'pickup_time' => $row['pickup_time'],
                );
            }
        }
        $view['guest'] = $guest;
        $view['response'] = $response;
        $view['pickup'] = $pickup;
        $view['max_companions'] = Wedding_RSVP_Response_Repo::max_companions($guest, $settings);
        $view['allow_plus_one'] = $view['max_companions'] > 0;
        $view['nonce'] = wp_create_nonce('wedding_rsvp_' . $guest['code']);
        $view['rest_url'] = rest_url('wedding/v1/rsvp/' . rawurlencode($guest['code']));
        return $view;
    }

    private static function og_title(array $settings) {
        $couple = Wedding_RSVP_Settings::couple_label($settings);
        if ($couple === '') {
            return 'Form khảo sát';
        }
        return 'Form khảo sát ' . $couple;
    }

    private function is_wedding_request() {
        return (bool) (get_query_var('wedding_code') || get_query_var('wedding_preview') || get_query_var('wedding_invalid'));
    }
}
