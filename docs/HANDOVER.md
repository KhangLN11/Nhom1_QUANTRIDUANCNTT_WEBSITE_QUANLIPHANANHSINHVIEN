# Bàn giao CampusVoice — đính kèm, CSV và cài mới

## Kết quả

- Apache/XAMPP đang chạy tại localhost/campusvoice: **53 kiểm tra đính kèm/CSV thành công** với cấu hình lưu tệp mặc định ngoài htdocs.
- Bản cài mới riêng: **279 kiểm tra thành công** (55 tệp/CSV + 76 bổ sung/thông báo + 70 cán bộ + 51 sinh viên + 27 Chrome qua form thật).
- Toàn bộ PHP đã kiểm tra cú pháp. Không còn lỗi chặn luồng được phát hiện trong các kịch bản này.

Bản cài mới dùng database ngẫu nhiên `campusvoice_installtest_515e1a51dbbe`, nhập `campusvoice.sql` rồi `001_followup_notifications.sql`, cấu hình kết nối bằng DB_NAME riêng, tạo ba tài khoản demo và chạy seed/migration lại. HTTP server thử dùng chính PHP XAMPP trên cổng riêng, không thay cấu hình Apache hay database hiện có. SQL gốc không sửa: bộ thử thay tên database trong bản SQL truyền cho mysql.exe.

Sau kiểm tra, database thử chỉ còn ba tài khoản demo, không còn phản ánh, lịch sử, thông báo hoặc tệp thử. Server đã dừng, database/thư mục thử đã được xóa bằng kiểm tra giới hạn đường dẫn/tên. Tài khoản demo trên database chính vẫn được giữ nguyên.

## Chức năng mới

### Đính kèm

- Một PDF/JPG/PNG, tối đa 5 MiB mỗi lần gửi phản ánh mới hoặc bổ sung tại NeedsInfo.
- Kiểm tra lỗi upload, kích thước thực, phần mở rộng/MIME và chữ ký PDF/ảnh. Bỏ qua MIME do client tự khai báo.
- Tên lưu ngẫu nhiên, ngoài webroot; metadata dùng bảng Attachments có sẵn.
- Sinh viên chủ phản ánh hoặc cán bộ trong phạm vi được tải qua attachment-download.php. Sinh viên khác, người chưa đăng nhập hoặc ID sai bị chặn. Hồ sơ Closed vẫn tải được theo quyền xem.
- Khi lưu metadata thất bại, phản ánh không được lưu dở và tệp vừa chuyển được xóa. Đã thử thêm lỗi thông báo sau khi lưu tệp bổ sung: metadata, lịch sử, trạng thái và file đều được hoàn tác/dọn đúng.
- Không có xóa/thay tệp hoặc preview nội tuyến trong bản này. Filesystem không có transaction phân tán; không khẳng định xử lý được mọi tình huống máy bị tắt đột ngột giữa các bước.

### CSV

- Nút Xuất CSV tại danh sách cán bộ giữ trạng thái lọc đang chọn.
- Danh sách và CSV dùng chung includes/staff-query.php; kiểm tra Staff hoạt động và phạm vi tiếp nhận hiện tại (toàn trường).
- UTF-8 BOM, delimiter comma, CRLF; dấu nháy, dấu phẩy và xuống dòng roundtrip đúng.
- Ô bắt đầu bằng công thức hoặc khoảng trắng rồi công thức được chuyển thành text; không xuất PasswordHash/ObjectKey.
- Máy thử không tìm thấy Excel để mở tự động. Đã kiểm tra BOM/tiếng Việt bằng parser; **mở bằng Excel thật vẫn là bước người dùng cần tự xác nhận**.

## Tệp mới và tệp cập nhật

| Tệp | Nội dung |
| --- | --- |
| config/uploads.php | Đường dẫn lưu tệp, có thể cấu hình ngoài webroot |
| includes/attachments.php | Validation, lưu/dọn tệp, danh sách và input upload |
| attachment-download.php | Download có kiểm tra quyền và tên UTF-8 |
| includes/staff-query.php | Truy vấn/phạm vi/bộ lọc chung cho danh sách và CSV |
| staff-export.php | Xuất CSV BOM và xử lý công thức |
| reflection-create.php | Multipart và transaction phản ánh + tệp |
| reflection.php | Multipart bổ sung và hiển thị danh sách tệp |
| staff-reflection.php | Hiển thị tệp cho cán bộ |
| includes/staff-workflow.php | Lưu tệp bổ sung cùng transaction; dọn khi rollback |
| staff.php | Dùng truy vấn chung và liên kết xuất theo bộ lọc |
| scripts/test_attachments_csv.py | Test multipart, download, quyền, CSV; lỗi SQL chỉ trong database thử riêng |
| scripts/test_fresh_install.py | Cài mới cô lập, seed, chạy các bộ test, dọn database/thư mục thử |
| README.md | Cách chạy XAMPP, thứ tự SQL, tài khoản, URL và demo 3–5 phút |
| docs/VERIFICATION.md | Đánh dấu báo cáo cũ là lịch sử |
| docs/HANDOVER.md | Báo cáo bàn giao hiện tại |

Không sửa config/database.php hoặc schema SQL; không cần migration 002 vì Attachments đã có từ đầu.

## Bằng chứng chạy thử

- [Log Apache 53 kiểm tra](../artifacts/apache-attachments-csv.log).
- [Log cài mới và dọn môi trường thử](../artifacts/fresh-install.log).
- [55 kiểm tra file/CSV trên bản cài mới](../artifacts/install-test_attachments_csv.py.log).
- [Luồng Chrome từ gửi đến Closed](../artifacts/install-test_browser_flow.mjs.log).
- Các log 76/70/51 kiểm tra trong artifacts/install-test_*.py.log.
- [CSV mẫu thực tế xuất từ dữ liệu thử tiếng Việt](../artifacts/sample-vietnamese.csv).

Test tệp có PDF/JPG/PNG thật, giả phần mở rộng/MIME, rỗng/quá giới hạn, CSRF, quyền hai vai trò, chặn sinh viên khác, tên UTF-8, tệp của hồ sơ đóng, bổ sung có file và gửi lại form. Test CSV có quyền, trạng thái, injection bộ lọc, tiếng Việt, dấu phẩy/xuống dòng và công thức Excel.

## Trước khi nộp

1. Mở CSV mẫu hoặc CSV tải thật bằng Excel trên máy trình diễn, kiểm tra tiếng Việt và cột. Nếu cần dùng Data → From Text/CSV, chọn UTF-8 65001 và Comma. Đây là phần chưa thể xác nhận bằng Excel trong môi trường hiện tại.
2. Chạy kịch bản khoảng 4 phút trong README với tệp thật trên máy nộp: xác nhận PHP/fileinfo, cổng DB/Apache, quyền thư mục ngoài webroot và các phiên trình duyệt riêng. Nếu chuyển dữ liệu đính kèm sang máy khác cần chuyển cả thư mục tệp và DB.
3. Kiểm tra trực quan trên trình duyệt/thiết bị sẽ trình diễn. Kiểm tra tự động không thay thế việc đọc nội dung và thao tác chạm trên mọi thiết bị.
4. Nếu yêu cầu chấm điểm đòi banner khớp hoàn toàn ảnh mẫu: còn cần minh họa riêng không chứa chữ/nút. Phân công theo khoa, email/push và mở lại Closed chưa thuộc chức năng hiện có.

URL khởi động: **http://localhost/campusvoice/**. Database hiện có đã có migration 001, không cần import lại. Cài mới luôn theo base SQL → migration 001 → cấu hình → tạo demo.
