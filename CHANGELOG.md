# 📜 Lịch sử phiên bản

Toàn bộ thay đổi đáng chú ý của hệ thống LMS được ghi lại tại đây.

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/),
đánh số phiên bản theo [Semantic Versioning](https://semver.org/lang/vi/).

Ký hiệu: `Thêm mới` · `Thay đổi` · `Sửa lỗi` · `Bỏ đi` · `Bảo mật`

---

## [1.2.0] — 10/09/2026

Bản cập nhật thêm **xem trước mọi loại tệp học liệu ngay trên web** — học sinh không phải
tải tệp về mới đọc được, thầy cô chấm bài Word mà không cần rời trang chấm.

### Thêm mới

- **Xem trước tệp đính kèm** với 12 nhóm định dạng, mở trong hộp thoại lớn ngay tại chỗ:
  - **Word `.docx`** → dựng lại thành HTML: tiêu đề, in đậm/nghiêng/gạch chân, chỉ số trên–dưới,
    tô sáng, danh sách nhiều cấp, **bảng**, **hình minh hoạ nhúng trong tài liệu** và liên kết.
  - **Excel `.xlsx`** → từng trang tính có thẻ chuyển, ô ngày tháng đổi sang `dd/mm/yyyy`,
    số căn phải; giới hạn 300 hàng × 40 cột cho mỗi trang.
  - **PowerPoint `.pptx`** → nội dung chữ từng slide kèm tiêu đề và các gạch đầu dòng.
  - **OpenDocument `.odt` `.ods` `.odp`** → nội dung chữ; riêng `.ods` dựng lại thành bảng.
  - **PDF · ảnh · video · âm thanh** → mở/phát thẳng trong trang.
  - **CSV/TSV** → bảng dữ liệu, tự nhận dấu phân cách `,` `;` tab `|`.
  - **Văn bản, phụ đề, mã nguồn** → có đánh số dòng, tự nhận bảng mã không phải UTF-8.
  - **ZIP** → danh sách tệp bên trong kèm dung lượng.
  - Toàn bộ do PHP tự đọc — **không cần thư viện ngoài, không gửi tệp ra dịch vụ nào khác**.
- Hai lớp mới `app/Office.php` (đọc docx/xlsx/pptx/odf) và `app/Preview.php` (dựng khung xem trước),
  cùng `assets/js/preview.js` cho hộp thoại.
- Đường dẫn `index.php?r=preview/file&f=…` mở tệp thành **một trang riêng** — dùng được cả khi
  trình duyệt tắt JavaScript, và chia sẻ được bằng liên kết.
- Lật nhanh giữa các tệp trong cùng một danh sách bằng **phím ← →** hoặc hai nút mũi tên;
  phím **Esc** đóng hộp thoại.
- Xem trước có mặt ở: học liệu của bài, tài liệu kèm theo đề bài, bài nộp của học sinh,
  màn hình chấm bài, kho dữ liệu của quản trị viên và trang sửa nội dung của giáo viên.
- **Trang chấm bài hiển thị thẳng bài làm Word/PDF/ảnh** của học sinh, không phải tải về.
- Hai công tắc mới trong `Quản trị → Cấu hình chung`: bật/tắt xem trước và
  đặt dung lượng tối đa còn bóc tách nội dung (mặc định 25 MB).

### Thay đổi

- Hộp thoại (`LMS.modal`) đóng được bằng phím **Esc** và nhận thêm tuỳ chọn lớp CSS.
- Tệp chính của mục học liệu không còn bị liệt kê hai lần khi cũng nằm trong danh sách đính kèm.
- Tệp `.doc` `.xls` `.ppt` đời cũ hiện lời nhắc lưu lại dưới dạng `.docx` `.xlsx` `.pptx`
  thay cho thông báo chung chung.

---

## [1.1.1] — 10/09/2026

Bản cập nhật xử lý trường hợp **lớp có rất nhiều đầu điểm** — trước đây bảng điểm PDF
bị tràn cột ra ngoài trang giấy và sổ điểm trên màn hình khó dò cột.

### Thêm mới

- **Bảng điểm PDF tự chia thành nhiều phần theo chiều ngang** khi số đầu điểm vượt quá
  bề ngang trang A4 ngang. Mỗi phần ghi rõ *"PHẦN 2/3 — cột điểm 8–14 trên tổng số 21"*
  và lặp lại cột STT · Họ và tên · Tài khoản; số cột được chia đều cho các phần.
- Bảng **KẾT QUẢ TỔNG HỢP** riêng ở cuối (Tổng điểm · Thang 10 · Xếp loại) kèm cột mới
  **Số cột đã có điểm** dạng `18/21`.
- Bảng **CHÚ THÍCH CÁC CỘT ĐIỂM** trong PDF: số thứ tự, loại, tên đầy đủ, điểm tối đa, hệ số.
- Sổ điểm trên màn hình: **đánh số từng cột điểm**, bảng **Chú thích cột điểm** bên dưới
  (bấm vào tên để mở thẳng bài tập / bài trắc nghiệm), dòng nhắc **↔️ cuộn ngang**
  khi lớp có từ 6 đầu điểm trở lên.
- **Ghim cụm Tổng · Thang 10 · Xếp loại** ở mép phải bảng điểm — cuộn ngang tới đâu vẫn
  thấy kết quả cuối cùng của từng em (cột Học sinh vẫn ghim ở mép trái như trước).

### Thay đổi

- Tiêu đề cột điểm trong PDF luôn mang số thứ tự; khi cột đủ rộng thì hiện cả tên đầu điểm.
- Trên điện thoại, sổ điểm rút gọn tiêu đề cột còn số thứ tự và bỏ ghim cụm cột tổng kết
  để còn chỗ cuộn xem điểm.
- Chân trang PDF in trên **mọi trang** kèm số trang dạng `Trang 2/3`
  (trước đây chỉ in ở trang cuối và không có tổng số trang).

### Sửa lỗi

- Bảng điểm PDF của lớp nhiều hơn khoảng 8 đầu điểm bị **vẽ tràn ra ngoài lề phải** và
  mất hẳn các cột cuối.
- Ô ghim che mất màu nền của hàng khi rê chuột trong sổ điểm.
- Tệp CSV xuất trên PHP 8.4 bị chèn dòng cảnh báo `Deprecated: fputcsv()` khi máy chủ
  bật hiển thị lỗi.

---

## [1.1.0] — 10/09/2026

Bản cập nhật tập trung vào **công thức toán học**, **hình minh hoạ trong bài giảng**
và **tài liệu hướng dẫn đầy đủ ảnh chụp màn hình**.

### Thêm mới

- **Công thức toán LaTeX (MathJax 3)** hiển thị ở bài giảng, học liệu, đề bài tập,
  câu hỏi trắc nghiệm và phương án trả lời, bài làm của học sinh, nhận xét của giáo viên,
  thông báo và diễn đàn.
  - Hỗ trợ `\( ... \)`, `$ ... $`, `\[ ... \]`, `$$ ... $$` và các môi trường
    `\begin{cases}`, `\begin{pmatrix}`, `\begin{align}`…
  - **Đóng gói sẵn bản MathJax rút gọn (1,6 MB)** trong `assets/js/mathjax/` nên công thức
    hiển thị được cả khi máy chủ hoặc học sinh không truy cập được Internet.
- **Thanh công cụ soạn thảo** cho mọi ô nội dung:
  - Định dạng chữ, tiêu đề, danh sách, trích dẫn, khối mã, bảng.
  - **Chèn ảnh minh hoạ**: tải tệp lên hoặc **dán thẳng ảnh chụp màn hình bằng Ctrl + V**.
  - Nhúng video YouTube / Vimeo / iframe.
  - **Thư viện hơn 20 mẫu công thức** Toán – Lí – Hoá (phân số, căn thức, tích phân,
    ma trận, hệ phương trình, phản ứng hoá học, định luật Newton, xác suất…).
  - Nút **xem trước** có render công thức.
- Endpoint tải ảnh riêng (`upload/image`), ảnh lưu thẳng vào MySQL như mọi tệp khác.
- Ba công tắc mới trong `Quản trị → Cấu hình chung`: bật/tắt MathJax, cho phép cú pháp `$...$`,
  hiện/ẩn thanh công cụ soạn thảo; kèm ô đổi địa chỉ thư viện MathJax.
- Mục **SCORM** có công tắc *"Tính điểm vào sổ điểm"* — dùng gói SCORM làm bài tập chấm điểm
  hoặc chỉ là học liệu tham khảo.
- **Bộ tài liệu hướng dẫn có ảnh minh hoạ**: `docs/HUONG-DAN-SU-DUNG.md` (57 ảnh chụp màn hình)
  và `docs/HUONG-DAN-CAI-DAT.md`.
- Tệp `CHANGELOG.md` này.

### Thay đổi

- Bộ lọc HTML giữ nguyên đoạn công thức nên các ký tự `<`, `>`, `&`, `\\` trong LaTeX
  không còn bị hiểu nhầm là thẻ HTML.
- Trang cấu hình AI **giữ lại thông số vừa nhập** khi bấm *Kiểm tra kết nối*.
- Địa chỉ trang gọn hơn: `index.php?r=course/view` thay cho `index.php?r=course%2Fview`.
- Trình phát SCORM đọc đường dẫn từ `REQUEST_URI` khi máy chủ không thiết lập `PATH_INFO`,
  giữ nguyên các liên kết tương đối bên trong gói.
- Bảng điều khiển của giáo viên không còn hiện khối "chưa tham gia khoá học nào" thừa.

### Sửa lỗi

- Lưới hai cột đặt bằng style nội tuyến nay tự xếp thành một cột trên điện thoại.
- Trang làm bài trắc nghiệm không còn báo lỗi `LMS is not defined` do thứ tự nạp JavaScript.
- Câu trả lời tự luận được lọc HTML ngay khi lưu và hiển thị đúng công thức khi xem lại.

### Bảo mật

- Mọi tệp trong thư mục `app/` từ chối truy cập trực tiếp qua trình duyệt
  (kiểm tra hằng số `LMS_ENTRY`), không phụ thuộc vào `.htaccess`.
- Quy tắc chặn `app/` trong `.htaccess` hoạt động cả khi cài vào thư mục con.

---

## [1.0.0] — 09/09/2026

Phiên bản đầu tiên — hệ thống quản lý học tập hoàn chỉnh viết bằng **PHP thuần**,
chạy được trên hosting chia sẻ (cPanel) mà không cần Composer, Node.js hay quyền SSH.

### Người dùng & phân quyền

- Ba vai trò: **quản trị viên**, **giáo viên**, **học sinh**.
- Đăng ký, đăng nhập, ghi nhớ đăng nhập, hồ sơ cá nhân, ảnh đại diện, đổi mật khẩu.
- Quản trị viên **bật/tắt riêng biệt** việc tự đăng ký của giáo viên và của học sinh,
  kèm tuỳ chọn bắt buộc phê duyệt cho từng nhóm.
- Duyệt / khoá / mở khoá / đặt lại mật khẩu / xoá tài khoản; duyệt hàng loạt.

### Khoá học & học liệu

- Danh mục môn học, chương (section), ảnh bìa, màu chủ đạo, sĩ số tối đa, thời gian mở lớp.
- Bốn hình thức ghi danh: tự do · cần mã · cần duyệt · chỉ giáo viên thêm.
- Giáo viên đồng phụ trách khoá học.
- Tám loại nội dung: trang nội dung, tệp học liệu, video, liên kết ngoài, gói SCORM,
  bài tập, bài trắc nghiệm, diễn đàn thảo luận.
- Đặt lịch **mở – đóng** cho từng mục, ẩn/hiện, điểm tối đa và hệ số.
- Xem trước PDF, ảnh, âm thanh, video ngay trên web; phát video có tua (HTTP Range).

### Chuẩn SCORM

- Nhập gói **SCORM 1.2** và **SCORM 2004** từ tệp `.zip`.
- Tự phân tích `imsmanifest.xml`, dựng mục lục nhiều SCO, xác định tệp khởi chạy.
- Bộ điều hợp API đầy đủ (`LMSInitialize`, `LMSGetValue`, `LMSSetValue`, `LMSCommit`,
  `LMSFinish` và bộ lệnh SCORM 2004 tương ứng).
- Ghi nhận trạng thái hoàn thành, điểm số, vị trí đang học, thời gian học và `suspend_data`;
  tự lưu định kỳ và khi rời trang.
- Báo cáo kết quả SCORM của cả lớp, xuất Excel.

### Bài tập & chấm bài

- Hạn nộp, hạn đóng, cho phép nộp trễ, nhiều lượt nộp, giới hạn số tệp và định dạng.
- Nộp tệp và/hoặc gõ bài trực tiếp trên web.
- Màn hình chấm bài xem trực tiếp PDF, ảnh, âm thanh, video; chấm liên tục bằng nút
  *Lưu & bài kế*.
- Bộ lọc *Tất cả · Chờ chấm · Đã chấm · Chưa nộp* kèm thống kê nhanh.

### Trợ lý AI chấm bài

- Cấu hình đầy đủ: **nhà cung cấp, Endpoint URL, API key, tên model,
  token tối đa (mặc định 64000), timeout (mặc định 300 giây), temperature,
  header HTTP bổ sung, lời nhắc hệ thống**.
- Hỗ trợ **OpenAI**, **Anthropic**, **Google Gemini** và **mọi API tương thích OpenAI**
  (OpenRouter, Groq, DeepSeek, Together, LM Studio, Ollama…).
- Đọc được bài làm dạng văn bản, tệp `.docx`, `.txt`, `.html`, `.pdf` và
  **ảnh chụp bài viết tay** (với model hỗ trợ hình ảnh).
- Trả về điểm đề xuất, **bảng tiêu chí**, điểm mạnh và điều cần cải thiện theo rubric
  do giáo viên đặt.
- Ba mức áp dụng: chỉ khi giáo viên bấm · tự động chấm khi học sinh nộp ·
  lấy điểm AI làm điểm chính thức.
- Nút **kiểm tra kết nối** và nhật ký các lần chấm.

### Trắc nghiệm

- Năm dạng câu hỏi: chọn một đáp án, chọn nhiều đáp án, đúng/sai, trả lời ngắn, tự luận.
- Giới hạn thời gian có đồng hồ đếm ngược, trộn câu hỏi và phương án, nhiều lượt làm.
- **Tự động lưu từng câu** ngay khi học sinh chọn hoặc gõ.
- Tự động chấm câu khách quan (trả lời ngắn không phân biệt hoa thường và dấu tiếng Việt);
  câu tự luận do giáo viên hoặc AI chấm.
- Bốn cách tính điểm khi làm nhiều lần: cao nhất · lần cuối · lần đầu · trung bình.

### Sổ điểm, thống kê và xuất dữ liệu

- Sổ điểm ma trận học sinh × đầu điểm, có hệ số, tổng điểm, quy đổi **thang 10** và **xếp loại**.
- Biểu đồ phổ điểm, phân bố xếp loại, hoạt động 14 ngày, mức độ hoàn thành từng đầu điểm —
  vẽ bằng SVG, **không phụ thuộc thư viện ngoài**.
- Xuất **Excel (.xlsx)**, **PDF** và **CSV** cho: bảng điểm, danh sách lớp, tiến độ học tập,
  kết quả bài tập, kết quả trắc nghiệm, kết quả SCORM, danh sách người dùng và
  phiếu kết quả cá nhân của học sinh.
- Bộ tạo tệp XLSX và PDF **tự viết bằng PHP thuần**; PDF nhúng font TrueType nên
  **tiếng Việt có dấu đầy đủ**, có logo, tên đơn vị, dòng bản quyền và ô ký tên.

### Tương tác

- Diễn đàn thảo luận theo lớp (ghim, khoá chủ đề, xoá bài).
- Thông báo lớp và thông báo toàn hệ thống; hộp thông báo cá nhân.

### Tuỳ biến & vận hành

- Đổi **tên website, khẩu hiệu, tên đơn vị, logo, favicon, ảnh trang chủ, màu chủ đạo,
  màu nhấn, dòng bản quyền chân trang**, thông tin liên hệ.
- Chế độ bảo trì, định dạng ngày giờ, số dòng mỗi trang.
- **Toàn bộ dữ liệu — kể cả tệp tin và gói SCORM — lưu trong MySQL** (chia mảnh 512 KB
  để không vượt `max_allowed_packet` của hosting chia sẻ).
- Trang **Kho dữ liệu**: thống kê dung lượng, dọn tệp thừa, dọn phiên hết hạn, dọn nhật ký cũ.
- **Phiên làm việc lưu trong CSDL**, mặc định **12 giờ**, tự động gia hạn khi trang còn mở —
  học sinh làm bài dài không bị đăng xuất.
- Toàn hệ thống dùng **giờ Việt Nam (Asia/Ho_Chi_Minh, GMT+7)** cho cả PHP lẫn MySQL.

### Giao diện

- Thiết kế tươi sáng, nhiều màu, hoạt ảnh nhẹ nhàng; **giao diện sáng / tối**.
- Chạy tốt trên máy tính, máy tính bảng và điện thoại.
- Bộ hình minh hoạ SVG tự vẽ, không phụ thuộc dịch vụ bên ngoài.
- Ô tìm kiếm lọc bảng ngay tại chỗ, **không phân biệt dấu tiếng Việt**.

### Cài đặt

- **Trình cài đặt web 3 bước**: kiểm tra máy chủ → nhập thông tin CSDL và quản trị viên → hoàn tất.
- Tự tạo hơn 25 bảng dữ liệu, danh mục môn học mẫu và tệp `config.php`.
- Tự bổ sung bảng/cột còn thiếu khi nâng cấp, **không mất dữ liệu**.
- Kèm `.htaccess`, `.user.ini` và script `tools/build-package.sh` đóng gói sẵn để upload cPanel.

### Bảo mật

- Mật khẩu băm bằng `password_hash()` (bcrypt).
- Mã CSRF cho mọi biểu mẫu; toàn bộ truy vấn dùng prepared statement.
- Lọc thẻ HTML nguy hiểm trong nội dung người dùng nhập.
- Tệp HTML/SVG do người dùng tải lên luôn bị buộc tải về, không chạy trong tên miền.
- Kiểm soát quyền theo vai trò và theo từng khoá học ở mọi trang.

---

## Dự kiến các phiên bản tới

- Nhập danh sách học sinh hàng loạt từ tệp Excel.
- Chứng nhận hoàn thành khoá học (xuất PDF).
- Điểm danh theo buổi học.
- Ngân hàng câu hỏi dùng chung giữa nhiều bài trắc nghiệm.
- Gửi email nhắc hạn nộp bài.

> Có ý tưởng hoặc phát hiện lỗi? Hãy tạo một *Issue* trên GitHub.

[1.2.0]: https://github.com/tlearnvn/tuan-lms/releases/tag/v1.2.0
[1.1.1]: https://github.com/tlearnvn/tuan-lms/releases/tag/v1.1.1
[1.1.0]: https://github.com/tlearnvn/tuan-lms/releases/tag/v1.1.0
[1.0.0]: https://github.com/tlearnvn/tuan-lms/releases/tag/v1.0.0
