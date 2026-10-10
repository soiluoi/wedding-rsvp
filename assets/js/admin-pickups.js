(function () {
    var list = document.getElementById('pickup-list');
    if (!list || !window.WeddingApi) return;
    var pickups = [];

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch];
        });
    }
    function showError(message) {
        var box = document.getElementById('pickup-error');
        box.hidden = !message;
        box.textContent = message || '';
    }
    function resetForm() {
        document.getElementById('pickup-id').value = '';
        document.getElementById('pickup-name').value = '';
        document.getElementById('pickup-address').value = '';
        document.getElementById('pickup-time').value = '';
        document.getElementById('pickup-note').value = '';
        document.getElementById('pickup-sort').value = '0';
        document.getElementById('pickup-save').textContent = 'Thêm điểm đón';
        document.getElementById('pickup-cancel').hidden = true;
    }
    function render() {
        if (!pickups.length) {
            list.innerHTML = '<p>Chưa có điểm đón.</p>';
            return;
        }
        list.innerHTML = '<table class="widefat striped"><thead><tr><th>Tên</th><th>Địa chỉ</th><th>Giờ</th><th>Khách</th><th>Ghi chú</th><th></th></tr></thead><tbody>' +
            pickups.map(function (pickup) {
                return '<tr><td>' + esc(pickup.name) + '</td><td>' + esc(pickup.address) + '</td><td>' + esc(pickup.pickup_time) + '</td><td>' + pickup.guest_count + '</td><td>' + esc(pickup.note) + '</td><td>' +
                    '<button type="button" class="button button-small edit-pickup" data-id="' + pickup.id + '">Sửa</button> ' +
                    '<button type="button" class="button button-small delete-pickup" data-id="' + pickup.id + '">Xóa</button></td></tr>';
            }).join('') + '</tbody></table>';
    }
    function load() {
        return WeddingApi.get('/pickups').then(function (rows) {
            pickups = rows;
            render();
        });
    }
    function payload() {
        return {
            name: document.getElementById('pickup-name').value,
            address: document.getElementById('pickup-address').value,
            pickup_time: document.getElementById('pickup-time').value,
            note: document.getElementById('pickup-note').value,
            sort_order: Number(document.getElementById('pickup-sort').value || 0)
        };
    }

    document.getElementById('pickup-form').addEventListener('submit', function (event) {
        event.preventDefault();
        var id = document.getElementById('pickup-id').value;
        var request = id
            ? WeddingApi.send('/pickups/' + id, {method: 'PATCH', body: payload()})
            : WeddingApi.send('/pickups', {method: 'POST', body: payload()});
        request.then(function () {
            showError('');
            resetForm();
            return load();
        }).catch(function (err) { showError(err.message); });
    });
    document.getElementById('pickup-cancel').addEventListener('click', resetForm);
    list.addEventListener('click', function (event) {
        var button = event.target.closest('button');
        if (!button) return;
        var id = button.getAttribute('data-id');
        var pickup = null;
        pickups.forEach(function (row) { if (String(row.id) === String(id)) pickup = row; });
        if (!pickup) return;
        if (button.classList.contains('edit-pickup')) {
            document.getElementById('pickup-id').value = pickup.id;
            document.getElementById('pickup-name').value = pickup.name;
            document.getElementById('pickup-address').value = pickup.address;
            document.getElementById('pickup-time').value = pickup.pickup_time;
            document.getElementById('pickup-note').value = pickup.note;
            document.getElementById('pickup-sort').value = pickup.sort_order;
            document.getElementById('pickup-save').textContent = 'Lưu điểm đón';
            document.getElementById('pickup-cancel').hidden = false;
            return;
        }
        if (!button.classList.contains('delete-pickup')) return;
        var reassign = '';
        if (pickup.guest_count > 0) {
            var others = pickups.filter(function (row) { return row.id !== pickup.id; });
            if (!others.length) {
                showError('Điểm này đang có khách. Tạo một điểm khác để chuyển khách trước khi xóa.');
                return;
            }
            reassign = window.prompt('Điểm này có ' + pickup.guest_count + ' khách. Nhập ID điểm đón để chuyển họ sang:\n' + others.map(function (row) {
                return row.id + ' — ' + row.name;
            }).join('\n'));
            if (!reassign) return;
        } else if (!window.confirm('Xóa điểm đón ' + pickup.name + '?')) {
            return;
        }
        var path = '/pickups/' + pickup.id + (reassign ? '?reassign_to=' + encodeURIComponent(reassign) : '');
        WeddingApi.send(path, {method: 'DELETE'}).then(function () {
            showError('');
            return load();
        }).catch(function (err) { showError(err.message); });
    });

    load().catch(function (err) { list.textContent = err.message; });
})();
