# Hướng dẫn khắc phục vấn đề Import Excel từ máy khách

## ⚠️ NGUYÊN NHÂN CHÍNH: ĐỊNH DẠNG NGÀY THÁNG (90% lỗi)

### 🎯 Vấn đề số 1: Định dạng ngày tháng không đồng nhất

**Triệu chứng:**
- Import thành công trên máy A nhưng thất bại trên máy B
- Lỗi "Ngày không hợp lệ" hoặc "Cannot parse date"
- Ngày tháng bị đảo lộn (25/12 thành 12/25)

**Nguyên nhân:**
- Máy Việt Nam: dd/mm/yyyy (25/12/2024)
- Máy nước ngoài: mm/dd/yyyy (12/25/2024)
- Regional Settings khác nhau
- Excel format cells khác nhau

**Giải pháp:**

#### A. Cài đặt Regional Settings (Windows)
1. **Control Panel → Region**
2. **Short date format: dd/MM/yyyy**
3. **Long date format: dddd, dd MMMM yyyy**
4. **Restart Excel sau khi thay đổi**

#### B. Format Excel Cells
1. **Chọn cột ngày tháng**
2. **Right-click → Format Cells**
3. **Category: Date**
4. **Type: 14/03/2012 (dd/mm/yyyy)**
5. **Save file lại**

#### C. Định dạng chuẩn được hỗ trợ
```
✅ ĐÚNG:
- 25/12/2024 (dd/mm/yyyy) - Ưu tiên
- 25-12-2024 (dd-mm-yyyy)
- 25.12.2024 (dd.mm.yyyy)
- 5/3/2024 (d/m/yyyy)
- 2024-12-25 (yyyy-mm-dd)
- Excel date number: 45646

❌ SAI:
- 12/25/2024 (mm/dd/yyyy) - Có thể gây nhầm lẫn
- Dec 25, 2024 (text format)
- 25122024 (không có separator)
- 2024/25/12 (Invalid format)
```

## Vấn đề khác (10% còn lại)

### 2. Encoding và ký tự đặc biệt

**Triệu chứng:**
- Tên máy/sản phẩm bị lỗi font
- Ký tự Việt Nam bị méo

**Giải pháp:**
1. **Save As → Excel Workbook (.xlsx)**
2. **Trong Notepad++: Encoding → UTF-8**
3. **Tránh ký tự đặc biệt trong tên**

### 3. Tên máy không tìm thấy

**Hỗ trợ tìm kiếm linh hoạt:**
```
- Tên chính xác: "Máy đùn A" 
- Tìm kiếm đơn giản: "A" → tìm thấy "Máy đùn A"
- Wildcard: "đùn" → tìm thấy các máy có chứa "đùn"
```

### 4. Định dạng số

**Tự động xử lý:**
```
✅ Hỗ trợ:
- 1.234,56 (EU format)
- 1,234.56 (US format)  
- 1234,56
- 1234.56

❌ Không hỗ trợ:
- 1,234.56.78 (invalid)
- "1234" (text trong quotes)
```

## Checklist Quick Fix (Theo độ ưu tiên)

### 🔥 Ưu tiên cao (Fix 90% lỗi):
- [ ] **Kiểm tra Regional Settings:** Control Panel → Region → Short date: dd/MM/yyyy
- [ ] **Format Excel date column:** Format Cells → Date → 14/03/2012
- [ ] **Restart Excel** sau khi thay đổi settings
- [ ] **Test với ngày rõ ràng:** 25/12/2024 (không thể nhầm lẫn)

### ⚠️ Ưu tiên trung bình:
- [ ] Save file .xlsx format
- [ ] Kiểm tra tên máy khớp với hệ thống
- [ ] Xóa ký tự đặc biệt

### ℹ️ Ưu tiên thấp:
- [ ] Check encoding UTF-8
- [ ] Clear Excel cache
- [ ] Restart máy

## Test case đơn giản

**File test tối thiểu:**
```
Ngày          | Ca | Tên máy    | ... | Ra máy
25/12/2024   | 1  | Máy đùn A  | ... | 100
26/12/2024   | 2  | A          | ... | 150
```

**Nếu import thành công → OK**
**Nếu lỗi ngày → Fix Regional Settings**

## Hỗ trợ debug

### Command kiểm tra nhanh:
```bash
# Xem log ngay lập tức
tail -f storage/logs/laravel.log | grep -i "ngày\|date"

# Kiểm tra máy trong hệ thống
php artisan tinker --execute="App\Models\Machine::pluck('name')->each(fn(\$n) => echo \$n . PHP_EOL);"
```

### Thông tin cần thiết khi báo lỗi:
1. **Screenshot lỗi import**
2. **File Excel gốc (2-3 dòng đầu)**
3. **Regional Settings screenshot**
4. **Excel version và OS**

## Tóm tắt: Cách fix nhanh nhất

1. **Control Panel → Region → Short date: dd/MM/yyyy**
2. **Restart Excel**
3. **Format cột ngày: dd/mm/yyyy**
4. **Test với ngày 25/12/2024**
5. **Nếu vẫn lỗi → Check tên máy**

> **Lưu ý:** 90% vấn đề import từ máy khách khác nhau là do định dạng ngày tháng. Fix Regional Settings sẽ giải quyết hầu hết các trường hợp.

## Vấn đề thường gặp

### 1. Import thành công trên máy này nhưng thất bại trên máy khác

**Nguyên nhân:**
- Encoding khác nhau giữa các máy
- Định dạng ngày tháng khác nhau
- Phiên bản Office khác nhau
- Cài đặt ngôn ngữ/locale khác nhau

**Giải pháp:**

#### A. Chuẩn hóa file Excel
1. **Lưu file đúng định dạng:**
   ```
   File → Save As → Excel Workbook (.xlsx)
   ```

2. **Kiểm tra encoding:**
   - Mở file bằng Notepad++
   - Chọn Encoding → UTF-8
   - Save lại file

#### B. Định dạng dữ liệu chuẩn

1. **Ngày tháng:** 
   ```
   ✓ Đúng: 25/12/2024, 01/01/2025
   ✗ Sai: 12-25-2024, 2024/12/25
   ```

2. **Tên máy:**
   ```
   ✓ Đúng: "Máy đùn A", "Máy đùn 1"
   ✗ Sai: "Máy_đùn_A", "MayDunA"
   ```

3. **Số liệu:**
   ```
   ✓ Đúng: 1234.56, 1.234,56, 1234
   ✗ Sai: 1,234.56.78, "1234"
   ```

### 2. Lỗi encoding từ các máy Windows khác nhau

**Triệu chứng:**
- Tên máy/sản phẩm bị lỗi font
- Ký tự Việt Nam bị méo

**Giải pháp:**

#### A. Cài đặt Excel
1. Mở Excel → File → Options → Advanced
2. Tìm "Web Options" → Encoding → UTF-8
3. Save và restart Excel

#### B. Tạo file mới từ template
1. Tải template từ hệ thống
2. Copy dữ liệu từ file cũ sang file template
3. Save file template với dữ liệu mới

### 3. Vấn đề định dạng số từ locale khác nhau

**Nguyên nhân:**
- Máy Việt Nam: dấu phẩy (,) làm thập phân
- Máy quốc tế: dấu chấm (.) làm thập phân

**Giải pháp tự động:**
Hệ thống đã được cập nhật để tự động nhận diện:
- `1.234,56` → 1234.56
- `1,234.56` → 1234.56  
- `1234,56` → 1234.56
- `1234.56` → 1234.56

### 4. Lỗi tên máy không tìm thấy

**Nguyên nhân phổ biến:**
- Tên máy không khớp chính xác
- Có ký tự ẩn/space thừa
- Encoding khác nhau

**Cách kiểm tra:**
1. Admin: Xem danh sách máy trong modal import
2. So sánh tên máy trong Excel với danh sách hệ thống
3. Copy chính xác tên máy từ hệ thống

**Tên máy hỗ trợ:**
```
- Tên chính xác: "Máy đùn A"
- Tìm kiếm linh hoạt: "A" → tìm thấy "Máy đùn A"
- Wildcard: "đùn" → tìm thấy các máy có chứa "đùn"
```

### 5. Lỗi định dạng ngày

**Định dạng được hỗ trợ:**
```
✓ dd/mm/yyyy: 25/12/2024
✓ dd-mm-yyyy: 25-12-2024  
✓ dd.mm.yyyy: 25.12.2024
✓ yyyy-mm-dd: 2024-12-25
✓ Excel date number: 45646
✓ dd/mm/yy: 25/12/24
```

**Lưu ý:**
- Hệ thống ưu tiên định dạng dd/mm/yyyy
- Năm 2 chữ số: 00-30 → 2000-2030, 31-99 → 1931-1999

## Checklist debug

### Cho người dùng:
- [ ] File Excel được save đúng định dạng .xlsx?
- [ ] Ngày tháng theo định dạng dd/mm/yyyy?
- [ ] Tên máy khớp chính xác với hệ thống?
- [ ] Mã sản phẩm tồn tại trong hệ thống?
- [ ] Không có ký tự đặc biệt trong dữ liệu?

### Cho Admin:
- [ ] Kiểm tra log file: `storage/logs/laravel.log`
- [ ] Xem thông tin debug trong modal import
- [ ] Verify danh sách máy và sản phẩm
- [ ] Test import với file template gốc

## Command hữu ích

### Kiểm tra danh sách máy:
```bash
php artisan tinker
App\Models\Machine::all(['id', 'name'])->each(function($m) { 
    echo $m->id . ': ' . $m->name . PHP_EOL; 
});
```

### Xem log real-time:
```bash
tail -f storage/logs/laravel.log | grep -i import
```

### Clear cache nếu cần:
```bash
php artisan config:clear
php artisan cache:clear
```

## Liên hệ hỗ trợ

Nếu vẫn gặp vấn đề, vui lòng cung cấp:
1. File Excel lỗi
2. Screenshot thông báo lỗi
3. Thông tin máy (OS, Office version)
4. Log từ storage/logs/laravel.log 