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

    function sync() {
        var attending = selected('attending');
        var coming = attending === 'yes' || attending === 'maybe';
        var bus = coming && selected('ride_bus') === '1';
        var box = field('has_companion');
        var plus = coming && data.allowPlusOne && box && box.checked;
        var child = plus && selected('companion_type') === 'child';
        toggle('[data-show="coming"]', coming);
        toggle('[data-show="bus"]', bus);
        toggle('[data-show="plus"]', plus);
        toggle('[data-show="child"]', child);
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
        if (payload.has_companion) {
            if (payload.companion_type !== 'adult' && payload.companion_type !== 'child') {
                return 'Bạn chọn giúp mình người đi cùng là người lớn hay trẻ em nhé.';
            }
            if (payload.companion_type === 'child' && !/^\d+$/.test(String(payload.companion_age || ''))) {
                return 'Bạn nhập tuổi của bé (từ 0 đến 15) giúp mình nhé.';
            }
            if (payload.companion_type === 'child' && Number(payload.companion_age) > 15) {
                return 'Bạn nhập tuổi của bé (từ 0 đến 15) giúp mình nhé.';
            }
        }
        return '';
    }

    function collect() {
        sync();
        var attending = selected('attending');
        var payload = {
            attending: attending,
            message: field('message').value,
            nonce: data.nonce,
            website: field('website').value
        };
        if (attending === 'yes' || attending === 'maybe') {
            payload.ride_bus = selected('ride_bus');
            if (payload.ride_bus === '1') {
                payload.phone = field('phone').value;
            }
            var box = field('has_companion');
            payload.has_companion = box && box.checked ? 1 : 0;
            if (payload.has_companion) {
                payload.companion_name = field('companion_name').value;
                payload.companion_type = selected('companion_type');
                if (payload.companion_type === 'child') {
                    payload.companion_age = field('companion_age').value;
                }
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

    function companion(response) {
        if (!response || Number(response.has_companion) !== 1) {
            return 'Không';
        }
        var parts = [];
        if (response.companion_name) parts.push(response.companion_name);
        if (response.companion_type === 'child') parts.push('Trẻ em');
        if (response.companion_type === 'adult') parts.push('Người lớn');
        if (response.companion_type === 'child' && response.companion_age != null && response.companion_age !== '') {
            parts.push(response.companion_age + ' tuổi');
        }
        return parts.join(' · ');
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
        var box = field('has_companion');
        if (box) box.checked = Number(response.has_companion) === 1;
        if (field('companion_name')) field('companion_name').value = response.companion_name || '';
        form.querySelectorAll('input[name="companion_type"]').forEach(function (el) {
            el.checked = el.value === response.companion_type;
        });
        if (field('companion_age')) field('companion_age').value = response.companion_age == null ? '' : response.companion_age;
        if (field('diet_note')) field('diet_note').value = response.diet_note || '';
        if (field('message')) field('message').value = response.message || '';
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
            if (response.diet_note) html += '<li><span>Ăn uống</span>' + esc(response.diet_note) + '</li>';
        }
        if (response.message) html += '<li><span>Lời chúc</span>' + esc(response.message) + '</li>';
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
                has_companion: payload.has_companion ? 1 : 0,
                companion_name: payload.companion_name || null,
                companion_type: payload.companion_type || null,
                companion_age: payload.companion_age == null || payload.companion_age === '' ? null : Number(payload.companion_age),
                diet_note: payload.diet_note || null,
                message: payload.message || null
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
