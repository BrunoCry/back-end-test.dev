<?php

class DB {

    private static $db;

    public static function connect() {
        if (!DB::$db) {
            try {
                DB::$db = new PDO(
                    'mysql:dbname='.DB_NAME.';host='.DB_HOST.';charset=utf8mb4;',
                    DB_USER,
                    DB_PASS,
                    [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                );
            } catch (PDOException $e) {
                print 'Error!: '.$e->getMessage().'<br/>';
                die();
            }
        }
        return DB::$db;
    }

    public static function query($q) {
        return DB::connect()->query($q);
    }

    public static function fetch_row($q) {
        return $q->fetch();
    }

    public static function error() {
        $res = DB::connect()->errorInfo();
        trigger_error($res[2], E_USER_WARNING);
        return $res[2];
    }

}
