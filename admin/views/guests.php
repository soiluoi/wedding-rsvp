<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="wrap wedding-admin">
    <h1>Danh sách khách</h1>
    <div class="wedding-filters">
        <input type="search" id="wedding-search" placeholder="Tìm tên, không cần dấu">
        <select id="filter-host"><option value="">Khách của</option></select>
        <select id="filter-group"><option value="">Nhóm khách</option></select>
        <select id="filter-attending">
            <option value="">Tham dự</option>
            <option value="yes">Có</option>
            <option value="maybe">Chưa chắc</option>
            <option value="no">Không đến</option>
            <option value="pending">Chưa trả lời</option>
        </select>
        <select id="filter-ride">
            <option value="">Đi xe</option>
            <option value="1">Đi xe chung</option>
            <option value="0">Tự đi</option>
            <option value="none">Chưa chọn</option>
        </select>
        <select id="filter-pickup"><option value="">Điểm đón</option></select>
        <select id="filter-link">
            <option value="">Đã gửi link</option>
            <option value="yes">Rồi</option>
            <option value="no">Chưa</option>
        </select>
        <select id="filter-notified">
            <option value="">Đã báo điểm đón</option>
            <option value="yes">Rồi</option>
            <option value="no">Chưa</option>
        </select>
        <select id="filter-status">
            <option value="active">Đang mời</option>
            <option value="inactive">Đã ẩn</option>
            <option value="all">Mọi trạng thái</option>
        </select>
    </div>
    <div class="wedding-toolbar">
        <button type="button" class="button" data-copy="invite">Copy danh sách mời</button>
        <button type="button" class="button" data-copy="full">Copy đầy đủ</button>
        <button type="button" class="button" data-copy="bus">Copy đi xe</button>
        <button type="button" class="button" data-copy="party">Copy tiệc</button>
        <span id="wedding-count"></span>
    </div>
    <div id="wedding-bulk" class="wedding-bulk" hidden>
        <strong id="wedding-selected">Đã chọn 0</strong>
        <select id="bulk-pickup"><option value="">Gán điểm đón...</option></select>
        <button type="button" class="button" id="bulk-assign">Gán</button>
        <button type="button" class="button" id="bulk-link">Đánh dấu đã gửi link</button>
        <button type="button" class="button" id="bulk-notified">Đánh dấu đã báo điểm đón</button>
        <button type="button" class="button" id="bulk-messages">Copy tin nhắn mời</button>
    </div>
    <div class="wedding-table-wrap">
        <table class="widefat striped" id="wedding-guest-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="wedding-check-all" aria-label="Chọn tất cả"></th>
                    <th>#</th>
                    <th>Khách</th>
                    <th>Khách của</th>
                    <th>Nhóm</th>
                    <th>Tham dự</th>
                    <th>Đi xe</th>
                    <th>Người đi cùng</th>
                    <th>SĐT</th>
                    <th>Điểm đón</th>
                    <th>Đã gửi link</th>
                    <th>Đã báo đón</th>
                    <th>Cập nhật</th>
                    <th>Việc</th>
                </tr>
            </thead>
            <tbody id="wedding-guest-body">
                <tr><td colspan="14">Đang tải...</td></tr>
            </tbody>
        </table>
    </div>

    <div id="wedding-dialog" class="wedding-dialog" hidden>
        <form id="wedding-edit-form" class="wedding-dialog-card">
            <h2>Sửa câu trả lời</h2>
            <p id="wedding-edit-name"></p>
            <input type="hidden" id="edit-id">
            <label>Tham dự
                <select id="edit-attending" required>
                    <option value="yes">Có, mình sẽ đến</option>
                    <option value="maybe">Chưa chắc</option>
                    <option value="no">Rất tiếc, mình không đến được</option>
                </select>
            </label>
            <div data-edit="coming">
                <label>Đi xe
                    <select id="edit-ride">
                        <option value="1">Đi xe chung</option>
                        <option value="0">Mình tự đi</option>
                    </select>
                </label>
                <label data-edit="bus">Số điện thoại
                    <input type="tel" id="edit-phone" inputmode="tel">
                </label>
                <label>Số người đi cùng <input type="number" id="edit-companion-count" min="0" max="10"></label>
                <div id="edit-companion-list"></div>
                <label>Ghi chú ăn uống <textarea id="edit-diet" rows="4"></textarea></label>
            </div>
            <input type="hidden" id="edit-message">
            <p id="wedding-edit-error" class="wedding-error" hidden></p>
            <p class="wedding-dialog-actions">
                <button type="submit" class="button button-primary">Lưu</button>
                <button type="button" class="button" id="wedding-edit-close">Đóng</button>
            </p>
        </form>
    </div>
</div>
