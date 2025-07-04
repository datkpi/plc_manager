<?php
namespace App\Http\Controllers\Plc;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Product;
use App\Models\ProductionEntry;
use App\Models\PeCoilStandard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\ExcelImportService;
use Illuminate\Support\Facades\DB;

class ProductionEntryController extends Controller
{
    public function index()
    {
        $entries = ProductionEntry::with(['machine', 'product'])
            ->orderBy('date', 'desc')
            ->orderBy('shift', 'desc')
            ->paginate(20);

        return view('plc.production.entries.index', compact('entries'));
    }

    public function create()
    {
        $machines = Machine::where('status', true)->get();
        $products = Product::all();

        return view('plc.production.entries.create', compact('machines', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machine,id',
            'date' => 'required|date',
            'shift' => 'required',
            'product_code' => 'required|exists:products,code',
            'output_quantity' => 'required|integer|min:0',
            'good_quantity' => 'required|integer|min:0|lte:output_quantity',
            'defect_weight' => 'required|numeric|min:0',
            'waste_weight' => 'required|numeric|min:0',
            'product_length' => 'required|integer|min:0',
            'notes' => 'nullable|string',
            'operator_name' => 'nullable|string',
            'operator_team' => 'nullable|string',
            'machine_operator' => 'nullable|string',
            'quality_checker' => 'nullable|string',
            'warehouse_staff' => 'nullable|string'
        ]);

        ProductionEntry::create($validated);

        return redirect()
            ->route('plc.production.entries.index')
            ->with('success', 'Thêm dữ liệu sản xuất thành công');
    }

    public function edit($id)
    {
        $entry = ProductionEntry::findOrFail($id);
        $machines = Machine::where('status', true)->get();
        $products = Product::all();

        return view('plc.production.entries.edit', compact('entry', 'machines', 'products'));
    }

    public function update(Request $request, $id)
    {
        $entry = ProductionEntry::findOrFail($id);

        $validated = $request->validate([
            'machine_id' => 'required|exists:machine,id',
            'date' => 'required|date',
            'shift' => 'required',
            'product_code' => 'required|exists:products,code',
            'output_quantity' => 'required|integer|min:0',
            'good_quantity' => 'required|integer|min:0|lte:output_quantity',
            'defect_weight' => 'required|numeric|min:0',
            'waste_weight' => 'required|numeric|min:0',
            'product_length' => 'required|integer|min:0',
            'notes' => 'nullable|string',
            'operator_name' => 'nullable|string',
            'operator_team' => 'nullable|string',
            'machine_operator' => 'nullable|string',
            'quality_checker' => 'nullable|string',
            'warehouse_staff' => 'nullable|string'
        ]);

        $entry->update($validated);

        return redirect()
            ->route('plc.production.entries.index')
            ->with('success', 'Cập nhật dữ liệu sản xuất thành công');
    }

    public function destroy($id)
    {
        $entry = ProductionEntry::findOrFail($id);
        $entry->delete();

        return redirect()
            ->route('plc.production.entries.index')
            ->with('success', 'Xóa dữ liệu sản xuất thành công');
    }

    /**
     * Điều hướng đến trang import Excel
     */
    public function showImportForm()
    {
        // Không cần chuyển trang nữa vì đã có modal import
        return redirect()->route('plc.production.entries.index');
    }
    
    /**
     * Xử lý request import file Excel
     */
    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            $excelImportService = app(ExcelImportService::class);
            
            $file = $request->file('excel_file');
            $fileName = 'import_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('imports', $fileName);

            $fullPath = Storage::path($filePath);
            
            // Log thêm thông tin về hệ thống
            Log::info('=== SYSTEM INFO ===');
            Log::info('PHP Version: ' . PHP_VERSION);
            Log::info('Operating System: ' . PHP_OS);
            Log::info('Server Software: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'));
            Log::info('Locale: ' . setlocale(LC_ALL, 0));
            Log::info('Timezone: ' . date_default_timezone_get());
            Log::info('Date format: ' . date('Y-m-d H:i:s'));
            
            // Log thêm thông tin về file
            Log::info('=== FILE INFO ===');
            Log::info('Import file: ' . $fileName);
            Log::info('File path: ' . $filePath);
            Log::info('Full path: ' . $fullPath);
            Log::info('File exists: ' . (file_exists($fullPath) ? 'Yes' : 'No'));
            Log::info('File size: ' . (file_exists($fullPath) ? filesize($fullPath) : 'N/A') . ' bytes');
            Log::info('MIME type: ' . $file->getMimeType());
            Log::info('Original name: ' . $file->getClientOriginalName());
            
            // Log dữ liệu về máy và sản phẩm
            Log::info('=== DATABASE INFO ===');
            Log::info('Danh sách máy trong hệ thống: ' . Machine::pluck('name')->implode(', '));
            Log::info('Danh sách sản phẩm trong hệ thống: ' . Product::pluck('code')->implode(', '));
            
            $result = $excelImportService->importProductionEntries($fullPath);

            // Xóa file sau khi đã import xong
            Storage::delete($filePath);

            if ($result['success']) {
                return redirect()
                    ->route('plc.production.entries.index')
                    ->with('success', 'Import thành công ' . $result['results']['success'] . ' bản ghi.');
            } else {
                $errorMessage = 'Lỗi import: ';
                if (isset($result['message'])) {
                    $errorMessage .= $result['message'];
                } elseif (isset($result['results']['errors']) && count($result['results']['errors']) > 0) {
                    // Hiển thị tất cả các lỗi
                    $errorMessage .= implode('<br>', $result['results']['errors']);
                }
                
                return redirect()
                    ->route('plc.production.entries.index')
                    ->withErrors(['excel_file' => $errorMessage])
                    ->withInput();
            }
        } catch (\Exception $e) {
            Log::error('=== IMPORT ERROR ===');
            Log::error('Lỗi import excel: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect()
                ->route('plc.production.entries.index')
                ->withErrors(['excel_file' => 'Có lỗi xảy ra: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Tải xuống mẫu file Excel
     */
    public function downloadTemplate()
    {
        $excelImportService = app(ExcelImportService::class);
        $spreadsheet = $excelImportService->createImportTemplate();
        
        // Tạo writer
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Lưu file tạm thời
        $tempFilePath = tempnam(sys_get_temp_dir(), 'production_template_');
        $writer->save($tempFilePath);
        
        // Trả về file
        return response()->download($tempFilePath, 'mau_nhap_san_xuat_HD08.21.xlsx')
            ->deleteFileAfterSend(true);
    }
}
