<?php

namespace App\Http\Controllers;

use App\Services\ExcelImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProductionEntryImportController extends Controller
{
    protected $excelImportService;

    public function __construct(ExcelImportService $excelImportService)
    {
        $this->excelImportService = $excelImportService;
    }

    /**
     * Hiển thị form import - Chuyển hướng đến route mới
     */
    public function showImportForm()
    {
        // Chuyển hướng đến trang danh sách với modal import
        return redirect()->route('plc.production.entries.index');
    }

    /**
     * Xử lý request import file Excel - Chuyển hướng đến route mới
     */
    public function import(Request $request)
    {
        // Chuyển hướng đến controller mới
        return redirect()->route('plc.production.entries.import.process');
    }

    /**
     * Tải xuống mẫu file Excel - Chuyển hướng đến route mới
     */
    public function downloadTemplate()
    {
        // Chuyển hướng đến controller mới
        return redirect()->route('plc.production.entries.import.template');
    }
} 