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
            $rows = $worksheet->toArray();
            
            Log::info('Đã đọc file Excel thành công. Số dòng: ' . count($rows));

            // Log dữ liệu gốc để debug
            foreach ($rows as $index => $row) {
                Log::info("Dữ liệu gốc dòng " . ($index + 1) . ": " . json_encode($row));
            }

            // Bỏ qua dòng tiêu đề và các dòng rỗng
            $dataRows = array_filter($rows, function($row, $index) {
                // Log để debug
                Log::info("Đang xét dòng " . ($index + 1) . ": " . json_encode($row));
                
                // Bỏ qua dòng tiêu đề (index 0)
                if ($index === 0) {
                    Log::info("Bỏ qua dòng tiêu đề " . ($index + 1));
                    return false;
                }
                
                // Kiểm tra nếu dòng có ít nhất một ô không trống
                $hasData = !empty(array_filter($row, function($cell) {
                    return trim((string)$cell) !== '';
                }));
                
                if (!$hasData) {
                    Log::info("Bỏ qua dòng trống " . ($index + 1));
                }
                
                return $hasData;
            }, ARRAY_FILTER_USE_BOTH);
            
            // Reset array keys để đảm bảo index liên tục
            $dataRows = array_values($dataRows);
            
            // Log dữ liệu sau khi lọc
            foreach ($dataRows as $index => $row) {
                Log::info("Dữ liệu sau lọc dòng " . ($index + 1) . ": " . json_encode($row));
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
                    $entry = ProductionEntry::updateOrCreate(
                        [
                            'date' => $date,
                            'shift' => $shift,
                            'machine_id' => $machine->id,
                            'product_code' => $productCode
                        ],
                        [
                            'output_quantity' => $runQuantity,
                            'good_quantity' => $goodQuantity,
                            'defect_weight' => $defectWeight,
                            'waste_weight' => $wasteWeight,
                            'product_length' => $productLength,
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
                    
                    // Commit transaction cho dòng này
                    DB::commit();

                } catch (\Exception $e) {
                    // Rollback transaction cho dòng này
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
            // Kiểm tra nếu là dạng Excel numeric date
            if (is_numeric($dateString)) {
                $excelBaseDate = Carbon::createFromDate(1900, 1, 1);
                return $excelBaseDate->addDays($dateString - 2)->format('Y-m-d');
            }
            
            // Xử lý định dạng dd/MM/yyyy
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateString)) {
                $parts = explode('/', $dateString);
                if (count($parts) === 3) {
                    $day = (int)$parts[0];
                    $month = (int)$parts[1];
                    $year = (int)$parts[2];
                    return sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
            
            // Xử lý ngày tháng với các định dạng khác
            $date = Carbon::parse($dateString);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning("Không thể parse ngày: {$dateString}, lỗi: {$e->getMessage()}, sử dụng ngày hiện tại");
            
            // Thử xử lý bằng cách split và reconstructing date string
            try {
                $parts = preg_split('/[\/\-\.]/', $dateString);
                if (count($parts) === 3) {
                    // Giả định định dạng ngày/tháng/năm
                    $day = (int)$parts[0];
                    $month = (int)$parts[1];
                    $year = (int)$parts[2];
                    
                    // Đảm bảo năm có 4 chữ số
                    if ($year < 100) {
                        $year += 2000;
                    }
                    
                    // Xác thực giá trị ngày tháng hợp lệ
                    if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                        return sprintf('%04d-%02d-%02d', $year, $month, $day);
                    }
                }
            } catch (\Exception $e2) {
                Log::error("Lỗi xử lý thủ công ngày tháng: " . $e2->getMessage());
            }
            
            return date('Y-m-d');
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
        // Ngày (Cột A)
        $validationDate = $sheet->getCell('A2')->getDataValidation();
        $validationDate->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DATE);
        $validationDate->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validationDate->setAllowBlank(false);
        $validationDate->setShowInputMessage(true);
        $validationDate->setShowErrorMessage(true);
        $validationDate->setErrorTitle('Lỗi định dạng');
        $validationDate->setError('Vui lòng nhập ngày hợp lệ (DD/MM/YYYY)');
        $validationDate->setPromptTitle('Nhập ngày');
        $validationDate->setPrompt('Nhập theo định dạng ngày/tháng/năm (DD/MM/YYYY)');
        $sheet->setDataValidation('A2:A1000', $validationDate);
        
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
                date('d/m/Y'), // Ngày hôm nay
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
        
        // Thêm dòng chú thích
        $row = count($sampleData) + 3;
        $sheet->setCellValue('A' . $row, 'Chú ý:');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue('A' . $row, '1. Điền đầy đủ thông tin vào các cột theo đúng định dạng');
        $row++;
        $sheet->setCellValue('A' . $row, '2. Các trường bắt buộc: Ngày, Ca, Tên máy, Sản phẩm, Ra máy, Chính phẩm, Phế phẩm');
        $row++;
        $sheet->setCellValue('A' . $row, '3. Trường Ca có thể nhập số 1, 2 hoặc 3 và sẽ được tự động chuyển thành CA1, CA2, CA3');
        $row++;
        $sheet->setCellValue('A' . $row, '4. Nếu không điền "Số m", hệ thống sẽ sử dụng chiều dài tiêu chuẩn theo loại sản phẩm');
        $row++;
        $sheet->setCellValue('A' . $row, '5. Ra máy = Chính phẩm + Số lượng lỗi');
        
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