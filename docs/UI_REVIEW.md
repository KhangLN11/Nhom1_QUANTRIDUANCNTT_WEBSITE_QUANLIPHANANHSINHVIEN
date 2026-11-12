# Rà soát giao diện CampusVoice

Đã chỉnh trực tiếp trên mã nguồn hiện tại; không đổi query nghiệp vụ, phân quyền, chuyển trạng thái, cấu trúc SQL hoặc dữ liệu người dùng. Các tài khoản, phản ánh, tệp và thông báo thử được tạo riêng rồi dọn sau khi chạy.

## Lỗi thực tế và cách sửa

| Trước sửa | Sau sửa |
| --- | --- |
| Tiêu đề không có khoảng trắng làm grid Phản hồi gần đây nở rộng, đẩy trang chủ tràn ngang ở cả ba kích thước | Dùng track minmax(0,...) và min-width:0 đúng cấp grid/flex; tiêu đề được xuống dòng, không giấu bằng ellipsis |
| Tên tệp dài không xuống dòng, làm trang chi tiết sinh viên/cán bộ rộng hơn màn hình | Liên kết tệp được wrap-anywhere; giữ URL và quyền tải xuống |
| Thanh đầu trang mobile có menu nhiều hàng và nút đăng xuất tách rời | Menu thu gọn, cụm logo/chuông/đăng xuất gọn trên một hàng; icon đăng xuất giữ aria-label/title |
| Bảng mobile chia cột danh mục quá hẹp, nội dung dựng thành nhiều dòng khó đọc | Đặt chiều rộng tối thiểu hợp lý, giới hạn cuộn ở table-scroll; có hướng dẫn và tabindex để cuộn bằng bàn phím |
| Đăng nhập tablet bị ép hai cột, tiêu đề/form hẹp; ngắt dòng cố định gây khoảng trắng không rõ trên mobile | Một cột dưới 900 px, tiêu đề tự xuống dòng và sửa khoảng trắng trong markup |
| Màu tím rượu/Segoe và nút ở các màn hình khác lệch khỏi chuẩn trang chủ | Lớp ui.css dùng chung màu đỏ–kem, Arial, border, bo góc, khoảng cách, button/input và focus ring |

Giữ ảnh minh họa, banner, tỷ lệ các khối chính và nút trang chủ desktop. Khi dữ liệu dài, phần thẻ được giãn theo nội dung thay vì làm cả trang rộng ra. Không dùng overflow-x:hidden để che nội dung bị tràn.

## Hiệu ứng và thao tác

- Tiêu đề banner xuất hiện bằng opacity/translateY 8 px trong 0,48 giây; cụm nút trễ nhẹ 0,1 giây. Tiêu đề vùng chào/tiếp nhận dùng cùng chuyển động.
- Nút và thẻ có liên kết chuyển màu/đổ bóng nhẹ khi hover bằng chuột; không gán cảm giác bấm được cho thẻ thông tin tĩnh.
- Thông báo thành công sẵn có của PHP dùng hiệu ứng cv-confirm 0,26 giây. Không thay đổi hành vi submit, transaction hoặc chuyển hướng.
- prefers-reduced-motion tắt animation, transition và smooth scrolling; nội dung vẫn hiển thị ngay.
- Menu dùng button native, aria-controls/aria-expanded. Enter/Space mở, Tab đi qua liên kết, Escape đóng và trả focus. Không trap focus. Khi JavaScript không tải được, các liên kết điều hướng vẫn hiển thị và hoạt động.
- JavaScript mới chỉ quản lý disclosure menu; các form không phụ thuộc JavaScript.

## Các trang đã kiểm tra thực tế

Mỗi biến thể bên dưới đã mở trong Chrome tại **360, 768 và 1440 px**, tổng **51 tổ hợp trang/kích thước**:

| Khu vực | Biến thể |
| --- | --- |
| Đăng nhập | Bình thường, báo lỗi sai thông tin |
| Trang chủ sinh viên | Có phản ánh, tài khoản chưa có phản ánh |
| Gửi phản ánh | Bình thường, lỗi validation và dữ liệu được giữ lại |
| Phản ánh của tôi | Có dữ liệu dài, danh sách trống |
| Chi tiết sinh viên | Tiêu đề/nội dung/tên tệp dài, NeedsInfo có form bổ sung |
| Cán bộ | Danh sách, chi tiết Pending, form trả lời InProgress, form đóng Resolved |
| Thông báo | Sinh viên/cán bộ có thông báo dài; sinh viên có danh sách trống |

Cả 51 tổ hợp không còn tràn ngang cả trang, cắt tiêu đề/nút hoặc chồng các điều khiển trong dữ liệu thử. Phần cột bảng nằm ngoài khung là vùng cuộn chủ động, không phải bị cắt mất.

15 kiểm tra tương tác thành công: menu mobile Enter/Space/Tab/Escape, liên kết điều hướng, cuộn bảng bằng phím phải mà trang không cuộn ngang, gửi form bằng click thật, mở chi tiết, cán bộ tiếp nhận/trả lời trên viewport 360 px, hiệu ứng xác nhận, reduced motion, fallback khi chặn JS và focus đăng nhập. Không ghi nhận ngoại lệ JavaScript.

Đã chạy lại **277 kiểm tra chức năng** trên Apache/XAMPP: 53 đính kèm/CSV, 76 bổ sung/thông báo, 70 cán bộ, 51 sinh viên và 27 luồng Chrome từ gửi đến Closed. Tất cả thành công; toàn bộ PHP hợp lệ cú pháp. Không yêu cầu SQL nâng cấp cho thay đổi giao diện này.

Các số liệu là kết quả trên Chrome headless với viewport tương ứng, không thay thế kiểm tra cảm ứng trên điện thoại thật hoặc mọi trình duyệt.

## Ảnh và log

| Màn hình | Điện thoại 360 px | Máy tính 1440 px |
| --- | --- | --- |
| Trang chủ | [Ảnh](../artifacts/ui-after/home-360.png) | [Ảnh](../artifacts/ui-after/home-1440.png) |
| Phản ánh của tôi | [Ảnh](../artifacts/ui-after/student-list-360.png) | [Ảnh](../artifacts/ui-after/student-list-1440.png) |
| Danh sách cán bộ | [Ảnh](../artifacts/ui-after/staff-list-360.png) | [Ảnh](../artifacts/ui-after/staff-list-1440.png) |

- [Menu mobile đang mở với focus bàn phím](../artifacts/ui-after/mobile-menu-open.png).
- [Form lỗi](../artifacts/ui-after/create-error-360.png).
- [Thông báo gửi thành công](../artifacts/ui-after/submit-success-360.png).
- [Cán bộ lưu kết quả thành công](../artifacts/ui-after/staff-success-360.png).
- Ảnh 768 px và các màn hình khác nằm trong artifacts/ui-after/; bản trước sửa nằm trong artifacts/ui-before/.
- [Số đo và kết quả 168 assertions giao diện](../artifacts/ui-after/audit.json).
- [Log giao diện và toàn bộ kiểm thử chức năng](../artifacts/ui-regression.log).

Ảnh sử dụng dữ liệu kiểm thử, gồm chuỗi A rất dài để thử xuống dòng. Dữ liệu và ID trong ảnh đã được dọn, không dùng ID đó mở lại website.

## Tệp đã thay đổi

- Mới: assets/ui.css, assets/ui.js, scripts/test_ui_responsive.mjs, docs/UI_REVIEW.md.
- Cập nhật giao diện: includes/student-layout.php, includes/staff-layout.php, includes/student-home.php, login.php, reflections.php, staff.php.
- Cập nhật hướng dẫn: README.md.
- ui.css được nạp sau CSS cũ để thống nhất các component, giữ phần minh họa và CSS bố cục chính của trang chủ. Các PHP nghiệp vụ/workflow, database, upload và CSV không thay đổi.

Chạy lại khi Apache/MySQL hoạt động và đã có schema hiện tại:

```powershell
node scripts/test_ui_responsive.mjs
```

Cần Node 22+, Chrome và PHP XAMPP, không cần npm packages. Có thể đổi CV_BASE_URL, CV_CHROME, CV_PHP. Bộ thử tự tạo/dọn tài khoản, tệp và thông báo tạm. Trước khi trình diễn nên kiểm tra thêm thao tác chạm và hiển thị trên thiết bị thật sẽ dùng.
