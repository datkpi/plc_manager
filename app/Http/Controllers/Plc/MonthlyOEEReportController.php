<?php

namespace App\Http\Controllers\Plc;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Machine;
use App\Models\MonthlyOEE;
use App\Models\DatalogData;
use App\Models\PlannedDowntime;
use Carbon\Carbon;

class MonthlyOEEReportController extends Controller
{
    /**
     * Hiển thị báo cáo OEE tháng
     */
    public function index()
    {
        dd('1');
        $machines = Machine::where('status', 1)->get();
        $year = request('year', Carbon::now()->year);
        $month = request('month', Carbon::now()->month);
        $machineId = request('machine_id');
        $type = request('type', 'daily'); // daily: trung bình từ OEE ngày, form: từ form nhập

        $monthlyOEE = null;
        $selectedMachine = null;

        if ($machineId) {
            $selectedMachine = Machine::findOrFail($machineId);
            
            if ($type == 'daily') {
                $monthlyOEE = $this->calculateFromDaily($selectedMachine, $year, $month);
            } else {
                $monthlyOEE = MonthlyOEE::where('machine_id', $machineId)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->first();
            }
        }

        return view('plc.reports.oee.monthly', compact(
            'machines',
            'year',
            'month',
            'selectedMachine',
            'monthlyOEE',
            'type'
        ));
    }

    /**
     * Tính OEE từ trung bình OEE ngày
     */
    private function calculateFromDaily($machine, $year, $month)
    {
        // Lấy dữ liệu từ bảng datalog_data cho tháng được chọn
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        // Tính toán các thông số
        $monthlyOEE = [
            'total_hours' => $endDate->diffInDays($startDate) * 24, // A
            'planned_downtime' => 0, // Lấy từ bảng planned_downtimes
            'actual_runtime' => 0, // C - Lấy từ datalog_data_gio_chay_2
            'theoretical_output' => $machine->max_speed, // D
            'actual_output' => 0, // E - Tính từ sản lượng thực tế
            'monthly_production' => 0, // F - Tổng sản lượng thực tế
            'defective_products' => 0, // G - Tổng sản phẩm lỗi
            'good_products' => 0, // H - F - G
        ];

        // Lấy thời gian ngừng máy có kế hoạch
        $plannedDowntimes = PlannedDowntime::where('machine_id', $machine->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('status', 'completed')
            ->sum('actual_hours');
        $monthlyOEE['planned_downtime'] = $plannedDowntimes;

        // B = A - planned_downtime
        $monthlyOEE['planned_runtime'] = $monthlyOEE['total_hours'] - $monthlyOEE['planned_downtime'];

        // Lấy dữ liệu từ bảng datalog_data
        $lastRecord = DatalogData::where('machine_id', $machine->id)
            ->whereBetween('ngay', [$startDate, $endDate])
            ->where('toc_do', '>=', 50)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastRecord) {
            // C - Thời gian chạy máy thực tế
            $monthlyOEE['actual_runtime'] = $lastRecord->datalog_data_gio_chay_2;
            
            // E - Năng suất thực tế
            $monthlyOEE['actual_output'] = $lastRecord->nang_suatkg_h;
            
            // F - Sản lượng thực tế
            $monthlyOEE['monthly_production'] = DatalogData::where('machine_id', $machine->id)
                ->whereBetween('ngay', [$startDate, $endDate])
                ->where('toc_do', '>=', 50)
                ->sum('san_luong_thuc_te');

            // G - Phế phẩm
            $monthlyOEE['defective_products'] = DatalogData::where('machine_id', $machine->id)
                ->whereBetween('ngay', [$startDate, $endDate])
                ->where('toc_do', '>=', 50)
                ->sum('phe_pham');

            // H - Sản phẩm thực tế
            $monthlyOEE['good_products'] = $monthlyOEE['monthly_production'] - $monthlyOEE['defective_products'];

            // Tính các chỉ số OEE
            // Availability = C/B
            $monthlyOEE['availability'] = ($monthlyOEE['actual_runtime'] / $monthlyOEE['planned_runtime']) * 100;
            
            // Performance = E/D
            $monthlyOEE['performance'] = ($monthlyOEE['actual_output'] / $monthlyOEE['theoretical_output']) * 100;
            
            // Quality = H/F
            $monthlyOEE['quality'] = ($monthlyOEE['good_products'] / $monthlyOEE['monthly_production']) * 100;
            
            // OEE = Availability * Performance * Quality / 10000
            $monthlyOEE['oee'] = ($monthlyOEE['availability'] * $monthlyOEE['performance'] * $monthlyOEE['quality']) / 10000;
        }

        return $monthlyOEE;
    }
} 