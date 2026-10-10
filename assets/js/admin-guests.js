(function () {
    var body = document.getElementById('wedding-guest-body');
    if (!body || !window.WeddingApi) return;
    var cfg = window.WeddingRSVP;
    var guests = [];
    var pickups = [];

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch];
        });
    }
    function fold(value) {
        var text = String(value || '');
        if (text.normalize) {
            text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return text.replace(/đ/g, 'd').replace(/Đ/g, 'd').toLowerCase();
    }
    function flag(value) {
        return value === true || value === 1 || value === '1';
    }
    function formatPhone(digits) {
        digits = String(digits || '').replace(/\D/g, '');
        if (digits.length !== 10) return digits;
        return digits.slice(0, 4) + ' ' + digits.slice(4, 7) + ' ' + digits.slice(7);
    }
    function formatDate(value) {
        var match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        return match ? match[3] + '/' + match[2] + '/' + match[1] + ' ' + match[4] + ':' + match[5] : '';
    }
    function companionList(response) {
        if (response && Array.isArray(response.companions) && response.companions.length) {
            return response.companions.filter(function (item) {
                return item && (item.type === 'adult' || item.type === 'child');
            });
        }
        if (response && Number(response.has_companion) === 1 && (response.companion_type === 'adult' || response.companion_type === 'child')) {
            return [{name: response.companion_name || '', type: response.companion_type}];
        }
        return [];
    }
    function units(response) {
        var empty = {adults: 0, children: 0, bus: 0};
        if (!response || response.attending !== 'yes') return empty;
        var adults = 1;
        var children = 0;
        companionList(response).forEach(function (person) {
            if (person.type === 'child') children += 1;
            else adults += 1;
        });
        var bus = 0;
        if (Number(response.ride_bus) === 1) {
            bus = 1;
            companionList(response).forEach(function (person) {
                if (person.type === 'adult') bus += 1;
                else if (person.type === 'child' && flag(cfg.settings.child_takes_bus_seat)) bus += 1;
            });
        }
        return {adults: adults, children: children, bus: bus};
    }
    function attendingLabel(value) {
        if (value === 'yes') return 'Có, mình sẽ đến';
        if (value === 'maybe') return 'Chưa chắc';
        if (value === 'no') return 'Rất tiếc, mình không đến được';
        return 'Chưa trả lời';
    }
    function rideLabel(response) {
        if (!response || response.attending === 'no' || response.ride_bus == null || response.ride_bus === '') return '';
        return Number(response.ride_bus) === 1 ? 'Đi xe chung' : 'Mình tự đi';
    }
    function companionLabel(response) {
        var people = companionList(response);
        if (!people.length) return '';
        return people.map(function (person) {
            var type = person.type === 'child' ? 'Trẻ em' : 'Người lớn';
            return person.name ? person.name + ' · ' + type : type;
        }).join(', ');
    }
    function cell(guest, key) {
        var response = guest.response || {};
        var count = units(response);
        switch (key) {
            case 'sort_order': return guest.sort_order;
            case 'code': return guest.code || '';
            case 'salutation': return guest.salutation || '';
            case 'name': return guest.name || '';
            case 'note': return guest.note || '';
            case 'invited_count': return guest.invited_count;
            case 'host': return guest.host || '';
            case 'group_name': return guest.group_name || '';
            case 'link': return guest.link || '';
            case 'attending': return attendingLabel(response.attending || '');
            case 'ride_bus': return rideLabel(guest.response);
            case 'phone': return response.phone ? formatPhone(response.phone) : '';
            case 'companion_name': return response.companion_name || '';
            case 'companion_type': return response.companion_type === 'child' ? 'Trẻ em' : (response.companion_type === 'adult' ? 'Người lớn' : '');
            case 'companion_age': return response.companion_type === 'child' && response.companion_age != null ? response.companion_age : '';
            case 'diet_note': return response.diet_note || '';
            case 'message': return response.message || '';
            case 'pickup_name': return guest.pickup_name || '';
            case 'pickup_address': return guest.pickup_address || '';
            case 'pickup_time': return guest.pickup_time || '';
            case 'link_sent_at': return formatDate(guest.link_sent_at);
            case 'pickup_notified_at': return formatDate(guest.pickup_notified_at);
            case 'response_updated_at': return formatDate(response.updated_at);
            case 'companion': return companionLabel(guest.response);
            case 'bus_seats': return count.bus;
            case 'adults': return count.adults;
            case 'children': return count.children;
            default: return '';
        }
    }
    function fillTemplate(template, guest) {
        var map = {
            danh_xung: guest.salutation || '',
            ten: guest.name || '',
            link: guest.link || '',
            co_dau: cfg.settings.bride_name || '',
            chu_re: cfg.settings.groom_name || '',
            ngay_cuoi: cfg.settings.wedding_date || '',
            dia_diem: cfg.settings.place || '',
            han_chot: cfg.settings.deadline || '',
            diem_don: guest.pickup_name || '',
            dia_chi_don: guest.pickup_address || '',
            gio_don: guest.pickup_time || ''
        };
        return String(template || '').replace(/\{([a-z_]+)\}/g, function (_, key) {
            return Object.prototype.hasOwnProperty.call(map, key) ? map[key] : '';
        });
    }
    function value(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }
    function visibleGuests() {
        var query = fold(value('wedding-search'));
        var host = value('filter-host');
        var group = value('filter-group');
        var attending = value('filter-attending');
        var ride = value('filter-ride');
        var pickup = value('filter-pickup');
        var link = value('filter-link');
        var notified = value('filter-notified');
        var status = value('filter-status') || 'active';
        return guests.filter(function (guest) {
            if (status !== 'all' && guest.status !== status) return false;
            if (host && guest.host !== host) return false;
            if (group && guest.group_name !== group) return false;
            var answer = guest.response ? guest.response.attending : '';
            if (attending === 'pending' && guest.response) return false;
            if (attending && attending !== 'pending' && answer !== attending) return false;
            var rideValue = guest.response && guest.response.ride_bus != null ? String(guest.response.ride_bus) : '';
            if (ride === 'none' && rideValue !== '') return false;
            if (ride && ride !== 'none' && rideValue !== ride) return false;
            if (pickup === 'unassigned' && guest.pickup_id) return false;
            if (pickup && pickup !== 'unassigned' && String(guest.pickup_id) !== pickup) return false;
            if (link === 'yes' && !guest.link_sent_at) return false;
            if (link === 'no' && guest.link_sent_at) return false;
            if (notified === 'yes' && !guest.pickup_notified_at) return false;
            if (notified === 'no' && guest.pickup_notified_at) return false;
            if (query) {
                var hay = fold([guest.name, guest.salutation, guest.note, guest.response && guest.response.companion_name].join(' '));
                if (hay.indexOf(query) === -1) return false;
            }
            return true;
        });
    }
    function selectedGuests(list) {
        var ids = {};
        body.querySelectorAll('.row-check:checked').forEach(function (box) {
            ids[box.value] = true;
        });
        var chosen = list.filter(function (guest) { return ids[String(guest.id)]; });
        return chosen.length ? chosen : list;
    }
    function pickupOptions(selectedId) {
        var html = '<option value="">Chưa gán</option>';
        pickups.forEach(function (pickup) {
            html += '<option value="' + pickup.id + '"' + (String(selectedId) === String(pickup.id) ? ' selected' : '') + '>' + esc(pickup.name) + '</option>';
        });
        return html;
    }
    function render() {
        var list = visibleGuests();
        document.getElementById('wedding-count').textContent = list.length + ' khách';
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="14">Không có khách nào khớp bộ lọc.</td></tr>';
            updateBulk();
            return;
        }
        body.innerHTML = list.map(function (guest) {
            var response = guest.response;
            return '<tr data-id="' + guest.id + '">' +
                '<td><input type="checkbox" class="row-check" value="' + guest.id + '"></td>' +
                '<td>' + esc(guest.sort_order) + '</td>' +
                '<td>' + esc(guest.salutation) + ' <strong>' + esc(guest.name) + '</strong>' + (guest.status === 'inactive' ? '<br><em>Đã ẩn</em>' : '') + '</td>' +
                '<td>' + esc(guest.host) + '</td>' +
                '<td>' + esc(guest.group_name) + '</td>' +
                '<td>' + esc(response ? attendingLabel(response.attending) : 'Chưa trả lời') + '</td>' +
                '<td>' + esc(rideLabel(response) || '—') + '</td>' +
                '<td>' + esc(companionLabel(response) || '—') + '</td>' +
                '<td>' + esc(response && response.phone ? formatPhone(response.phone) : '') + '</td>' +
                '<td><select class="pickup-select" data-id="' + guest.id + '">' + pickupOptions(guest.pickup_id) + '</select></td>' +
                '<td><button type="button" class="button button-small toggle-link" data-id="' + guest.id + '" data-on="' + (guest.link_sent_at ? '1' : '0') + '">' + (guest.link_sent_at ? 'Rồi' : 'Chưa') + '</button></td>' +
                '<td><button type="button" class="button button-small toggle-notified" data-id="' + guest.id + '" data-on="' + (guest.pickup_notified_at ? '1' : '0') + '">' + (guest.pickup_notified_at ? 'Rồi' : 'Chưa') + '</button></td>' +
                '<td>' + esc(response ? formatDate(response.updated_at) : '') + '</td>' +
                '<td class="wedding-actions">' +
                    '<button type="button" class="button button-small copy-link" data-id="' + guest.id + '">Copy link</button>' +
                    '<button type="button" class="button button-small copy-invite" data-id="' + guest.id + '">Copy tin mời</button>' +
                    (guest.pickup_id ? '<button type="button" class="button button-small copy-pickup" data-id="' + guest.id + '">Copy tin đón</button>' : '') +
                    '<button type="button" class="button button-small edit-response" data-id="' + guest.id + '">Sửa câu trả lời</button>' +
                    (response ? '<button type="button" class="button button-small delete-response" data-id="' + guest.id + '">Xóa câu trả lời</button>' : '') +
                '</td></tr>';
        }).join('');
        updateBulk();
    }
    function updateBulk() {
        var count = body.querySelectorAll('.row-check:checked').length;
        var bar = document.getElementById('wedding-bulk');
        bar.hidden = count === 0;
        document.getElementById('wedding-selected').textContent = 'Đã chọn ' + count;
    }
    function guestById(id) {
        for (var i = 0; i < guests.length; i++) {
            if (String(guests[i].id) === String(id)) return guests[i];
        }
        return null;
    }
    function replaceGuest(updated) {
        guests = guests.map(function (guest) { return guest.id === updated.id ? updated : guest; });
        render();
    }
    function fillFilters() {
        var hosts = {};
        var groups = {};
        guests.forEach(function (guest) {
            if (guest.host) hosts[guest.host] = true;
            if (guest.group_name) groups[guest.group_name] = true;
        });
        var hostSelect = document.getElementById('filter-host');
        var groupSelect = document.getElementById('filter-group');
        Object.keys(hosts).sort().forEach(function (host) {
            hostSelect.insertAdjacentHTML('beforeend', '<option value="' + esc(host) + '">' + esc(host) + '</option>');
        });
        Object.keys(groups).sort().forEach(function (group) {
            groupSelect.insertAdjacentHTML('beforeend', '<option value="' + esc(group) + '">' + esc(group) + '</option>');
        });
        var pickupSelect = document.getElementById('filter-pickup');
        pickupSelect.insertAdjacentHTML('beforeend', '<option value="unassigned">Chưa gán</option>');
        var bulk = document.getElementById('bulk-pickup');
        bulk.insertAdjacentHTML('beforeend', '<option value="0">Bỏ gán</option>');
        pickups.forEach(function (pickup) {
            var option = '<option value="' + pickup.id + '">' + esc(pickup.name) + '</option>';
            pickupSelect.insertAdjacentHTML('beforeend', option);
            bulk.insertAdjacentHTML('beforeend', option);
        });
    }
    function matrixFor(preset, list) {
        var columns = cfg.presets[preset].columns;
        var matrix = [columns.map(function (column) { return column.header; })];
        list.forEach(function (guest) {
            matrix.push(columns.map(function (column) { return cell(guest, column.key); }));
        });
        return matrix;
    }
    function maxCompanions() {
        return 10;
    }
    function readEditCompanions() {
        var people = [];
        document.querySelectorAll('#edit-companion-list .wedding-companion-row').forEach(function (row) {
            people.push({
                name: row.querySelector('[data-companion-name]').value,
                type: row.querySelector('[data-companion-type]').value
            });
        });
        return people;
    }
    function renderEditCompanions(seed) {
        var max = maxCompanions();
        var input = document.getElementById('edit-companion-count');
        input.max = String(max);
        var count = parseInt(input.value, 10);
        if (isNaN(count) || count < 0) count = 0;
        if (count > max) count = max;
        input.value = String(count);
        var people = seed || [];
        var html = '';
        for (var i = 0; i < count; i++) {
            var person = people[i] || {name: '', type: 'adult'};
            html += '<div class="wedding-companion-row">';
            html += '<label>Người đi cùng ' + (i + 1) + '<input type="text" data-companion-name value="' + esc(person.name || '') + '"></label>';
            html += '<label>Là<select data-companion-type>';
            html += '<option value="adult"' + (person.type === 'child' ? '' : ' selected') + '>Người lớn</option>';
            html += '<option value="child"' + (person.type === 'child' ? ' selected' : '') + '>Trẻ em</option>';
            html += '</select></label></div>';
        }
        document.getElementById('edit-companion-list').innerHTML = html;
    }
    function openEdit(guest) {
        var response = guest.response || {};
        document.getElementById('edit-id').value = guest.id;
        document.getElementById('wedding-edit-name').textContent = guest.salutation + ' ' + guest.name;
        document.getElementById('edit-attending').value = response.attending || 'yes';
        document.getElementById('edit-ride').value = response.ride_bus == null ? '1' : String(response.ride_bus);
        document.getElementById('edit-phone').value = response.phone || '';
        var people = companionList(response);
        document.getElementById('edit-companion-count').value = String(people.length);
        renderEditCompanions(people);
        document.getElementById('edit-diet').value = response.diet_note || '';
        document.getElementById('edit-message').value = response.message || '';
        document.getElementById('wedding-edit-error').hidden = true;
        syncEdit();
        document.getElementById('wedding-dialog').hidden = false;
    }
    function syncEdit() {
        var attending = document.getElementById('edit-attending').value;
        var coming = attending === 'yes' || attending === 'maybe';
        var bus = coming && document.getElementById('edit-ride').value === '1';
        document.querySelectorAll('[data-edit="coming"]').forEach(function (el) { el.hidden = !coming; });
        document.querySelectorAll('[data-edit="bus"]').forEach(function (el) { el.hidden = !bus; });
    }

    document.getElementById('wedding-edit-form').addEventListener('change', syncEdit);
    document.getElementById('edit-companion-count').addEventListener('input', function () {
        renderEditCompanions(readEditCompanions());
    });
    document.getElementById('wedding-edit-close').addEventListener('click', function () {
        document.getElementById('wedding-dialog').hidden = true;
    });
    document.getElementById('wedding-edit-form').addEventListener('submit', function (event) {
        event.preventDefault();
        var attending = document.getElementById('edit-attending').value;
        var payload = {attending: attending, message: document.getElementById('edit-message').value};
        if (attending !== 'no') {
            payload.ride_bus = document.getElementById('edit-ride').value;
            if (payload.ride_bus === '1') payload.phone = document.getElementById('edit-phone').value;
            payload.companions = readEditCompanions();
            payload.diet_note = document.getElementById('edit-diet').value;
        }
        var id = document.getElementById('edit-id').value;
        WeddingApi.send('/guests/' + id + '/response', {method: 'PUT', body: payload}).then(function () {
            return WeddingApi.get('/guests');
        }).then(function (rows) {
            guests = rows;
            document.getElementById('wedding-dialog').hidden = true;
            render();
        }).catch(function (err) {
            var box = document.getElementById('wedding-edit-error');
            box.hidden = false;
            box.textContent = err.message;
        });
    });

    body.addEventListener('change', function (event) {
        var target = event.target;
        if (target.classList.contains('row-check')) {
            updateBulk();
            return;
        }
        if (target.classList.contains('pickup-select')) {
            WeddingApi.send('/guests/' + target.getAttribute('data-id'), {
                method: 'PATCH',
                body: {pickup_id: target.value || null}
            }).then(replaceGuest).catch(function (err) { window.alert(err.message); });
        }
    });
    body.addEventListener('click', function (event) {
        var button = event.target.closest('button');
        if (!button) return;
        var guest = guestById(button.getAttribute('data-id'));
        if (!guest) return;
        if (button.classList.contains('copy-link')) {
            window.WeddingClipboard.copyText(guest.link, 'Đã copy link');
        } else if (button.classList.contains('copy-invite')) {
            window.WeddingClipboard.copyText(fillTemplate(cfg.settings.invite_message_template, guest), 'Đã copy tin nhắn mời');
        } else if (button.classList.contains('copy-pickup')) {
            window.WeddingClipboard.copyText(fillTemplate(cfg.settings.pickup_message_template, guest), 'Đã copy tin báo điểm đón');
        } else if (button.classList.contains('edit-response')) {
            openEdit(guest);
        } else if (button.classList.contains('delete-response')) {
            if (!window.confirm('Xóa câu trả lời của ' + guest.name + '?')) return;
            WeddingApi.send('/guests/' + guest.id + '/response', {method: 'DELETE'}).then(function () {
                guest.response = null;
                render();
            }).catch(function (err) { window.alert(err.message); });
        } else if (button.classList.contains('toggle-link') || button.classList.contains('toggle-notified')) {
            var on = button.getAttribute('data-on') !== '1';
            var payload = button.classList.contains('toggle-link') ? {link_sent: on} : {pickup_notified: on};
            WeddingApi.send('/guests/' + guest.id, {method: 'PATCH', body: payload}).then(replaceGuest).catch(function (err) {
                window.alert(err.message);
            });
        }
    });

    document.querySelector('.wedding-filters').addEventListener('input', render);
    document.querySelector('.wedding-filters').addEventListener('change', render);
    document.getElementById('wedding-check-all').addEventListener('change', function (event) {
        body.querySelectorAll('.row-check').forEach(function (box) { box.checked = event.target.checked; });
        updateBulk();
    });
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var list = selectedGuests(visibleGuests());
            window.WeddingClipboard.copy(matrixFor(button.getAttribute('data-copy'), list));
        });
    });
    document.getElementById('bulk-assign').addEventListener('click', function () {
        var checked = body.querySelectorAll('.row-check:checked');
        if (!checked.length) return;
        var pickupId = document.getElementById('bulk-pickup').value;
        if (pickupId === '') {
            window.alert('Bạn chọn điểm đón, hoặc chọn Bỏ gán.');
            return;
        }
        var ids = [];
        checked.forEach(function (box) { ids.push(Number(box.value)); });
        WeddingApi.send('/guests/bulk', {
            method: 'POST',
            body: {action: 'assign_pickup', ids: ids, pickup_id: pickupId}
        }).then(reload).catch(function (err) { window.alert(err.message); });
    });
    document.getElementById('bulk-link').addEventListener('click', function () {
        bulk('mark_link_sent');
    });
    document.getElementById('bulk-notified').addEventListener('click', function () {
        bulk('mark_pickup_notified');
    });
    document.getElementById('bulk-messages').addEventListener('click', function () {
        var list = [];
        body.querySelectorAll('.row-check:checked').forEach(function (box) {
            var guest = guestById(box.value);
            if (guest) list.push(guest);
        });
        var text = list.map(function (guest) {
            return fillTemplate(cfg.settings.invite_message_template, guest);
        }).join('\n\n');
        window.WeddingClipboard.copyText(text, 'Đã copy ' + list.length + ' tin nhắn');
    });

    function bulk(action) {
        var ids = [];
        body.querySelectorAll('.row-check:checked').forEach(function (box) { ids.push(Number(box.value)); });
        WeddingApi.send('/guests/bulk', {method: 'POST', body: {action: action, ids: ids}}).then(reload).catch(function (err) {
            window.alert(err.message);
        });
    }
    function reload() {
        return WeddingApi.get('/guests').then(function (rows) {
            guests = rows;
            render();
        });
    }

    Promise.all([WeddingApi.get('/guests'), WeddingApi.get('/pickups')]).then(function (result) {
        guests = result[0];
        pickups = result[1];
        fillFilters();
        render();
    }).catch(function (err) {
        body.innerHTML = '<tr><td colspan="14">' + esc(err.message) + '</td></tr>';
    });
})();
