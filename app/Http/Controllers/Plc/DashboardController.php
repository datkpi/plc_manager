<?php
namespace App\Http\Controllers\Plc;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\ProductionEntry;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\OEECalculationService;
use App\Services\RealtimeOEEService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\PlcData;

class DashboardController extends Controller
{
    protected $oeeService;
    protected $realtimeOEEService;

    public function __construct(OEECalculationService $oeeService, RealtimeOEEService $realtimeOEEService)
    {
        $this->oeeService = $oeeService;
        $this->realtimeOEEService = $realtimeOEEService;
    }

    public function index()
    {
        // Get basic stats
        $today = Carbon::today();
        
        $stats = [
            'total_machines' => Machine::where('status', 1)->count(),
            'total_products' => Product::count(),
            'total_production' => ProductionEntry::whereDate('date', $today)->sum('output_quantity'),
            'total_defect' => ProductionEntry::whereDate('date', $today)->sum('defect_weight'),
            'active_alerts' => 0, // Placeholder for future alert system
        ];
        
        // // Get today's OEE data for all machines
        $machines = Machine::where('status', 1)
            ->select('id', 'name', 'max_speed')
            ->get();
        // $todayOEE = [
        //     'daily' => [
        //         'oee' => 0,
        //         'availability' => 0,
        //         'performance' => 0,
        //         'quality' => 0
        //     ],
        //     'shifts' => []
        // ];

        // // Tính trung bình OEE của tất cả các máy
        // foreach ($machines as $machine) {
        //     $machineOEE = $this->realtimeOEEService->getRealtimeOEE($machine->id);
        //     if ($machineOEE) {
        //         $todayOEE['daily']['oee'] += $machineOEE['oee'] ?? 0;
        //         $todayOEE['daily']['availability'] += $machineOEE['availability'] ?? 0;
        //         $todayOEE['daily']['performance'] += $machineOEE['performance'] ?? 0;
        //         $todayOEE['daily']['quality'] += $machineOEE['quality'] ?? 0;
        //     }
        // }

        // // Tính trung bình
        // $machineCount = $machines->count();
        // if ($machineCount > 0) {
        //     $todayOEE['daily']['oee'] /= $machineCount;
        //     $todayOEE['daily']['availability'] /= $machineCount;
        //     $todayOEE['daily']['performance'] /= $machineCount;
        //     $todayOEE['daily']['quality'] /= $machineCount;
        // }

        // // 3. Tình trạng máy hiện tại
        // $machines = Machine::with(['plcData' => function($query) {
        //     $query->latest();
        // }])->get();

        // // 4. Top 5 sản phẩm có sản lượng cao nhất trong tuần
        // $topProducts = ProductionEntry::with('product')
        //     ->whereBetween('date', [now()->startOfWeek(), now()])
        //     ->selectRaw('product_code, sum(output_quantity) as total_quantity')
        //     ->groupBy('product_code')
        //     ->orderByDesc('total_quantity')
        //     ->limit(5)
        //     ->get();

        // // 5. Biểu đồ sản lượng 7 ngày gần nhất
        // $dailyProduction = ProductionEntry::whereBetween('date', [
        //         now()->subDays(6), now()
        //     ])
        //     ->selectRaw('date, sum(output_quantity) as total_quantity')
        //     ->groupBy('date')
        //     ->get();

        // // Thêm dữ liệu OEE chi tiết cho từng máy
        // $machineOEEDetails = [];
        // foreach ($machines as $machine) {
        //     $machineOEE = $this->realtimeOEEService->getRealtimeOEE($machine->id);
        //     $machineOEEDetails[$machine->id] = [
        //         'name' => $machine->name,
        //         'oee' => [
        //             'oee' => $machineOEE['oee'] ?? 0,
        //             'availability' => $machineOEE['availability'] ?? 0,
        //             'performance' => $machineOEE['performance'] ?? 0,
        //             'quality' => $machineOEE['quality'] ?? 0
        //         ]
        //     ];
        // }

        return view('plc.dashboard.index', compact(
            'stats',
            // 'todayOEE',
            'machines',
            // 'topProducts',
            // 'dailyProduction',
            // 'machineOEEDetails'
        ));
    }
    
    public function machineDetail($id)
    {
        $machine = Machine::findOrFail($id);
        
        // Get today's OEE for this machine
        $today = Carbon::today();
        $machineOEE = $this->oeeService->calculateMachineOEE($id, $today);
        
        // Get production data for the last 7 days
        $productionData = ProductionEntry::where('machine_id', $id)
            ->where('date', '>=', Carbon::today()->subDays(7))
            ->orderBy('date')
            ->get()
            ->groupBy(function($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            })
            ->map(function($entries) {
                return [
                    'output_quantity' => $entries->sum('output_quantity'),
                    'good_quantity' => $entries->sum('good_quantity'),
                    'defect_weight' => $entries->sum('defect_weight'),
                    'waste_weight' => $entries->sum('waste_weight')
                ];
            });
            
        // Get last 10 production entries
        $recentEntries = ProductionEntry::where('machine_id', $id)
            ->with('product')
            ->orderBy('date', 'desc')
            ->orderBy('shift', 'desc')
            ->limit(10)
            ->get();
            
        return view('plc.dashboard.machine-detail', compact('machine', 'machineOEE', 'productionData', 'recentEntries'));
    }

    // Thêm API endpoint để lấy dữ liệu OEE
    public function getMachineOEEData()
    {
        $today = Carbon::today();
        $machines = Machine::where('status', 1)->get();
        $oeeData = [];
        
        foreach ($machines as $machine) {
            $machineOEE = $this->oeeService->calculateMachineOEE($machine->id, $today);
            if ($machineOEE) {
                $oeeData[] = [
                    'machine' => $machine->name,
                    'availability' => $machineOEE['availability'] * 100,
                    'performance' => $machineOEE['performance'] * 100,
                    'quality' => $machineOEE['quality'] * 100,
                    'oee' => $machineOEE['oee'] * 100
                ];
            }
        }
        
        return response()->json($oeeData);
    }

    public function getOEEData(Request $request)
    {
        $machineId = $request->input('machine_id');
        $fromDate = $request->input('from_date', Carbon::today()->format('Y-m-d'));
        $toDate = $request->input('to_date', Carbon::today()->format('Y-m-d'));

        // Ghi log cho debugging
        // \Log::info("getOEEData request: machine=$machineId, from=$fromDate, to=$toDate");

        // Lấy dữ liệu OEE realtime
        $realtimeOEE = $this->realtimeOEEService->getRealtimeOEE($machineId);
        
        // Log thông tin realtime OEE
        // \Log::info("Realtime OEE values:", [
        //     'availability' => $realtimeOEE['availability'] ?? 0,
        //     'performance' => $realtimeOEE['performance'] ?? 0,
        //     'quality' => $realtimeOEE['quality'] ?? 0,
        //     'oee' => $realtimeOEE['oee'] ?? 0,
        //     'current_shift' => $realtimeOEE['current_shift'] ?? 'Unknown'
        // ]);

        // Tính lại giá trị OEE bằng cách nhân 3 giá trị
        $availabilityPercent = ($realtimeOEE['availability'] ?? 0) * 100;
        $performancePercent = ($realtimeOEE['performance'] ?? 0) * 100;
        $qualityPercent = ($realtimeOEE['quality'] ?? 0) * 100;
        
        // Tính OEE = (A * P * Q) / 10000
        $oeePercent = ($availabilityPercent * $performancePercent * $qualityPercent) / 10000;
        $realtimeOEE['oee'] = $oeePercent;

        // Lấy dữ liệu PLC mới nhất để hiển thị sản phẩm hiện tại
        $latestPlcData = \App\Models\PlcData::where('machine_id', $machineId)
            ->whereDate('created_at', Carbon::today())
            ->orderBy('id', 'desc')
            ->first();
        
        // Lấy tên sản phẩm hiện tại từ PLC
        $currentProduct = $latestPlcData ? $latestPlcData->datalog_data_ma_sp : null;
        $currentProductivity = $latestPlcData ? $latestPlcData->nang_suatkg_h : 0;
        
        // \Log::info("Latest PLC data found: " . ($latestPlcData ? 'Yes' : 'No') . 
        //           ", Product: " . ($currentProduct ?? 'N/A') . 
        //           ", Productivity: " . $currentProductivity);

        // Lấy kế hoạch sản xuất từ KHSXM (tổng số mét cần sản xuất)
        $planQuantity = $latestPlcData->khsxm ?? 0;
        // \Log::info("Kế hoạch sản xuất (mét): " . $planQuantity);

        // Lấy sản lượng thực tế từ realtime OEE
        $actualQuantity = $realtimeOEE['actual_quantity'] ?? 0;
        // \Log::info("Sản lượng thực tế từ realtime: " . $actualQuantity);

        // Cache key cho dữ liệu lịch sử
        $cacheKey = "machine_oee_history_{$machineId}_{$fromDate}_{$toDate}";
        
        // Xóa cache để đảm bảo dữ liệu luôn mới nhất
        Cache::forget($cacheKey);
        
        // Lấy dữ liệu lịch sử từ cache
        $historyData = Cache::remember($cacheKey, 60, function() use ($machineId, $fromDate, $toDate) {
            $machine = Machine::select('id', 'name', 'max_speed')->findOrFail($machineId);
            $oeeData = [];
            $currentDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);

            // \Log::info("Calculating OEE history from {$fromDate} to {$toDate}");

            while ($currentDate <= $endDate) {
                $dailyOEE = $this->oeeService->calculateDailyOEE($machineId, $currentDate->format('Y-m-d'));
                if ($dailyOEE) {
                    $oeeData[] = [
                        'date' => $currentDate->format('Y-m-d'),
                        'oee' => round($dailyOEE['daily']['oee'] * 100, 2),
                        'availability' => round($dailyOEE['daily']['availability'] * 100, 2),
                        'performance' => round($dailyOEE['daily']['performance'] * 100, 2),
                        'quality' => round($dailyOEE['daily']['quality'] * 100, 2)
                    ];
                }
                $currentDate->addDay();
            }

            return [
                'machine' => $machine,
                'oee_history' => $oeeData
            ];
        });

        // Trả về dữ liệu cho dashboard
        return [
            'machine' => [
                'name' => $historyData['machine']->name,
                'current_product' => $currentProduct,
                'plan_quantity' => $planQuantity,
                'actual_quantity' => round($actualQuantity, 2),
                'completion_rate' => $planQuantity > 0 
                    ? round(($actualQuantity / $planQuantity) * 100, 2) 
                    : 0,
                'current_speed' => $currentProductivity,
                'max_speed' => $historyData['machine']->max_speed
            ],
            'oee_trend' => $historyData['oee_history'],
            'current_oee' => [
                'oee' => round($realtimeOEE['oee'], 2),
                'availability' => round(($realtimeOEE['availability'] ?? 0) * 100, 2),
                'performance' => round(($realtimeOEE['performance'] ?? 0) * 100, 2),
                'quality' => round(($realtimeOEE['quality'] ?? 0) * 100, 2),
                'shift' => $realtimeOEE['current_shift'] ?? ''
            ],
            'realtime_info' => [
                'shift' => $realtimeOEE['current_shift'] ?? '',
                'running_time' => $realtimeOEE['running_time'] ?? 0,
                'shift_start_time' => isset($realtimeOEE['shift_start_time']) ? $realtimeOEE['shift_start_time']->format('H:i') : '',
                'current_time' => isset($realtimeOEE['current_time']) ? $realtimeOEE['current_time']->format('H:i') : date('H:i'),
                'estimated_completion_time' => isset($realtimeOEE['estimated_completion_time']) 
                    ? 'còn ' . sprintf('%02d:%02d', floor($realtimeOEE['estimated_completion_time'] / 60), $realtimeOEE['estimated_completion_time'] % 60)
                    : 'còn 00:00'
            ]
        ];
    }

    public function calculateAvailability($machineId, $shift) {
        // 1. Lấy dữ liệu PLC của ca hiện tại
        $plcData = PlcData::where('machine_id', $machineId)
            ->where('datalog_data_ca', $shift)
            ->orderBy('id', 'desc')  // Sắp xếp giảm dần để lấy bản ghi mới nhất
            ->first();

        // 2. Lấy giờ chạy 2 từ bản ghi cuối cùng của ca
        $runTimeMinutes = $plcData ? $plcData->datalog_data_gio_chay_2 : 0;

        // 3. Tính Availability = Thời gian chạy / (8 giờ * 60 phút)
        $availability = $runTimeMinutes / (8 * 60);

        // 4. Giới hạn tối đa là 1 (100%)
        return min(1, $availability);
    }
}
