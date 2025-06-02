<?php

namespace App\Services;

use App\Models\PlcData;
use App\Models\ProductionEntry;
use App\Models\Product;
use App\Models\Machine;
use App\Models\PeCoilStandard;
use Carbon\Carbon;

class OEECalculationService
{
    /**
     * Tính OEE theo ca
     * Tính gộp cho tất cả sản phẩm trong ca thay vì tính riêng từng sản phẩm
     */
    public function calculateShiftOEE($machineId, $date, $shift, $entries = null)
    {
        // Lấy dữ liệu từ PLC - thử nhiều định dạng ca
        $possibleShiftFormats = [
            'CA ' . $shift,  // Format "CA 1", "CA 2", "CA 3"
            'CA' . $shift,   // Format "CA1", "CA2", "CA3"
            $shift           // Format "1", "2", "3"
        ];
        
        $plcData = PlcData::where('machine_id', $machineId)
            ->whereDate('datalog_date', $date)
            ->where(function($query) use ($possibleShiftFormats) {
                foreach($possibleShiftFormats as $format) {
                    $query->orWhere('datalog_data_ca', $format);
                }
            })
            ->orderBy('id', 'desc')
            ->get();
        
        \Log::info("calculateShiftOEE [$machineId, $date, $shift]: PLC data count: " . $plcData->count() . ", Trying formats: " . implode(', ', $possibleShiftFormats));
        
        if ($plcData->isEmpty()) {
            \Log::info("calculateShiftOEE [$machineId, $date, $shift]: No PLC data found");
            return [
                'oee' => 0,
                'availability' => 0,
                'performance' => 0,
                'quality' => 0,
                'details' => [
                    'run_time_minutes' => 0,
                    'actual_productivity' => 0,
                    'target_productivity' => 0,
                    'good_products' => 0,
                    'good_products_kg' => 0,
                    'defect_products_kg' => 0,
                    'total_products_kg' => 0
                ]
            ];
        }

        // A: Availability (Thời gian chạy máy / Thời gian ca)
        // - Lấy thời gian chạy máy từ datalog_data_gio_chay_2
        // - Xử lý trường hợp giờ chạy bị reset trong ca và trừ đi giờ chạy từ ca trước
        $runTimeMinutes = 0;
        
        // Sắp xếp theo ID tăng dần (theo thời gian)
        $plcDataOrdered = $plcData->sortBy('id');
        
        // Lấy bản ghi đầu tiên và cuối cùng của ca hiện tại
        $firstRecord = $plcDataOrdered->first();
        $lastRecord = $plcDataOrdered->last();
        
        if ($firstRecord && $lastRecord) {
            // Lấy giờ chạy đầu ca
            $startRunTime = $firstRecord->datalog_data_gio_chay_2 ?? 0;
            \Log::info("calculateShiftOEE [$machineId, $date, $shift]: Start run time: $startRunTime");
            
            // Theo dõi các lần reset trong ca
            $resets = 0;
            $accumulatedRunTime = 0;
            $previousRunTime = null;
            
            foreach ($plcDataOrdered as $record) {
                $currentRunTime = $record->datalog_data_gio_chay_2 ?? 0;
                
                // Nếu đã có giá trị trước đó và giá trị hiện tại nhỏ hơn giá trị trước đó
                // tức là đã có reset
                if ($previousRunTime !== null && $currentRunTime < $previousRunTime) {
                    \Log::info("calculateShiftOEE [$machineId, $date, $shift]: Detected reset in datalog_data_gio_chay_2: Previous=$previousRunTime, Current=$currentRunTime");
                    $accumulatedRunTime += $previousRunTime; // Cộng dồn thời gian trước khi reset
                    $resets++;
                }
                $previousRunTime = $currentRunTime;
            }
            
            // Lấy giờ chạy cuối ca
            $endRunTime = $lastRecord->datalog_data_gio_chay_2 ?? 0;
            \Log::info("calculateShiftOEE [$machineId, $date, $shift]: End run time: $endRunTime");
            
            // Tính tổng giờ chạy
            if ($resets > 0) {
                // Nếu có reset, cộng dồn các lần reset và giờ chạy cuối cùng
                $runTimeMinutes = $accumulatedRunTime + $endRunTime;
                \Log::info("calculateShiftOEE [$machineId, $date, $shift]: Total with resets: $accumulatedRunTime + $endRunTime = $runTimeMinutes");
            } else {
                // Nếu không có reset, lấy hiệu của giờ chạy cuối và đầu ca
                $runTimeMinutes = $endRunTime - $startRunTime;
                \Log::info("calculateShiftOEE [$machineId, $date, $shift]: No resets, time difference: $endRunTime - $startRunTime = $runTimeMinutes");
            }
            
            // Đảm bảo giờ chạy không âm
            $runTimeMinutes = max(0, $runTimeMinutes);
        }
        
        \Log::info("calculateShiftOEE [$machineId, $date, $shift]: Final run time minutes: $runTimeMinutes");
        
        // - Thời gian ca mặc định là 8h = 480 phút
        $shiftTimeMinutes = 8 * 60;
        $availability = $runTimeMinutes > 0 ? min(1, $runTimeMinutes / $shiftTimeMinutes) : 0;

        // P: Performance (Năng suất thực tế / Năng suất định mức)
        // - Năng suất thực tế từ nang_suatkg_h
        $machine = Machine::find($machineId);
        
        // Lấy max_speed từ machine
        $targetProductivity = $machine ? $machine->max_speed : 0;
        
        \Log::info("calculateShiftOEE [$machineId, $date, $shift]: Target productivity (max_speed): $targetProductivity");

        if ($targetProductivity <= 0) {
            \Log::warning("calculateShiftOEE [$machineId, $date, $shift]: Target productivity is zero or negative");
            $performance = 0;
        } else {
            // Lấy năng suất thực tế trung bình từ các bản ghi PLC có tốc độ > 50
            $totalProductivity = 0;
            $recordsWithProductivity = 0;
            
            foreach ($plcData as $record) {
                // Chỉ tính năng suất khi tốc độ > 50
                if (isset($record->toc_do_thuc_te_vx) && $record->toc_do_thuc_te_vx > 50 && 
                    isset($record->nang_suatkg_h) && $record->nang_suatkg_h > 0) {
                    $totalProductivity += $record->nang_suatkg_h;
                    $recordsWithProductivity++;
                }
            }
            
            // Tính năng suất trung bình của các bản ghi có tốc độ > 50
            $actualProductivity = $recordsWithProductivity > 0 ? $totalProductivity / $recordsWithProductivity : 0;
            
            \Log::info("calculateShiftOEE [$machineId, $date, $shift]: Average productivity: $actualProductivity kg/h from $recordsWithProductivity records with speed > 50");
            
            // Tính Performance - bỏ giới hạn 100%
            $performance = $actualProductivity > 0 ? $actualProductivity / $targetProductivity : 0;
            
            \Log::info("calculateShiftOEE [$machineId, $date, $shift]: Performance: $performance");
        }

        // Q: Quality (Chính phẩm / (Chính phẩm + Phế phẩm))
        // - Lấy từ bảng production_entries
        $quality = 0;
        $goodProductsKg = 0;
        $defectProductsKg = 0;
        $totalProductsKg = 0;
        
        if ($entries && (is_array($entries) || is_object($entries)) && (is_array($entries) ? count($entries) > 0 : $entries->count() > 0)) {
            // Tính tổng cho tất cả entries của ca này
            $totalGoodQuantity = 0;
            $totalDefectWeight = 0;
            
            foreach ($entries as $entry) {
                $totalGoodQuantity += $entry->output_quantity ?? 0;
                $totalDefectWeight += $entry->defect_weight ?? 0;
            }
            
            \Log::info("calculateShiftOEE [$machineId, $date, $shift]: Entries data - Good: $totalGoodQuantity, Defect: $totalDefectWeight");
            
            // Thay đổi ở đây: Tổng sản phẩm bằng chính phẩm (không cộng phế phẩm)
            $totalQuantity = $totalGoodQuantity;
            // Tính tỷ lệ chính phẩm trên tổng ra máy (nếu có cả phế phẩm)
            $totalOutput = $totalGoodQuantity + $totalDefectWeight;
            $quality = $totalOutput > 0 ? min(1, $totalGoodQuantity / $totalOutput) : 0;

            $goodProductsKg = $totalGoodQuantity;
            $defectProductsKg = $totalDefectWeight;
            $totalProductsKg = $totalGoodQuantity; // Thay đổi: Tổng sản phẩm = Chính phẩm
        } else {
            \Log::warning("calculateShiftOEE [$machineId, $date, $shift]: No production entry data found");
            // Nếu không có production entries, vẫn tính OEE với Quality = 0
        }

        // Tính OEE = A * P * Q
        $oee = $availability * $performance * $quality;
        \Log::info("calculateShiftOEE [$machineId, $date, $shift]: A=$availability, P=$performance, Q=$quality, OEE=$oee");

        // Trả về kết quả
        return [
            'oee' => $oee,
            'availability' => $availability,
            'performance' => $performance,
            'quality' => $quality,
            'details' => [
                'run_time_minutes' => $runTimeMinutes,
                'actual_productivity' => $actualProductivity ?? 0,
                'target_productivity' => $targetProductivity,
                'good_products' => $goodProductsKg,
                'good_products_kg' => $goodProductsKg,
                'defect_products_kg' => $defectProductsKg,
                'total_products_kg' => $totalProductsKg
            ]
        ];
    }

    /**
     * Tính OEE theo ngày (chỉ chia theo sản phẩm)
     */
    public function calculateDailyOEE($machineId, $date)
    {
        \Log::info("calculateDailyOEE [$machineId, $date]: Start calculation");
        
        // Lấy dữ liệu của 3 ca
        $shifts = ['CA1', 'CA2', 'CA3']; // Sửa định dạng shift phù hợp với production_entries
        $shiftResults = [];

        // Dữ liệu tổng hợp
        $totalRunTimeMinutes = 0;
        $totalGoodProductsKg = 0;
        $totalDefectProductsKg = 0;
        $validShifts = 0;

        // Lấy entry từ production_entries
        $entries = ProductionEntry::where('machine_id', $machineId)
            ->whereDate('date', $date)
            ->get();
            
        \Log::info("calculateDailyOEE [$machineId, $date]: Found " . $entries->count() . " production entries");

        // Tính OEE cho từng ca và tổng hợp số liệu
        foreach($shifts as $shift) {
            // Lấy tất cả entries cho ca hiện tại
            $shiftEntries = $entries->where('shift', $shift);
            
            \Log::info("calculateDailyOEE [$machineId, $date, $shift]: " . 
                    ($shiftEntries->count() > 0 ? "Found " . $shiftEntries->count() . " entries" : "No entries found"));
            
            // Lấy số ca từ $shift (ví dụ: CA1 -> 1)
            $shiftNumber = substr($shift, 2);
            
            $shiftData = $this->calculateShiftOEE($machineId, $date, $shiftNumber, $shiftEntries);
            $shiftResults[$shift] = $shiftData;

            // Cộng dồn thời gian chạy và sản phẩm
            if ($shiftData['oee'] > 0) {
                $validShifts++;
                $totalRunTimeMinutes += $shiftData['details']['run_time_minutes'];
                $totalGoodProductsKg += $shiftData['details']['good_products_kg'];
                $totalDefectProductsKg += $shiftData['details']['defect_products_kg'];
                
                \Log::info("calculateDailyOEE [$machineId, $date, $shift]: Valid shift with OEE " . $shiftData['oee']);
            } else {
                \Log::info("calculateDailyOEE [$machineId, $date, $shift]: Invalid shift with OEE 0");
            }
        }

        $totalProductsKg = $totalGoodProductsKg + $totalDefectProductsKg;
        
        \Log::info("calculateDailyOEE [$machineId, $date]: ValidShifts: $validShifts, TotalRunTime: $totalRunTimeMinutes, GoodProducts: $totalGoodProductsKg, DefectProducts: $totalDefectProductsKg");

        // Tính OEE ngày
        if ($validShifts > 0) {
            // Tính trung bình cộng đơn giản của 3 ca
            $availability = 0;
            $performance = 0;
            $quality = 0;
            $oee = 0;
            
            foreach($shifts as $shift) {
                $availability += isset($shiftResults[$shift]) ? $shiftResults[$shift]['availability'] : 0;
                $performance += isset($shiftResults[$shift]) ? $shiftResults[$shift]['performance'] : 0;
                $quality += isset($shiftResults[$shift]) ? $shiftResults[$shift]['quality'] : 0;
                $oee += isset($shiftResults[$shift]) ? $shiftResults[$shift]['oee'] : 0;
            }
            
            // Chia cho 3 (số ca)
            $availability = $availability / 3;
            $performance = $performance / 3;
            $quality = $quality / 3;
            $oee = $oee / 3;
            
            \Log::info("calculateDailyOEE [$machineId, $date]: A=$availability, P=$performance, Q=$quality, OEE=$oee");
        } else {
            $availability = 0;
            $performance = 0;
            $quality = 0;
            $oee = 0;
            \Log::warning("calculateDailyOEE [$machineId, $date]: No valid shifts found, OEE set to 0");
        }

        return [
            'shifts' => $shiftResults,
            'daily' => [
                'availability' => $availability,
                'performance' => $performance,
                'quality' => $quality,
                'oee' => $oee
            ],
            'details' => [
                'valid_shifts' => $validShifts,
                'total_run_time_minutes' => $totalRunTimeMinutes,
                'total_good_products_kg' => $totalGoodProductsKg,
                'total_defect_products_kg' => $totalDefectProductsKg,
                'total_products_kg' => $totalProductsKg
            ]
        ];
    }

    /**
     * Tính OEE theo tháng (Phương pháp 1: Lấy trung bình từ OEE ngày)
     */
    public function calculateMonthlyOEE($machineId, $year, $month)
    {
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $dailyOEEs = [];

        // Tính OEE cho từng ngày trong tháng
        for($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dailyResult = $this->calculateDailyOEE($machineId, $date->format('Y-m-d'));

            // Chỉ tính những ngày có dữ liệu
            if ($dailyResult['daily']['oee'] > 0) {
                $dailyOEEs[$date->format('Y-m-d')] = $dailyResult['daily'];
            }
        }

        // Tính trung bình OEE tháng từ các ngày
        if (count($dailyOEEs) > 0) {
            $monthlyOEE = [
                'availability' => collect($dailyOEEs)->avg('availability'),
                'performance' => collect($dailyOEEs)->avg('performance'),
                'quality' => collect($dailyOEEs)->avg('quality')
            ];

            // OEE = A × P × Q (trung bình APQ từ các ngày)
            $monthlyOEE['oee'] = $monthlyOEE['availability'] * $monthlyOEE['performance'] * $monthlyOEE['quality'];
        } else {
            $monthlyOEE = [
                'availability' => 0,
                'performance' => 0,
                'quality' => 0,
                'oee' => 0
            ];
        }

        return [
            'daily' => $dailyOEEs,
            'monthly' => $monthlyOEE
        ];
    }

     /**
     * Tính OEE theo tháng (Phương pháp không chia theo sản phẩm)
     */
    public function calculateMonthlyOEEByDesign($machineId, $year, $month, $params)
    {
        // Input từ form
        $totalMonthTime = $params['total_month_time'] ?? 0;    // Thời gian tháng (phút)
        $unplannedTime = $params['unplanned_time'] ?? 0;       // Thời gian không có kế hoạch (phút)
        $designCapacity = $params['design_capacity'] ?? 0;      // Năng suất thiết kế (kg/h)

        // Thời gian có kế hoạch = Thời gian tháng - Thời gian không có kế hoạch
        $plannedTime = $totalMonthTime - $unplannedTime;

        // Lấy thời gian chạy máy từ PLC data (tổng giờ chạy cuối của mỗi ca)
        $totalRunTime = 0;
        $shifts = ["CA1", "CA2", "CA3"];
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();

        for($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            foreach($shifts as $shift) {
                $lastRecord = PlcData::where('machine_id', $machineId)
                    ->whereDate('datalog_date', $date->format('Y-m-d'))
                    ->where('datalog_data_ca', $shift)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($lastRecord) {
                    $totalRunTime += $lastRecord->datalog_data_gio_chay_2;
                }
            }
        }

        // A = Thời gian chạy / Thời gian có kế hoạch
        $availability = $plannedTime > 0 ? min(1, $totalRunTime / $plannedTime) : 0;

        // Lấy dữ liệu sản xuất trong tháng
        $entries = ProductionEntry::where('machine_id', $machineId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $totalOutputQuantity = 0;     // Tổng ra máy (cây/cuộn)
        $totalGoodQuantity = 0;       // Tổng chính phẩm (cây/cuộn)
        $totalDefectWeight = 0;       // Tổng phế phẩm (kg)
        $totalWasteWeight = 0;        // Tổng phế liệu (kg)

        foreach($entries as $entry) {
            $totalOutputQuantity += $entry->output_quantity;
            $totalGoodQuantity += $entry->good_quantity;
            $totalDefectWeight += $entry->defect_weight;
            $totalWasteWeight += $entry->waste_weight;
        }

        // Chuyển đổi số lượng sang kg
        $totalOutputKg = 0;
        $totalGoodKg = 0;
        foreach($entries as $entry) {
            $totalOutputKg += $this->convertToKg($entry->product_code, $entry->output_quantity, $entry->product_length);
            $totalGoodKg += $this->convertToKg($entry->product_code, $entry->good_quantity, $entry->product_length);
        }

        // P = (Tổng sản phẩm ra máy / Thời gian chạy) / Năng suất thiết kế
        $totalRunTimeHours = $totalRunTime / 60; // Chuyển phút sang giờ
        $actualHourlyRate = $totalRunTimeHours > 0 ? $totalOutputKg / $totalRunTimeHours : 0;
        $performance = $designCapacity > 0 ? min(1, $actualHourlyRate / $designCapacity) : 0;

        // Q = Khối lượng chính phẩm / (Khối lượng chính phẩm + Khối lượng phế phẩm)
        // Chỉ sử dụng phế phẩm (defect_weight), không dùng phế liệu (waste_weight)
        $totalGoodAndDefectKg = $totalGoodKg + $totalDefectWeight;
        $quality = $totalGoodAndDefectKg > 0 ? min(1, $totalGoodKg / $totalGoodAndDefectKg) : 0;

        // OEE = A × P × Q
        $oee = $availability * $performance * $quality;

        return [
            'availability' => $availability,
            'performance' => $performance,
            'quality' => $quality,
            'oee' => $oee,
            'details' => [
                'total_month_time' => $totalMonthTime,
                'unplanned_time' => $unplannedTime,
                'planned_time' => $plannedTime,
                'total_run_time' => $totalRunTime,
                'total_run_time_hours' => $totalRunTimeHours,
                // Số lượng (cây/cuộn)
                'total_output_quantity' => $totalOutputQuantity,
                'total_good_quantity' => $totalGoodQuantity,
                // Khối lượng (kg)
                'total_output_kg' => $totalOutputKg,
                'total_good_kg' => $totalGoodKg,
                'total_defect_weight' => $totalDefectWeight,
                'total_waste_weight' => $totalWasteWeight,
                'total_waste' => $totalDefectWeight + $totalWasteWeight,
                // Năng suất
                'actual_hourly_rate' => $actualHourlyRate,
                'design_capacity' => $designCapacity
            ]
        ];
    }

    /**
     * Chuyển đổi từ số lượng (cây/cuộn) sang khối lượng (kg)
     * @param string $productCode Mã sản phẩm
     * @param int $quantity Số lượng (cây/cuộn)
     * @param int|null $productLength Chiều dài (m) từ form nhập liệu
     * @return float Khối lượng (kg)
     */
    public function convertToKg($productCode, $quantity, $productLength = null)
    {
        if (!$productCode || $quantity <= 0) {
            return 0;
        }

        // 1. Lấy thông tin sản phẩm từ database
        $product = Product::where('code', $productCode)->first();
        if (!$product) {
            return 0;
        }

        // 2. Lấy định mức g/m
        $weightPerMeter = $product->gm_spec; // g/m
        if (!$weightPerMeter) {
            return 0;
        }

        // 3. Nếu cung cấp chiều dài từ form, ưu tiên sử dụng chiều dài này
        if ($productLength !== null && $productLength > 0) {
            // Tính tổng số mét và chuyển sang kg
            $totalMeters = $quantity * $productLength;
            $weightInKg = round(($totalMeters * $weightPerMeter) / 1000, 2);
            return $weightInKg;
        }

        // 4. Nếu không có chiều dài từ form, dùng quy tắc cũ
        // Bóc tách tên sản phẩm để lấy thông tin
        $productName = $product->name;
        $parts = explode(' ', $productName);

        if (count($parts) < 3) {
            return 0; // Tên sản phẩm không đúng định dạng
        }

        $diameter = intval($parts[0]);            // "110 PN6 PE80" => 110
        $material = $parts[2];                    // "110 PN6 PE80" => PE80

        // Lấy chiều dài tiêu chuẩn từ bảng pe_coil_standards hoặc từ quy tắc
        $standardLength = $this->getStandardLength($diameter, $material);

        // 5. Tính tổng số mét
        $totalMeters = $quantity * $standardLength;

        // 6. Tính tổng khối lượng (kg)
        $weightInKg = round(($totalMeters * $weightPerMeter) / 1000, 2);

        return $weightInKg;
    }

    /**
     * Lấy chiều dài tiêu chuẩn từ database hoặc quy tắc
     * @param int $diameter Đường kính ống
     * @param string $material Loại vật liệu (PE80, PE100, PPR)
     * @return int Chiều dài tiêu chuẩn (m)
     */
    protected function getStandardLength($diameter, $material)
    {
        // 1. Lấy từ database nếu có
        $standard = PeCoilStandard::where('diameter', $diameter)->first();
        if ($standard && $standard->length > 0) {
            return $standard->length;
        }

        // 2. Áp dụng quy tắc nếu không có trong database

        // PPR: mặc định 4m
        if ($material == 'PPR') {
            return 4;
        }

        // PE80, PE100: dựa vào đường kính
        if ($material == 'PE80' || $material == 'PE100') {
            // PE DN ≤ 90mm: theo tiêu chuẩn cuộn
            if ($diameter <= 90) {
                $defaultLengths = [
                    16 => 300,
                    20 => 300,
                    25 => 300,
                    32 => 200,
                    40 => 100,
                    50 => 100,
                    63 => 50,
                    75 => 25,
                    90 => 25
                ];

                return $defaultLengths[$diameter] ?? 100;
            }
            // PE DN ≥ 110mm: mặc định 6m
            else {
                return 6;
            }
        }

        // PSU: mặc định 6m
        if (strpos($material, 'PSU') !== false) {
            return 6;
        }

        return 100; // Mặc định nếu không xác định được
    }

    protected function calculatePerformance($entries, $machine)
    {
        if ($entries->isEmpty() || !$machine->max_speed) {
            return 0;
        }

        // Lấy danh sách các sản phẩm trong ca
        $products = $entries->pluck('product_code')->unique();
        $totalWeightedProductivity = 0;
        $totalRunTimeHours = 0;

        foreach ($products as $productCode) {
            // Lọc entries theo sản phẩm
            $productEntries = $entries->where('product_code', $productCode);
            
            // Tính thời gian chạy cho sản phẩm này
            $productRunTimeHours = $productEntries->sum('run_time_minutes') / 60;
            if ($productRunTimeHours <= 0) continue;

            // Tính tổng khối lượng sản phẩm thực tế (kg)
            $actualOutputKg = 0;
            foreach ($productEntries as $entry) {
                $actualOutputKg += $this->convertToKg($entry->product_code, $entry->output_quantity, $entry->product_length);
            }

            // Tính năng suất thực tế cho sản phẩm này (kg/h)
            $actualHourlyRate = $actualOutputKg / $productRunTimeHours;

            // Cộng dồn (năng suất thực tế * thời gian chạy) 
            $totalWeightedProductivity += $actualHourlyRate * $productRunTimeHours;
            $totalRunTimeHours += $productRunTimeHours;
        }

        // Tính năng suất trung bình
        if ($totalRunTimeHours > 0) {
            $averageProductivity = $totalWeightedProductivity / $totalRunTimeHours;
            // So sánh với năng suất tối đa của máy - không giới hạn ở 100%
            return $averageProductivity / $machine->max_speed;
        }

        return 0;
    }

    /**
     * Tính toán chỉ số Quality
     */
    protected function calculateQuality($entries)
    {
        if ($entries->isEmpty()) {
            return 0;
        }

        $totalOutput = $entries->sum('output_quantity');
        $totalDefect = $entries->sum('defect_weight');

        if (($totalOutput + $totalDefect) <= 0) {
            return 0;
        }

        // Công thức mới: output_quantity/(output_quantity + defect_weight)
        return $totalOutput / ($totalOutput + $totalDefect);

        // Công thức cũ:
        // $totalQuantity = $entries->sum('output_quantity');
        // if ($totalQuantity <= 0) {
        //     return 0;
        // }
        // return $entries->sum('good_quantity') / $totalQuantity;
    }
}
