(function () {
    'use strict';
    var script = document.currentScript;
    var token = script && script.getAttribute('data-csrf');
    if (!token) return;
    function local(url) {
        try { return new URL(url || location.href, location.href).origin === location.origin; }
        catch (_) { return false; }
    }
    function addToken(form) {
        if (!local(form.action) || (form.method || 'get').toLowerCase() !== 'post') return;
        var field = form.querySelector('input[name="_csrf"]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden'; field.name = '_csrf'; form.appendChild(field);
        }
        field.value = token;
    }
    var open = XMLHttpRequest.prototype.open;
    var send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) {
        this._csrfLocal = local(url) && !/^(GET|HEAD|OPTIONS)$/i.test(method);
        return open.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function () {
        if (this._csrfLocal) this.setRequestHeader('X-CSRF-Token', token);
        return send.apply(this, arguments);
    };
    if (window.fetch) {
        var fetch = window.fetch;
        window.fetch = function (input, options) {
            var request = new Request(input, options);
            if (local(request.url) && !/^(GET|HEAD|OPTIONS)$/i.test(request.method)) {
                var headers = new Headers(request.headers);
                headers.set('X-CSRF-Token', token);
                request = new Request(request, { headers: headers });
            }
            return fetch.call(window, request);
        };
    }
    document.addEventListener('submit', function (event) { addToken(event.target); }, true);
    var submit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () { addToken(this); return submit.apply(this, arguments); };
    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.forms, addToken);
    });
    document.addEventListener('click', function (event) {
        var link = event.target.closest && event.target.closest('a[href]');
        if (!link || !local(link.href) || new URL(link.href).pathname !== '/logout.php') return;
        event.preventDefault(); event.stopImmediatePropagation();
        var form = document.createElement('form');
        form.method = 'post'; form.action = '/logout.php'; document.body.appendChild(form);
        form.submit();
    }, true);
}());
