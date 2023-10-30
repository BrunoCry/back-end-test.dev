<?php

class User {

    // GENERAL

    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, phone, access FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0
            ];
        }
    }

    public static function users_list($d = []) {
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];

        // where
        $where = [];

        // Search by first name, email, phone
        if ($search) $where[] = "first_name LIKE '%" . $search . "%' OR phone LIKE '%" . $search . "%' OR email LIKE '%" . $search . "%'";
        $where = $where ? "WHERE " . implode(" AND ", $where) : "";

        // info
        $q = DB::query("SELECT * FROM users " . $where . " ORDER BY user_id+0 LIMIT " . $offset . ", " . $limit . ";") or die (DB::error());

        while ($row = DB::fetch_row($q)) {
            $id = (int) $row['user_id'];

            $items[] = [
                'id' => $id,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'last_login' => $row['last_login'],
                'plots' => self::parce_user_plots($id)
            ];
        }

        // paginator
        $q = DB::query("SELECT count(*) FROM users ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search='.$search;
        paginator($count, $offset, $limit, $url, $paginator);

        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function user_edit_info($id) {
        $query = DB::query("SELECT first_name, last_name, email, phone FROM users WHERE user_id = " . $id . " LIMIT 1;");

        $plots = self::parce_user_plots($id);

        if($row = DB::fetch_row($query)) {
            return [
                'user_id'    => $id,
                'first_name' => $row['first_name'],
                'last_name'  => $row['last_name'],
                'email'      => $row['email'],
                'phone'      => $row['phone'],
                'plots'    => $plots
            ];
        } else {
            return [
                'user_id'    => 0,
                'first_name' => '',
                'last_name'  => '',
                'email'      => '',
                'phone'      => '',
                'plots'   => ''
            ];
        }
    }

    public static function user_edit_update($d = []) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $email = strtolower($d['email']);
        $phone = preg_replace("/[^0-9 ]/", '', $d['phone']);
        $first_name = $d['first_name'];
        $last_name = $d['last_name'];

        if(isset($d['plots'])) {
            $items = explode(', ', $d['plots']);

            $delete_exists = DB::query("DELETE FROM users_plots WHERE user_id = " . $user_id . ";") or die (DB::error());

            foreach($items as $item) {
                $add_new = DB::query("INSERT INTO users_plots (user_id, plot_id) VALUES (" . $user_id . ", " . $item . ");") or die (DB::error());
            }
        }

        $code = 1111;

        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        // update
        if ($user_id) {
            $set = [];
            $set[] = "email='" . $email . "'";
            $set[] = "phone='" . $phone . "'";
            $set[] = "first_name='" . $first_name . "'";
            $set[] = "last_name='" . $last_name . "'";
            $set[] = "updated='" . Session::$ts . "'";
            $set = implode(", ", $set);
            DB::query("UPDATE users SET " . $set . " WHERE user_id='" . $user_id . "' LIMIT 1;") or die (DB::error());
        } else {
            DB::query("INSERT INTO users (
                email,
                phone,
                first_name,
                last_name,
                phone_code,
                updated
            ) VALUES (
                '" . $email . "',
                '" . $phone . "',
                '" . $first_name . "',
                '" . $last_name . "',
                '" . $code . "',
                '" . Session::$ts . "'
            );") or die (DB::error());
        }
        // output
        return User::users_fetch(['offset' => $offset]);
    }

    public static function users_fetch($d = []) {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    public static function user_edit_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;

        HTML::assign('user', User::user_edit_info($user_id));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_delete($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;

        HTML::assign('user', User::user_edit_info($user_id));
        return ['html' => HTML::fetch('./partials/user_delete.html')];
    }

    public static function user_delete_confirm($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;

        $delete = DB::query("DELETE FROM users WHERE user_id = " . $user_id . ";");

        return User::users_fetch();
    }

    public static function users_list_plots($number) {
        $items = [];

        $query = DB::query("SELECT users_plots.user_id, users.first_name, users.email, users.phone FROM users_plots LEFT JOIN users ON users.user_id = users_plots.user_id WHERE plot_id = " . $number . " ORDER BY users.user_id;");

        while($row = DB::fetch_row($query)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }

        return $items;
        /*
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;*/
    }

    public static function parce_user_plots($id) {
        $items = [];

        $query = DB::query("SELECT plot_id FROM users_plots WHERE user_id = " . $id . ";");

        while($row = DB::fetch_row($query)) {
            $items[] = $row['plot_id'];
        }

        $items = implode(", ", $items);

        return $items;
    }
}
