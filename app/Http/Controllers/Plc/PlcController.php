<?php

namespace App\Http\Controllers\Plc;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\PlcAlert;
use App\Models\ProductionEntry;
use App\Models\Product;
use App\Services\OEECalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\PlcData;

class PlcController extends Controller
{
    public function index()
    {
        // Số liệu tổng quan
        $stats = [
            'total_machines' => Machine::where('status', true)->count(),
            'total_products' => Product::count(),
            'total_production' => ProductionEntry::whereDate('date', today())->sum('output_quantity'),
            'active_alerts' => PlcAlert::where('status', 'active')->count(),
        ];

        // OEE trung bình hôm nay
        $oeeService = new OEECalculationService();
        $todayOEE = $oeeService->calculateDailyOEE(null, today());

        // Lấy bản ghi PLC mới nhất
        $latestPlcData = PlcData::where('machine_id', $machineId)
            ->orderBy('created_at', 'desc')
            ->first();

        return view('plc.index', compact('stats', 'todayOEE', 'latestPlcData'));
    }
}
