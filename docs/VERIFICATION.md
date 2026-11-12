# Kiểm tra CampusVoice — lượt trước

> Báo cáo lịch sử trước khi triển khai đính kèm/CSV. Xem [bàn giao hiện tại](HANDOVER.md) và README cho bản mới nhất.

Đã chạy trực tiếp tại `http://localhost/campusvoice/`, PHP 8.0.30, MariaDB 10.4.32 trên XAMPP. Kiểm tra trình duyệt dùng Chrome headless, Node 24.21.0 và form HTML/PHP thật, không chỉ gọi hàm workflow hoặc tạo sẵn phản ánh trong database.

## Luồng thực tế

**27 kiểm tra trình duyệt thành công; không phát hiện lỗi chặn luồng ứng dụng.** Đã sửa bộ chọn nút trong script kiểm tra để chọn đúng form gửi phản ánh, và đối chiếu nội dung theo chuẩn xuống dòng CRLF của biểu mẫu trình duyệt. Không cần sửa quy tắc trạng thái hoặc transaction của ứng dụng trong lượt kiểm tra này.

| Bước | Kết quả đã xác nhận |
| --- | --- |
| Sinh viên đăng nhập và điền form Gửi phản ánh | Tạo Reflections Pending; có thông báo thành công |
| Cán bộ mở danh sách, xem chi tiết và Tiếp nhận | Chuyển InProgress |
| Cán bộ nhập lý do, Yêu cầu bổ sung thông tin | Chuyển NeedsInfo; lưu lịch sử và thông báo sinh viên |
| Sinh viên mở thông báo | Đến đúng chi tiết; thông báo được đánh dấu đã đọc; thấy lý do |
| Sinh viên gửi phần bổ sung | Chuyển Pending; lưu riêng, nội dung gốc trong database không đổi |
| Cán bộ mở thông báo bổ sung, Tiếp nhận lại | Chuyển InProgress |
| Cán bộ trả lời và hoàn tất | Chuyển Resolved; đúng một Responses và activity liên kết |
| Sinh viên mở thông báo kết quả | Thấy câu trả lời và Đã xử lý |
| Cán bộ nhập lý do và Đóng hồ sơ | Chuyển Closed; lưu lý do và thông báo |
| Sinh viên mở thông báo đóng hồ sơ | Thấy Đã đóng, lý do và trao đổi theo đúng thứ tự |

Đã xác nhận một lượt yêu cầu, một lượt bổ sung, một kết quả và một lý do đóng tạo bốn activity, đúng một Responses. Phản ánh thử, tài khoản tạm và mọi thông báo liên quan, kể cả gửi đến cán bộ có sẵn, đã được dọn.

## Quyền và thông báo

Tài khoản thứ hai được tạo và giữ lại: **Lê Thu Hà**, mã **SV2026002**, email **student2@campusvoice.test**, mật khẩu **Student2@123**.

Đã dùng tài khoản này kiểm tra:

- Mở URL phản ánh của sinh viên khác: không tìm thấy/không có quyền, không lộ nội dung.
- Danh sách phản ánh và thông báo không hiển thị dữ liệu riêng của người đầu tiên.
- POST mở/đánh dấu notification_id người khác: HTTP 404.
- POST bổ sung phản ánh người khác: HTTP 404, database không đổi.
- POST trả lời vào trang cán bộ: chuyển về trang sinh viên trước cập nhật; database không đổi.
- Mở danh sách cán bộ: chuyển về trang sinh viên.

Thông báo được thử ở cả hai vai trò. Phạm vi hiện tại: mọi Staff hoạt động được tiếp nhận toàn trường và nhận thông báo khi sinh viên bổ sung. Sinh viên nhận thông báo yêu cầu, kết quả và đóng hồ sơ; chỉ được đọc/đánh dấu thông báo của mình.

## Các bộ kiểm thử chạy lại

| Bộ kiểm thử | Kết quả |
| --- | --- |
| scripts/test_followup_notifications.py | 76 thành công |
| scripts/test_staff_reflections.py | 70 thành công |
| scripts/test_student_reflections.py | 51 thành công |
| scripts/test_browser_flow.mjs | 27 thành công |

Tổng **224 kiểm tra thành công**. Các bộ HTTP/PDO còn kiểm tra CSRF, sai trạng thái, dữ liệu rỗng/quá dài, HTML escaping, nhiều vòng bổ sung, Version cũ ở vòng sau, thao tác đồng thời, tài khoản vô hiệu hóa và rollback sau lỗi tại các bước ghi, gồm tạo thông báo. Lỗi được tiêm sau SQL thực để kiểm tra transaction; không mô phỏng mất điện hoặc ngắt mạng giữa lúc commit.

Log và ảnh:

- [Log trình duyệt](../artifacts/browser-flow-audit.log).
- [Log 197 kiểm tra HTTP/PDO](../artifacts/regression-audit.log).
- [Cán bộ xem hồ sơ Closed](../artifacts/audit-staff-closed.png).
- [Sinh viên xem hồ sơ Closed](../artifacts/audit-student-closed.png).
- [Sinh viên khác bị chặn](../artifacts/audit-other-student-denied.png).

Ảnh là snapshot dữ liệu thử đã được dọn; không dùng ID trong ảnh để mở lại trên website.

## Hiện trạng đính kèm và CSV

| Phần | Đã có | Chưa có | Đã kiểm tra |
| --- | --- | --- | --- |
| Tệp đính kèm | Bảng Attachments: FileName, ObjectKey, ContentType, FileSize, UploadedAt, ReflectionId và FK đến Reflections | Form chọn file, xử lý upload, nơi lưu file, danh sách file trong chi tiết, endpoint tải xuống có phân quyền | Rà toàn bộ PHP/SQL và truy vấn database: bảng tồn tại, 0 dòng; chưa có luồng file để chạy thử |
| Xuất CSV | Chưa có chức năng | Nút xuất, endpoint, truy vấn xuất và mã sinh CSV | Rà mã nguồn: không có fputcsv, text/csv hay route xuất; chưa có CSV đầu ra để thử |

Hai phần này **chưa triển khai**, không phải chỉ chờ bật cấu hình XAMPP. Lượt này không bổ sung upload/download hay CSV ngoài yêu cầu rà soát.

## Bạn kiểm tra lại trên XAMPP

1. Bật Apache/MySQL, mở `http://localhost/campusvoice/`. Workspace này đã có Version/ReflectionActivities; không cần import lại SQL. Khi cài database ở máy khác, làm đúng thứ tự SQL trong README.
2. Dùng cửa sổ thường cho `student@campusvoice.test` / `Student@123`, cửa sổ riêng hoặc trình duyệt khác cho `staff@campusvoice.test` / `Staff@123`. Gửi phản ánh mới rồi đi theo bảng luồng ở trên.
3. Sau bước yêu cầu/kết quả/đóng, tải lại trang sinh viên và mở chuông tại `notifications.php`; sau bổ sung, mở chuông cán bộ. Thông báo cập nhật khi tải trang, chưa realtime.
4. Ghi URL phản ánh vừa tạo. Đăng xuất hoặc dùng phiên trình duyệt riêng đăng nhập student2, mở URL đó: phải bị từ chối. Các tab cùng một phiên trình duyệt dùng chung cookie, không đăng nhập hai người trong cùng phiên.
5. Kiểm tra trực quan trên thiết bị của bạn: tiếng Việt, cuộn lịch sử dài, biểu mẫu và số chưa đọc. Lượt này chạy Chrome headless desktop 1448 × 1086, chưa kiểm tra mọi trình duyệt hoặc thao tác chạm thực trên điện thoại.
6. Chưa thể thử upload/download hoặc CSV vì các màn hình/endpoint chưa tồn tại; cần triển khai trước.

Chạy lại tự động tại thư mục dự án:

```powershell
& C:/xampp/php/php.exe scripts/create_demo_users.php
node scripts/test_browser_flow.mjs
python scripts/test_followup_notifications.py
python scripts/test_staff_reflections.py
python scripts/test_student_reflections.py
```

Tài khoản student2 được giữ lại. Script tạo tài khoản không đặt lại mật khẩu đã tồn tại. Tài khoản/dữ liệu tạm được xóa trong finally; nếu cưỡng bức dừng, có thể còn dữ liệu thử với tiền tố cvbrowser-, cvfollowuptest-, cvstafftest- hoặc cvtest-.
