<?php

class Session {

    // VARS

    public static $access = 0;
    public static $cookies_secure = false;
    public static $mode = 0; // 0 - web, 1 - call, 2 - api
    public static $ts = 0;
    public static $tz = 0;
    public static $token = '';
    public static $user_id = 0;

    // INIT

    public static function init($mode = 0) {
        Session::$mode = $mode;
        Session::$ts = time();
        Session::$cookies_secure = SITE_SCHEME == 'https';
        return Session::session_common();
    }

    private static function session_common() {
        // vars
        Session::$token = isset($_COOKIE['token']) ? flt_input($_COOKIE['token']) : '';
        Session::$tz = isset($_COOKIE['timezone']) && is_numeric($_COOKIE['timezone']) && $_COOKIE['timezone'] >= -720 && $_COOKIE['timezone'] <= 720 ? round(flt_input($_COOKIE['timezone'])) : DEFAULT_TIMEZONE;
        // query
        $q = DB::query("SELECT user_id, access, token, updated FROM sessions WHERE token='".Session::$token."' LIMIT 1;") or die (DB::error());
        $row = DB::fetch_row($q);
        if (!$row) return Session::unset_cookie_token();
        // vars
        Session::$user_id = $row['user_id'];
        Session::$access = $row['access'];
        // output
        Session::session_refresh();
        return 'ok';
    }

    // AUTH

    private static function session_create() {
        // token
        Session::$token = generate_rand_str(40);
        Session::unset_cookie_token();
        Session::set_cookie('token', Session::$token);
        // update
        DB::query("UPDATE users SET phone_attempts_code='0', last_login='".Session::$ts."'
            WHERE user_id='".Session::$user_id."' LIMIT 1;") or die (DB::error());
        DB::query("INSERT INTO sessions (
            user_id,
            access,
            token,
            tz,
            created,
            logged
        ) VALUES (
            '".Session::$user_id."',
            '".Session::$access."',
            '".Session::$token."',
            '".Session::$tz."',
            '".Session::$ts."',
            '".Session::$ts."'
        );") or die (DB::error());
        // output
        return ['token' => Session::$token];
    }

    private static function phone_attempts_code($user_id, $last_login, $attempts) {
        // clear
        if ((Session::$ts - $last_login) > 3600) {
            DB::query("UPDATE users SET phone_attempts_code='0' WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
            return 0;
        }
        // default
        return $attempts;
    }

    public static function logout() {
        DB::query("DELETE FROM sessions WHERE token='".Session::$token."' LIMIT 1;") or die (DB::error());
        Session::unset_cookie_token();
        header('Location: /');
        exit();
    }

    // ADMIN

    public static function auth_send($d) {
        // vars
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // validate
        if (!$phone) return error_response(1003, 'One of the parameters was missing or was passed in the wrong format.', ['phone' => 'empty field']);
        $user = User::user_info(['phone' => $phone]);
        if ($user['access'] != 1) return error_response(1004, 'User with this phone is not found.', ['phone' => 'incorrect phone']);
        // query
        DB::query("UPDATE users SET phone_attempts_sms=phone_attempts_sms+1, phone_attempts_code='0' WHERE user_id='1' LIMIT 1;") or die (DB::error());
        // output
        HTML::assign('phone', $phone);
        return ['html' => HTML::fetch('./partials/login_confirm.html')];
    }

    public static function auth_confirm($d) {
        // vars
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        $code = isset($d['code']) && is_numeric($d['code']) ? $d['code'] : 0;
        // error (empty)
        if (!$phone && !$code) return error_response(1003, 'One of the parameters was missing or was passed in the wrong format.', ['phone' => 'empty field', 'code' => 'empty field']);
        if (!$phone) return error_response(1003, 'One of the parameters was missing or was passed in the wrong format.', ['phone' => 'empty field']);
        if (!$code) return error_response(1003, 'One of the parameters was missing or was passed in the wrong format.', ['code' => 'empty field']);
        // check
        $q = DB::query("SELECT user_id, access, first_name, phone_code, phone_attempts_code, last_login FROM users WHERE phone='".$phone."' LIMIT 1;") or die (DB::error());
        $row = DB::fetch_row($q);
        // error (unregistered)
        if (!$row) return error_response(1004, 'User with this phone is not found', ['phone' => 'user is not registered']);
        // error (login attempts)
        $attempts = LOGIN_ATTEMPTS - Session::phone_attempts_code($row['user_id'], $row['last_login'], $row['phone_attempts_code']);
        if (!$attempts) return error_response(1005, 'Number of invalid code attempts has been exceeded for this user, please try again later.', ['code' => 'exceeded error limit, please try later']);
        // error (code)
        if ($row['phone_code'] != $code) {
            DB::query("UPDATE users SET phone_attempts_code=phone_attempts_code+1, last_login='".Session::$ts."' WHERE user_id='".$row['user_id']."' LIMIT 1;") or die (DB::error());
            return error_response(1005, 'Invalid phone code, number of remaining attempts is '.$attempts.'.', ['code' => 'invalid phone code']);
        }
        // vars
        Session::$user_id = $row['user_id'];
        Session::$access = $row['access'];
        Session::$ts = time();
        Session::$tz = 240;
        // update
        return Session::session_create();
    }

    // SERVICE

    private static function session_refresh() {
        if (Session::$token) setcookie('token', Session::$token, strtotime('+1 year'), '/', '', Session::$cookies_secure, true);
        DB::query("UPDATE sessions SET tz='".Session::$tz."', updated='".Session::$ts."' WHERE token='".Session::$token."' LIMIT 1;") or die (DB::error());
    }

    private static function set_cookie($key, $value) {
        setcookie($key, $value, strtotime('+1 year'), '/', '', Session::$cookies_secure, true);
    }

    private static function unset_cookie_token() {
        setcookie('token', '', 1, '/', '', Session::$cookies_secure, true);
        unset($_COOKIE['token']);
        return 'ok';
    }

}
