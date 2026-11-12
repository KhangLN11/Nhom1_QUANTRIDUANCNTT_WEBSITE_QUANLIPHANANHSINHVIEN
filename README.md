# CampusVoice — bàn giao PHP/PDO trên XAMPP

Ứng dụng có đăng nhập Student/Staff, phản ánh văn bản và tệp, xử lý nhiều vòng bổ sung, thông báo trong website, đóng hồ sơ và xuất CSV dành cho cán bộ.

**URL khởi động: http://localhost/campusvoice/**

## 1. Cài mới

1. Chép dự án vào `C:/xampp/htdocs/campusvoice`.
2. Bật Apache và MySQL trong XAMPP Control Panel.
3. Mở `http://localhost/phpmyadmin`, import đúng thứ tự, mã hóa UTF-8:
   - `database/campusvoice.sql` — tạo database/bảng và ba danh mục, chỉ chạy vào database mới.
   - `database/migrations/001_followup_notifications.sql` — thêm Version và lịch sử; có thể chạy lại.
4. Sửa `config/database.php` cho đúng máy. Mặc định: host `127.0.0.1`, port `3306`, database `campusvoice`, user `root`, mật khẩu rỗng. Nếu dùng database tên khác, đổi CREATE DATABASE/USE trong các SQL cho khớp.
5. Kiểm tra `config/uploads.php`. Mặc định tệp được lưu tại `C:/xampp/campusvoice-storage/campusvoice`, ngoài htdocs. Apache/PHP cần quyền tạo thư mục và đọc/ghi tại đó. Có thể sửa đường dẫn tuyệt đối trong tệp cấu hình; ứng dụng từ chối lưu bên trong webroot.
6. Trong PowerShell:

   ```powershell
   cd C:/xampp/htdocs/campusvoice
   & C:/xampp/php/php.exe scripts/create_demo_users.php
   ```

7. Mở URL khởi động ở trên. Nếu Apache dùng cổng 8080, dùng `http://localhost:8080/campusvoice/`.

Yêu cầu PHP 7.4+, extension `pdo_mysql`, `mbstring`, `fileinfo`. XAMPP hiện tại dùng PHP 8.0.30, MariaDB 10.4.32. Trong `C:/xampp/php/php.ini`, bảo đảm:

```ini
file_uploads=On
upload_max_filesize=6M
post_max_size=8M
```

Khởi động lại Apache khi sửa php.ini. Ứng dụng vẫn giới hạn mỗi tệp 5 MiB dù PHP cho phép lớn hơn. `upload_max_filesize` của Apache hiện tại là 40M nên đã thử được giới hạn ứng dụng.

Có thể ghi đè cấu hình qua DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, CV_UPLOAD_DIR. Biến môi trường phải có trong tiến trình PHP; đặt biến ở PowerShell không tự thay đổi Apache đang chạy. Cách đơn giản khi trình diễn là sửa hai tệp config.

## 2. Nâng cấp database đã có dữ liệu

Không import lại `database/campusvoice.sql`. Chỉ chạy `database/migrations/001_followup_notifications.sql` nếu chưa chạy. Bản đính kèm/CSV dùng bảng Attachments sẵn có, không cần migration 002.

```powershell
& C:/xampp/mysql/bin/mysql.exe -u root --default-character-set=utf8mb4 --execute="source database/migrations/001_followup_notifications.sql"
```

Nếu MySQL có mật khẩu, thêm `-p`. Migration không xóa dữ liệu cũ. Khi chuyển máy có dữ liệu đính kèm, cần chuyển cả database lẫn thư mục lưu tệp tương ứng và cấu hình lại đường dẫn ngoài webroot.

## 3. Tài khoản demo

| Vai trò | Email | Mật khẩu |
| --- | --- | --- |
| Sinh viên | student@campusvoice.test | Student@123 |
| Sinh viên khác | student2@campusvoice.test | Student2@123 |
| Cán bộ | staff@campusvoice.test | Staff@123 |

Script dùng password_hash, chỉ chạy CLI; chạy lại giữ nguyên tài khoản/email/mã sinh viên đã tồn tại và không đặt lại mật khẩu. Muốn thêm tài khoản, sửa `$accounts` trong script rồi chạy lại.

## 4. Các trang

| Chức năng | URL |
| --- | --- |
| Đăng nhập | http://localhost/campusvoice/login.php |
| Trang chủ sinh viên | http://localhost/campusvoice/student.php |
| Gửi phản ánh và tệp | http://localhost/campusvoice/reflection-create.php |
| Phản ánh của tôi | http://localhost/campusvoice/reflections.php |
| Chi tiết sinh viên, bổ sung | http://localhost/campusvoice/reflection.php?id=ID |
| Danh sách cán bộ, lọc và nút CSV | http://localhost/campusvoice/staff.php |
| Chi tiết cán bộ | http://localhost/campusvoice/staff-reflection.php?id=ID |
| Thông báo hai vai trò | http://localhost/campusvoice/notifications.php |
| Tải tệp có phân quyền | attachment-download.php?id=ID_TEP, mở từ chi tiết |
| CSV của cán bộ | staff-export.php?status=Pending, hoặc status rỗng để xuất tất cả |

ID lấy từ danh sách thực tế. Không dùng ID trong ảnh kiểm thử vì dữ liệu thử đã được dọn.

## 5. Luồng, quyền và giới hạn

- Gửi mới → Pending; cán bộ Tiếp nhận → InProgress.
- InProgress → NeedsInfo khi cán bộ nhập lý do yêu cầu bổ sung. Chỉ chủ phản ánh được bổ sung, đưa hồ sơ về Pending để tiếp nhận lại.
- InProgress → Resolved khi cán bộ nhập câu trả lời; Resolved → Closed khi nhập lý do kết thúc.
- Nội dung gốc được giữ nguyên, lịch sử yêu cầu/bổ sung/kết quả/lý do đóng lưu riêng và hiển thị theo thời gian; Responses cũ vẫn đọc được, câu trả lời không hiển thị trùng.
- Các biểu mẫu ghi có CSRF; ID người thao tác từ phiên đăng nhập; truy vấn có tham số. Transaction khóa bản ghi và kiểm tra Version/trạng thái để chống gửi trùng, kể cả biểu mẫu cũ của vòng trước.
- Schema chưa có phân công theo khoa/danh mục: mọi Staff hoạt động được xem phản ánh toàn trường. Sinh viên chỉ xem phản ánh của mình.
- Chuông hiển thị số chưa đọc; sinh viên nhận thông báo yêu cầu/kết quả/đóng, cán bộ hoạt động nhận thông báo bổ sung. Mỗi người chỉ mở/đánh dấu thông báo của mình. Thông báo cập nhật khi tải trang, chưa realtime/email/push.
- Tiêu đề tối đa 200 ký tự; nội dung và lý do tối đa 10.000 ký tự UTF-8, không chỉ khoảng trắng. Closed chỉ xem, không mở lại.

### Tệp đính kèm

Một tệp không bắt buộc khi gửi mới hoặc gửi bổ sung tại NeedsInfo. Chấp nhận **PDF, JPG/JPEG, PNG**, tối đa **5 MiB/tệp**. Kiểm tra kích thước thực, phần mở rộng, MIME bằng fileinfo, chữ ký PDF/ảnh; không tin Content-Type do trình duyệt gửi. Không nhận PHP, HTML, SVG, ZIP hay DOCX trong bản này.

Tên gốc được hiển thị, tên lưu thật là chuỗi ngẫu nhiên. Tệp lưu ngoài webroot, không có URL trực tiếp. Endpoint tải xuống kiểm tra cùng quyền xem phản ánh, trả Content-Disposition attachment và tên UTF-8. Cán bộ/chủ phản ánh vẫn tải được tệp của hồ sơ Closed; sinh viên khác bị từ chối.

Tệp bổ sung giữ cùng phản ánh; danh sách giữ các tệp trước. Khi có lỗi form phải chọn lại file. Nếu ghi database thất bại, transaction hoàn tác và ứng dụng xóa tệp vừa lưu. Cơ chế này xử lý lỗi bắt được trong tiến trình; không tuyên bố database và filesystem có transaction phân tán khi máy bị tắt đột ngột. Chưa có xóa/thay tệp độc lập hoặc xem trước nội tuyến.

### CSV và Excel

Nút Xuất CSV giữ bộ lọc trạng thái đang chọn. Danh sách và CSV dùng cùng truy vấn/phạm vi quyền. Xuất ID, tiêu đề, danh mục, người gửi, mã sinh viên, email, ngày gửi, nhãn trạng thái và nội dung; không xuất PasswordHash, khóa lưu tệp hoặc dữ liệu ngoài quyền cán bộ.

CSV dùng **UTF-8 BOM**, dấu phẩy, kết thúc bản ghi CRLF, xử lý dấu phẩy/dấu nháy/xuống dòng; ô có nguy cơ công thức Excel được chuyển thành văn bản bằng dấu nháy đơn. Có thể dùng [CSV mẫu từ bản cài thử](artifacts/sample-vietnamese.csv).

Đã kiểm tra byte BOM và đọc lại đúng tiếng Việt bằng parser CSV. Máy kiểm tra không có Excel, nên **cần tự mở CSV bằng Excel trước khi nộp**. Nếu Excel mở một cột hoặc nhận sai mã hóa, dùng Data → From Text/CSV, chọn UTF-8 (65001), delimiter Comma. Kiểm tra tiêu đề “Tiêu đề”, “Người gửi”, nội dung tiếng Việt và số dòng đúng bộ lọc.

## 6. Kịch bản trình diễn 3–5 phút

Chuẩn bị trước một PDF/PNG dưới 5 MiB; mở hai phiên trình duyệt riêng cho sinh viên và cán bộ. Các tab cùng phiên dùng chung cookie nên không đăng nhập hai vai trò trong cùng phiên.

| Thời gian | Thao tác |
| --- | --- |
| 0:00–0:45 | Sinh viên đăng nhập, gửi “Wi-Fi thư viện” với nội dung và tệp. Mở chi tiết, tải lại tệp. |
| 0:45–1:30 | Cán bộ lọc Pending, mở phản ánh, Tiếp nhận, nhập “Bổ sung tầng và thời điểm” rồi Yêu cầu bổ sung. |
| 1:30–2:15 | Sinh viên mở chuông, xem yêu cầu, gửi phần bổ sung; chỉ ra nội dung gốc không đổi và lịch sử có lượt riêng. |
| 2:15–3:00 | Cán bộ mở thông báo, Tiếp nhận lại, gửi câu trả lời và đánh dấu đã xử lý. Sinh viên xem kết quả. |
| 3:00–3:45 | Cán bộ nhập lý do Đóng hồ sơ. Sinh viên xem thông báo/lý do; tệp cũ vẫn tải được. |
| 3:45–4:30 | Cán bộ lọc Closed, Xuất CSV và mở Excel. Dùng student2 ở phiên khác mở URL phản ánh vừa tạo: bị từ chối. |

## 7. Những gì đã chạy thử

- Apache/XAMPP hiện có: 53 kiểm tra upload/CSV thật qua multipart HTTP; PDF/JPG/PNG hợp lệ, giả MIME/phần mở rộng, rỗng/quá 5 MiB, CSRF, tải xuống hai vai trò/chặn sinh viên khác, tệp khi bổ sung/Closed, bộ lọc CSV, Unicode và chống công thức.
- Cài mới trên database ngẫu nhiên campusvoice_installtest_*: nhập base SQL → migration, tạo ba tài khoản, chạy seed/migration lại, xác nhận PDO bằng DB_NAME riêng. Máy chủ thử dùng chính PHP XAMPP trên cổng riêng; không đổi cấu hình/database Apache hiện có.
- Trên bản cài mới chạy các bộ upload/CSV, 76 kiểm tra bổ sung/thông báo, 70 cán bộ, 51 sinh viên và 27 kiểm tra Chrome qua form thật từ gửi đến Closed. Có thêm lỗi SQL có kiểm soát trong database thử để xác nhận dọn tệp/metadata khi lỗi lưu hoặc thông báo.
- Sau thử: chỉ còn ba tài khoản demo trong database thử, không còn nội dung/tệp thử; database và thư mục thử đã được xóa có giới hạn. Toàn bộ PHP qua kiểm tra cú pháp.

Chi tiết cuối cùng và log tại [báo cáo bàn giao](docs/HANDOVER.md). [Báo cáo lượt trước](docs/VERIFICATION.md) là lịch sử trước khi triển khai file/CSV, không phải hiện trạng mới nhất.

Chạy lại tại thư mục dự án, Python 3 dùng thư viện chuẩn; browser dùng Node 22+ và Chrome:

```powershell
python scripts/test_attachments_csv.py
python scripts/test_followup_notifications.py
python scripts/test_staff_reflections.py
python scripts/test_student_reflections.py
node scripts/test_browser_flow.mjs
python scripts/test_fresh_install.py
```

Bộ cài thử riêng cần quyền MySQL CREATE/DROP DATABASE và đường dẫn mysql.exe/PHP/Chrome. Tự tạo/xóa duy nhất database tên ngẫu nhiên đã kiểm tra chưa tồn tại; chỉ sửa tên database trong bản SQL dùng để thử, không sửa SQL gốc. Có thể đổi CV_MYSQL/CV_PHP/CV_CHROME; CV_BASE_URL dùng cho các bộ HTTP thông thường. Các bộ kiểm thử tự dọn tài khoản/nội dung/thông báo/tệp của mình; tài khoản student2 demo được giữ lại trên database chính.

## 8. Cần tự xác nhận trước khi nộp

1. Mở CSV thật bằng Excel như hướng dẫn; Excel không cài trên máy kiểm tra.
2. Trên máy trình diễn, xác nhận cổng Apache/MySQL, cấu hình DB, quyền ghi thư mục ngoài webroot và các extension/giới hạn PHP. Tải một tệp thật rồi tải xuống, so sánh với bản gốc.
3. Chạy kịch bản 3–5 phút trên trình duyệt/thiết bị sẽ dùng, nhất là lịch sử dài và các phiên đăng nhập riêng. Chưa xác nhận mọi trình duyệt hoặc thao tác chạm thực.
4. Nếu đề yêu cầu hình minh họa banner khớp hoàn toàn mẫu, còn cần asset trường riêng không chứa chữ/nút; hiện dùng phần minh họa từ ảnh tham khảo. Chưa có phân công khoa, mở lại Closed hoặc email/push, ngoài phạm vi đã triển khai.

Tệp chính mới: includes/attachments.php, attachment-download.php, config/uploads.php, includes/staff-query.php, staff-export.php; các trang gửi/bổ sung/chi tiết/danh sách được nối với chức năng này. Không có bước SQL mới cho đính kèm/CSV.

## Giao diện đồng bộ và responsive

Đã đồng bộ đỏ–kem từ trang chủ sang đăng nhập, sinh viên, cán bộ và thông báo; giữ banner/minh họa chính. Có menu mobile hỗ trợ bàn phím, bảng cuộn riêng và hiệu ứng CSS tôn trọng prefers-reduced-motion. Không cần thay đổi SQL.

Xem [báo cáo giao diện, các lỗi đã sửa và ảnh 360/768/1440 px](docs/UI_REVIEW.md). Đã qua 51 tổ hợp trang/kích thước, 15 kiểm tra thao tác và 277 kiểm tra chức năng.

```powershell
node scripts/test_ui_responsive.mjs
```
