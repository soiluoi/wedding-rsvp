<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="wrap wedding-admin">
    <h1>Điểm đón</h1>
    <form id="pickup-form" class="wedding-pickup-form">
        <input type="hidden" id="pickup-id" value="">
        <label>Tên <input type="text" id="pickup-name" required placeholder="Cổng công ty A"></label>
        <label>Địa chỉ <input type="text" id="pickup-address"></label>
        <label>Giờ <input type="text" id="pickup-time" placeholder="06:30"></label>
        <label>Ghi chú <input type="text" id="pickup-note"></label>
        <label>Thứ tự <input type="number" id="pickup-sort" value="0"></label>
        <button type="submit" class="button button-primary" id="pickup-save">Thêm điểm đón</button>
        <button type="button" class="button" id="pickup-cancel" hidden>Huỷ sửa</button>
    </form>
    <p id="pickup-error" class="wedding-error" hidden></p>
    <div id="pickup-list">Đang tải...</div>
</div>
