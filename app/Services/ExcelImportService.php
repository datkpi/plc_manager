<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\Product;
use App\Models\ProductionEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Carbon\Carbon;

class ExcelImportService
{
    /**
     * Import dữ liệu ProductionEntry từ file Excel
     */
    public function importProductionEntries($filePath)
    {
        try {
            Log::info('Bắt đầu import file: ' . $filePath);
            
            // Đọc file Excel
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Lấy dữ liệu với format gốc thay vì chuyển đổi thành array
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            
            $rows = [];
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cell = $worksheet->getCellByColumnAndRow($col, $row);
                    $cellValue = $cell->getValue();
                    
                    // Xử lý đặc biệt cho cột ngày tháng (cột A)
                    if ($col == 1 && $row > 1) { // Cột A, bỏ qua header
                        // Kiểm tra xem cell có được format như ngày tháng không
                        if ($cell->getDataType() == \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC) {
                            $formatCode = $cell->getStyle()->getNumberFormat()->getFormatCode();
                            if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                                // Đây là ngày tháng được format
                                $dateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellValue);
                                $cellValue = $dateTime->format('d/m/Y');
                                Log::info("Detected formatted date in row {$row}: {$cellValue}");
                            }
                        }
                    }
                    
                    $rowData[] = $cellValue;
                }
                $rows[] = $rowData;
            }
            
            Log::info('Đã đọc file Excel thành công. Số dòng: ' . count($rows));

            // Log chi tiết từng dòng dữ liệu gốc
            foreach ($rows as $index => $row) {
                Log::info("Dữ liệu gốc dòng " . ($index + 1) . ": " . json_encode($row, JSON_UNESCAPED_UNICODE));
            }

            // Bỏ qua dòng tiêu đề và các dòng rỗng
            $dataRows = array_filter($rows, function($row, $index) {
                // Log để debug từng dòng
                Log::info("Đang kiểm tra dòng " . ($index + 1) . ": " . json_encode($row, JSON_UNESCAPED_UNICODE));
                
                // Bỏ qua dòng tiêu đề (index 0)
                if ($index === 0) {
                    Log::info("Bỏ qua dòng tiêu đề " . ($index + 1));
                    return false;
                }
                
                // Kiểm tra từng cell trong dòng
                $nonEmptyCells = array_filter($row, function($cell) {
                    $cellValue = trim((string)$cell);
                    return $cellValue !== '' && $cellValue !== null;
                });
                
                $hasData = !empty($nonEmptyCells);
                
                if (!$hasData) {
                    Log::info("Bỏ qua dòng " . ($index + 1) . " vì không có dữ liệu");
                } else {
                    Log::info("Chấp nhận dòng " . ($index + 1) . " với " . count($nonEmptyCells) . " ô có dữ liệu");
                }
                
                return $hasData;
            }, ARRAY_FILTER_USE_BOTH);
            
            // Reset array keys để đảm bảo index liên tục
            $dataRows = array_values($dataRows);
            
            // Log kết quả sau khi lọc
            Log::info("Tổng số dòng sau khi lọc: " . count($dataRows));
            foreach ($dataRows as $index => $row) {
                Log::info("Dòng " . ($index + 1) . " sau khi lọc: " . json_encode($row, JSON_UNESCAPED_UNICODE));
            }
            
            Log::info('Số dòng dữ liệu cần import: ' . count($dataRows));

            $importResults = [
                'total' => count($dataRows),
                'success' => 0,
                'error' => 0,
                'errors' => []
            ];

            // Không sử dụng transaction bao toàn bộ quá trình import
            // mà chỉ sử dụng transaction cho từng dòng
            // DB::beginTransaction();
            // dd($dataRows);

            foreach ($dataRows as $rowIndex => $row) {
                // Bắt đầu transaction cho dòng này
                DB::beginTransaction();
                
                try {
                    // Số dòng trong file Excel = index + 2 (vì bỏ qua dòng tiêu đề và index bắt đầu từ 0)
                    $rowNumber = $rowIndex + 2;

                    // Map dữ liệu theo cột biểu mẫu thực tế từ file Excel được upload
                    $date = $this->parseDate($row[0]); // Cột A: Ngày
                    $shift = $row[1]; // Cột B: Ca
                    
                    // Chuyển đổi ca từ số (1, 2, 3) sang chuỗi (CA1, CA2, CA3)
                    if (is_numeric($shift)) {
                        $shift = 'CA' . $shift;
                    }
                    
                    $machineName = $row[2]; // Cột C: Tên máy
                    $operatorName = $row[3]; // Cột D: Tên công nhân vận hành
                    $operatorTeam = $row[4]; // Cột E: Tổ
                    $productCode = $row[5]; // Cột F: Sản phẩm
                    $productLength = is_numeric($row[6]) ? (float)$row[6] : null; // Cột G: Số m
                    $runQuantity = is_numeric($row[7]) ? (float)$row[7] : 0; // Cột H: Ra máy (cây/cuộn)
                    $goodQuantity = is_numeric($row[8]) ? (float)$row[8] : 0; // Cột I: Chính phẩm (cây/cuộn)
                    $defectWeight = is_numeric($row[9]) ? (float)$row[9] : 0; // Cột J: Phế phẩm (kg)
                    $wasteWeight = is_numeric($row[10]) ? (float)$row[10] : 0; // Cột K: Phế liệu (kg)
                    $machineOperator = $row[11]; // Cột L: CN chạy máy
                    $qualityInspector = $row[12]; // Cột M: CN kiểm
                    $warehouseStaff = $row[13]; // Cột N: CN kho
                    $notes = $row[14] ?? ''; // Cột O: Ghi chú

                    // Kiểm tra dữ liệu bắt buộc
                    if (empty($date)) {
                        throw new \Exception("Ngày không được để trống");
                    }
                    if (empty($shift)) {
                        throw new \Exception("Ca không được để trống");
                    }
                    if (empty($machineName)) {
                        throw new \Exception("Máy không được để trống");
                    }
                    if (empty($productCode)) {
                        throw new \Exception("Sản phẩm không được để trống");
                    }
                    if ($runQuantity <= 0) {
                        throw new \Exception("Số lượng ra máy phải lớn hơn 0");
                    }
                    if ($goodQuantity < 0) {
                        throw new \Exception("Số lượng chính phẩm không được âm");
                    }
                    if ($defectWeight < 0) {
                        throw new \Exception("Khối lượng phế phẩm không được âm");
                    }

                    // Tính số lượng lỗi từ số lượng
                    $defectQuantity = $runQuantity - $goodQuantity;

                    // Log chi tiết dữ liệu để debug
                    Log::info("Dòng {$rowNumber}: Ngày={$date}, Ca={$shift}, Máy={$machineName}, Nhân viên={$operatorName}, Tổ={$operatorTeam}, SP={$productCode}, Dài={$productLength}m, SL={$runQuantity}, Đạt={$goodQuantity}, Lỗi KL={$defectWeight}kg");

                    // Tìm máy theo tên
                    $machine = $this->findMachine($machineName);
                    if (!$machine) {
                        Log::error("Không tìm thấy máy: {$machineName}. Các máy hiện có: " . Machine::pluck('name')->implode(', '));
                        throw new \Exception("Không tìm thấy máy: {$machineName}");
                    }

                    // Tìm sản phẩm theo mã
                    $product = $this->findProduct($productCode);
                    if (!$product) {
                        Log::error("Không tìm thấy sản phẩm: {$productCode}. Các sản phẩm hiện có: " . Product::pluck('code')->implode(', '));
                        throw new \Exception("Không tìm thấy sản phẩm: {$productCode}");
                    }

                    // Nếu không có chiều dài từ file Excel, xác định chiều dài tiêu chuẩn
                    if ($productLength === null) {
                        $productLength = $this->calculateProductLength($product);
                        Log::info("Dòng {$rowNumber}: Sử dụng chiều dài tiêu chuẩn {$productLength}m cho sản phẩm {$productCode}");
                    } else {
                        Log::info("Dòng {$rowNumber}: Sử dụng chiều dài từ file Excel: {$productLength}m cho sản phẩm {$productCode}");
                    }

                    // Tính khối lượng dựa trên định mức g/m và chiều dài
                    $gm = $product->gm_spec ?? 0;
                    if ($gm > 0 && $productLength > 0) {
                        $kgPerUnit = ($gm * $productLength) / 1000;
                        $runWeight = $kgPerUnit * $runQuantity;
                        $goodWeight = $kgPerUnit * $goodQuantity;
                        // Nếu phế phẩm được nhập theo kg thì giữ nguyên, không cần tính lại
                    } else {
                        $runWeight = 0;
                        $goodWeight = 0;
                        Log::warning("Dòng {$rowNumber}: Không thể tính khối lượng do thiếu định mức g/m hoặc chiều dài");
                    }

                    // Thiết lập thời gian vận hành mặc định cho ca (8 giờ = 480 phút)
                    $operatingTime = 480;
                    $downtime = 0;

                    // Tạo hoặc cập nhật ProductionEntry
                    try {
                        $entry = ProductionEntry::updateOrCreate(
                            [
                                'date' => $date,
                                'shift' => $shift,
                                'machine_id' => $machine->id,
                                'product_code' => $productCode,
                                'product_length' => $productLength
                            ],
                            [
                                'output_quantity' => $runQuantity,
                                'good_quantity' => $goodQuantity,
                                'defect_weight' => $defectWeight,
                                'waste_weight' => $wasteWeight,
                                'notes' => $notes,
                                'operator_name' => $operatorName,
                                'operator_team' => $operatorTeam,
                                'machine_operator' => $machineOperator,
                                'quality_checker' => $qualityInspector,
                                'warehouse_staff' => $warehouseStaff
                            ]
                        );

                        Log::info("Dòng {$rowNumber}: Import thành công ID={$entry->id}");
                        $importResults['success']++;
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error("Lỗi khi lưu dữ liệu dòng {$rowNumber}: " . $e->getMessage());
                        throw new \Exception("Lỗi khi lưu dữ liệu: " . $e->getMessage());
                    }

                } catch (\Exception $e) {
                    DB::rollBack();
                    $importResults['error']++;
                    $importResults['errors'][] = "Dòng {$rowNumber}: " . $e->getMessage();
                    Log::error("Lỗi import dòng {$rowNumber}: " . $e->getMessage());
                }
            }

            // Không cần rollback toàn cục nữa vì mỗi dòng đã có transaction riêng
            /* if ($importResults['error'] > 0) {
                // Log tất cả các lỗi trước khi rollback
                Log::error("ImportProductionEntries rollback vì có {$importResults['error']} lỗi: " . implode("; ", $importResults['errors']));
                DB::rollBack();
                return [
                    'success' => false,
                    'results' => $importResults
                ];
            }

            DB::commit(); */

            // Xem import thành công nếu có ít nhất một dòng được import
            $success = $importResults['success'] > 0;
            
            // Log kết quả
            if ($success) {
                Log::info("Import thành công {$importResults['success']}/{$importResults['total']} dòng. Có {$importResults['error']} dòng lỗi.");
            } else {
                Log::error("Import thất bại. {$importResults['error']}/{$importResults['total']} dòng lỗi.");
                if (count($importResults['errors']) > 0) {
                    Log::error("Chi tiết lỗi: " . implode("; ", $importResults['errors']));
                }
            }

            return [
                'success' => $success,
                'results' => $importResults
            ];

        } catch (\Exception $e) {
            Log::error("Lỗi import file Excel: " . $e->getMessage());
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Chuyển đổi chuỗi ngày thành định dạng Y-m-d
     */
    private function parseDate($dateString)
    {
        if (empty($dateString)) {
            return date('Y-m-d');
        }

        try {
            // Log để debug
            Log::info("Đang xử lý ngày từ Excel: " . $dateString . " (Type: " . gettype($dateString) . ")");
            
            // Xử lý trường hợp là đối tượng DateTime từ PhpSpreadsheet
            if ($dateString instanceof \DateTime) {
                $date = Carbon::instance($dateString);
                Log::info("Chuyển đổi DateTime object thành: " . $date->format('Y-m-d'));
                return $date->format('Y-m-d');
            }
            
            // Kiểm tra nếu là dạng Excel numeric date
            if (is_numeric($dateString)) {
                // Sử dụng PhpSpreadsheet để chuyển đổi chính xác
                try {
                    if (class_exists('\PhpOffice\PhpSpreadsheet\Shared\Date')) {
                        // Kiểm tra xem có phải là Excel date không
                        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($dateString)) {
                            $dateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateString);
                            $date = Carbon::instance($dateTime);
                            Log::info("Chuyển đổi Excel numeric date bằng PhpSpreadsheet ({$dateString}) thành: " . $date->format('Y-m-d'));
                            return $date->format('Y-m-d');
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Không thể sử dụng PhpSpreadsheet Date converter: " . $e->getMessage());
                }
                
                // Fallback: Sử dụng phương pháp cũ
                // Excel lưu ngày dưới dạng số từ 1900-01-01
                $baseDate = Carbon::create(1900, 1, 1);
                
                // Xử lý trường hợp Excel 1900 date system
                if ($dateString > 59) {
                    // Excel có bug với năm 1900 (coi 1900 là năm nhuận)
                    $dateString = $dateString - 1;
                }
                
                $date = $baseDate->addDays($dateString - 2);
                Log::info("Chuyển đổi Excel numeric date fallback ({$dateString}) thành: " . $date->format('Y-m-d'));
                return $date->format('Y-m-d');
            }
            
            // Chuẩn hóa định dạng ngày
            if (is_string($dateString)) {
                // Loại bỏ khoảng trắng thừa
                $dateString = trim($dateString);
                
                // Xử lý định dạng dd/mm/yyyy hoặc mm/dd/yyyy
                if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/', $dateString, $matches)) {
                    $firstNumber = (int)$matches[1];
                    $secondNumber = (int)$matches[2];
                    $year = (int)$matches[3];
                    
                    // Xử lý năm 2 chữ số
                    if ($year < 100) {
                        if ($year > 50) {
                            $year += 1900; // 51-99 -> 1951-1999
                        } else {
                            $year += 2000; // 00-50 -> 2000-2050
                        }
                    }

                    // Kiểm tra năm hợp lệ
                    $currentYear = (int)date('Y');
                    if ($year > $currentYear + 10) {
                        throw new \Exception("Năm không hợp lệ: {$year}");
                    }

                    // Thử các trường hợp khác nhau
                    $possibleDates = [];
                    
                    // Trường hợp 1: DD/MM/YYYY (phổ biến ở Việt Nam)
                    if ($firstNumber <= 31 && $secondNumber <= 12) {
                        $possibleDates[] = ['day' => $firstNumber, 'month' => $secondNumber, 'format' => 'DD/MM/YYYY'];
                    }
                    
                    // Trường hợp 2: MM/DD/YYYY (phổ biến ở Mỹ)
                    if ($firstNumber <= 12 && $secondNumber <= 31) {
                        $possibleDates[] = ['day' => $secondNumber, 'month' => $firstNumber, 'format' => 'MM/DD/YYYY'];
                    }
                    
                    // Ưu tiên DD/MM/YYYY nếu cả hai đều hợp lệ
                    // Trừ khi ngày > 12 thì chắc chắn là DD/MM
                    if (count($possibleDates) > 1) {
                        if ($firstNumber > 12) {
                            // Chắc chắn là DD/MM/YYYY
                            $possibleDates = [['day' => $firstNumber, 'month' => $secondNumber, 'format' => 'DD/MM/YYYY']];
                        } elseif ($secondNumber > 12) {
                            // Chắc chắn là MM/DD/YYYY
                            $possibleDates = [['day' => $secondNumber, 'month' => $firstNumber, 'format' => 'MM/DD/YYYY']];
                        } else {
                            // Cả hai đều có thể, ưu tiên DD/MM/YYYY cho Việt Nam
                            $possibleDates = [['day' => $firstNumber, 'month' => $secondNumber, 'format' => 'DD/MM/YYYY']];
                        }
                    }

                    // Kiểm tra và chọn ngày hợp lệ
                    foreach ($possibleDates as $dateAttempt) {
                        if (checkdate($dateAttempt['month'], $dateAttempt['day'], $year)) {
                            $formattedDate = sprintf('%04d-%02d-%02d', $year, $dateAttempt['month'], $dateAttempt['day']);
                            Log::info("Chuyển đổi ngày {$dateString} thành: {$formattedDate} (định dạng: {$dateAttempt['format']})");
                            return $formattedDate;
                        }
                    }

                    // Nếu không có trường hợp nào hợp lệ
                    throw new \Exception("Ngày tháng không hợp lệ: {$dateString}");
                }
                
                // Xử lý định dạng YYYY-MM-DD
                if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $dateString, $matches)) {
                    $year = (int)$matches[1];
                    $month = (int)$matches[2];
                    $day = (int)$matches[3];
                    
                    if (checkdate($month, $day, $year)) {
                        $formattedDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        Log::info("Chuyển đổi ngày YYYY-MM-DD {$dateString} thành: {$formattedDate}");
                        return $formattedDate;
                    }
                }
            }
            
            // Thử parse với Carbon với các định dạng khác nhau
            $formats = [
                'd/m/Y',    // 01/12/2024
                'm/d/Y',    // 12/01/2024
                'd-m-Y',    // 01-12-2024
                'm-d-Y',    // 12-01-2024
                'Y-m-d',    // 2024-12-01
                'd.m.Y',    // 01.12.2024
                'm.d.Y',    // 12.01.2024
                'd/m/y',    // 01/12/24
                'm/d/y',    // 12/01/24
            ];
            
            foreach ($formats as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $dateString);
                    if ($date && $date->year >= 1900 && $date->year <= (date('Y') + 10)) {
                        Log::info("Đã parse thành công ngày với định dạng {$format}: " . $date->format('Y-m-d'));
                        return $date->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            // Nếu không parse được theo bất kỳ định dạng nào
            throw new \Exception("Không thể chuyển đổi ngày: {$dateString}. Vui lòng nhập theo định dạng dd/mm/yyyy");
            
        } catch (\Exception $e) {
            Log::error("Lỗi parse ngày {$dateString}: " . $e->getMessage());
            throw new \Exception("Ngày không hợp lệ: {$dateString}. Vui lòng nhập theo định dạng dd/mm/yyyy");
        }
    }

    /**
     * Tính chiều dài sản phẩm dựa vào loại sản phẩm
     * Nếu không thể tính được, sẽ trả về 0 hoặc chiều dài mặc định
     */
    private function calculateProductLength($product)
    {
        if (!$product) {
            return 0;
        }

        try {
            // Trường hợp đơn giản, sử dụng chiều dài tiêu chuẩn dựa vào đường kính và loại ống
            $productName = $product->name;
            $parts = explode(' ', $productName);

            if (count($parts) < 3) {
                return 0;
            }

            $diameter = intval($parts[0]);
            $material = $parts[2];

            // Lấy chiều dài tiêu chuẩn
            if (strpos($material, 'PE') !== false) {
                if ($diameter <= 90) {
                    // Ống PE cuộn
                    switch($diameter) {
                        case 90: return 25;
                        case 75: return 25;
                        case 63: return 50;
                        case 50: return 100;
                        case 40: return 100;
                        case 32: return 200;
                        case 25: return 300;
                        case 20: return 300;
                        case 16: return 300;
                        default: return 100;
                    }
                } else {
                    // Ống PE cây (DN ≥ 110mm)
                    return 6;
                }
            } else if ($material == 'PPR') {
                return 4;
            } else if (strpos($material, 'PSU') !== false) {
                return 6;
            }

            return 0;
        } catch (\Exception $e) {
            Log::error("Lỗi tính chiều dài sản phẩm: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Tạo template Excel để download
     */
    public function createImportTemplate()
    {
        // Khởi tạo đối tượng Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Thiết lập tiêu đề các cột
        $headers = [
            'A' => 'Ngày (DD/MM/YYYY)',
            'B' => 'Ca (1, 2, 3)',
            'C' => 'Tên máy',
            'D' => 'Tên công nhân vận hành',
            'E' => 'Tổ',
            'F' => 'Sản phẩm (Mã SP)',
            'G' => 'Số m (Chiều dài)',
            'H' => 'Ra máy (cây/cuộn)',
            'I' => 'Chính phẩm (cây/cuộn)',
            'J' => 'Phế phẩm (kg)',
            'K' => 'Phế liệu (kg)',
            'L' => 'CN chạy máy',
            'M' => 'CN kiểm',
            'N' => 'CN kho',
            'O' => 'Ghi chú'
        ];
        
        // Ghi tiêu đề
        foreach ($headers as $column => $header) {
            $sheet->setCellValue($column . '1', $header);
        }
        
        // Định dạng tiêu đề
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];
        
        // Áp dụng định dạng cho hàng tiêu đề
        $sheet->getStyle('A1:O1')->applyFromArray($headerStyle);
        
        // Thiết lập chiều rộng cột
        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Thêm validation cho một số cột
        // Ngày (Cột A) - Định dạng ngày tháng chuẩn
        $validationDate = $sheet->getCell('A2')->getDataValidation();
        $validationDate->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DATE);
        $validationDate->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validationDate->setAllowBlank(false);
        $validationDate->setShowInputMessage(true);
        $validationDate->setShowErrorMessage(true);
        $validationDate->setErrorTitle('Lỗi định dạng ngày');
        $validationDate->setError('Vui lòng nhập ngày hợp lệ theo định dạng DD/MM/YYYY (ví dụ: 15/12/2024)');
        $validationDate->setPromptTitle('Nhập ngày');
        $validationDate->setPrompt('Nhập theo định dạng ngày/tháng/năm (DD/MM/YYYY).\nVí dụ: 15/12/2024');
        $sheet->setDataValidation('A2:A1000', $validationDate);
        
        // Định dạng cột ngày tháng
        $sheet->getStyle('A2:A1000')->getNumberFormat()->setFormatCode('DD/MM/YYYY');
        
        // Ca (Cột B)
        $validationShift = $sheet->getCell('B2')->getDataValidation();
        $validationShift->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validationShift->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validationShift->setAllowBlank(false);
        $validationShift->setShowInputMessage(true);
        $validationShift->setShowErrorMessage(true);
        $validationShift->setErrorTitle('Lỗi nhập liệu');
        $validationShift->setError('Vui lòng chọn ca từ danh sách');
        $validationShift->setPromptTitle('Chọn ca');
        $validationShift->setPrompt('Chọn một trong các ca: 1, 2, 3');
        $validationShift->setFormula1('"1,2,3"');
        $sheet->setDataValidation('B2:B1000', $validationShift);
        
        // Thêm một số dữ liệu mẫu (tùy chọn)
        $sampleData = [
            [
                '15/12/2024', // Ngày theo định dạng DD/MM/YYYY
                '1',
                'Máy đùn 1',
                'Nguyễn Văn A',
                'Tổ 1',
                'Ø32 PN16 PE100',
                '200',
                '150',
                '148',
                '3.5',
                '2.1',
                'Nguyễn Văn A',
                'Trần Thị B',
                'Lê Văn C',
                'Sản xuất bình thường'
            ]
        ];
        
        $row = 2;
        foreach ($sampleData as $data) {
            $col = 0;
            foreach ($data as $value) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $value);
                $col++;
            }
            $row++;
        }
        
        // Định dạng ngày tháng cho dữ liệu mẫu
        $sheet->getStyle('A2')->getNumberFormat()->setFormatCode('DD/MM/YYYY');
        
        // Thêm dòng chú thích
        $row = count($sampleData) + 4;
        $sheet->setCellValue('A' . $row, '📋 HƯỚNG DẪN SỬ DỤNG:');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFE6CC');
        $row++;
        
        $instructions = [
            '1. 📅 Cột "Ngày": Nhập theo định dạng DD/MM/YYYY (ví dụ: 15/12/2024)',
            '2. 🕐 Cột "Ca": Chỉ nhập số 1, 2 hoặc 3 (sẽ tự động chuyển thành CA1, CA2, CA3)',
            '3. 🏭 Cột "Tên máy": Phải khớp với tên máy trong hệ thống',
            '4. 📦 Cột "Sản phẩm": Nhập mã sản phẩm chính xác',
            '5. 📏 Cột "Số m": Để trống sẽ dùng chiều dài tiêu chuẩn',
            '6. 🔢 Các cột số liệu: Ra máy ≥ Chính phẩm ≥ 0',
            '7. ⚠️ Các trường bắt buộc: Ngày, Ca, Tên máy, Sản phẩm, Ra máy, Chính phẩm',
            '',
            '🎯 LƯU Ý QUAN TRỌNG:',
            '• Không thay đổi định dạng cột ngày tháng',
            '• Định dạng ngày phải là DD/MM/YYYY (ngày/tháng/năm)',
            '• Trên Windows: Đảm bảo Excel hiển thị ngày đúng định dạng',
            '• Trên macOS: Kiểm tra Regional Settings nếu có lỗi',
            '• Nếu import bị lỗi, kiểm tra log để xem chi tiết'
        ];
        
        foreach ($instructions as $instruction) {
            if (empty($instruction)) {
                $row++;
                continue;
            }
            $sheet->setCellValue('A' . $row, $instruction);
            if (strpos($instruction, '🎯') !== false) {
                $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0000'));
            }
            $row++;
        }
        
        // Merge cells cho hướng dẫn
        for ($i = count($sampleData) + 4; $i < $row; $i++) {
            $sheet->mergeCells("A{$i}:O{$i}");
        }
        
        // Thiết lập vùng in
        $sheet->getPageSetup()->setPrintArea('A1:O' . $row);
        
        return $spreadsheet;
    }

    /**
     * Tìm máy dựa trên tên từ file Excel
     * Tìm kiếm linh hoạt theo nhiều phương pháp
     */
    private function findMachine($machineName)
    {
        // Log tên máy gốc để debug
        Log::info("Tìm máy với tên: '{$machineName}'");
        
        // Kiểm tra nếu đã có máy khớp
        $machines = Machine::all();
        if ($machines->isEmpty()) {
            Log::warning("Không tìm thấy máy nào trong hệ thống");
            return null;
        }
        
        // Tìm chính xác
        $machine = Machine::where('name', $machineName)->first();
        if ($machine) {
            Log::info("Tìm thấy máy '{$machine->name}' theo tên chính xác");
            return $machine;
        }
        
        // Tìm dựa vào ký tự đầu (trường hợp chỉ nhập A, B, C)
        if (strlen(trim($machineName)) == 1) {
            $singleChar = trim($machineName);
            Log::info("Đang tìm máy theo ký tự đơn '{$singleChar}'");
            
            // Tìm các máy có thể có tên tương tự
            $possibleMachines = [
                "Máy {$singleChar}",
                "Máy đùn {$singleChar}",
                "Máy sản xuất {$singleChar}",
                "{$singleChar}",
                "Extruder {$singleChar}",
                "Line {$singleChar}"
            ];
            
            Log::info("Thử các biến thể tên máy: " . implode(", ", $possibleMachines));
            
            // Thử từng tên máy có thể có
            foreach ($possibleMachines as $possibleName) {
                $machine = Machine::where('name', $possibleName)->first();
                if ($machine) {
                    Log::info("Tìm thấy máy '{$machine->name}' dựa vào tên có thể '{$possibleName}'");
                    return $machine;
                }
            }
            
            // Tìm theo LIKE
            $machine = Machine::where('name', 'LIKE', "Máy {$singleChar}%")
                    ->orWhere('name', 'LIKE', "Máy đùn {$singleChar}%")
                    ->orWhere('name', 'LIKE', "{$singleChar}%")
                    ->orWhere('name', 'LIKE', "% {$singleChar} %")
                    ->orWhere('name', 'LIKE', "% {$singleChar}")
                    ->first();
            
            if ($machine) {
                Log::info("Tìm thấy máy '{$machine->name}' dựa vào ký tự đầu '{$singleChar}' bằng LIKE");
                return $machine;
            }
            
            // Nếu chỉ có một máy trong hệ thống, hãy sử dụng máy đó
            if ($machines->count() == 1) {
                $machine = $machines->first();
                Log::warning("Sử dụng máy duy nhất '{$machine->name}' vì không tìm thấy máy '{$machineName}'");
                return $machine;
            }
        }
        
        // Tìm theo wildcard
        $machine = Machine::where('name', 'LIKE', "%{$machineName}%")->first();
        if ($machine) {
            Log::info("Tìm thấy máy '{$machine->name}' dựa vào wildcard '%{$machineName}%'");
            return $machine;
        }
        
        // Liệt kê tất cả máy hiện có
        $machineNames = $machines->pluck('name')->implode(', ');
        Log::warning("Không tìm thấy máy '{$machineName}'. Các máy hiện có: {$machineNames}");
        
        return null;
    }
    
    /**
     * Tìm sản phẩm dựa trên mã từ file Excel
     * Tìm kiếm linh hoạt theo nhiều phương pháp
     */
    private function findProduct($productCode)
    {
        // Log mã sản phẩm gốc để debug
        Log::info("Tìm sản phẩm với mã: '{$productCode}'");
        
        // Tìm chính xác
        $product = Product::where('code', $productCode)->first();
        if ($product) {
            Log::info("Tìm thấy sản phẩm '{$product->name}' với mã '{$productCode}' theo mã chính xác");
            return $product;
        }
        
        // Tìm theo wildcard
        $product = Product::where('code', 'LIKE', "%{$productCode}%")->first();
        if ($product) {
            Log::info("Tìm thấy sản phẩm '{$product->name}' với mã '{$product->code}' dựa vào wildcard '%{$productCode}%'");
            return $product;
        }
        
        // Xử lý đặc biệt cho PE908, PE9010, v.v.
        if (preg_match('/^P(?:E)?(\d+)$/', $productCode, $matches)) {
            $numericPart = $matches[1];
            
            // Thử các biến thể
            $variants = [
                "P{$numericPart}",
                "PE{$numericPart}",
                "P9{$numericPart}",
                "PE9{$numericPart}"
            ];
            
            Log::info("Thử các biến thể mã sản phẩm: " . implode(", ", $variants));
            
            foreach ($variants as $variant) {
                $product = Product::where('code', $variant)->first();
                if ($product) {
                    Log::info("Tìm thấy sản phẩm '{$product->name}' với mã '{$product->code}' qua biến thể '{$variant}'");
                    return $product;
                }
            }
        }
        
        // Kiểm tra trường hợp đặc biệt cho PE, PPR, v.v.
        if (strpos($productCode, 'PE') === 0 || strpos($productCode, 'P') === 0) {
            // Mã có thể bị thiếu tiền tố
            $variations = ['P', 'PE', 'PPR', 'PSU'];
            foreach ($variations as $prefix) {
                $testCode = str_replace(['P', 'PE'], $prefix, $productCode);
                $product = Product::where('code', $testCode)->first();
                if ($product) {
                    Log::info("Tìm thấy sản phẩm '{$product->name}' với mã '{$product->code}' bằng cách thay thế tiền tố");
                    return $product;
                }
            }
        }
        
        Log::warning("Không tìm thấy sản phẩm nào với mã '{$productCode}' sau khi thử tất cả các phương pháp");
        return null;
    }
} 