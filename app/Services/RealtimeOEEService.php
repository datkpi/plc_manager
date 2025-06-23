<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\ProductionEntry;
use App\Models\PlcData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RealtimeOEEService
{
    /**
     * Lấy dữ liệu OEE realtime cho một máy
     */


    public function getRealtimeOEE($machineId)
    {
        // dd('a');
        $now = Carbon::now();
        $currentShift = $this->getCurrentShift($now);
        $shiftStartTime = $this->getShiftStartTime($now, $currentShift);
        
        // Lấy thông tin máy
        $machine = Machine::find($machineId);
        if (!$machine) {
            \Log::warning("Machine with ID $machineId not found");
            // Trả về giá trị 0 nếu không tìm thấy máy
            return [
                'oee' => 0,
                'availability' => 0,
                'performance' => 0, 
                'quality' => 0,
                'current_shift' => $currentShift,
                'shift_start_time' => $shiftStartTime,
                'current_time' => $now,
                'running_time' => 0,
                'current_speed' => 0,
                'output_quantity' => 0,
                'good_quantity' => 0,
                'nang_suatkg_h' => 0,
                'plan_quantity' => 0,
                'actual_quantity' => 0,
                'estimated_completion_time' => null
            ];
        }

        // Lấy dữ liệu PLC mới nhất
        $latestPlcData = PlcData::where('machine_id', $machineId)
            ->whereDate('created_at', $now->format('Y-m-d'))
            ->where('datalog_data_ca', 'like', "%{$currentShift}%")
            ->orderBy('id', 'desc')
            ->first();
            
        // Nếu không có dữ liệu PLC, thử lấy dữ liệu không cần lọc ca
        if (!$latestPlcData) {
            \Log::info("No PLC data found for machine $machineId in shift $currentShift, trying without shift filter");
            $latestPlcData = PlcData::where('machine_id', $machineId)
                ->whereDate('created_at', $now->format('Y-m-d'))
                ->orderBy('id', 'desc')
                ->first();
        }
            
        // Lấy năng suất và tốc độ từ PLC data
        $currentSpeed = $latestPlcData->toc_do_thuc_te_vx ?? 0;
        $nangSuatKgH = 0;
        
        // Chỉ lấy năng suất khi tốc độ > 50
        if ($currentSpeed > 50 && isset($latestPlcData->nang_suatkg_h)) {
            $nangSuatKgH = $latestPlcData->nang_suatkg_h;
        } else {
            // Nếu tốc độ hiện tại <= 50, thử tìm bản ghi gần nhất có tốc độ > 50
            $highSpeedRecord = PlcData::where('machine_id', $machineId)
                ->whereDate('created_at', $now->format('Y-m-d'))
                ->where('toc_do_thuc_te_vx', '>', 50)
                ->orderBy('id', 'desc')
                ->first();
                
            if ($highSpeedRecord) {
                $nangSuatKgH = $highSpeedRecord->nang_suatkg_h ?? 0;
                \Log::info("Found high speed record with productivity: $nangSuatKgH");
            }
        }
        
        \Log::info("PLC data for machine $machineId: nang_suatkg_h=$nangSuatKgH, current_speed=$currentSpeed");
            
        // Lấy dữ liệu sản xuất của ca hiện tại
        $currentEntries = ProductionEntry::where('machine_id', $machineId)
            ->whereDate('date', $now->format('Y-m-d'))
            ->where('shift', $currentShift)
            ->get();

        if ($currentEntries->isEmpty()) {
            \Log::info("No production entries found for machine $machineId in shift $currentShift");
        }

        // Tổng hợp số liệu từ tất cả các bản ghi
        $totalOutputQuantity = 0;
        $totalGoodQuantity = 0;
        foreach ($currentEntries as $entry) {
            $totalOutputQuantity += $entry->output_quantity;
            $totalGoodQuantity += $entry->good_quantity;
        }

        // Gán mức năng suất từ plc_data vào entry nếu có entry nhưng chưa có năng suất
        if ($currentEntries && (!isset($currentEntries[0]->nang_suatkg_h) || $currentEntries[0]->nang_suatkg_h == 0) && $nangSuatKgH > 0) {
            // $currentEntries[0]->nang_suatkg_h = $nangSuatKgH;
            foreach ($currentEntries as $entry) {
                $entry->nang_suatkg_h = $nangSuatKgH;
                $entry->save(); // Lưu lại để lần sau không cần tính lại
                \Log::info("Saved nang_suatkg_h=$nangSuatKgH to production entry");
            }
        }

        // Lấy mã sản phẩm hiện tại từ PLC
        $currentProduct = $latestPlcData ? $latestPlcData->datalog_data_ma_sp : null;
        $currentProductivity = $latestPlcData ? $latestPlcData->nang_suatkg_h : 0;
        
        \Log::info("Latest PLC data found: " . ($latestPlcData ? 'Yes' : 'No') . 
                  ", Product: " . ($currentProduct ?? 'N/A') . 
                  ", Productivity: " . $currentProductivity);

        // Lấy kế hoạch sản xuất từ KHSXM (tổng số mét cần sản xuất)
        $planQuantity = $latestPlcData->khsxm ?? 0;
        \Log::info("Kế hoạch sản xuất (mét): " . $planQuantity);

        // Lấy dữ liệu PLC mới nhất
        $plcRecords = PlcData::where('machine_id', $machineId)
            ->whereDate('created_at', $now->format('Y-m-d'))
            ->orderBy('created_at', 'desc')
            ->get(['created_at', 'toc_do_thuc_te_vx', 'toc_do_thuc_te_dan_keo_m_p', 'datalog_data_ma_sp']);

        // Tính actual_quantity từ tốc độ thực tế dàn kéo
        $actualQuantity = 0;
        $startRunTime = null;
        $lastProductCode = null;

        foreach ($plcRecords as $record) {
            $recordProductCode = $record->datalog_data_ma_sp;
            
            // Nếu gặp mã sản phẩm khác, dừng vòng lặp
            if ($recordProductCode !== $currentProduct && $lastProductCode === $currentProduct) {
                break;
            }

            // Chỉ tính khi là sản phẩm hiện tại và tốc độ > 50
            if ($recordProductCode === $currentProduct && $record->toc_do_thuc_te_vx > 50) {
                $currentSpeed = $record->toc_do_thuc_te_dan_keo_m_p;
                $currentTime = Carbon::parse($record->created_at);

                if ($startRunTime === null) {
                    $startRunTime = $currentTime;
                    $periodStartSpeed = $currentSpeed;
                }

                // Kết thúc một đoạn chạy khi:
                // 1. Tốc độ <= 50 ở bản ghi tiếp theo
                // 2. Hoặc là bản ghi cuối cùng của sản phẩm này
                $nextRecord = $plcRecords->where('created_at', '<', $record->created_at)->first();
                if (!$nextRecord || 
                    $nextRecord->toc_do_thuc_te_vx <= 50 || 
                    $nextRecord->datalog_data_ma_sp !== $currentProduct) {
                    
                    if ($startRunTime) {
                        $periodRunTime = $startRunTime->diffInSeconds($currentTime);
                        if ($periodRunTime > 0) {
                            // Tốc độ trung bình của đoạn (m/p)
                            $avgSpeed = ($periodStartSpeed + $currentSpeed) / 2;
                            // Số mét = tốc độ (m/p) * thời gian (giây) / 60
                            $meters = $avgSpeed * ($periodRunTime / 60);
                            $actualQuantity += $meters;

                            \Log::info("Đoạn chạy từ " . $currentTime->format('Y-m-d H:i:s') . 
                                     " đến " . $startRunTime->format('Y-m-d H:i:s') . 
                                     ", thời gian: " . $periodRunTime . " giây" .
                                     ", tốc độ TB: " . $avgSpeed . " m/p" .
                                     ", sản lượng: " . $meters . " mét");
                        }
                        $startRunTime = null;
                    }
                }
            }
            
            $lastProductCode = $recordProductCode;
        }

        \Log::info("Sản lượng thực tế từ tốc độ dàn kéo: " . $actualQuantity . " mét");

        // Tính Availability
        $runningTime = 0;
        $availability = $this->calculateAvailability($machineId, $currentShift);

        // Tính Performance
        $performance = $this->calculatePerformance($machineId, $currentShift);

        // Tính Quality
        $quality = $this->calculateQuality($currentEntries);

        // Tính OEE = A * P * Q
        // Chuyển đổi các giá trị về phần trăm trước khi nhân
        $availabilityPercent = $availability * 100;
        $performancePercent = $performance * 100;
        $qualityPercent = $quality * 100;
        $oee = ($availabilityPercent * $performancePercent * $qualityPercent) / 10000;
        
        \Log::info("OEE calculation for machine $machineId: A=$availabilityPercent%, P=$performancePercent%, Q=$qualityPercent%, OEE=$oee%");

        // Tính thời gian dự kiến hoàn thành
        $estimatedCompletionTime = $this->calculateEstimatedCompletionTime($planQuantity, $actualQuantity, $currentProductivity, $now, $machineId);

        // Xác định ca hiện tại và ngày hiện tại
        $now = Carbon::now();
        $currentShift = $this->getCurrentShift($now); // Hàm này trả về CA1/CA2/CA3
        $currentDate = $now->format('Y-m-d');

        // Xác định ca trước đó
        if ($currentShift == 'CA1') {
            $prevShift = 'CA3';
            $prevDate = Carbon::parse($currentDate)->subDay()->format('Y-m-d');
        } elseif ($currentShift == 'CA2') {
            $prevShift = 'CA1';
            $prevDate = $currentDate;
        } else { // CA3
            $prevShift = 'CA2';
            $prevDate = $currentDate;
        }

        // Lấy production_entries của ca trước đó
        $entries = \App\Models\ProductionEntry::where('machine_id', $machineId)
            ->where('date', $prevDate)
            ->where('shift', $prevShift)
            ->get();

        $totalGoodKg = 0;
        $totalDefectKg = 0;
        foreach ($entries as $entry) {
            $product = $entry->product;
            if ($product && $product->gm_spec > 0 && $entry->product_length > 0) {
                $totalGoodKg += ($product->gm_spec * $entry->product_length * $entry->good_quantity) / 1000;
            }
            $totalDefectKg += $entry->defect_weight;
        }
        $quality = ($totalGoodKg + $totalDefectKg) > 0 ? $totalGoodKg / ($totalGoodKg + $totalDefectKg) : 0;

        return [
            'oee' => $oee,
            'availability' => $availability,
            'performance' => $performance,
            'quality' => $quality,
            'current_shift' => $currentShift,
            'shift_start_time' => $shiftStartTime,
            'current_time' => $now,
            'running_time' => $runningTime,
            'current_speed' => $currentSpeed,
            'output_quantity' => $totalOutputQuantity,
            'good_quantity' => $totalGoodQuantity,
            'nang_suatkg_h' => $nangSuatKgH,
            'plan_quantity' => $planQuantity,
            'actual_quantity' => $actualQuantity,
            'estimated_completion_time' => $estimatedCompletionTime
        ];
    }

    /**
     * Tính toán availability dựa trên thời gian đã trôi qua của ca hiện tại
     */
    public function calculateAvailability($machineId, $shift)
    {
        // 1. Lấy thời gian bắt đầu và kết thúc của ca hiện tại
        $shiftTimes = $this->getShiftTimes();
        $now = Carbon::now();
        
        // 2. Lấy thời điểm bắt đầu ca
        $shiftStart = $shiftTimes[$shift]['start'];
        $shiftStartTime = Carbon::today()->setTimeFromTimeString($shiftStart);
        
        // Xử lý ca 3 (từ 22h hôm trước đến 6h hôm sau)
        if ($shift === 'CA3' && $now->format('H:i:s') < '06:00:00') {
            $shiftStartTime = $shiftStartTime->subDay();
        }
        
        // 3. Tính thời gian đã trôi qua của ca (phút)
        $elapsedMinutes = $shiftStartTime->diffInMinutes($now);

        // 4. Lấy dữ liệu PLC của ca hiện tại
        $currentPlcData = PlcData::where('machine_id', $machineId)
            ->where('datalog_data_ca', $shift)
            ->orderBy('id', 'desc')
            ->first();

        if (!$currentPlcData) {
            \Log::warning("No PLC data found for current shift");
            return 0;
        }

        $currentProduct = $currentPlcData->datalog_data_ma_sp;
        $runTimeMinutes = $currentPlcData->datalog_data_gio_chay_2 ?? 0;

        // 5. Nếu là CA2 hoặc CA3, tính toán giờ chạy từ ca trước
        if ($shift !== 'CA1') {
            $previousShift = $shift === 'CA2' ? 'CA1' : 'CA2';
            $previousDate = $now->format('Y-m-d');

            // Nếu là CA3 trước 6h sáng, lấy dữ liệu của ngày hôm trước
            if ($shift === 'CA3' && $now->format('H:i:s') < '06:00:00') {
                $previousDate = $now->copy()->subDay()->format('Y-m-d');
            }

            // Lấy tất cả bản ghi PLC của ca trước theo thời gian
            $previousPlcData = PlcData::where('machine_id', $machineId)
                ->where('datalog_data_ca', $previousShift)
                ->whereDate('datalog_date', $previousDate)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($previousPlcData->isNotEmpty()) {
                // Tính thời gian chạy cho từng sản phẩm trong ca trước
                $productRunTimes = [];
                $lastRecord = null;
                $lastProductCode = null;

                foreach ($previousPlcData as $record) {
                    $productCode = $record->datalog_data_ma_sp;
                    
                    // Nếu là sản phẩm mới hoặc bản ghi đầu tiên
                    if ($productCode !== $lastProductCode) {
                        // Lưu giờ chạy cho sản phẩm trước đó
                        if ($lastProductCode && $lastRecord) {
                            $productRunTimes[$lastProductCode] = $lastRecord->datalog_data_gio_chay_2 ?? 0;
                        }
                    }

                    $lastProductCode = $productCode;
                    $lastRecord = $record;
                }

                // Xử lý sản phẩm cuối cùng
                if ($lastProductCode && $lastRecord) {
                    $productRunTimes[$lastProductCode] = $lastRecord->datalog_data_gio_chay_2 ?? 0;
                }

                // Tính tỷ lệ thời gian chạy cho từng sản phẩm
                $totalTime = array_sum($productRunTimes);
                if ($totalTime > 0) {
                    foreach ($productRunTimes as $productCode => $runtime) {
                        $ratio = $runtime / $totalTime;
                        if ($productCode === $currentProduct) {
                            // Lấy giờ chạy 2 của bản ghi cuối cùng trong ca trước
                            $lastPreviousRecord = $previousPlcData->last();
                            $previousRunTime = $lastPreviousRecord->datalog_data_gio_chay_2 ?? 0;
                            // Cộng thêm thời gian chạy theo tỷ lệ
                            $runTimeMinutes += $previousRunTime * $ratio;
                            \Log::info("Added proportional runtime from previous shift for product $productCode: " . 
                                     "Runtime = $runtime, Total = $totalTime, " .
                                     "Ratio = " . round($ratio * 100, 2) . "%, " .
                                     "Added = " . round($previousRunTime * $ratio, 2) . " minutes");
                        }
                    }
                }
            }
        }

        // 6. Nếu là CA1, xử lý tương tự với CA3 của ngày hôm trước
        if ($shift === 'CA1') {
            $previousDate = $now->copy()->subDay()->format('Y-m-d');
            
            $previousPlcData = PlcData::where('machine_id', $machineId)
                ->where('datalog_data_ca', 'CA3')
                ->whereDate('datalog_date', $previousDate)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($previousPlcData->isNotEmpty()) {
                // Tính thời gian chạy cho từng sản phẩm
                $productRunTimes = [];
                $lastRecord = null;
                $lastProductCode = null;

                foreach ($previousPlcData as $record) {
                    $productCode = $record->datalog_data_ma_sp;
                    
                    // Nếu là sản phẩm mới hoặc bản ghi đầu tiên
                    if ($productCode !== $lastProductCode) {
                        // Lưu giờ chạy cho sản phẩm trước đó
                        if ($lastProductCode && $lastRecord) {
                            $productRunTimes[$lastProductCode] = $lastRecord->datalog_data_gio_chay_2 ?? 0;
                        }
                    }

                    $lastProductCode = $productCode;
                    $lastRecord = $record;
                }

                // Xử lý sản phẩm cuối cùng
                if ($lastProductCode && $lastRecord) {
                    $productRunTimes[$lastProductCode] = $lastRecord->datalog_data_gio_chay_2 ?? 0;
                }

                // Tính tỷ lệ thời gian chạy cho từng sản phẩm
                $totalTime = array_sum($productRunTimes);
                if ($totalTime > 0) {
                    foreach ($productRunTimes as $productCode => $runtime) {
                        $ratio = $runtime / $totalTime;
                        if ($productCode === $currentProduct) {
                            $lastPreviousRecord = $previousPlcData->last();
                            $previousRunTime = $lastPreviousRecord->datalog_data_gio_chay_2 ?? 0;
                            $runTimeMinutes += $previousRunTime * $ratio;
                            \Log::info("Added proportional runtime from previous day CA3 for product $productCode: " . 
                                     "Runtime = $runtime, Total = $totalTime, " .
                                     "Ratio = " . round($ratio * 100, 2) . "%, " .
                                     "Added = " . round($previousRunTime * $ratio, 2) . " minutes");
                        }
                    }
                }
            }
        }

        \Log::info("Total runtime after adding proportional previous shifts: $runTimeMinutes minutes");

        // 7. Tính Availability = Thời gian chạy / Thời gian đã trôi qua
        $availability = $elapsedMinutes > 0 ? $runTimeMinutes / $elapsedMinutes : 0;

        // 8. Giới hạn tối đa là 1 (100%)
        return min(1, $availability);
    }

    /**
     * Lấy thời gian bắt đầu và kết thúc của các ca
     */
    private function getShiftTimes()
    {
        return [
            'CA1' => ['start' => '06:00:00', 'end' => '14:00:00'],
            'CA2' => ['start' => '14:00:00', 'end' => '22:00:00'],
            'CA3' => ['start' => '22:00:00', 'end' => '06:00:00']
        ];
    }

    /**
     * Tính Performance dựa trên tốc độ dàn kéo thực tế (m/p)
     * Chỉ tính từ khi sản phẩm xuất hiện và tốc độ > 50
     */
    public function calculatePerformance($machineId, $shift)
    {
        // Lấy thông tin máy
        $machine = Machine::find($machineId);
        if (!$machine) {
            \Log::warning("Machine with ID $machineId not found");
            return 0;
        }

        // Lấy năng suất hiện tại từ PLC data
        $now = Carbon::now();
        $latestPlcData = PlcData::where('machine_id', $machineId)
            ->whereDate('created_at', $now->format('Y-m-d'))
            ->where('datalog_data_ca', 'like', "%{$shift}%")
            ->orderBy('id', 'desc')
            ->first();

        if (!$latestPlcData) {
            \Log::info("No PLC data found for machine $machineId in shift $shift");
            return 0;
        }

        // Lấy năng suất hiện tại (kg/h)
        $currentProductivity = $latestPlcData->nang_suatkg_h ?? 0;
        $maxProductivity = $machine->max_speed ?? 0;

        \Log::info("Performance calculation for machine $machineId:");
        \Log::info("Current productivity: $currentProductivity kg/h");
        \Log::info("Max productivity: $maxProductivity kg/h");

        // Tính Performance = Năng suất hiện tại / Năng suất tối đa
        // Không giới hạn ở 100% nữa
        $performance = $maxProductivity > 0 ? $currentProductivity / $maxProductivity : 0;

        \Log::info("Calculated performance: $performance");

        return $performance;
    }

    /**
     * Tính Quality dựa trên tổng hợp của tất cả các bản ghi trong ca
     */
    protected function calculateQuality($currentEntries)
    {
        if (!$currentEntries || $currentEntries->isEmpty()) {
            \Log::info("No production entries for quality calculation");
            return 0.01; // Giá trị mặc định
        }

        $totalGoodKg = 0;
        $totalDefectKg = 0;

        foreach ($currentEntries as $entry) {
            $product = $entry->product;
            if ($product && $product->gm_spec > 0 && $entry->product_length > 0) {
                $totalGoodKg += ($product->gm_spec * $entry->product_length * $entry->good_quantity) / 1000;
            }
            $totalDefectKg += $entry->defect_weight;
        }

        $quality = ($totalGoodKg + $totalDefectKg) > 0 ? $totalGoodKg / ($totalGoodKg + $totalDefectKg) : 0.01;
        \Log::info("Quality calculation: good_kg=$totalGoodKg, defect_kg=$totalDefectKg, quality=$quality");
        
        return $quality;
    }

    /**
     * Xác định ca làm việc hiện tại
     */
    protected function getCurrentShift(Carbon $time)
    {
        $hour = $time->hour;
        
        if ($hour >= 6 && $hour < 14) {
            return 'CA1';
        } elseif ($hour >= 14 && $hour < 22) {
            return 'CA2';
        } else {
            return 'CA3';
        }
    }

    /**
     * Lấy thời điểm bắt đầu ca
     */
    protected function getShiftStartTime(Carbon $time, $shift)
    {
        $startTime = $time->copy();
        
        switch ($shift) {
            case 'CA1':
                return $startTime->setTime(6, 0, 0);
            case 'CA2':
                return $startTime->setTime(14, 0, 0);
            case 'CA3':
                // Nếu là từ 0h đến 6h sáng thì là ca 3 của ngày hôm trước
                return $time->hour < 6 
                    ? $startTime->subDay()->setTime(22, 0, 0)
                    : $startTime->setTime(22, 0, 0);
        }
    }

    /**
     * Tính thời gian dự kiến hoàn thành dựa trên kế hoạch sản xuất và tốc độ thực tế dàn kéo
     * 
     * @param float $planQuantity Kế hoạch sản xuất (mét)
     * @param float $actualQuantity Sản lượng thực tế đã sản xuất (mét)
     * @param float $currentProductivity Năng suất hiện tại (kg/h) - không sử dụng
     * @param Carbon $currentTime Thời điểm hiện tại
     * @param int $machineId ID của máy
     * @return int Thời gian còn lại (phút)
     */
    protected function calculateEstimatedCompletionTime($planQuantity, $actualQuantity, $currentProductivity, $currentTime, $machineId)
    {
        // Kiểm tra kế hoạch sản xuất
        if ($planQuantity <= 0) {
            \Log::info("Không thể tính thời gian còn lại: Kế hoạch sản xuất không hợp lệ");
            return 0;
        }

        // Tính số lượng còn lại cần sản xuất (mét)
        $remainingQuantity = $planQuantity - $actualQuantity;
        
        // Nếu đã hoàn thành kế hoạch
        if ($remainingQuantity <= 0) {
            \Log::info("Đã hoàn thành kế hoạch sản xuất");
            return 0;
        }

        // Lấy tốc độ thực tế dàn kéo trung bình (m/p) - MD116
        $avgSpeed = PlcData::where('machine_id', $machineId)
            ->whereDate('created_at', $currentTime->format('Y-m-d'))
            ->where('toc_do_thuc_te_vx', '>', 50)
            ->orderBy('id', 'desc')
            ->limit(10) // Lấy 10 bản ghi gần nhất để tính trung bình
            ->avg('toc_do_thuc_te_dan_keo_m_p') ?? 0;

        // Nếu không có tốc độ hợp lệ, lấy bản ghi mới nhất
        if ($avgSpeed <= 0) {
            $latestSpeed = PlcData::where('machine_id', $machineId)
                ->whereDate('created_at', $currentTime->format('Y-m-d'))
                ->orderBy('id', 'desc')
                ->value('toc_do_thuc_te_dan_keo_m_p') ?? 0;
                
            if ($latestSpeed > 0) {
                $avgSpeed = $latestSpeed;
            } else {
                \Log::info("Không thể tính thời gian còn lại: Không có tốc độ thực tế dàn kéo hợp lệ");
                return 0;
            }
        }

        // Tính thời gian còn lại (phút)
        // Số mét còn lại / (tốc độ thực tế mét/phút)
        $remainingMinutes = round($remainingQuantity / $avgSpeed);
        
        \Log::info("Thời gian còn lại: " . $remainingMinutes . " phút" . 
                  " (còn " . round($remainingQuantity, 2) . " mét" .
                  ", tốc độ thực tế TB: " . round($avgSpeed, 3) . " m/p)");
        
        return $remainingMinutes;
    }
} 