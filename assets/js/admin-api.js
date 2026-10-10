(function () {
    var cfg = window.WeddingRSVP || {};
    function send(path, options) {
        options = options || {};
        var headers = {'X-WP-Nonce': cfg.nonce};
        if (options.body) {
            headers['Content-Type'] = 'application/json';
        }
        return fetch(cfg.restUrl + String(path).replace(/^\//, ''), {
            method: options.method || 'GET',
            credentials: 'same-origin',
            headers: headers,
            body: options.body ? JSON.stringify(options.body) : undefined
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    throw new Error((data && data.message) ? data.message : 'Có lỗi xảy ra.');
                }
                return data;
            });
        });
    }
    window.WeddingApi = {
        get: function (path) { return send(path, {method: 'GET'}); },
        send: send
    };
})();
