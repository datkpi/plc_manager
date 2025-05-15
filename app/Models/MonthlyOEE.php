<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyOEE extends Model
{
    protected $table = 'monthly_oee';

    protected $fillable = [
        'machine_id',
        'year',
        'month',
        'total_hours', // A. Tổng thời gian (giờ)
        'planned_downtime', // Thời gian ngừng máy có kế hoạch (giờ)
        'planned_runtime', // B. Thời gian chạy máy theo kế hoạch = A - planned_downtime
        'unplanned_downtime', // Tổn thất do ngừng máy không có kế hoạch (giờ)
        'actual_runtime', // C. Thời gian chạy máy thực tế (giờ)
        'theoretical_output', // D. Năng suất lý thuyết của máy (kg/giờ)
        'actual_output', // E. Năng suất thực tế của máy (kg/giờ)
        'monthly_production', // F. Sản lượng thực tế của máy trong tháng (kg)
        'defective_products', // G. Phế phẩm (kg)
        'good_products', // H. Sản phẩm thực tế (kg)
        'availability', // Availability = C/B
        'performance', // Performance = E/D
        'quality', // Quality = H/F
        'oee', // OEE = Availability * Performance * Quality
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'total_hours' => 'float',
        'planned_downtime' => 'float',
        'planned_runtime' => 'float',
        'unplanned_downtime' => 'float',
        'actual_runtime' => 'float',
        'theoretical_output' => 'float',
        'actual_output' => 'float',
        'monthly_production' => 'float',
        'defective_products' => 'float',
        'good_products' => 'float',
        'availability' => 'float',
        'performance' => 'float',
        'quality' => 'float',
        'oee' => 'float',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Tính toán các chỉ số OEE
     */
    public function calculateOEE()
    {
        // Availability = C/B (Thời gian chạy máy thực tế / Thời gian chạy máy theo kế hoạch)
        if ($this->planned_runtime > 0) {
            $this->availability = ($this->actual_runtime / $this->planned_runtime) * 100;
        }

        // Performance = E/D (Năng suất thực tế / Năng suất lý thuyết)
        if ($this->theoretical_output > 0) {
            $this->performance = ($this->actual_output / $this->theoretical_output) * 100;
        }

        // Quality = H/F (Sản phẩm thực tế / Sản lượng thực tế)
        if ($this->monthly_production > 0) {
            $this->quality = ($this->good_products / $this->monthly_production) * 100;
        }

        // OEE = Availability * Performance * Quality
        $this->oee = ($this->availability * $this->performance * $this->quality) / 10000;

        return $this;
    }

    /**
     * Tính toán thời gian chạy máy theo kế hoạch
     */
    public function calculatePlannedRuntime()
    {
        $this->planned_runtime = $this->total_hours - $this->planned_downtime;
        return $this;
    }

    /**
     * Tính toán sản phẩm thực tế
     */
    public function calculateGoodProducts()
    {
        $this->good_products = $this->monthly_production - $this->defective_products;
        return $this;
    }

    /**
     * Tính toán năng suất thực tế
     */
    public function calculateActualOutput()
    {
        if ($this->actual_runtime > 0) {
            $this->actual_output = $this->monthly_production / $this->actual_runtime;
        }
        return $this;
    }
} 