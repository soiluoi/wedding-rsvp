(function () {
    function clean(value) {
        return String(value == null ? '' : value).replace(/[\t\r\n]+/g, ' ');
    }

    function esc(value) {
        return clean(value).replace(/[&<>"']/g, function (ch) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch];
        });
    }

    function toast(message) {
        var el = document.createElement('div');
        el.className = 'wedding-toast';
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 3200);
    }

    function fallback(text) {
        var area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.top = '0';
        area.style.left = '-9999px';
        document.body.appendChild(area);
        area.focus();
        area.select();
        area.setSelectionRange(0, area.value.length);
        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (err) {
            ok = false;
        }
        document.body.removeChild(area);
        return ok;
    }

    function tsv(matrix) {
        return matrix.map(function (row) {
            return row.map(clean).join('\t');
        }).join('\n');
    }

    function htmlTable(matrix) {
        return '<table>' + matrix.map(function (row) {
            return '<tr>' + row.map(function (cell) {
                return '<td>' + esc(cell) + '</td>';
            }).join('') + '</tr>';
        }).join('') + '</table>';
    }

    window.WeddingClipboard = {
        copy: function (matrix) {
            var plain = tsv(matrix);
            var html = htmlTable(matrix);
            var count = Math.max(0, matrix.length - 1);
            var done = function (ok) {
                toast(ok
                    ? 'Đã copy ' + count + ' dòng, dán vào Google Sheet bằng Ctrl+V'
                    : 'Không copy được. Bạn thử lại giúp mình.');
            };
            var secure = window.isSecureContext && navigator.clipboard && window.ClipboardItem;
            if (!secure) {
                done(fallback(plain));
                return;
            }
            try {
                var item = new ClipboardItem({
                    'text/plain': new Blob([plain], {type: 'text/plain'}),
                    'text/html': new Blob([html], {type: 'text/html'})
                });
                navigator.clipboard.write([item]).then(function () {
                    done(true);
                }).catch(function () {
                    done(fallback(plain));
                });
            } catch (err) {
                done(fallback(plain));
            }
        },
        copyText: function (text, message) {
            var finish = function (ok) {
                toast(ok ? (message || 'Đã copy') : 'Không copy được. Bạn thử lại giúp mình.');
            };
            if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    finish(true);
                }).catch(function () {
                    finish(fallback(text));
                });
                return;
            }
            finish(fallback(text));
        }
    };
})();
