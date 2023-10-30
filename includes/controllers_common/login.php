<?php

function controller_login() {
    Route::$path = 'login';
    HTML::assign('main_content', 'login.html');
}
