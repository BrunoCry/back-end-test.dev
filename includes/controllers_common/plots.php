<?php

function controller_plots() {
    // vars
    $offset = isset($_GET['offset']) ? flt_input($_GET['offset']) : 0;
    $search = $_GET['search'] ?? '';
    // info
    $plots = Plot::plots_list(['mode' => 'page', 'offset' => $offset, 'search' => $search]);
    // output
    HTML::assign('plots', $plots['items']);
    HTML::assign('paginator', $plots['paginator']);
    HTML::assign('search', $search);
    HTML::assign('offset', $offset);
    HTML::assign('section', 'plots.html');
    HTML::assign('main_content', 'home.html');
}
