<?php

namespace App\Http\Controllers\Plc;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Product;
use App\Models\ProductionEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductionReportController extends Controller
{
    /**
     * Hiển thị báo cáo sản xuất theo form mẫu THSX
     */
    public function index(Request $request)
    {
        // Lấy tham số lọc
        $fromDate = $request->input('from_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $toDate = $request->input('to_date', Carbon::now()->format('Y-m-d'));
        $machineId = $request->input('machine_id');
        $productCode = $request->input('product_code');
        
        // Danh sách để lọc
        $machines = Machine::orderBy('name')->get();
        $products = Product::orderBy('code')->get();
        
        // Xây dựng query cơ bản
        $query = ProductionEntry::with(['machine', 'product'])
            ->whereBetween('date', [$fromDate, $toDate])
            ->orderBy('date')
            ->orderBy('shift');
        
        // Thêm bộ lọc tùy chọn
        if ($machineId) {
            $query->where('machine_id', $machineId);
        }
        
        if ($productCode) {
            $query->where('product_code', $productCode);
        }
        
        // Lấy dữ liệu
        $entries = $query->get();
        
        // Tổng hợp dữ liệu theo ngày để hiển thị trong báo cáo
        $reportData = [];
        $totals = [
            'output_quantity' => 0,
            'good_quantity' => 0,
            'defect_weight' => 0,
            'waste_weight' => 0,
            'total_weight' => 0
        ];
        
        foreach ($entries as $entry) {
            // Chuyển đổi date thành đối tượng Carbon trước khi gọi format()
            $dateKey = Carbon::parse($entry->date)->format('Y-m-d');
            
            if (!isset($reportData[$dateKey])) {
                $reportData[$dateKey] = [
                    'date' => Carbon::parse($entry->date),
                    'entries' => collect([]),
                    'daily_totals' => [
                        'CA1' => [
                            'output_quantity' => 0,
                            'good_quantity' => 0,
                            'defect_weight' => 0,
                            'waste_weight' => 0,
                            'total_weight' => 0
                        ],
                        'CA2' => [
                            'output_quantity' => 0,
                            'good_quantity' => 0,
                            'defect_weight' => 0,
                            'waste_weight' => 0,
                            'total_weight' => 0
                        ],
                        'CA3' => [
                            'output_quantity' => 0,
                            'good_quantity' => 0,
                            'defect_weight' => 0,
                            'waste_weight' => 0,
                            'total_weight' => 0
                        ]
                    ]
                ];
            }
            
            // Tính khối lượng sản phẩm (kg)
            $productWeight = 0;
            if ($entry->product && $entry->product->gm_spec > 0 && $entry->product_length > 0) {
                $gramPerMeter = $entry->product->gm_spec;
                $productWeight = ($gramPerMeter * $entry->product_length * $entry->output_quantity) / 1000;
            }
            
            // Thêm thông tin khối lượng vào entry
            $entry->product_weight = $productWeight;
            $entry->total_weight = $productWeight;
            
            // Thêm entry vào ngày tương ứng
            $reportData[$dateKey]['entries']->push($entry);
            
            // Cập nhật tổng theo ca
            $reportData[$dateKey]['daily_totals'][$entry->shift]['output_quantity'] += $entry->output_quantity;
            $reportData[$dateKey]['daily_totals'][$entry->shift]['good_quantity'] += $entry->good_quantity;
            $reportData[$dateKey]['daily_totals'][$entry->shift]['defect_weight'] += $entry->defect_weight;
            $reportData[$dateKey]['daily_totals'][$entry->shift]['waste_weight'] += $entry->waste_weight;
            $reportData[$dateKey]['daily_totals'][$entry->shift]['total_weight'] += $productWeight;
            
            // Cập nhật tổng toàn bộ
            $totals['output_quantity'] += $entry->output_quantity;
            $totals['good_quantity'] += $entry->good_quantity;
            $totals['defect_weight'] += $entry->defect_weight;
            $totals['waste_weight'] += $entry->waste_weight;
            $totals['total_weight'] += $productWeight;
        }
        
        // Lấy thông tin tháng/năm cho tiêu đề báo cáo
        $reportMonth = Carbon::parse($fromDate)->format('m');
        $reportYear = Carbon::parse($fromDate)->format('Y');
        
        return view('plc.reports.production.index', compact(
            'fromDate', 'toDate', 'machineId', 'productCode',
            'machines', 'products', 'reportData', 'totals',
            'reportMonth', 'reportYear'
        ));
    }
    
    /**
     * Export báo cáo ra Excel
     */
    public function exportExcel(Request $request)
    {
        // Lấy tham số lọc giống như trong index
        $fromDate = $request->input('from_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $toDate = $request->input('to_date', Carbon::now()->format('Y-m-d'));
        $machineId = $request->input('machine_id');
        $productCode = $request->input('product_code');
        
        // Xây dựng query giống như trong index
        $query = ProductionEntry::with(['machine', 'product'])
            ->whereBetween('date', [$fromDate, $toDate])
            ->orderBy('date')
            ->orderBy('shift');
        
        if ($machineId) {
            $query->where('machine_id', $machineId);
        }
        
        if ($productCode) {
            $query->where('product_code', $productCode);
        }
        
        $entries = $query->get();
        
        // Tạo spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Đặt tiêu đề
        $sheet->setCellValue('A1', 'TÌNH HÌNH SẢN XUẤT NGÀY CHI TIẾT');
        $sheet->mergeCells('A1:U1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        $reportMonth = Carbon::parse($fromDate)->format('m');
        $reportYear = Carbon::parse($fromDate)->format('Y');
        $sheet->setCellValue('A2', "Tháng {$reportMonth} năm {$reportYear}");
        $sheet->mergeCells('A2:U2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Đặt tiêu đề cột
        $headers = [
            'A' => 'Ngày',
            'B' => 'Mã máy',
            'C' => 'Tên máy',
            'D' => 'Mã sản phẩm',
            'E' => 'Tên sản phẩm',
            'F' => 'ĐVT',
            'G' => 'Nhóm nguyên liệu',
            'H' => 'CA 1',
            'I' => 'CA 2',
            'J' => 'CA 3',
            'K' => 'Số lượng (M)',
            'L' => 'Định mức (Kg)',
            'M' => 'CA 1 (Kg)',
            'N' => 'CA 2 (Kg)',
            'O' => 'CA 3 (Kg)',
            'P' => 'Trọng lượng (Kg)',
            'Q' => 'Giờ ngừng hỏ',
            'R' => 'Mã nguyên nhân',
            'S' => 'Nguyên nhân dừng',
            'T' => 'Số lượng chạy đạt',
            'U' => 'Ghi chú'
        ];
        
        $row = 4;
        foreach ($headers as $col => $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
        }
        
        $sheet->getStyle("A{$row}:U{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:U{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDDDDD');
        
        // Điền dữ liệu
        $row = 5;
        $currentDate = null;
        
        foreach ($entries as $entry) {
            $dateStr = $entry->date->format('d/m/Y');
            
            // Nếu ngày đã thay đổi, hiển thị ngày mới
            if ($currentDate !== $dateStr) {
                $currentDate = $dateStr;
                $displayDate = $dateStr;
            } else {
                $displayDate = '';
            }
            
            // Tính khối lượng sản phẩm
            $productWeight = 0;
            if ($entry->product && $entry->product->gm_spec > 0 && $entry->product_length > 0) {
                $productWeight = ($entry->product->gm_spec * $entry->product_length * $entry->output_quantity) / 1000;
            }
            
            // Khối lượng theo ca
            $weightCA1 = $entry->shift === 'CA1' ? $productWeight : 0;
            $weightCA2 = $entry->shift === 'CA2' ? $productWeight : 0;
            $weightCA3 = $entry->shift === 'CA3' ? $productWeight : 0;
            
            // Số lượng theo ca
            $quantityCA1 = $entry->shift === 'CA1' ? $entry->output_quantity : 0;
            $quantityCA2 = $entry->shift === 'CA2' ? $entry->output_quantity : 0;
            $quantityCA3 = $entry->shift === 'CA3' ? $entry->output_quantity : 0;
            
            // Đổ dữ liệu vào các cột
            $sheet->setCellValue("A{$row}", $displayDate);
            $sheet->setCellValue("B{$row}", $entry->machine->id ?? '');
            $sheet->setCellValue("C{$row}", $entry->machine->name ?? '');
            $sheet->setCellValue("D{$row}", $entry->product_code);
            $sheet->setCellValue("E{$row}", $entry->product->name ?? '');
            $sheet->setCellValue("F{$row}", 'M');
            $sheet->setCellValue("G{$row}", $entry->product->material ?? '');
            $sheet->setCellValue("H{$row}", $quantityCA1);
            $sheet->setCellValue("I{$row}", $quantityCA2);
            $sheet->setCellValue("J{$row}", $quantityCA3);
            $sheet->setCellValue("K{$row}", $entry->product_length);
            $sheet->setCellValue("L{$row}", $entry->product->gm_spec ?? 0);
            $sheet->setCellValue("M{$row}", $weightCA1);
            $sheet->setCellValue("N{$row}", $weightCA2);
            $sheet->setCellValue("O{$row}", $weightCA3);
            $sheet->setCellValue("P{$row}", $productWeight);
            $sheet->setCellValue("Q{$row}", '');
            $sheet->setCellValue("R{$row}", '');
            $sheet->setCellValue("S{$row}", '');
            $sheet->setCellValue("T{$row}", $entry->good_quantity);
            $sheet->setCellValue("U{$row}", $entry->notes);
            
            $row++;
        }
        
        // Tự động điều chỉnh chiều rộng cột
        foreach (range('A', 'U') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Tạo và trả về file Excel
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = "bao-cao-san-xuat-" . Carbon::now()->format('d-m-Y') . ".xlsx";
        
        // Lưu file tạm thời
        $tempFilePath = tempnam(sys_get_temp_dir(), 'production_report_');
        $writer->save($tempFilePath);
        
        // Trả về file
        return response()->download($tempFilePath, $fileName)
            ->deleteFileAfterSend(true);
    }
} 