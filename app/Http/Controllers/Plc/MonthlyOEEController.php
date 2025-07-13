<?php

namespace App\Http\Controllers\Plc;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Machine;
use App\Models\MonthlyOEE;
use App\Models\Shift;
use App\Services\MonthlyOEEService;
use Carbon\Carbon;
use App\Models\Datalog;
use App\Models\PlcData;

class MonthlyOEEController extends Controller
{
    protected $monthlyOEEService;

    public function __construct(MonthlyOEEService $monthlyOEEService)
    {
        $this->monthlyOEEService = $monthlyOEEService;
    }

    /**
     * Hiển thị báo cáo OEE tháng hoặc form nhập liệu
     */
    public function report(Request $request)
    {
        $machines = Machine::where('status', 1)->get();
        $machineId = $request->input('machine_id');
        
        // Lấy thời gian mặc định là tháng hiện tại
        $now = Carbon::now();
        $fromDate = $request->input('from_month') 
            ? Carbon::createFromFormat('Y-m', $request->input('from_month'))
            : $now->copy()->startOfMonth();
        $toDate = $request->input('to_month')
            ? Carbon::createFromFormat('Y-m', $request->input('to_month'))
            : $now->copy()->startOfMonth();

        // Nếu là route monthly-oee-form thì hiển thị form nhập liệu
        if ($request->route()->getName() === 'plc.reports.monthly-oee-form') {
            $monthlyOEEs = collect();
            if ($machineId) {
                // Tạo danh sách các tháng cần lọc
                $startDate = $fromDate->copy();
                $endDate = $toDate->copy();

                // Lấy dữ liệu OEE cho từng tháng
                while ($startDate <= $endDate) {
                    $oee = MonthlyOEE::where('machine_id', $machineId)
                        ->where('year', $startDate->year)
                        ->where('month', $startDate->month)
                        ->first();
                    
                    if ($oee) {
                        $monthlyOEEs->push($oee);
                    }
                    
                    $startDate->addMonth();
                }
            }
            
            return view('plc.reports.monthly-form', compact(
                'machines',
                'monthlyOEEs',
                'machineId',
                'fromDate',
                'toDate'
            ));
        }

        $selectedMachine = null;
        $monthlyOEEList = [];

        if ($machineId) {
            $selectedMachine = Machine::findOrFail($machineId);
            
            // Tạo danh sách các tháng cần lọc
            $startDate = $fromDate->copy();
            $endDate = $toDate->copy();

            while ($startDate <= $endDate) {
                    // Lấy dữ liệu thực tế từ service
                    $actual_runtime = 0;
                    $year = $startDate->year;
                    $month = $startDate->month;
                    $startOfMonth = Carbon::create($year, $month, 1, 0, 0, 0);
                    $endOfMonth = $startOfMonth->copy()->endOfMonth();
                    $caList = ['CA1', 'CA2', 'CA3'];

                    // Lấy thời gian chạy máy thực tế từ plc_data
                    for ($date = $startOfMonth->copy(); $date->lte($endOfMonth); $date->addDay()) {
                        foreach ($caList as $ca) {
                            $lastRecord = PlcData::where('machine_id', $machineId)
                                ->whereDate('datalog_date', $date->format('Y-m-d'))
                                ->where('datalog_data_ca', $ca)
                                ->orderBy('created_at', 'desc')
                                ->first();
                            if ($lastRecord) {
                                $actual_runtime += $lastRecord->datalog_data_gio_chay_2 ?? 0;
                            }
                        }
                    }

                    // Tính toán OEE với thời gian chạy máy thực tế mới
                    $oee = $this->monthlyOEEService->calculateMonthlyOEE($machineId, $year, $month);
                    if ($oee) {
                        $monthlyOEEList[] = [
                            'month' => $startDate->copy(),
                            'oee' => $oee
                        ];
                    }
                
                $startDate->addMonth();
            }
        }

        return view('plc.reports.monthly-oee', compact(
            'machines',
            'selectedMachine',
            'fromDate',
            'toDate',
            'monthlyOEEList'
        ));
    }

    /**
     * Hiển thị danh sách OEE tháng
     */
    public function index()
    {
        $monthlyOEEs = MonthlyOEE::with('machine')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(10);

        return view('plc.monthly-oee.index', compact('monthlyOEEs'));
    }

    /**
     * Hiển thị form tạo mới
     */
    public function create()
    {
        $machines = Machine::all();
        $shifts = Shift::all();
        
        return view('plc.monthly-oee.create', compact('machines', 'shifts'));
    }

    /**
     * Lưu dữ liệu mới
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machine,id',
            'month_year' => 'required|date_format:Y-m',
            'planned_runtime' => 'required|numeric|min:0',
        ]);

        // Tách năm và tháng từ month_year
        [$year, $month] = explode('-', $request->month_year);

        // Kiểm tra xem đã có dữ liệu của tháng này chưa
        $exists = MonthlyOEE::where('machine_id', $request->machine_id)
            ->where('year', $year)
            ->where('month', $month)
            ->exists();

        if ($exists) {
            return back()->withErrors(['message' => 'Đã tồn tại dữ liệu OEE cho máy này trong tháng đã chọn'])
                        ->withInput();
        }

        $monthlyOEE = new MonthlyOEE([
            'machine_id' => $request->machine_id,
            'year' => $year,
            'month' => $month,
            'unplanned_downtime' => $request->unplanned_downtime,
            'created_by' => auth()->id(),
        ]);
        $monthlyOEE->save();

        return redirect()->route('plc.reports.monthly-oee.index')
            ->with('success', 'Đã thêm dữ liệu OEE tháng thành công');
    }

    /**
     * Hiển thị form chỉnh sửa
     */
    public function edit($id)
    {
        $monthlyOEE = MonthlyOEE::findOrFail($id);
        $machines = Machine::all();
        $shifts = Shift::all();
        // dd($monthlyOEE);
        return view('plc.monthly-oee.edit', compact('monthlyOEE', 'machines', 'shifts'));
    }

    /**
     * Cập nhật dữ liệu
     */
    public function update(Request $request, $id)
    {
        $monthlyOEE = MonthlyOEE::findOrFail($id);

        $validated = $request->validate([
            'machine_id' => 'required|exists:machine,id',
            'month_year' => 'required|date_format:Y-m',
            'planned_runtime' => 'required|numeric|min:0',
        ]);

        [$year, $month] = explode('-', $request->month_year);

        // Kiểm tra xem đã có dữ liệu của tháng này chưa (trừ record hiện tại)
        $exists = MonthlyOEE::where('machine_id', $request->machine_id)
            ->where('year', $year)
            ->where('month', $month)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['message' => 'Đã tồn tại dữ liệu OEE cho máy này trong tháng đã chọn']);
        }

        $monthlyOEE->fill([
            'machine_id' => $request->machine_id,
            'year' => $year,
            'month' => $month,
            'unplanned_downtime' => $request->unplanned_downtime,
            'updated_by' => auth()->id(),
        ]);
        $monthlyOEE->save();

        return redirect()->route('plc.reports.monthly-oee.index')
            ->with('success', 'Đã cập nhật dữ liệu OEE tháng thành công');
    }

    /**
     * Xóa dữ liệu
     */
    public function destroy($id)
    {
        $monthlyOEE = MonthlyOEE::findOrFail($id);
        $monthlyOEE->delete();

        return redirect()->route('plc.reports.monthly-oee')
            ->with('success', 'Đã xóa dữ liệu OEE tháng thành công');
    }

    /**
     * Hiển thị form nhập liệu OEE tháng
     */
    public function showForm(Request $request)
    {
        $machines = Machine::where('status', 1)->get();
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);
        $machineId = $request->input('machine_id');

        $monthlyOEE = null;
        if ($machineId) {
            $monthlyOEE = MonthlyOEE::where('machine_id', $machineId)
                ->where('year', $year)
                ->where('month', $month)
                ->first();
        }

        return view('plc.reports.monthly-form', compact(
            'machines',
            'monthlyOEE',
            'year',
            'month',
            'machineId'
        ));
    }

    /**
     * Lưu dữ liệu từ form
     */
    public function saveForm(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'required|integer|min:1|max:12',
            'total_hours' => 'required|numeric|min:0',
            'planned_downtime' => 'required|numeric|min:0',
            'planned_runtime' => 'required|numeric|min:0',
            'unplanned_downtime' => 'required|numeric|min:0',
            'actual_runtime' => 'required|numeric|min:0',
            'theoretical_output' => 'required|numeric|min:0',
            'actual_output' => 'required|numeric|min:0',
            'monthly_production' => 'required|numeric|min:0',
            'defective_products' => 'required|numeric|min:0',
            'good_products' => 'required|numeric|min:0',
        ]);

        // Kiểm tra xem đã có dữ liệu của tháng này chưa
        $exists = MonthlyOEE::where('machine_id', $request->machine_id)
            ->where('year', $request->year)
            ->where('month', $request->month)
            ->exists();

        if ($exists) {
            return back()->withErrors(['message' => 'Đã tồn tại dữ liệu OEE cho máy này trong tháng đã chọn'])
                        ->withInput();
        }

        // Tính toán các chỉ số OEE
        $availability = $request->actual_runtime / $request->planned_runtime * 100;
        $performance = ($request->actual_output / $request->theoretical_output) * 100;
        $quality = $request->good_products / $request->monthly_production * 100;
        $oee = ($availability * $performance * $quality) / 10000;

        $monthlyOEE = new MonthlyOEE([
            'machine_id' => $request->machine_id,
            'year' => $request->year,
            'month' => $request->month,
            'planned_downtime' => $request->planned_downtime,
            'availability' => $availability,
            'performance' => $performance,
            'quality' => $quality,
            'oee' => $oee,
            'created_by' => auth()->id(),
        ]);
        $monthlyOEE->save();

        return redirect()->route('plc.reports.oee.monthly')
            ->with('success', 'Đã thêm dữ liệu OEE tháng thành công');
    }

    /**
     * Xuất báo cáo Excel
     */
    public function export(Request $request)
    {
        $machineId = $request->input('machine_id');
        $fromDate = $request->input('from_month') 
            ? Carbon::createFromFormat('Y-m', $request->input('from_month'))
            : Carbon::now()->startOfMonth();
        $toDate = $request->input('to_month')
            ? Carbon::createFromFormat('Y-m', $request->input('to_month'))
            : Carbon::now()->startOfMonth();

        $monthlyOEEList = collect();
        $selectedMachine = null;

        if ($machineId) {
            $selectedMachine = Machine::findOrFail($machineId);
            
            // Tạo danh sách các tháng cần lọc
            $startDate = $fromDate->copy();
            $endDate = $toDate->copy();

            // Lấy dữ liệu OEE cho từng tháng
            while ($startDate <= $endDate) {
                $year = $startDate->year;
                $month = $startDate->month;
                
                $oee = $this->monthlyOEEService->calculateMonthlyOEE($machineId, $year, $month);
                if ($oee) {
                    $monthlyOEEList->push([
                        'month' => $startDate->format('m/Y'),
                        'oee' => $oee
                    ]);
                }
                
                $startDate->addMonth();
            }
        }

        // Tạo file Excel
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('OEE Report');

        // Set header
        $sheet->setCellValue('A1', 'Báo cáo OEE từ tháng ' . $fromDate->format('m/Y') . ' đến ' . $toDate->format('m/Y'));
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        if ($selectedMachine) {
            $sheet->setCellValue('A2', 'Máy: ' . $selectedMachine->name);
            $sheet->mergeCells('A2:E2');
            $sheet->getStyle('A2')->getFont()->setBold(true);
        }

        // Bảng báo cáo dạng bảng với các tháng
        $sheet->setCellValue('A4', 'Tháng');
        $sheet->setCellValue('B4', 'Availability (%)');
        $sheet->setCellValue('C4', 'Performance (%)');
        $sheet->setCellValue('D4', 'Quality (%)');
        $sheet->setCellValue('E4', 'OEE (%)');
        
        $sheet->getStyle('A4:E4')->getFont()->setBold(true);
        $sheet->getStyle('A4:E4')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDDDDD');
        $sheet->getStyle('A4:E4')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Set data
        $row = 5;
        foreach ($monthlyOEEList as $item) {
            $sheet->setCellValue('A' . $row, $item['month']);
            $sheet->setCellValue('B' . $row, $item['oee']['metrics']['availability']);
            $sheet->setCellValue('C' . $row, $item['oee']['metrics']['performance']);
            $sheet->setCellValue('D' . $row, $item['oee']['metrics']['quality']);
            $sheet->setCellValue('E' . $row, $item['oee']['metrics']['oee']);
            $row++;
        }

        // Thêm hàng tính trung bình nếu có nhiều hơn 1 tháng
        if ($monthlyOEEList->count() > 1) {
            $sheet->setCellValue('A' . $row, 'Trung bình');
            $sheet->setCellValue('B' . $row, '=AVERAGE(B5:B' . ($row-1) . ')');
            $sheet->setCellValue('C' . $row, '=AVERAGE(C5:C' . ($row-1) . ')');
            $sheet->setCellValue('D' . $row, '=AVERAGE(D5:D' . ($row-1) . ')');
            $sheet->setCellValue('E' . $row, '=AVERAGE(E5:E' . ($row-1) . ')');
            $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
            $row++;
        }

        // Auto size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Đặt border cho bảng
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A4:E' . ($row - 1))->applyFromArray($styleArray);
        
        // Format percentage cells
        $sheet->getStyle('B5:E' . ($row - 1))->getNumberFormat()
            ->setFormatCode('0.00"%"');

        // Align center for data
        $sheet->getStyle('A5:E' . ($row - 1))->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Create Excel file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $machine = $selectedMachine ? $selectedMachine->name : 'all';
        $fileName = 'OEE_' . $machine . '_' . 
                   $fromDate->format('m-Y') . '_' . $toDate->format('m-Y') . '.xlsx';

        // Return response
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
} 