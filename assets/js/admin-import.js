(function () {
    var result = document.getElementById('wedding-import-result');
    if (!result || !window.WeddingApi) return;
    var tab = 'csv';
    var token = '';

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch];
        });
    }
    function setTab(next) {
        tab = next;
        document.getElementById('panel-csv').hidden = next !== 'csv';
        document.getElementById('panel-paste').hidden = next !== 'paste';
        document.getElementById('tab-csv').className = next === 'csv' ? 'button button-primary' : 'button';
        document.getElementById('tab-paste').className = next === 'paste' ? 'button button-primary' : 'button';
        var mode = document.querySelector('input[name="import-mode"][value="' + (next === 'csv' ? 'sync' : 'upsert') + '"]');
        if (mode) mode.checked = true;
    }
    document.getElementById('tab-csv').addEventListener('click', function () { setTab('csv'); });
    document.getElementById('tab-paste').addEventListener('click', function () { setTab('paste'); });

    function readText() {
        if (tab === 'paste') {
            return Promise.resolve(document.getElementById('wedding-paste').value);
        }
        var file = document.getElementById('wedding-file').files[0];
        if (!file) return Promise.reject(new Error('Bạn chọn file CSV giúp mình.'));
        if (typeof file.text === 'function') return file.text();
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () { resolve(String(reader.result || '')); };
            reader.onerror = function () { reject(new Error('Không đọc được file.')); };
            reader.readAsText(file, 'UTF-8');
        });
    }

    function renderDiff(diff) {
        var labels = {new: 'Thêm mới', update: 'Cập nhật', unchanged: 'Không đổi', reactivated: 'Kích hoạt lại', conflict: 'Xung đột', error: 'Lỗi'};
        var html = '<p>Thêm ' + diff.counts.new + ' · cập nhật ' + diff.counts.update + ' · không đổi ' + diff.counts.unchanged + ' · kích hoạt lại ' + diff.counts.reactivated + ' · xung đột ' + diff.counts.conflict + ' · lỗi ' + diff.counts.error + '</p>';
        diff.rows.forEach(function (row) {
            html += '<article class="wedding-diff"><h2>Dòng ' + row.line + ' · ' + esc(labels[row.status] || row.status) + '</h2>';
            html += '<p><strong>' + esc(row.incoming.salutation) + ' ' + esc(row.incoming.name) + '</strong> · ' + esc(row.incoming.host) + (row.match === 'name' ? ' · khớp theo tên' : '') + '</p>';
            Object.keys(row.changes || {}).forEach(function (key) {
                var change = row.changes[key];
                html += '<p>' + esc(change.label) + ': ' + esc(change.from) + ' → ' + esc(change.to) + '</p>';
            });
            (row.warnings || []).forEach(function (warning) { html += '<p>⚠ ' + esc(warning) + '</p>'; });
            (row.errors || []).forEach(function (error) { html += '<p>⛔ ' + esc(error) + '</p>'; });
            if (row.status === 'conflict') {
                html += '<p>Có nhiều khách trùng tên. Bạn chọn một người, hoặc tạo mới. Ở chế độ đồng bộ, khách không được chọn sẽ bị ẩn.</p>';
                (row.candidates || []).forEach(function (candidate) {
                    html += '<label><input type="radio" name="res-' + row.line + '" value="match:' + candidate.id + '"> ' + esc(candidate.salutation + ' ' + candidate.name) + ' · mã ' + esc(candidate.code) + ' · ' + esc(candidate.status === 'inactive' ? 'đang ẩn' : 'đang mời') + '</label><br>';
                });
                html += '<label><input type="radio" name="res-' + row.line + '" value="create"> Tạo khách mới</label>';
            }
            html += '</article>';
        });
        if (diff.mode === 'sync' && diff.to_inactivate.length) {
            html += '<article class="wedding-diff"><h2>Sẽ ẩn</h2><ul>';
            diff.to_inactivate.forEach(function (guest) {
                html += '<li>' + esc(guest.name) + ' · ' + esc(guest.code) + '</li>';
            });
            html += '</ul><p>Khách đang nằm trong xung đột được tính sau khi bạn chọn.</p></article>';
        }
        if (diff.has_errors) {
            html += '<p>File còn dòng lỗi nên chưa áp dụng được.</p>';
        } else {
            html += '<p><button type="button" class="button button-primary" id="wedding-apply">Áp dụng</button></p>';
        }
        result.innerHTML = html;
        var apply = document.getElementById('wedding-apply');
        if (apply) apply.addEventListener('click', applyImport);
    }

    function resolutions(diff) {
        var chosen = {};
        var missing = false;
        diff.rows.forEach(function (row) {
            if (row.status !== 'conflict') return;
            var input = document.querySelector('input[name="res-' + row.line + '"]:checked');
            if (!input) {
                missing = true;
                return;
            }
            if (input.value === 'create') {
                chosen[row.line] = {action: 'create'};
            } else {
                chosen[row.line] = {action: 'match', guest_id: Number(input.value.split(':')[1])};
            }
        });
        if (missing) throw new Error('Bạn xử lý hết các dòng xung đột giúp mình.');
        return chosen;
    }

    function applyImport() {
        var diff = result._diff;
        var chosen;
        try {
            chosen = resolutions(diff);
        } catch (err) {
            window.alert(err.message);
            return;
        }
        WeddingApi.send('/import/apply', {method: 'POST', body: {import_token: token, resolutions: chosen}}).then(function (summary) {
            var names = (summary.inactivated_guests || []).map(function (guest) { return guest.name; }).filter(Boolean);
            result.innerHTML = '<div class="notice notice-success"><p>Đã thêm ' + summary.created + ', cập nhật ' + summary.updated + ', kích hoạt lại ' + summary.reactivated + ', không đổi ' + summary.unchanged + ', ẩn ' + summary.inactivated + '.</p>' +
                (names.length ? '<p>Đã ẩn: ' + esc(names.join(', ')) + '</p>' : '') +
                '<p><a class="button button-primary" href="' + esc(window.WeddingRSVP.exportUrl) + '">Export CSV ngay</a></p></div>';
        }).catch(function (err) {
            window.alert(err.message);
        });
    }

    document.getElementById('wedding-preview').addEventListener('click', function () {
        readText().then(function (text) {
            var mode = document.querySelector('input[name="import-mode"]:checked').value;
            return WeddingApi.send('/import/preview', {
                method: 'POST',
                body: {text: text, format: tab === 'paste' ? 'tsv' : 'csv', mode: mode}
            });
        }).then(function (payload) {
            token = payload.import_token;
            result._diff = payload.diff;
            renderDiff(payload.diff);
        }).catch(function (err) {
            result.innerHTML = '<p class="wedding-error">' + esc(err.message) + '</p>';
        });
    });
})();
