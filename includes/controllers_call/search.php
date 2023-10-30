<?php

function controller_search($act, $d) {
    if ($act == 'plots') return Plot::plots_fetch($d);
    if ($act == 'users') return User::users_fetch($d);
    return '';
}
