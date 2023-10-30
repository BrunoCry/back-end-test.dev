<?php

function controller_user($act, $d) {
    if ($act == 'edit_window') return User::user_edit_window($d);
    if ($act == 'edit_update') return User::user_edit_update($d);
    if ($act == 'delete') return User::user_delete($d);
    if ($act == 'delete_confirm') return User::user_delete_confirm($d);
    return '';
}