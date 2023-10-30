<?php

function controller_auth($act, $d) {
    if ($act == 'send') return Session::auth_send($d);
    if ($act == 'confirm') return Session::auth_confirm($d);
    return '';

}
