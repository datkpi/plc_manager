<?php

namespace App\Http\Controllers\Plc\Api;

use App\Http\Controllers\Controller;
use App\Models\PeCoilStandard;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Lấy chiều dài tiêu chuẩn dựa trên đường kính và vật liệu
     */
    public function getStandardLength(Request $request)
    {
        $diameter = $request->input('diameter', 0);
        $material = $request->input('material', '');
        $productCode = $request->input('product_code', '');
        
        \Log::info('API getStandardLength - Tham số: ', [
            'diameter' => $diameter,
            'material' => $material,
            'product_code' => $productCode,
            'all_params' => $request->all()
        ]);
        
        // 0. Nếu có mã sản phẩm, kiểm tra chiều dài trong production_entries gần nhất
        if ($productCode) {
            $lastEntry = \App\Models\ProductionEntry::where('product_code', $productCode)
                ->orderBy('created_at', 'desc')
                ->first();
                
            if ($lastEntry && $lastEntry->product_length > 0) {
                \Log::info('Found from last entry: ' . $lastEntry->product_length);
                return response()->json([
                    'length' => $lastEntry->product_length,
                    'source' => 'latest_entry'
                ]);
            }
        }
        
        // 1. Lấy từ database nếu có
        $standard = PeCoilStandard::where('diameter', $diameter)->first();
        if ($standard && $standard->length > 0) {
            \Log::info('Found from pe_standard: ' . $standard->length);
            return response()->json([
                'length' => $standard->length,
                'source' => 'pe_standard'
            ]);
        }
        
        // 2. Áp dụng quy tắc nếu không có trong database
        $standardLength = 100; // Mặc định
        $source = 'default_rules';
        
        // PPR: mặc định 4m
        if ($material == 'PPR') {
            $standardLength = 4;
        }
        // PE80, PE100: dựa vào đường kính
        else if ($material == 'PE80' || $material == 'PE100') {
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

                $standardLength = $defaultLengths[$diameter] ?? 100;
            }
            // PE DN ≥ 110mm: mặc định 6m
            else {
                $standardLength = 6;
            }
        }
        // PSU: mặc định 6m
        else if (strpos($material, 'PSU') !== false) {
            $standardLength = 6;
        }
        
        \Log::info('Using default rule: ' . $standardLength);
        
        return response()->json([
            'length' => $standardLength,
            'source' => $source
        ]);
    }
} 