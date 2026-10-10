<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="wrap wedding-admin">
    <h1>Import khách</h1>
    <p>Import chỉ cập nhật thông tin mời. Câu trả lời, điểm đón và trạng thái đã gửi tin không bị đụng tới. Khách không bao giờ bị xóa hẳn, mã cũng không đổi.</p>
    <div class="wedding-tabs">
        <button type="button" class="button button-primary" id="tab-csv">Upload CSV</button>
        <button type="button" class="button" id="tab-paste">Dán từ Google Sheet</button>
    </div>
    <div id="panel-csv" class="wedding-panel">
        <p><input type="file" id="wedding-file" accept=".csv,text/csv"></p>
    </div>
    <div id="panel-paste" class="wedding-panel" hidden>
        <p>Bôi đen vùng trong Google Sheet, gồm cả dòng tiêu đề, rồi dán vào đây.</p>
        <textarea id="wedding-paste" rows="12" class="large-text" placeholder="#	Mã	Danh xưng	Tên trên thiệp	Note	Số người	Khách của	Nhóm khách"></textarea>
    </div>
    <fieldset class="wedding-modes">
        <label><input type="radio" name="import-mode" value="sync"> Đồng bộ toàn bộ danh sách <span class="description">— khách không có trong dữ liệu sẽ bị ẩn</span></label>
        <label><input type="radio" name="import-mode" value="upsert" checked> Chỉ thêm và cập nhật <span class="description">— không đụng khách nằm ngoài dữ liệu</span></label>
    </fieldset>
    <p><button type="button" class="button button-primary" id="wedding-preview">Xem trước</button></p>
    <div id="wedding-import-result"></div>
</div>
