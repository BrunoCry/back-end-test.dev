let common = {

    // vars

    modal_progress: false,
    modal_open: false,

    // common

    init: () => {
        add_event(document, 'mousedown touchstart', common.auto_hide_modal);
        add_event(document, 'click', () => common.menu_popup_hide_all('inactive', event));
        add_event(document, 'scroll', () => common.menu_popup_hide_all('all', event));
    },

    menu_popup_toggle: (el, e) => {
        el = qs('.menu_popup', el);
        if (has_class(el, 'active') && !e.target.closest('.menu_popup')) remove_class(el, 'active');
        else {
            common.menu_popup_hide_all('all');
            add_class(el, 'active');
        }
        if (e.target.tagName !== 'A') cancel_event(e);
    },

    menu_popup_hide_all: (mode, e) => {
        qs_all('.menu_popup.active').forEach((el) => {
            if (mode === 'all' || !e.target.closest('.menu_popup')) remove_class(el, 'active');
        })
    },

    // modal

    modal_show: (width, content) => {
        // progress
        if (common.modal_progress) return false;
        // width
        let display_width = w_width();
        if (width > display_width - 20) width = display_width - 40;
        // active
        add_class('modal', 'active');
        common.modal_open = true;
        set_style('modal_content', 'width', width);
        set_style(document.body, 'overflowY', 'hidden');
        // actions
        html('modal_content', content);
        common.modal_resize();
    },

    modal_hide: () => {
        // progress
        if (common.modal_progress) return false;
        common.modal_progress = true;
        // update
        set_style('modal_container', 'overflow', 'hidden');
        remove_class('modal', 'active');
        html('modal_content', '');
        set_style('modal_container', 'overflow', '');
        set_style(document.body, 'overflowY', 'scroll');
        common.modal_progress = false;
        common.modal_open = false;
    },

    modal_resize: () => {
        // vars
        let h_display = window.innerHeight;
        let h_content = ge('modal_content').clientHeight;
        let k = (h_content * 100 / h_display > 85) ? 0.5 : 0.25;
        let margin = (h_display - h_content) * k;
        if (margin < 20) margin = 20;
        // update
        ge('modal_content').style.marginTop = margin + 'px';
        ge('modal_content').style.height = 'auto';
    },

    auto_hide_modal: (e) => {
        if (!has_class('modal', 'active')) return false;
        let t = e.target || e.srcElement;
        if (t.id === 'modal_overlay') on_click('modal_close');
    },

    // auth

    auth_send: () => {
        // vars
        let data = {phone: gv('phone')};
        let location = {dpt: 'auth', act: 'send'};
        // call
        request({location: location, data: data}, (result) => {
            if (result.error_msg) {
                html('login_note', result.error_msg);
                remove_class('login_note', 'fade');
                setTimeout(function() { add_class('login_note', 'fade'); }, 3000);
                setTimeout(function() { html('login_note', ''); }, 3500);
            } else html(qs('body'), result.html);
        });
    },

    auth_confirm: () => {
        // vars
        let data = { phone: gv('phone'), code: gv('code') };
        let location = { dpt: 'auth', act: 'confirm' };
        // call
        request({ location: location, data: data }, (result) => {
            if (result.error_msg) {
                html('login_note', result.error_msg);
                remove_class('login_note', 'fade');
                setTimeout(function() { add_class('login_note', 'fade'); }, 3000);
                setTimeout(function() { html('login_note', ''); }, 3500);
            } else window.location = window.location.href;
        });
    },

    // search

    search_do: (act) => {
        // vars
        let data = { search: gv('search') };
        let location = { dpt: 'search', act: act };
        // call
        request({location: location, data: data}, (result) => {
            html('table', result.html);
            html('paginator', result.paginator);
        });
    },

    // plots

    plot_edit_window: (plot_id, e) => {
        // actions
        cancel_event(e);
        common.menu_popup_hide_all('all');
        // vars
        let data = {plot_id: plot_id};
        let location = {dpt: 'plot', act: 'edit_window'};
        // call
        request({location: location, data: data}, (result) => {
            common.modal_show(400, result.html);
        });
    },

    plot_edit_update: (plot_id = 0) => {
        // vars
        let data = {
            plot_id: plot_id,
            status: gv('status'),
            billing: gv('billing'),
            number: gv('number'),
            size: gv('size'),
            price: gv('price'),
            offset: global.offset
        };
        let location = {dpt: 'plot', act: 'edit_update'};
        // call
        request({location: location, data: data}, (result) => {
            common.modal_hide();
            html('table', result.html);
        });
    },

    // users

    user_edit_window: (user_id, e) => {
        cancel_event(e);
        common.menu_popup_hide_all('all');

        let data = {user_id: user_id};
        let location = {dpt: 'user', act: 'edit_window'};

        request({location: location, data: data}, (result) => {
            common.modal_show(400, result.html);
        });
    },

    user_edit_update: (user_id, event) => {
        cancel_event(event);
        common.menu_popup_hide_all('all');

        var data = {
            user_id: user_id,
            first_name: gv('first_name'),
            last_name: gv('last_name'),
            phone: gv('phone'),
            email: gv('email'),
            plots: gv('plots')
        };

        let location = {dpt: 'user', act: 'edit_update'};

        if(common.is_completed(data)) {
            request({location: location, data: data}, (result) => {
                common.modal_hide();
                html('table', result.html);
            });

            return;
        }

        alert('Not all data is filled!');
    },

    is_completed: (data) => {
        for(var key in data) {
            if(key == 'plots') continue;
            if(data[key].length == 0) return false;
        }

        return true;
    },

    user_delete: (user_id, event) => {
        cancel_event(event);
        common.menu_popup_hide_all('all');

        let data = {user_id: user_id};
        let location = {dpt: 'user', act: 'delete'};

        request({location: location, data: data}, (result) => {
            common.modal_show(400, result.html);
        });
    },

    user_delete_confirm: (user_id, event) => {
        cancel_event(event);
        common.menu_popup_hide_all('all');

        let data = {user_id: user_id};
        let location = {dpt: 'user', act: 'delete_confirm'};

        request({location: location, data: data}, (result) => {
            common.modal_hide();
            html('table', result.html);
        });
    },

    search_users: (act) => {
        // vars
        let data = { search: gv('search') };
        let location = { dpt: 'search', act: act };
        // call
        request({location: location, data: data}, (result) => {
            html('table', result.html);
            html('paginator', result.paginator);
        });
    }
}

add_event(document, 'DOMContentLoaded', common.init);