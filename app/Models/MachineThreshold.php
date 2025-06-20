<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Kiểm tra một giá trị với ngưỡng
     *
     * @param mixed $value Giá trị cần kiểm tra
     * @return boolean True nếu có cảnh báo, false nếu không có cảnh báo
     */
    public function checkThreshold($value)
    {
        if (empty($this->conditions)) {
            return false;
        }

        // Với mỗi điều kiện, nếu thỏa mãn điều kiện đó thì sẽ trả về true (OR logic)
        foreach ($this->conditions as $condition) {
            if ($this->checkSingleCondition($condition, $value)) {
                return true;
            }
        }

        // Nếu không có điều kiện nào thỏa mãn
        return false;
    }

    protected function checkSingleCondition($condition, $value)
    {
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
                if (!isset($condition['base_value']) || !isset($condition['percent'])) {
                    return false;
                }
                // Get average value from last 10 minutes
                $avgValue = $this->getAverageValue(10);
                if ($avgValue === null) {
                    return false;
                }
                $min = $condition['base_value'] * (1 - $condition['percent'] / 100);
                $max = $condition['base_value'] * (1 + $condition['percent'] / 100);
                return $avgValue < $min || $avgValue > $max;

            default:
                return false;
        }
    }

    protected function getAverageValue($minutes)
    {
        // Lấy dữ liệu PLC từ 10 phút trước
        $data = $this->machine->plcData()
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->get();

        if ($data->isEmpty()) {
            return null;
        }

        // Chuyển đổi plc_data_key thành tên cột tương ứng
        $columnName = $this->mapPlcDataKeyToColumn($this->plc_data_key);
        if (!$columnName) {
            return null;
        }

        // Tính giá trị trung bình của cột
        return $data->avg($columnName);
    }

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
}
