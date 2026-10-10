(function () {
    var root = document.getElementById('wedding-dashboard');
    if (!root || !window.WeddingApi) return;

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch];
        });
    }

    function busLine(seats, buses, empty) {
        if (!seats) return '0 ghế → 0 xe';
        if (!empty) return seats + ' ghế → ' + buses + ' xe, kín chỗ';
        if (buses <= 1) return seats + ' ghế → 1 xe, còn ' + empty + ' ghế trống';
        return seats + ' ghế → ' + buses + ' xe, xe ' + buses + ' còn ' + empty + ' ghế trống';
    }

    function sourceMatrix(stats) {
        var rows = [['Khách của', 'Nhóm khách', 'Lượt mời', 'Đã trả lời', 'Tham dự (người)', 'Đi xe (ghế)', 'Chưa chắc đi xe']];
        stats.by_source.groups.forEach(function (group) {
            group.rows.forEach(function (row) {
                rows.push([group.host, row.group_name, row.invited, row.responded, row.attending_people, row.bus_seats, row.maybe_bus_seats]);
            });
            rows.push([group.host, 'Tổng', group.subtotal.invited, group.subtotal.responded, group.subtotal.attending_people, group.subtotal.bus_seats, group.subtotal.maybe_bus_seats]);
        });
        var total = stats.by_source.total;
        rows.push(['Tổng', '', total.invited, total.responded, total.attending_people, total.bus_seats, total.maybe_bus_seats]);
        return rows;
    }

    function render(stats) {
        var maxParty = stats.maybe ? '<p>Nếu tính cả chưa chắc: ' + stats.max_adults + ' người lớn · ' + stats.max_children + ' trẻ em · tổng ' + stats.max_party_total + '</p>' : '';
        var maxBus = stats.maybe ? '<p>Nếu tính cả chưa chắc: ' + busLine(stats.max_bus_seats, stats.max_buses, stats.max_bus_empty_seats) + '</p>' : '';
        var maxTables = stats.maybe && stats.max_tables !== stats.tables ? '<p>Nếu tính cả chưa chắc: ' + stats.max_tables + ' bàn</p>' : '';
        var html = '<div class="wedding-cards">';
        html += '<article class="wedding-card"><span>Đã phản hồi</span><strong>' + stats.responded + ' / ' + stats.invited + '</strong><div class="wedding-bar"><span style="width:' + stats.response_rate + '%"></span></div><p>' + stats.response_rate + '%</p></article>';
        html += '<article class="wedding-card"><span>Tham dự</span><strong>' + stats.party_total + ' người</strong><p>' + stats.adults + ' người lớn · ' + stats.children + ' trẻ em</p>' + maxParty + '</article>';
        html += '<article class="wedding-card"><span>Phản hồi khác</span><strong>' + stats.no + ' không đến</strong><p>' + stats.maybe + ' chưa chắc · ' + stats.pending + ' chưa trả lời</p></article>';
        html += '<article class="wedding-card"><span>Số bàn dự kiến</span><strong>' + stats.tables + ' bàn</strong>' + maxTables + '</article>';
        html += '<article class="wedding-card"><span>Xe đưa đón</span><strong>' + busLine(stats.bus_seats, stats.buses, stats.bus_empty_seats) + '</strong>' + maxBus + '</article>';
        html += '</div>';

        html += '<h2>Theo nguồn khách</h2><p><button type="button" class="button" id="copy-source">Copy bảng</button></p>';
        html += '<table class="widefat striped"><thead><tr><th>Khách của</th><th>Nhóm khách</th><th>Lượt mời</th><th>Đã trả lời</th><th>Tham dự (người)</th><th>Đi xe (ghế)</th><th>Chưa chắc đi xe</th></tr></thead><tbody>';
        stats.by_source.groups.forEach(function (group) {
            group.rows.forEach(function (row) {
                html += '<tr><td>' + esc(group.host) + '</td><td>' + esc(row.group_name) + '</td><td>' + row.invited + '</td><td>' + row.responded + '</td><td>' + row.attending_people + '</td><td>' + row.bus_seats + '</td><td>' + row.maybe_bus_seats + '</td></tr>';
            });
            html += '<tr class="wedding-subtotal"><td>' + esc(group.host) + '</td><td>Tổng</td><td>' + group.subtotal.invited + '</td><td>' + group.subtotal.responded + '</td><td>' + group.subtotal.attending_people + '</td><td>' + group.subtotal.bus_seats + '</td><td>' + group.subtotal.maybe_bus_seats + '</td></tr>';
        });
        var total = stats.by_source.total;
        html += '<tr class="wedding-subtotal"><td>Tổng</td><td></td><td>' + total.invited + '</td><td>' + total.responded + '</td><td>' + total.attending_people + '</td><td>' + total.bus_seats + '</td><td>' + total.maybe_bus_seats + '</td></tr>';
        html += '</tbody></table>';

        html += '<h2>Theo điểm đón</h2><table class="widefat striped"><thead><tr><th>Điểm đón</th><th>Ghế đã gán</th><th>Khách chưa báo</th></tr></thead><tbody>';
        stats.by_pickup.pickups.forEach(function (pickup) {
            html += '<tr><td>' + esc(pickup.name) + '</td><td>' + pickup.seats + '</td><td>' + pickup.unnotified_guests + '</td></tr>';
        });
        html += '<tr><td>Chưa gán điểm đón</td><td>' + stats.by_pickup.unassigned_seats + '</td><td>' + stats.by_pickup.unassigned_guests + ' khách đi xe</td></tr>';
        html += '</tbody></table>';

        html += '<h2>Hoạt động gần đây</h2>';
        if (!stats.recent.length) {
            html += '<p>Chưa có câu trả lời.</p>';
        } else {
            html += '<table class="widefat striped"><thead><tr><th>Khách</th><th>Lúc</th><th></th><th>Tham dự</th></tr></thead><tbody>';
            stats.recent.forEach(function (row) {
                html += '<tr><td>' + esc(row.name) + '</td><td>' + esc(row.updated_at) + '</td><td>' + esc(row.kind) + '</td><td>' + esc(row.attending) + '</td></tr>';
            });
            html += '</tbody></table>';
        }
        root.innerHTML = html;
        var button = document.getElementById('copy-source');
        if (button) {
            button.addEventListener('click', function () {
                window.WeddingClipboard.copy(sourceMatrix(stats));
            });
        }
    }

    WeddingApi.get('/stats').then(render).catch(function (err) {
        root.textContent = err.message;
    });
})();
