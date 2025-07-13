<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class MachineThreshold extends Model
{
    protected $table = 'machine_threshold';

    // Constants cho type
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_RANGE = 'range';
    const TYPE_PERCENT = 'percent';
    const TYPE_AVG = 'avg';

    const TYPE_LIST = [
        self::TYPE_BOOLEAN => 'Boolean',
        self::TYPE_RANGE => 'Min-Max',
        self::TYPE_PERCENT => '% Dao động',
        self::TYPE_AVG => 'Trung bình 10 phút'
    ];

    protected $fillable = [
        'machine_id',
        'plc_data_key',
        'name',
        'color',
        'show_on_chart',
        'status',
        'conditions',
        'operator',
        'type'
    ];

    protected $casts = [
        'show_on_chart' => 'boolean',
        'status' => 'boolean',
        'conditions' => 'array'
    ];

    // Constants cho operators
    const OPERATOR_AND = 'and';
    const OPERATOR_OR = 'or';

    const OPERATOR_LIST = [
        self::OPERATOR_AND => 'Tất cả điều kiện (AND)',
        self::OPERATOR_OR => 'Một trong các điều kiện (OR)'
    ];

    /**
     * Relationship với model Machine
     */
    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    /**
     * Cập nhật điều kiện cảnh báo từ request
     * @param array $data Dữ liệu từ request
     * @return array ['conditions' => array, 'errors' => array] Mảng conditions đã được cập nhật và các lỗi nếu có
     */
    public function updateConditions($data)
    {
        $conditions = [];
        $errors = [];

        // Xử lý điều kiện Boolean
        if (isset($data['boolean_enabled']) && $data['boolean_enabled']) {
            $condition = [
                'type' => self::TYPE_BOOLEAN,
                'value' => $data['boolean_value'] ?? true,
                'message' => $data['boolean_message'] ?? 'Cảnh báo khi bật',
                'enabled' => true
            ];
            
            $validation = $this->validateCondition($condition);
            if ($validation['valid']) {
                $conditions[] = $condition;
            } else {
                $errors['boolean'] = $validation['errors'];
            }
        }

        // Xử lý điều kiện Range (Min-Max)
        if (isset($data['range_enabled']) && $data['range_enabled']) {
            $condition = [
                'type' => self::TYPE_RANGE,
                'min' => null,
                'max' => null,
                'message' => $data['range_message'] ?? 'Cảnh báo vượt ngưỡng min-max',
                'enabled' => true
            ];
            
            if (isset($data['range_min']) && $data['range_min'] !== '') {
                $condition['min'] = (float)$data['range_min'];
            }
            
            if (isset($data['range_max']) && $data['range_max'] !== '') {
                $condition['max'] = (float)$data['range_max'];
            }

            $validation = $this->validateCondition($condition);
            if ($validation['valid']) {
                $conditions[] = $condition;
            } else {
                $errors['range'] = $validation['errors'];
            }
        }

        // Xử lý điều kiện Percent (% Dao động)
        if (isset($data['percent_enabled']) && $data['percent_enabled']) {
            if (isset($data['percent_base_value']) && isset($data['percent_value'])) {
                $condition = [
                    'type' => self::TYPE_PERCENT,
                    'base_value' => (float)$data['percent_base_value'],
                    'percent' => (float)$data['percent_value'],
                    'message' => $data['percent_message'] ?? 'Cảnh báo dao động % so với giá trị cơ sở',
                    'enabled' => true
                ];

                $validation = $this->validateCondition($condition);
                if ($validation['valid']) {
                    $conditions[] = $condition;
                } else {
                    $errors['percent'] = $validation['errors'];
                }
            }
        }

        // Xử lý điều kiện Average (Trung bình 10 phút)
        if (isset($data['avg_enabled']) && $data['avg_enabled']) {
            if (isset($data['avg_percent'])) {
                $condition = [
                    'type' => self::TYPE_AVG,
                    'percent' => (float)$data['avg_percent'],
                    'message' => $data['avg_message'] ?? 'Cảnh báo dao động so với trung bình 10 phút',
                    'enabled' => true
                ];

                $validation = $this->validateCondition($condition);
                if ($validation['valid']) {
                    $conditions[] = $condition;
                } else {
                    $errors['avg'] = $validation['errors'];
                }
            }
        }

        // Log để debug
        \Log::info('Đã cập nhật conditions:', [
            'data' => $data,
            'conditions' => $conditions,
            'errors' => $errors
        ]);

        return [
            'conditions' => $conditions,
            'errors' => $errors
        ];
    }

    /**
     * Kiểm tra một giá trị với ngưỡng
     *
     * @param mixed $value Giá trị cần kiểm tra
     * @return array Kết quả kiểm tra bao gồm trạng thái và chi tiết từng điều kiện
     */
    public function checkThreshold($value)
    {
        if (empty($this->conditions)) {
            return [
                'has_warning' => false,
                'alerts' => []
            ];
        }

        $alerts = [];
        $hasWarning = false;

        // Kiểm tra từng điều kiện và lưu kết quả
        foreach ($this->conditions as $index => $condition) {
            // Bỏ qua điều kiện bị disable
            if (isset($condition['enabled']) && !$condition['enabled']) {
                continue;
            }

            $result = $this->checkSingleCondition($condition, $value);
            $details = $this->getConditionDetails($condition, $value);

            if ($result) {
                $alert = [
                    'type' => $condition['type'],
                    'message' => $condition['message'] ?? $this->getDefaultMessage($condition, $value, $details),
                    'details' => $details
                ];
                $alerts[] = $alert;
                $hasWarning = true;
            }
        }

        // Log kết quả kiểm tra
        \Log::info("Kết quả kiểm tra ngưỡng:", [
            'key' => $this->plc_data_key,
            'giá_trị' => $value,
            'số_cảnh_báo' => count($alerts),
            'chi_tiết_cảnh_báo' => $alerts
        ]);

        return [
            'has_warning' => $hasWarning,
            'alerts' => $alerts
        ];
    }

    /**
     * Lấy chi tiết điều kiện để hiển thị
     */
    protected function getConditionDetails($condition, $value)
    {
        $details = [];
        
        switch ($condition['type']) {
            case self::TYPE_BOOLEAN:
                $details = [
                    'expected' => $condition['value'],
                    'actual' => $value
                ];
                break;

            case self::TYPE_RANGE:
                $details = [
                    'min' => $condition['min'] ?? null,
                    'max' => $condition['max'] ?? null,
                    'actual' => $value
                ];
                break;

            case self::TYPE_PERCENT:
                if (isset($condition['base_value']) && isset($condition['percent'])) {
                    $min = $condition['base_value'] * (1 - $condition['percent'] / 100);
                    $max = $condition['base_value'] * (1 + $condition['percent'] / 100);
                    $details = [
                        'base_value' => $condition['base_value'],
                        'percent' => $condition['percent'],
                        'min' => $min,
                        'max' => $max,
                        'actual' => $value
                    ];
                }
                break;

            case self::TYPE_AVG:
                if (isset($condition['percent'])) {
                    $avgValue = $this->getAverageValue(10);
                    if ($avgValue !== null) {
                        $deviation = abs($value - $avgValue);
                        $percentDeviation = ($avgValue != 0) ? ($deviation / $avgValue) * 100 : 0;
                        $details = [
                            'avg_value' => $avgValue,
                            'allowed_percent' => $condition['percent'],
                            'actual_deviation' => $percentDeviation,
                            'actual' => $value
                        ];
                    }
                }
                break;
        }

        return $details;
    }

    protected function checkSingleCondition($condition, $value)
    {
        \Log::info("Bắt đầu kiểm tra điều kiện:", [
            'loại' => $condition['type'],
            'key' => $this->plc_data_key,
            'giá_trị_hiện_tại' => $value
        ]);

        switch ($condition['type']) {
            case self::TYPE_BOOLEAN:
                return $value == $condition['value'];

            case self::TYPE_RANGE:
                if (isset($condition['min']) && $value < $condition['min']) {
                    return true;
                }
                if (isset($condition['max']) && $value > $condition['max']) {
                    return true;
                }
                return false;

            case self::TYPE_PERCENT:
                if (!isset($condition['base_value']) || !isset($condition['percent'])) {
                    return false;
                }
                $min = $condition['base_value'] * (1 - $condition['percent'] / 100);
                $max = $condition['base_value'] * (1 + $condition['percent'] / 100);
                return $value < $min || $value > $max;

            case self::TYPE_AVG:
                if (!isset($condition['percent'])) {
                    \Log::warning("Thiếu cấu hình % dao động cho phép");
                    return false;
                }
                
                // Lấy trung bình 10 phút gần nhất
                $avgValue = $this->getAverageValue(10);
                if ($avgValue === null) {
                    \Log::warning("Không thể tính giá trị trung bình 10 phút");
                    return false;
                }
                
                // Tính % chênh lệch thực tế
                $deviation = abs($value - $avgValue);
                $percentDeviation = ($avgValue != 0) ? ($deviation / $avgValue) * 100 : 0;
                
                // Log chi tiết kết quả kiểm tra
                \Log::info("Chi tiết kiểm tra cảnh báo trung bình 10p:", [
                    'key' => $this->plc_data_key,
                    'giá_trị_hiện_tại' => number_format($value, 6),
                    'giá_trị_trung_bình' => number_format($avgValue, 6),
                    'chênh_lệch_tuyệt_đối' => number_format($deviation, 6),
                    'phần_trăm_chênh_lệch' => number_format($percentDeviation, 6) . '%',
                    'ngưỡng_cho_phép' => number_format($condition['percent'], 2) . '%',
                    'vượt_ngưỡng' => ($percentDeviation > $condition['percent'] ? 'Có' : 'Không')
                ]);
                
                return $percentDeviation > $condition['percent'];

            default:
                return false;
        }
    }

    // protected function getAverageValue($minutes)
    // {
    //     // Kiểm tra machine có tồn tại không
    //     if (!$this->machine) {
    //         \Log::warning("Không tìm thấy machine_id: {$this->machine_id}");
    //         return null;
    //     }

    //     // Lấy dữ liệu PLC từ 10 phút trước
    //     $data = $this->machine->plcData()
    //         ->where('created_at', '>=', now()->subMinutes($minutes))
    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     if ($data->isEmpty()) {
    //         \Log::warning("Không có dữ liệu PLC trong {$minutes} phút gần nhất");
    //         return null;
    //     }

    //     // Kiểm tra số lượng mẫu tối thiểu (ít nhất 5 mẫu)
    //     if ($data->count() < 5) {
    //         \Log::warning("Số lượng mẫu ({$data->count()}) không đủ để tính trung bình tin cậy");
    //         return null;
    //     }

    //     // Chuyển đổi plc_data_key thành tên cột tương ứng
    //     $columnName = $this->mapPlcDataKeyToColumn($this->plc_data_key);
    //     if (!$columnName) {
    //         \Log::warning("Không tìm thấy mapping cho key: {$this->plc_data_key}");
    //         return null;
    //     }

    //     // Tính trung bình có trọng số theo thời gian
    //     $totalWeight = 0;
    //     $weightedSum = 0;
    //     $now = now();
        
    //     // Log danh sách các giá trị để debug
    //     $values = [];
        
    //     foreach ($data as $record) {
    //         $value = $record->$columnName;
    //         if ($value === null) continue;
            
    //         // Tính trọng số dựa trên thời gian (càng gần hiện tại càng có trọng số cao)
    //         $ageInMinutes = $now->diffInMinutes($record->created_at);
    //         $weight = ($minutes - $ageInMinutes) / $minutes;
            
    //         $weightedSum += $value * $weight;
    //         $totalWeight += $weight;
            
    //         $values[] = [
    //             'thời_gian' => $record->created_at->format('H:i:s'),
    //             'giá_trị' => number_format($value, 6),
    //             'trọng_số' => number_format($weight, 4)
    //         ];
    //     }

    //     if ($totalWeight == 0) {
    //         \Log::warning("Không thể tính trung bình do không có giá trị hợp lệ");
    //         return null;
    //     }

    //     $avgValue = $weightedSum / $totalWeight;

    //     \Log::info("=== Chi tiết tính trung bình {$minutes} phút ===");
    //     \Log::info("Key: {$this->plc_data_key}");
    //     \Log::info("Cột: {$columnName}");
    //     \Log::info("Số bản ghi: {$data->count()}");
    //     \Log::info("Danh sách giá trị:");
    //     foreach ($values as $value) {
    //         \Log::info("  {$value['thời_gian']}: {$value['giá_trị']} (trọng số: {$value['trọng_số']})");
    //     }
    //     \Log::info("Giá trị trung bình có trọng số: " . number_format($avgValue, 6));
    //     \Log::info("=====================================");

    //     return $avgValue;
    // }

    protected function mapPlcDataKeyToColumn($plcDataKey)
    {
        // Map các giá trị plc_data_key sang tên cột tương ứng
        $mapping = [
            '%MD108' => 'toc_do_thuc_te_vx',
            '%MD112' => 'toc_do_thuc_te_may_chi',
            '%MD116' => 'toc_do_thuc_te_dan_keo_m_p',
            '%MD120' => 'toc_do_dat_vx',
            '%MD124' => 'toc_do_dat_may_chi',
            '%MD128' => 'toc_do_dat_dan_keo',
            '%MD176' => 'nhiet_do_nhua',
            '%MD180' => 'app_luc_nhua',
            // Thêm các mapping khác nếu cần
        ];

        return $mapping[$plcDataKey] ?? null;
    }

    /**
     * Tạo message mặc định cho cảnh báo
     */
    protected function getDefaultMessage($condition, $value, $details)
    {
        switch ($condition['type']) {
            case self::TYPE_BOOLEAN:
                return "Giá trị boolean không đúng (hiện tại: " . ($value ? 'true' : 'false') . ")";

            case self::TYPE_RANGE:
                $message = "Giá trị nằm ngoài khoảng cho phép";
                if (isset($details['min'])) {
                    $message .= ", min: " . $details['min'];
                }
                if (isset($details['max'])) {
                    $message .= ", max: " . $details['max'];
                }
                $message .= " (hiện tại: " . number_format($value, 2) . ")";
                return $message;

            case self::TYPE_PERCENT:
                return "Dao động " . $condition['percent'] . "% so với giá trị " . 
                       number_format($condition['base_value'], 2) . 
                       " (hiện tại: " . number_format($value, 2) . ")";

            case self::TYPE_AVG:
                if (isset($details['avg_value'])) {
                    return "Dao động " . $condition['percent'] . "% so với trung bình 10p " .
                           number_format($details['avg_value'], 2) . 
                           " (hiện tại: " . number_format($value, 2) . ")";
                }
                return "Dao động bất thường so với trung bình 10 phút";

            default:
                return "Cảnh báo không xác định";
        }
    }

    /**
     * Kiểm tra tính hợp lệ của một điều kiện
     * @param array $condition Điều kiện cần kiểm tra
     * @return array ['valid' => bool, 'errors' => array]
     */
    protected function validateCondition($condition)
    {
        $result = [
            'valid' => true,
            'errors' => []
        ];

        // Kiểm tra các trường bắt buộc
        if (!isset($condition['type'])) {
            $result['errors'][] = 'Thiếu trường type';
            $result['valid'] = false;
        }

        // Kiểm tra type có hợp lệ không
        if (!in_array($condition['type'], array_keys(self::TYPE_LIST))) {
            $result['errors'][] = 'Type không hợp lệ';
            $result['valid'] = false;
        }

        // Kiểm tra theo từng loại
        switch ($condition['type']) {
            case self::TYPE_RANGE:
                if (!isset($condition['min']) && !isset($condition['max'])) {
                    $result['errors'][] = 'Cần ít nhất một trong hai giá trị min hoặc max';
                    $result['valid'] = false;
                }
                if (isset($condition['min']) && isset($condition['max']) && $condition['min'] > $condition['max']) {
                    $result['errors'][] = 'Giá trị min không được lớn hơn max';
                    $result['valid'] = false;
                }
                break;

            case self::TYPE_PERCENT:
                if (!isset($condition['base_value'])) {
                    $result['errors'][] = 'Thiếu giá trị cơ sở (base_value)';
                    $result['valid'] = false;
                }
                if (!isset($condition['percent']) || $condition['percent'] <= 0) {
                    $result['errors'][] = 'Phần trăm dao động phải lớn hơn 0';
                    $result['valid'] = false;
                }
                break;

            case self::TYPE_AVG:
                if (!isset($condition['percent']) || $condition['percent'] <= 0) {
                    $result['errors'][] = 'Phần trăm dao động phải lớn hơn 0';
                    $result['valid'] = false;
                }
                break;
        }

        return $result;
    }

    /**
     * Tính giá trị trung bình của một key trong khoảng thời gian
     * @param int $minutes Số phút cần tính trung bình
     * @return float|null Giá trị trung bình hoặc null nếu không tính được
     */
    public function getAverageValue($minutes = 10)
    {
        try {
            // Kiểm tra machine có tồn tại không
            if (!$this->machine) {
                \Log::warning("Không tìm thấy machine_id: {$this->machine_id}");
                return null;
            }

            // Lấy dữ liệu PLC từ $minutes phút trước
            $data = $this->machine->plcData()
                ->where('created_at', '>=', now()->subMinutes($minutes))
                ->orderBy('created_at', 'desc')
                ->get();

            if ($data->isEmpty()) {
                \Log::warning("Không có dữ liệu PLC trong {$minutes} phút gần nhất");
                return null;
            }

            // Kiểm tra số lượng mẫu tối thiểu (ít nhất 5 mẫu)
            if ($data->count() < 5) {
                \Log::warning("Số lượng mẫu ({$data->count()}) không đủ để tính trung bình tin cậy");
                return null;
            }

            // Tính trung bình có trọng số theo thời gian
            $totalWeight = 0;
            $weightedSum = 0;
            $now = now();
            
            // Log danh sách các giá trị để debug
            $values = [];
            
            foreach ($data as $record) {
                $value = $record->{$this->plc_data_key};
                if ($value === null) continue;
                
                // Tính trọng số dựa trên thời gian (càng gần hiện tại càng có trọng số cao)
                $ageInMinutes = $now->diffInMinutes($record->created_at);
                $weight = ($minutes - $ageInMinutes) / $minutes;
                
                $weightedSum += $value * $weight;
                $totalWeight += $weight;
                
                $values[] = [
                    'thời_gian' => $record->created_at->format('H:i:s'),
                    'giá_trị' => number_format($value, 6),
                    'trọng_số' => number_format($weight, 4)
                ];
            }

            if ($totalWeight == 0) {
                \Log::warning("Không thể tính trung bình do không có giá trị hợp lệ");
                return null;
            }

            $avgValue = $weightedSum / $totalWeight;

            \Log::info("=== Chi tiết tính trung bình {$minutes} phút ===");
            \Log::info("Key: {$this->plc_data_key}");
            \Log::info("Số bản ghi: {$data->count()}");
            \Log::info("Danh sách giá trị:");
            foreach ($values as $value) {
                \Log::info("  {$value['thời_gian']}: {$value['giá_trị']} (trọng số: {$value['trọng_số']})");
            }
            \Log::info("Giá trị trung bình có trọng số: " . number_format($avgValue, 6));
            \Log::info("=====================================");

            return $avgValue;

        } catch (\Exception $e) {
            \Log::error("Lỗi tính giá trị trung bình: " . $e->getMessage());
            return null;
        }
    }
}
