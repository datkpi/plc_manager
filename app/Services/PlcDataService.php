<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\MachineThreshold;
use App\Models\PlcAlert;
use App\Models\PlcAlertSummary;
use App\Models\PlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use App\Services\OEECalculationService;
use App\Models\Product;
use App\Models\ProductionEntry;
use Carbon\Carbon;

class PlcDataService
{
    protected $oeeService;

    public function __construct(OEECalculationService $oeeService)
    {
        $this->oeeService = $oeeService;
    }

    /**
     * Crawl dữ liệu từ PLC và lưu vào cơ sở dữ liệu
     *
     * @param int $machine_id ID của máy cần crawl dữ liệu
     * @return PlcData|null Đối tượng PlcData đã lưu hoặc null nếu có lỗi
     */
    public function crawlData($machine_id)
    {
        try {
            $machine = Machine::findOrFail($machine_id);
            $response = Http::get($machine->ip_address);

            if (!$response->successful()) {
                Log::error("Không thể kết nối đến máy {$machine_id}");
                return null;
            }

            // dd($response->body());
            // Parse dữ liệu từ HTML
            $data = $this->parseHtmlData($response->body());
            foreach ($data as $key => $value) {
                $key = str_replace('cai_dat_tham_so_hmi_', '', $key); 
                $data[$key] = $value;
            }
                // dd($data);  

            if (empty($data)) {
                Log::error("Không tìm thấy dữ liệu từ máy {$machine_id}");
                return null;
            }

            // Thêm machine_id vào data
            $data['machine_id'] = $machine_id;

            // Log::info("Raw data from PLC:", $data);

            // Lưu PLC data
            $plcData = PlcData::create($data);

            // Kiểm tra chuyển ca và tính OEE
            $this->checkShiftChange($machine_id, $plcData);

            // Kiểm tra ngưỡng và tạo cảnh báo
            $this->checkThresholds($machine, $plcData);

            return $plcData;

        } catch (\Exception $e) {
            Log::error("Lỗi crawl data từ máy {$machine_id}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Parse dữ liệu từ HTML của TIA Portal
     */
    protected function parseHtmlData($html)
    {
        $crawler = new Crawler($html);
        $data = [];

        try {
            $crawler->filter('#watch_table tr.var')->each(function (Crawler $row) use (&$data) {
                // dd($data);
                $name = $this->normalizeKey($row->filter('td')->eq(0)->text());
                $value = $this->parseValue($row->filter('td')->eq(3)->text());

                if ($value !== null && !str_contains($value, 'no_scope')) {
                    $data[$name] = $value;
                }
            });

            return $data;

        } catch (\Exception $e) {
            Log::error("Lỗi parse HTML: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse giá trị từ string sang kiểu dữ liệu phù hợp
     */
    protected function parseValue($value)
    {
        // Bỏ dấu nháy đơn
        $value = trim($value, "'");

        // Xử lý boolean từ hình ảnh và text
        if (str_contains($value, 'icontrue.gif') || str_contains($value, 'true')) {
            return true;
        }
        if (str_contains($value, 'iconfalse.gif') || str_contains($value, 'false')) {
            return false;
        }

        // Xử lý date
        if (str_starts_with($value, 'D#')) {
            return substr($value, 2);
        }

        // Xử lý số thập phân
        if (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        // Xử lý số
        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * Chuẩn hóa key để mapping với database
     */
    protected function normalizeKey($key)
    {
        // Bỏ dấu nháy kép và khoảng trắng
        $key = trim(str_replace('"', '', $key));
        // dd($key);

        // Tách các phần của key
        $parts = explode('.', $key);
        $lastPart = end($parts);

        // Xử lý key từ Datalog hoặc DATA hoặc "Cài đặt tham số"
        if (count($parts) > 1) {
            $prefix = strtolower($parts[0]);
            
            // Chuyển "Cài đặt tham số" thành "cai_dat_tham_so"
            // if ($prefix == "cài đặt tham số hmi") {
            //     $prefix = "cai_dat_tham_so_hmi";
            // }
            
            if (isset($parts[1]) && $parts[1] === 'DATA') {
                $prefix .= '_data';
            }
            $key = $prefix . '_' . $lastPart;
        } else {
            $key = $lastPart;
        }

        // Xử lý các ký tự đặc biệt
        $key = str_replace(['(', ')', '/', ',', ' '], ['', '', '_', '_', '_'], $key);

        // Bỏ tiền tố "cai_dat_tham_so_hmi_" để lấy trực tiếp tên trường
        

        // Chuyển thành không dấu và lowercase
        return strtolower($this->removeAccents($key));
    }

    /**
     * Kiểm tra các ngưỡng và tạo cảnh báo nếu cần
     *
     * @param Machine $machine Đối tượng máy
     * @param PlcData $plcData Dữ liệu PLC cần kiểm tra
     */
    protected function checkThresholds($machine, $plcData)
    {
        try {
            // Lấy tất cả ngưỡng active của máy
            $thresholds = MachineThreshold::where('machine_id', $machine->id)
                ->where('status', true)
                ->get();

            if ($thresholds->isEmpty()) {
                Log::info("Không có ngưỡng nào được cấu hình cho máy {$machine->id}");
                return;
            }

            Log::info("Đang kiểm tra {$thresholds->count()} ngưỡng cho máy {$machine->id}");

            // Chuyển đổi $plcData thành mảng nếu là object
            $plcDataArray = $plcData;
            if (is_object($plcData) && method_exists($plcData, 'toArray')) {
                $plcDataArray = $plcData->toArray();
            } elseif (is_object($plcData)) {
                $plcDataArray = (array)$plcData;
            }

            // Kiểm tra từng ngưỡng
            foreach ($thresholds as $threshold) {
                $plcDataKey = $threshold->plc_data_key;

                // Kiểm tra xem key có tồn tại trong dữ liệu không
                if (!array_key_exists($plcDataKey, $plcDataArray)) {
                    Log::warning("Key {$plcDataKey} không tồn tại trong dữ liệu PLC cho máy {$machine->id}");
                    continue;
                }

                $currentValue = $plcDataArray[$plcDataKey];

                // Bỏ qua nếu giá trị là null
                if (is_null($currentValue)) {
                    Log::info("Giá trị null cho key {$plcDataKey}, bỏ qua kiểm tra");
                    continue;
                }

                Log::info("Kiểm tra ngưỡng {$threshold->name} (key: {$plcDataKey}): giá trị hiện tại = {$currentValue}");

                // Kiểm tra điều kiện vượt ngưỡng
                $alertResult = $threshold->checkThreshold($currentValue);
                
                // Nếu vượt ngưỡng, tạo cảnh báo
                if ($alertResult) {
                    Log::info("Phát hiện cảnh báo cho {$threshold->name}");
                    
                    $this->createAlert(
                        $machine->id,
                        $plcData->id,
                        $threshold->plc_data_key,
                        (string) $currentValue,
                        'danger',
                        "Cảnh báo {$threshold->name}: giá trị {$currentValue}",
                        $threshold
                    );
                } else {
                    Log::info("Giá trị trong ngưỡng cho phép cho {$threshold->name}");
                }
            }
        } catch (\Exception $e) {
            Log::error("Lỗi kiểm tra ngưỡng: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Tạo cảnh báo mới và cập nhật summary
     */
    protected function createAlert($machineId, $plcDataId, $key, $value, $type, $message, $threshold)
    {
        DB::beginTransaction();

        try {
            Log::info("Đang tạo cảnh báo cho máy {$machineId}, key: {$key}, giá trị: {$value}");

            // Luôn tạo alert mới để lưu lịch sử
            $alert = new PlcAlert();
            $alert->machine_id = $machineId;
            $alert->plc_data_id = $plcDataId;
            $alert->plc_data_key = $key;
            $alert->value = $value;
            $alert->type = $type;
            $alert->message = $message;
            $alert->status = 'new';
            $alert->save();

            // Kiểm tra summary hiện tại
            $summary = PlcAlertSummary::where([
                'machine_id' => $machineId,
                'plc_data_key' => $key,
                'status' => 'new'
            ])->first();

            if (!$summary) {
                Log::info("Tạo summary mới cho {$key}");

                // Nếu chưa có summary, tạo mới
                $summary = new PlcAlertSummary();
                $summary->machine_id = $machineId;
                $summary->plc_data_key = $key;
                $summary->current_value = $value;
                $summary->message = $message;
                $summary->type = $type;
                $summary->status = 'new';
                $summary->occurrence_count = 1;
                $summary->first_occurred_at = now();
                $summary->last_occurred_at = now();
                $summary->save();
            } else {
                Log::info("Cập nhật summary cho {$key}");

                // Cập nhật summary hiện tại
                $summary->current_value = $value;
                $summary->occurrence_count += 1;
                $summary->last_occurred_at = now();
                $summary->save();
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lỗi khi tạo cảnh báo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Chuyển text tiếng Việt sang không dấu
     */
    protected function removeAccents($str)
    {
        // Thay thế các ký tự "a"
        $str = str_replace(
            array(
                'à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ',
                'À','Á','Ạ','Ả','Ã','Â','Ầ','Ấ','Ậ','Ẩ','Ẫ','Ă','Ằ','Ắ','Ặ','Ẳ','Ẵ'
            ),
            'a',
            $str
        );

        // Thay thế các ký tự "e"
        $str = str_replace(
            array(
                'è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
                'È','É','Ẹ','Ẻ','Ẽ','Ê','Ề','Ế','Ệ','Ể','Ễ'
            ),
            'e',
            $str
        );

        // Thay thế các ký tự "i"
        $str = str_replace(
            array(
                'ì','í','ị','ỉ','ĩ',
                'Ì','Í','Ị','Ỉ','Ĩ'
            ),
            'i',
            $str
        );

        // Thay thế các ký tự "o"
        $str = str_replace(
            array(
                'ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
                'Ò','Ó','Ọ','Ỏ','Õ','Ô','Ồ','Ố','Ộ','Ổ','Ỗ','Ơ','Ờ','Ớ','Ợ','Ở','Ỡ'
            ),
            'o',
            $str
        );

        // Thay thế các ký tự "u"
        $str = str_replace(
            array(
                'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ',
                'Ù','Ú','Ụ','Ủ','Ũ','Ư','Ừ','Ứ','Ự','Ử','Ữ'
            ),
            'u',
            $str
        );

        // Thay thế các ký tự "y"
        $str = str_replace(
            array(
                'ỳ','ý','ỵ','ỷ','ỹ',
                'Ỳ','Ý','Ỵ','Ỷ','Ỹ'
            ),
            'y',
            $str
        );

        // Thay thế ký tự "đ"
        $str = str_replace(
            array('đ','Đ'),
            'd',
            $str
        );

        // Chuyển toàn bộ chuỗi thành chữ thường
        return strtolower($str);
    }

    /**
     * Kiểm tra chuyển ca và tính OEE
     */
    protected function checkShiftChange($machineId, $plcData)
    {
        try {
            // Lấy bản ghi PLC data gần nhất trước đó
            $previousData = PlcData::where('machine_id', $machineId)
                ->where('id', '<', $plcData->id)
                ->orderBy('id', 'desc')
                ->first();

            if (!$previousData) {
                Log::info("Không có dữ liệu PLC trước đó để kiểm tra chuyển ca");
                return;
            }

            $currentShift = $plcData->datalog_data_ca;
            $previousShift = $previousData->datalog_data_ca;

            // Kiểm tra nếu có chuyển ca
            if ($currentShift != $previousShift) {
                Log::info("Phát hiện chuyển ca từ {$previousShift} sang {$currentShift} cho máy {$machineId}");

                // Lấy bản ghi cuối cùng của ca trước để có giờ chạy chính xác
                $lastPreviousShiftData = PlcData::where('machine_id', $machineId)
                    ->where('datalog_data_ca', $previousShift)
                    ->where('datalog_date', $previousData->datalog_date)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($lastPreviousShiftData) {
                    // Lấy giờ chạy từ bản ghi cuối cùng của ca trước
                    $runTimeMinutes = $lastPreviousShiftData->datalog_data_gio_chay_2;
                    
                    Log::info("Giờ chạy cuối cùng của ca {$previousShift}: {$runTimeMinutes} phút");

                    // Tính OEE cho ca trước với giờ chạy chính xác
                    $previousDate = $lastPreviousShiftData->datalog_date;
                    $previousOEE = $this->oeeService->calculateShiftOEE(
                        $machineId,
                        $previousDate,
                        $previousShift,
                        $runTimeMinutes // Truyền thêm giờ chạy chính xác
                    );

                    Log::info("OEE ca {$previousShift} ngày {$previousDate}:", $previousOEE);

                    // Có thể thêm logic lưu kết quả OEE vào database
                    $this->saveShiftOEE($machineId, $previousDate, $previousShift, $previousOEE);
                }
            }

        } catch (\Exception $e) {
            Log::error("Lỗi kiểm tra chuyển ca: " . $e->getMessage());
        }
    }

    /**
     * Lưu kết quả OEE của ca
     */
    protected function saveShiftOEE($machineId, $date, $shift, $oeeData)
    {
        try {
            // TODO: Implement logic to save OEE results
            // Ví dụ: lưu vào bảng shift_oee_results
            Log::info("Lưu kết quả OEE cho máy {$machineId}, ngày {$date}, ca {$shift}:", $oeeData);
        } catch (\Exception $e) {
            Log::error("Lỗi lưu kết quả OEE: " . $e->getMessage());
        }
    }

    /**
     * Chuyển đổi từ cuộn/cây sang kg
     */
    public function convertToKg($productCode, $rollCount, $date = null, $machine = null)
    {
        // Tìm kiếm sản phẩm
        $product = Product::where('code', $productCode)->first();
        if (!$product || !$product->gm_spec) {
            Log::warning("Không thể tìm thấy sản phẩm hoặc định mức g/m cho mã: {$productCode}");
            return 0;
        }

        // Tìm kiếm chiều dài thực tế từ dữ liệu sản xuất
        $productionEntry = null;
        if ($date && $machine) {
            $productionEntry = ProductionEntry::where('date', $date)
                ->where('product_code', $productCode)
                ->where('machine_id', $machine->id)
                ->first();
        }

        // Nếu có chiều dài thực tế, sử dụng giá trị đó
        if ($productionEntry && $productionEntry->product_length > 0) {
            $length = $productionEntry->product_length;
            Log::info("Sử dụng chiều dài thực tế {$length}m từ dữ liệu sản xuất cho sản phẩm {$productCode}");
        } else {
            // Nếu không, tính toán chiều dài tiêu chuẩn
            $parts = explode(' ', $product->name);
            $length = 0;

            if (count($parts) >= 3) {
                $diameter = intval($parts[0]);
                $material = $parts[2];

                if (strpos($material, 'PE') !== false) {
                    if ($diameter <= 90) {
                        // Ống PE cuộn
                        switch($diameter) {
                            case 90: $length = 25; break;
                            case 75: $length = 25; break;
                            case 63: $length = 50; break;
                            case 50: $length = 100; break;
                            case 40: $length = 100; break;
                            case 32: $length = 200; break;
                            case 25: $length = 300; break;
                            case 20: $length = 300; break;
                            case 16: $length = 300; break;
                            default: $length = 100; break;
                        }
                    } else {
                        // Ống PE cây (DN ≥ 110mm)
                        $length = 6;
                    }
                } else if ($material == 'PPR') {
                    $length = 4;
                } else if (strpos($material, 'PSU') !== false) {
                    $length = 6;
                }
            }

            if ($length <= 0) {
                Log::warning("Không thể xác định chiều dài cho sản phẩm: {$productCode}, sử dụng chiều dài mặc định 100m");
                $length = 100; // Chiều dài mặc định nếu không xác định được
            } else {
                Log::info("Sử dụng chiều dài tiêu chuẩn {$length}m cho sản phẩm {$productCode}");
            }
        }

        // Tính khối lượng dựa trên định mức g/m và chiều dài
        $weightPerUnit = ($product->gm_spec * $length) / 1000; // Chuyển g thành kg
        $totalWeight = $weightPerUnit * $rollCount;

        return $totalWeight;
    }

    public function calculateShiftOEE($machineId, $date, $shift, $runTimeMinutes = null)
    {
        // Lấy tất cả production entries trong ca
        $entries = ProductionEntry::where([
            'machine_id' => $machineId,
            'date' => $date,
            'shift' => $shift
        ])->get();

        // Lấy tất cả PLC data trong ca để tính trung bình năng suất
        $plcData = PlcData::where('machine_id', $machineId)
            ->whereDate('datalog_date', $date)
            ->where('datalog_data_ca', $shift)
            ->get();

        // A: Availability (giữ nguyên)
        if ($runTimeMinutes === null) {
            $lastRecord = $plcData->sortByDesc('id')->first();
            $runTimeMinutes = $lastRecord ? $lastRecord->datalog_data_gio_chay_2 : 0;
        }
        $availability = $runTimeMinutes / (8 * 60); // 8h = 480 phút
        $availability = min(1, $availability);

        // P: Performance - tính trung bình năng suất thực tế của tất cả sản phẩm trong ca
        $avgActualProductivity = $plcData->avg('nang_suatkg_h') ?? 0;
        
        // Lấy năng suất định mức trung bình có trọng số của các sản phẩm trong ca
        $totalWeight = 0;
        $weightedTargetProductivity = 0;
        
        foreach($entries as $entry) {
            $product = Product::where('code', $entry->product_code)->first();
            if ($product) {
                $weight = $entry->output_quantity; // Trọng số là số lượng sản xuất
                $totalWeight += $weight;
                $targetProductivity = $product->min_productivity ?: $entry->target_productivity ?: 0;
                $weightedTargetProductivity += $targetProductivity * $weight;
            }
        }

        $avgTargetProductivity = $totalWeight > 0 ? $weightedTargetProductivity / $totalWeight : 0;
        $performance = $avgTargetProductivity > 0 ? $avgActualProductivity / $avgTargetProductivity : 0;
        $performance = min(1, $performance);

        // Q: Quality - tính tổng của tất cả sản phẩm trong ca
        $totalGoodProducts = 0;
        $totalDefectProducts = 0;

        foreach($entries as $entry) {
            $goodProductsKg = $this->convertToKg($entry->product_code, $entry->good_quantity, $entry->product_length);
            $totalGoodProducts += $goodProductsKg;
            $totalDefectProducts += $entry->defect_weight;
        }

        $totalProducts = $totalGoodProducts + $totalDefectProducts;
        $quality = $totalProducts > 0 ? $totalGoodProducts / $totalProducts : 0;

        // OEE = A × P × Q
        $oee = $availability * $performance * $quality;

        return [
            'availability' => $availability,
            'performance' => $performance,
            'quality' => $quality,
            'oee' => $oee,
            'details' => [
                'run_time_minutes' => $runTimeMinutes,
                'actual_productivity' => $avgActualProductivity,
                'target_productivity' => $avgTargetProductivity,
                'good_products_kg' => $totalGoodProducts,
                'defect_products_kg' => $totalDefectProducts,
                'total_products_kg' => $totalProducts
            ]
        ];
    }

    public function calculateOEEByDateRange($machineId, $startDate, $endDate)
    {
        // Chuyển đổi sang kiểu date nếu input là string
        $startDate = Carbon::parse($startDate)->startOfDay();
        $endDate = Carbon::parse($endDate)->endOfDay();
        
        $dailyResults = [];
        $totalStats = [
            'run_time_minutes' => 0,
            'actual_productivity' => 0,
            'target_productivity' => 0,
            'good_products_kg' => 0,
            'defect_products_kg' => 0,
            'valid_days' => 0
        ];

        // Lặp qua từng ngày trong khoảng
        for($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dailyOEE = $this->calculateDailyOEE($machineId, $date->format('Y-m-d'));
            
            
            // Chỉ tính các ngày có dữ liệu (có ít nhất 1 ca có OEE > 0)
            if ($dailyOEE['details']['valid_shifts'] > 0) {
                $dailyResults[$date->format('Y-m-d')] = $dailyOEE;
                
                // Cộng dồn thống kê
                $totalStats['run_time_minutes'] += $dailyOEE['details']['total_run_time_minutes'];
                $totalStats['actual_productivity'] += $dailyOEE['details']['avg_actual_productivity'];
                $totalStats['target_productivity'] += $dailyOEE['details']['avg_target_productivity'];
                $totalStats['good_products_kg'] += $dailyOEE['details']['total_good_products_kg'];
                $totalStats['defect_products_kg'] += $dailyOEE['details']['total_defect_products_kg'];
                $totalStats['valid_days']++;
            }
        }

        // Tính OEE trung bình cho cả khoảng thời gian
        if ($totalStats['valid_days'] > 0) {
            // A: Tổng thời gian chạy / (Số ngày có dữ liệu * 24h)
            $availability = $totalStats['run_time_minutes'] / 
                           ($totalStats['valid_days'] * 24 * 60);
            $availability = min(1, $availability);

            // P: Trung bình năng suất thực tế / năng suất định mức
            $avgActualProductivity = $totalStats['actual_productivity'] / $totalStats['valid_days'];
            $avgTargetProductivity = $totalStats['target_productivity'] / $totalStats['valid_days'];
            $performance = $avgTargetProductivity > 0 ? 
                min(1, $avgActualProductivity / $avgTargetProductivity) : 0;

            // Q: Tổng chính phẩm / Tổng sản phẩm
            $totalProducts = $totalStats['good_products_kg'] + $totalStats['defect_products_kg'];
            $quality = $totalProducts > 0 ? 
                $totalStats['good_products_kg'] / $totalProducts : 0;

            // OEE tổng = A × P × Q
            $totalOEE = $availability * $performance * $quality;
        } else {
            $availability = 0;
            $performance = 0;
            $quality = 0;
            $totalOEE = 0;
        }

        return [
            'daily_results' => $dailyResults,  // OEE chi tiết từng ngày
            'total' => [                       // OEE trung bình cả khoảng thời gian
                'availability' => $availability,
                'performance' => $performance,
                'quality' => $quality,
                'oee' => $totalOEE
            ],
            'summary' => [                     // Thống kê tổng hợp
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ],
                'valid_days' => $totalStats['valid_days'],
                'total_run_time_minutes' => $totalStats['run_time_minutes'],
                'avg_actual_productivity' => $avgActualProductivity ?? 0,
                'avg_target_productivity' => $avgTargetProductivity ?? 0,
                'total_good_products_kg' => $totalStats['good_products_kg'],
                'total_defect_products_kg' => $totalStats['defect_products_kg'],
                'total_products_kg' => $totalProducts ?? 0
            ]
        ];
    }
}
