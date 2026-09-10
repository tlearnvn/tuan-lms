# 📗 Hướng dẫn sử dụng hệ thống LMS

Tài liệu dành cho **quản trị viên**, **giáo viên** và **học sinh**.

---

## Phần I — Quản trị viên 👑

### 1. Tổng quan hệ thống
`Quản trị → Tổng quan hệ thống`: số người dùng, khoá học, lượt nộp bài, người đang trực tuyến,
dung lượng cơ sở dữ liệu, biểu đồ đăng nhập 14 ngày, danh sách tài khoản chờ duyệt.

### 2. Quản lý người dùng
`Quản trị → Người dùng`

- Lọc theo vai trò/trạng thái, tìm theo tên – tài khoản – email.
- **Duyệt** tài khoản chờ (hoặc *Duyệt tất cả*).
- **Khoá / mở khoá**, **đặt lại mật khẩu** (hệ thống sinh mật khẩu mới và hiển thị một lần), **xoá**.
- Thêm tài khoản thủ công và **xuất danh sách ra Excel / PDF**.

### 3. Bật/tắt đăng ký tài khoản giáo viên
`Quản trị → Cấu hình chung → Đăng ký tài khoản`

| Công tắc | Tác dụng |
|---|---|
| Cho phép học sinh tự đăng ký | Ẩn/hiện lựa chọn "Học sinh" ở trang đăng ký |
| **Cho phép giáo viên tự đăng ký** | Ẩn/hiện lựa chọn "Giáo viên" ở trang đăng ký |
| Giáo viên đăng ký phải chờ duyệt | Tài khoản mới ở trạng thái *chờ duyệt* |
| Học sinh đăng ký phải chờ duyệt | Tương tự cho học sinh |

Khi tắt cả hai, trang đăng ký hiển thị thông báo lịch sự và chỉ còn nút đăng nhập.

### 4. Tuỳ biến thương hiệu
`Quản trị → Cấu hình chung`: tên website, khẩu hiệu, tên đơn vị, logo, favicon,
ảnh trang chủ, màu chủ đạo, màu nhấn, **dòng bản quyền chân trang**, thông tin liên hệ,
định dạng ngày giờ, chế độ bảo trì.

### 5. Trợ lý AI chấm bài
`Quản trị → Trợ lý AI chấm bài` — xem mục 5.6 trong *Hướng dẫn cài đặt*.
Trang này còn hiển thị **nhật ký các lần chấm gần đây** (thành công/lỗi, thời gian phản hồi).

### 6. Kho dữ liệu
`Quản trị → Kho dữ liệu`: dung lượng theo định dạng tệp, bảng dữ liệu lớn nhất,
tệp lớn nhất, **dọn tệp không còn được sử dụng**, dọn phiên hết hạn, dọn nhật ký cũ,
danh sách phiên đang hoạt động.

---

## Phần II — Giáo viên 👩‍🏫

### 1. Tạo khoá học
`Khoá học giảng dạy → ➕ Tạo khoá học mới`

- Tên, mã lớp, danh mục, mô tả (có thể chèn công thức và hình ảnh).
- **Hình thức ghi danh**: tự do / cần mã / cần giáo viên duyệt / chỉ giáo viên thêm.
- Ảnh bìa, màu chủ đạo, sĩ số tối đa, ngày bắt đầu – kết thúc.
- Trạng thái: *Bản nháp* (soạn riêng) → *Đang mở* (học sinh vào học) → *Lưu trữ*.

### 2. Xây dựng nội dung
`Khoá học → 🧱 Quản lý nội dung`

Tạo **chương** rồi thêm các **mục nội dung**:

| Loại | Dùng khi |
|---|---|
| 📖 Trang nội dung | Bài giảng viết trực tiếp, có công thức, hình ảnh, video nhúng |
| 📎 Tệp học liệu | Word, PDF, PowerPoint, Excel, ZIP… (xem trước PDF/ảnh ngay trên web) |
| 🎬 Video bài giảng | Tải lên hoặc dán liên kết YouTube/Vimeo |
| 🔗 Liên kết ngoài | Tài nguyên trên web |
| 🧩 Gói SCORM | Bài giảng đóng gói từ Articulate, iSpring, Captivate, H5P… |
| 📝 Bài tập | Học sinh nộp tệp và/hoặc gõ bài |
| ❓ Trắc nghiệm | Kiểm tra tự động chấm |
| 💬 Diễn đàn | Không gian hỏi đáp |

Mỗi mục có: hiển thị/ẩn, **giờ mở – giờ đóng**, tính điểm vào sổ, **điểm tối đa và hệ số**.

### 3. Soạn bài có công thức và hình minh hoạ ∑🖼️

Ô soạn thảo có thanh công cụ:

| Nút | Chức năng |
|---|---|
| **B** *I* U H • 1. ❝ | Định dạng chữ, tiêu đề, danh sách, trích dẫn |
| 🔗 | Chèn liên kết |
| **🖼️** | **Tải ảnh minh hoạ lên và chèn vào bài** (kèm chú thích) |
| 🎞️ | Nhúng video YouTube/Vimeo hoặc iframe |
| ▦ | Chèn bảng |
| **∑** | Công thức trong dòng `\( ... \)` |
| **∫** | Công thức riêng dòng `\[ ... \]` |
| **ƒ** | **Hơn 20 mẫu công thức** Toán – Lí – Hoá (phân số, căn, tích phân, ma trận, hệ phương trình, phản ứng hoá học…) |
| 👁️ | Xem trước có render công thức |

💡 **Mẹo:** chụp màn hình rồi **dán thẳng (Ctrl+V)** vào ô soạn thảo — ảnh tự động được tải lên.

Cú pháp công thức hỗ trợ:

```
Trong dòng:   \(a^2 + b^2 = c^2\)   hoặc   $a^2 + b^2 = c^2$
Riêng dòng:   \[ \int_0^1 x^2\,dx = \frac{1}{3} \]   hoặc   $$ ... $$
Môi trường:   \begin{cases} x + y = 5 \\ 2x - y = 1 \end{cases}
```

Công thức hiển thị được ở: bài giảng, đề bài tập, câu hỏi trắc nghiệm và phương án trả lời,
nhận xét của giáo viên, bài làm của học sinh và diễn đàn.

### 4. Gói SCORM 🧩

1. Thêm mục nội dung loại **Gói SCORM**.
2. Tải lên tệp `.zip` (phải chứa `imsmanifest.xml`). Hỗ trợ **SCORM 1.2 và SCORM 2004**.
3. Hệ thống tự giải nén, lưu vào CSDL, xác định tệp khởi chạy và mục lục các SCO.
4. Học sinh học trực tiếp trong trang; tiến độ, trạng thái hoàn thành và điểm được ghi tự động.
5. `📊 Kết quả lớp` xem chi tiết từng học sinh, xuất Excel.

### 5. Bài tập và chấm bài 📝

**Cấu hình:** đề bài, hạn nộp, hạn đóng, cho nộp trễ, số lần nộp, số tệp, định dạng cho phép,
hình thức nộp (tệp / gõ trực tiếp).

**Chấm bài:** `Cần chấm bài` hoặc vào bài tập → *Chấm ngay*.

- Xem trực tiếp PDF, ảnh, audio, video học sinh nộp.
- Nhập điểm + nhận xét (có công thức và hình ảnh).
- Nút **Lưu & bài kế →** để chấm liên tục.
- **🤖 Nhờ AI chấm bài này**: AI đọc bài (kể cả ảnh chụp bài viết tay), chấm theo rubric,
  trả về điểm đề xuất, bảng tiêu chí, điểm mạnh và điều cần cải thiện.
  Thầy cô vẫn là người quyết định điểm cuối cùng.

### 6. Trắc nghiệm ❓

- 5 dạng câu hỏi: chọn 1 đáp án, chọn nhiều, đúng/sai, trả lời ngắn (tự chấm), tự luận.
- Câu hỏi có thể kèm **ảnh minh hoạ** và **công thức LaTeX**.
- Giới hạn thời gian, trộn câu hỏi/đáp án, nhiều lượt làm, cách tính điểm (cao nhất/lần cuối/trung bình).
- Học sinh làm bài có **đồng hồ đếm ngược** và **tự động lưu** từng câu.
- Câu tự luận: giáo viên chấm tay hoặc để **AI chấm**.

### 7. Sổ điểm và xuất báo cáo 📊

`Khoá học → 📊 Sổ điểm`

- Bảng ma trận học sinh × đầu điểm, có hệ số, tổng điểm, **quy đổi thang 10**, **xếp loại**.
- Phổ điểm lớp.
- Xuất **Excel (.xlsx)** (2 trang: bảng điểm + thống kê), **PDF** (khổ ngang, có logo, chữ ký),
  **CSV**.
- `Thống kê & báo cáo`: tiến độ trung bình, phân bố xếp loại, hoạt động 14 ngày,
  mức độ hoàn thành từng đầu điểm.

### 8. Quản lý học viên 👥

`Khoá học → 👥 Học viên`: thêm hàng loạt bằng tên đăng nhập/email, duyệt yêu cầu ghi danh,
xem tiến độ từng em, gỡ khỏi lớp, thêm **giáo viên đồng phụ trách**, xem **mã ghi danh**.

---

## Phần III — Học sinh 🧑‍🎓

1. **Đăng ký / đăng nhập** tại trang chủ (nếu nhà trường cho phép tự đăng ký).
2. **Khám phá khoá học** → *Tham gia ngay* (có thể cần mã ghi danh do thầy cô cung cấp).
3. **Bảng điều khiển**: khoá học đang theo, tiến độ, bài sắp đến hạn, điểm mới nhận, thông báo.
4. **Học bài**: đọc trang nội dung, xem video, tải tài liệu, học gói SCORM.
   Hệ thống tự đánh dấu hoàn thành và tính tiến độ.
5. **Nộp bài tập**: gõ trực tiếp (có nút chèn công thức, ảnh) và/hoặc tải tệp lên.
   Nếu thầy cô bật, có nút **✨ Nhờ AI nhận xét bài** để nhận góp ý tức thì.
6. **Làm trắc nghiệm**: bài tự lưu liên tục, hết giờ hệ thống tự nộp.
   Xem lại đáp án và giải thích nếu được cho phép.
7. **Kết quả học tập**: điểm từng đầu điểm, điểm trung bình thang 10, xếp loại;
   tải phiếu kết quả dạng **PDF** hoặc **Excel** trong *Hồ sơ cá nhân*.
8. **Thảo luận**: đặt câu hỏi trong diễn đàn lớp.

> ⏰ Phiên đăng nhập kéo dài mặc định **12 giờ** và tự động được gia hạn khi em còn mở trang,
> nên làm bài dài không lo bị đăng xuất giữa chừng.

---

## Phụ lục — Phím tắt & mẹo nhỏ

- 🌙 Nút mặt trăng trên thanh trên cùng: đổi **giao diện sáng / tối**.
- 🔍 Ô tìm kiếm trong các bảng: lọc ngay tại chỗ, **không phân biệt dấu tiếng Việt**
  (gõ `nguyen` vẫn tìm ra `Nguyễn`).
- Trên điện thoại, bấm ☰ để mở thanh điều hướng.
- Trang bảng điểm và danh sách có thể **in trực tiếp** (Ctrl+P) với bố cục gọn gàng.
