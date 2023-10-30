// EVENTS

function add_event(el, types, handler, useCapture) {
    // vars
    if (typeof useCapture === 'undefined') useCapture = false;
    el = ge(el);
    // for IE
    if (el.setInterval && el !== window) el = window;
    // add listeners
    types.split(/\s+/).forEach(function(type) {
        if (el.addEventListener) el.addEventListener(type, handler, useCapture);
        else if (el.attachEvent) el.attachEvent('on' + type, handler);
    });
    // clear
    el = null;
}

function cancel_event(event) {
    event = (event || window.event);
    if (!event) return false;
    while (event.originalEvent) {
        event = event.originalEvent;
    }
    if (event.preventDefault) event.preventDefault();
    if (event.stopPropagation) event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    event.cancelBubble = true; // for IE
    event.returnValue = false;
    return false;
}

// DOM

function ge(id) {
    return 'string' == typeof id || 'number' == typeof id ? document.getElementById(id) : id
}

function html(el, html) {
    if (el = ge(el)) el.innerHTML = html;
}

function gv(el) {
    return (el = ge(el)) ? el.value : '';
}

function qs(el, node) {
    return (node || document).querySelector(el);
}

function qs_all(el, node) {
    return (ge(node) || document).querySelectorAll(el)
}

// SIZES

function w_width() {
    return Math.max(
        window.innerWidth || 0,
        document.documentElement.clientWidth || 0,
        document.body.clientWidth || 0
    );
}

// CSS

function has_class(el, name) {
    el = ge(el);
    return el && 1 === el.nodeType && (' ' + el.className + ' ').replace(/[\t\r\n\f]/g, ' ').indexOf(' ' + name + ' ') >= 0;
}

function add_class(el, name) {
    el = ge(el);
    el && !has_class(el, name) && (el.className = (el.className ? el.className + ' ' : '') + name);
}

function remove_class(el, name) {
    el = ge(el);
    el && (el.className = trim((el.className || '').replace((new RegExp('(\\s|^)' + name + '(\\s|$)')), ' ')));
}

function set_style(el, name, value) {
    // vars
    el = ge(el);
    if (!el) return;
    let is_number = typeof (value) == 'number';
    // actions
    if (is_number && (/height|width/i).test(name)) value = Math.abs(value);
    el.style[name] = is_number && !(/font-?weight|line-?height|opacity|z-?index|zoom/i).test(name) ? value + 'px' : value;
}

// REQUESTS

function request(data, callback) {
    let xhr = new XMLHttpRequest();
    if (!xhr) return;
    xhr.open('POST', '/call.php', true);
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.send(request_serialize(data));
    xhr.onreadystatechange = () => {
        if (xhr.readyState !== 4) return;
        if (xhr.status === 200) callback(JSON.parse(xhr.responseText));
        xhr = null;
    }
}

function request_serialize(data, prefix) {
    // vars
    let str = [], p;
    // serialize
    for (p in data) {
        if (data.hasOwnProperty(p)) {
            let k = prefix ? prefix + "[" + p + "]" : p;
            let v = data[p];
            str.push((v !== null && typeof v === "object") ? request_serialize(v, k) : encodeURIComponent(k) + "=" + encodeURIComponent(v));
        }
    }
    // output
    return str.join("&");
}

// SERVICE

function trim(text) {
    return (text || '').replace(/^\s+|\s+$/g, '');
}

function on_click(el) {
    if (el = ge(el)) {
        if (el.click) el.click();
        else if (document.createEvent) {
            let event_obj = document.createEvent('MouseEvents');
            event_obj.initEvent('click', true, true);
            el.dispatchEvent(event_obj);
        }
    } else return false;
}

// POLYFILLS

if (!Element.prototype.closest) {
    Element.prototype.closest = function(css) {
        let node = this;
        while (node) {
            if (node.matches(css)) return node;
            else node = node.parentElement;
        }
        return null;
    };
}

if (!Element.prototype.matches) {
    Element.prototype.matches = Element.prototype.matchesSelector ||
        Element.prototype.webkitMatchesSelector ||
        Element.prototype.mozMatchesSelector ||
        Element.prototype.msMatchesSelector;
}

if (window.NodeList && !NodeList.prototype.forEach) {
    NodeList.prototype.forEach = function(callback, thisArg) {
        thisArg = thisArg || window;
        for (var i = 0; i < this.length; i++) {
            callback.call(thisArg, this[i], i, this);
        }
    };
}
