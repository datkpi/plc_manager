<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionEntry extends Model
{
    protected $table = 'production_entries';

    protected $fillable = [
        'machine_id',
        'product_code',
        'shift',
        'output_quantity',
        'good_quantity',
        'defect_weight',
        'waste_weight',
        'operator_team',
        'operator_name',
        'machine_operator',
        'quality_checker',
        'warehouse_staff',
        'product_length',
        'date',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'output_quantity' => 'integer',
        'good_quantity' => 'integer',
        'defect_weight' => 'decimal:2',
        'waste_weight' => 'decimal:2',
        'product_length' => 'integer'
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_code', 'code');
    }

    // Tính tổng phế
    public function getTotalWasteAttribute()
    {
        return $this->defect_weight;
    }

    // Tính tỷ lệ phế
    public function getWasteRateAttribute()
    {
        // Khối lượng đạt được tính dựa trên số lượng đạt và định mức
        $product = $this->product;
        if (!$product) {
            return 0;
        }
        
        $productLength = $this->product_length ?: 0;
        $gm = $product->gm_spec ?? 0;
        $goodWeight = ($gm * $productLength * $this->good_quantity) / 1000;
        
        $total = $goodWeight + $this->defect_weight;
        return $total > 0 ? ($this->defect_weight / $total) * 100 : 0;
    }
}
