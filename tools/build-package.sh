#!/usr/bin/env bash
# ============================================================
#  Tạo gói cài đặt sẵn sàng tải lên hosting cPanel.
#  Kết quả: dist/lms-<ngày>.zip — giải nén thẳng vào public_html.
#  Dùng: bash tools/build-package.sh
# ============================================================
set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/src"
DIST="$ROOT/dist"
STAMP="$(date +%Y%m%d)"
NAME="lms-$STAMP.zip"

if [ ! -d "$SRC" ]; then
  echo "Không tìm thấy thư mục src/"; exit 1
fi

mkdir -p "$DIST"
rm -f "$DIST/$NAME"

TMP="$(mktemp -d)"
cp -r "$SRC/." "$TMP/"

# Không đóng gói cấu hình của máy cài đặt trước đó
rm -f "$TMP/config.php"
find "$TMP" -name '.DS_Store' -delete 2>/dev/null || true
find "$TMP" -name '*.log' -delete 2>/dev/null || true

cd "$TMP"
if command -v zip >/dev/null 2>&1; then
  zip -rq "$DIST/$NAME" . -x '.git/*'
else
  # Dự phòng khi máy không có lệnh zip: dùng chính bộ ZIP viết bằng PHP của hệ thống
  php -r '
    define("LMS_ENTRY", true);
    require "app/Zip.php";
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(".", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $rel = ltrim(str_replace("\\\\", "/", substr($f->getPathname(), 2)), "/");
        $files[$rel] = file_get_contents($f->getPathname());
    }
    file_put_contents(getenv("OUT"), Zip::create($files));
  ' OUT="$DIST/$NAME"
fi
cd "$ROOT"
rm -rf "$TMP"

SIZE=$(du -h "$DIST/$NAME" | cut -f1)
echo "✅ Đã tạo gói: dist/$NAME ($SIZE)"
echo
echo "Các bước tiếp theo:"
echo "  1. Tải tệp này lên cPanel → File Manager → public_html"
echo "  2. Chuột phải vào tệp → Extract"
echo "  3. Mở https://ten-mien-cua-ban/install.php"
echo "  4. Cài xong nhớ xoá install.php"
