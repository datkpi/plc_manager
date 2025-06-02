<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\PlcData;
use App\Models\PlannedDowntime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyOEEService
{
    /**
     * Tính OEE tháng theo phương án không chia theo sản phẩm
     */
    public function calculateMonthlyOEE($machineId, $year, $month)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        $totalDays = $endDate->diffInDays($startDate) + 1;
        
        // Tổng thời gian và thời gian ngừng máy theo kế hoạch
        $totalHours = $totalDays * 24;

        // Lấy máy
        $machine = Machine::find($machineId);
        $maxSpeed = $machine ? $machine->max_speed : 0;

        // Lấy thời gian không có kế hoạch (unplanned_downtime) từ monthly_oee nếu có
        $monthlyOEE = \App\Models\MonthlyOEE::where('machine_id', $machineId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
        $plannedDowntime = $monthlyOEE ? $monthlyOEE->planned_downtime : 0;
        
        // Thời gian chạy máy theo kế hoạch
        $plannedRuntime = $totalHours - $plannedDowntime;

        // Lấy thời gian chạy máy thực tế từ plc_data
        $actualRuntime = 0;
        $caList = ['CA1', 'CA2', 'CA3'];
        
        // Xác định ngày và ca hiện tại
        $today = Carbon::now()->format('Y-m-d');
        $currentCa = $this->getCurrentShift(Carbon::now());

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            foreach ($caList as $ca) {
                // Kiểm tra xem có phải là ca hiện tại trong tháng hiện tại không
                $isCurrentShift = ($date->format('Y-m-d') == $today && 
                                   $ca == $currentCa && 
                                   $date->format('Y-m') == Carbon::now()->format('Y-m'));
                
                $lastRecord = PlcData::where('machine_id', $machineId)
                    ->whereDate('datalog_date', $date->format('Y-m-d'))
                    ->where('datalog_data_ca', $ca)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($lastRecord) {
                    $actualRuntime += $lastRecord->datalog_data_gio_chay_2 ?? 0;
                    
                    // Nếu là ca hiện tại và máy đang chạy, ghi log
                    if ($isCurrentShift) {
                        \Log::info("Ca hiện tại ($ca): Đã cộng thời gian chạy máy thực tế: " . 
                                   ($lastRecord->datalog_data_gio_chay_2 ?? 0) . " phút");
                    }
                }
            }
        }

        $actualRuntime = $actualRuntime / 60; 
        
        // Tính thời gian ngừng máy không có kế hoạch (từ công thức)
        $unplannedDowntime = $plannedRuntime - $actualRuntime;
        // Đảm bảo không âm
        $unplannedDowntime = max(0, $unplannedDowntime);

        // Lấy dữ liệu sản xuất trong tháng từ production_entries
        $entries = \App\Models\ProductionEntry::where('machine_id', $machineId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        // Quy đổi output_quantity sang kg
        $totalOutputKg = 0;
        $totalDefectWeight = 0;
        foreach($entries as $entry) {
            // Quy đổi ra kg: gm_spec * product_length * output_quantity / 1000
            $product = $entry->product;
            if ($product && $product->gm_spec > 0 && $entry->product_length > 0) {
                $totalOutputKg += ($product->gm_spec * $entry->product_length * $entry->output_quantity) / 1000;
            }
            $totalDefectWeight += $entry->defect_weight;
        }
        
        // Tổng sản phẩm thực tế = Tổng sản lượng - Phế phẩm
        $totalGoodOutputKg = $totalOutputKg - $totalDefectWeight;
        
        // Năng suất thực tế của máy (kg/giờ)
        $actualProductivity = ($actualRuntime > 0) ? $totalOutputKg / $actualRuntime : 0;

        // Availability (A)
        $availability = ($plannedRuntime > 0) ? $actualRuntime / $plannedRuntime : 0;

        // Performance (P) 
        $performance = ($maxSpeed > 0) ? $actualProductivity / $maxSpeed : 0;

        // Quality (Q)
        $quality = ($totalOutputKg > 0) ? $totalGoodOutputKg / $totalOutputKg : 0;

        // OEE
        $oee = $availability * $performance * $quality;

        return [
            'parameters' => [
                'total_hours' => $totalHours,
                'planned_downtime' => $plannedDowntime,
                'planned_runtime' => $plannedRuntime,
                'unplanned_downtime' => $unplannedDowntime,
                'actual_runtime' => $actualRuntime,
                'max_speed' => $maxSpeed,
                'actual_productivity' => $actualProductivity,
                'total_output_kg' => $totalOutputKg,
                'total_defect_weight' => $totalDefectWeight,
                'total_good_output_kg' => $totalGoodOutputKg,
            ],
            'metrics' => [
                'availability' => $availability * 100,
                'performance' => $performance * 100,
                'quality' => $quality * 100,
                'oee' => $oee * 100,
            ]
        ];
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
} 