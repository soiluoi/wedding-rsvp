(function () {
    var node = document.getElementById('wedding-rsvp-data');
    var form = document.getElementById('rsvp-form');
    if (!node || !form) {
        return;
    }
    var data = JSON.parse(node.textContent);
    var summary = document.getElementById('summary');
    var errorBox = document.getElementById('form-error');
    var last = data.response || null;

    function selected(name) {
        var el = form.querySelector('input[name="' + name + '"]:checked');
        return el ? el.value : '';
    }

    function field(name) {
        return form.querySelector('[name="' + name + '"]');
    }

    function toggle(selector, show) {
        form.querySelectorAll(selector).forEach(function (el) {
            if (show) {
                el.removeAttribute('hidden');
            } else {
                el.setAttribute('hidden', '');
            }
        });
    }

    function companionCount() {
        var input = field('companion_count');
        if (!input) return 0;
        var count = parseInt(input.value, 10);
        if (isNaN(count) || count < 0) count = 0;
        var max = data.maxCompanions || 0;
        if (count > max) {
            count = max;
            input.value = String(max);
        }
        return count;
    }

    function readCompanions() {
        var people = [];
        var rows = form.querySelectorAll('.companion');
        rows.forEach(function (row, index) {
            var name = row.querySelector('[name="companion_name_' + index + '"]');
            var type = row.querySelector('[name="companion_type_' + index + '"]:checked');
            people.push({name: name ? name.value : '', type: type ? type.value : ''});
        });
        return people;
    }

    function renderCompanions(count, seed) {
        var list = document.getElementById('companion-list');
        if (!list) return;
        var existing = seed || readCompanions();
        var html = '';
        for (var i = 0; i < count; i++) {
            var person = existing[i] || {name: '', type: ''};
            html += '<div class="companion">';
            html += '<p class="companion-title">Người đi cùng ' + (i + 1) + '</p>';
            html += '<label class="field">Tên<input type="text" name="companion_name_' + i + '" value="' + esc(person.name || '') + '"></label>';
            html += '<div class="choice-row">';
            html += '<label class="choice"><input type="radio" name="companion_type_' + i + '" value="adult"' + (person.type === 'adult' ? ' checked' : '') + '><span>Người lớn</span></label>';
            html += '<label class="choice"><input type="radio" name="companion_type_' + i + '" value="child"' + (person.type === 'child' ? ' checked' : '') + '><span>Trẻ em</span></label>';
            html += '</div></div>';
        }
        list.innerHTML = html;
        updateDietPlaceholder();
    }

    function updateDietPlaceholder() {
        var note = field('diet_note');
        if (!note) return;
        note.placeholder = companionCount() > 0
            ? 'Nếu bạn hay người đi cùng ăn chay hay đặc biệt dị ứng với món nào thì hãy note thêm thông tin nhé'
            : 'Nếu bạn ăn chay hay đặc biệt dị ứng với món nào thì hãy note thêm thông tin nhé';
    }

    function sync() {
        var attending = selected('attending');
        var coming = attending === 'yes';
        var bus = coming && selected('ride_bus') === '1';
        toggle('[data-show="coming"]', coming);
        toggle('[data-show="bus"]', bus);
        if (field('companion_count')) {
            var count = companionCount();
            if (form.querySelectorAll('.companion').length !== count) {
                renderCompanions(count);
            } else {
                updateDietPlaceholder();
            }
        }
    }

    function showError(message) {
        errorBox.hidden = !message;
        errorBox.textContent = message || '';
    }

    function validate(payload) {
        if (!payload.attending) {
            return 'Bạn chọn giúp mình có tham dự được không nhé.';
        }
        if (payload.attending === 'no') {
            return '';
        }
        if (payload.ride_bus !== '0' && payload.ride_bus !== '1') {
            return 'Bạn chọn giúp mình cách đi lại nhé.';
        }
        if (payload.ride_bus === '1' && !String(payload.phone || '').trim()) {
            return 'Bạn để lại số điện thoại để nhà xe tiện liên hệ nhé.';
        }
        var people = payload.companions || [];
        for (var i = 0; i < people.length; i++) {
            if (people[i].type !== 'adult' && people[i].type !== 'child') {
                return 'Bạn chọn người đi cùng ' + (i + 1) + ' là người lớn hay trẻ em nhé.';
            }
            if (!String(people[i].name || '').trim()) {
                return 'Bạn nhập tên người đi cùng ' + (i + 1) + ' giúp mình nhé.';
            }
        }
        return '';
    }

    function collect() {
        sync();
        var attending = selected('attending');
        var payload = {
            attending: attending,
            nonce: data.nonce,
            website: field('website').value
        };
        if (attending === 'yes') {
            payload.ride_bus = selected('ride_bus');
            if (payload.ride_bus === '1') {
                payload.phone = field('phone').value;
            }
            if (field('companion_count')) {
                payload.companions = readCompanions().slice(0, companionCount());
            }
            payload.diet_note = field('diet_note').value;
        }
        return payload;
    }

    function clearHidden() {
        form.querySelectorAll('[hidden] input, [hidden] textarea').forEach(function (el) {
            if (el.name === 'website') return;
            if (el.type === 'radio' || el.type === 'checkbox') {
                el.checked = false;
            } else {
                el.value = '';
            }
        });
    }

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch];
        });
    }

    function phone(value) {
        var digits = String(value || '').replace(/\D/g, '');
        if (digits.length !== 10) {
            return esc(value || '');
        }
        return digits.slice(0, 4) + ' ' + digits.slice(4, 7) + ' ' + digits.slice(7);
    }

    function attendingLabel(value) {
        if (value === 'yes') return 'Có, mình sẽ đến';
        if (value === 'maybe') return 'Chưa chắc';
        if (value === 'no') return 'Rất tiếc, mình không đến được';
        return '';
    }

    function companionPeople(response) {
        if (response && Array.isArray(response.companions) && response.companions.length) {
            return response.companions;
        }
        if (response && Number(response.has_companion) === 1 && (response.companion_type === 'adult' || response.companion_type === 'child')) {
            return [{name: response.companion_name || '', type: response.companion_type}];
        }
        return [];
    }

    function companion(response) {
        var people = companionPeople(response);
        if (!people.length) return 'Không';
        return people.map(function (person) {
            var type = person.type === 'child' ? 'Trẻ em' : 'Người lớn';
            return person.name ? person.name + ' · ' + type : type;
        }).join(', ');
    }

    function fill(response) {
        if (!response) return;
        form.querySelectorAll('input[name="attending"]').forEach(function (el) {
            el.checked = el.value === response.attending;
        });
        form.querySelectorAll('input[name="ride_bus"]').forEach(function (el) {
            el.checked = response.ride_bus != null && el.value === String(response.ride_bus);
        });
        if (field('phone')) field('phone').value = response.phone || '';
        var people = companionPeople(response);
        if (field('companion_count')) {
            field('companion_count').value = String(people.length);
            renderCompanions(people.length, people);
        }
        if (field('diet_note')) field('diet_note').value = response.diet_note || '';
        sync();
    }

    function renderSummary(response, pickup, updatedText) {
        var html = '<h2>Cảm ơn ' + esc(data.salutation || 'bạn') + ' đã phản hồi</h2><ul class="facts">';
        html += '<li><span>Tham dự</span>' + esc(attendingLabel(response.attending)) + '</li>';
        if (response.attending !== 'no') {
            html += '<li><span>Đi lại</span>' + esc(Number(response.ride_bus) === 1 ? 'Đi xe chung' : 'Mình tự đi') + '</li>';
            if (Number(response.ride_bus) === 1 && response.phone) {
                html += '<li><span>Điện thoại</span>' + phone(response.phone) + '</li>';
            }
            html += '<li><span>Người đi cùng</span>' + esc(companion(response)) + '</li>';
            if (response.diet_note) html += '<li><span>Ăn uống</span>' + esc(response.diet_note).replace(/\n/g, '<br>') + '</li>';
        }
        html += '</ul>';
        if (pickup) {
            html += '<div class="pickup"><strong>Điểm đón của bạn</strong><p>' + esc(pickup.name);
            if (pickup.address) html += ' — ' + esc(pickup.address);
            html += '</p>';
            if (pickup.pickup_time) html += '<p>Giờ đón: ' + esc(pickup.pickup_time) + '</p>';
            html += '</div>';
        }
        if (updatedText) html += '<p class="meta">Cập nhật lần cuối: ' + esc(updatedText) + '</p>';
        if (!data.locked) html += '<button type="button" class="ghost" id="edit-response">Sửa câu trả lời</button>';
        summary.innerHTML = html;
        summary.hidden = false;
        form.hidden = true;
        summary.scrollIntoView({block: 'start'});
    }

    form.addEventListener('change', sync);
    form.addEventListener('input', function (event) {
        if (event.target && event.target.name === 'companion_count') sync();
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var payload = collect();
        var problem = validate(payload);
        if (problem) {
            showError(problem);
            return;
        }
        clearHidden();
        showError('');
        if (data.preview) {
            renderSummary({
                attending: payload.attending,
                ride_bus: payload.ride_bus == null ? null : Number(payload.ride_bus),
                phone: payload.phone || null,
                companions: payload.companions || [],
                diet_note: payload.diet_note || null
            }, null, 'Bản xem thử, chưa lưu');
            return;
        }
        var button = form.querySelector('.send');
        button.disabled = true;
        fetch(data.restUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json().then(function (body) {
                if (!res.ok) {
                    throw new Error((body && body.message) ? body.message : 'Không gửi được, bạn thử lại nhé.');
                }
                return body;
            });
        }).then(function (body) {
            last = body.response;
            fill(body.response);
            renderSummary(body.response, body.pickup, body.updated_text);
        }).catch(function (err) {
            showError(err.message || 'Mạng đang chập chờn, bạn thử lại giúp mình nhé.');
            form.hidden = false;
            summary.hidden = true;
        }).then(function () {
            button.disabled = false;
        });
    });

    document.addEventListener('click', function (event) {
        if (!event.target || event.target.id !== 'edit-response') return;
        if (last) fill(last);
        summary.hidden = true;
        form.hidden = false;
        sync();
        form.scrollIntoView({block: 'start'});
    });

    sync();
})();
