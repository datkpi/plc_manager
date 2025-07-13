<?php

namespace App\Console\Commands;

use App\Models\Machine;
use App\Models\MachineThreshold;
use App\Services\PlcDataService;
use Illuminate\Console\Command;

class CrawlPlcDataCommand extends Command
{
    protected $signature = 'plc:crawl {--interval=5 : Thời gian giữa các lần crawl (giây)}';
    protected $description = 'Crawl dữ liệu từ PLC web interface';

    protected $plcDataService;

    public function __construct(PlcDataService $plcDataService)
    {
        parent::__construct();
        $this->plcDataService = $plcDataService;
    }

 public function handle()
{
    $interval = $this->option('interval');
    $this->info('Bắt đầu lấy dữ liệu');

    while(true) {
        try {
            // Lấy tất cả máy đang active
            $machines = Machine::where('status', true)->get();
            $this->info("\nTìm thấy " . $machines->count() . " máy đang hoạt động");

            foreach($machines as $machine) {
                $this->info("\nĐang lấy dữ liệu máy {$machine->name}...");

                $data = $this->plcDataService->crawlData($machine->id);

                if ($data) {
                    $this->info("Lưu dữ liệu máy {$machine->name} (ID: {$data->id}) thành công");

                    // Kiểm tra các ngưỡng
                    $thresholds = MachineThreshold::where('machine_id', $machine->id)
                        ->where('status', true)
                        ->get();

                    $this->info("Tìm thấy " . $thresholds->count() . " ngưỡng cần kiểm tra");

                    foreach($thresholds as $threshold) {
                        $this->info("Kiểm tra {$threshold->name}:");
                        $this->line("- Key: {$threshold->plc_data_key}");
                        
                        // Lấy giá trị hiện tại từ dữ liệu PLC
                        $value = $data->{$threshold->plc_data_key};
                        if (is_null($value)) {
                            $this->warn("- Giá trị hiện tại: null (bỏ qua kiểm tra)");
                            continue;
                        }
                        
                        $this->line("- Giá trị hiện tại: " . number_format($value, 6));

                        // Log chi tiết các giá trị để debug
                        if ($threshold->type == 'avg') {
                            $avgValue = $threshold->getAverageValue(10);
                            if ($avgValue === null) {
                                $this->warn("- Không thể tính giá trị trung bình 10 phút");
                                continue;
                            }
                            
                            $deviation = abs($value - $avgValue);
                            $percentDeviation = ($avgValue != 0) ? ($deviation / $avgValue) * 100 : 0;
                            
                            $this->line("- Giá trị trung bình 10p: " . number_format($avgValue, 6));
                            $this->line("- Chênh lệch tuyệt đối: " . number_format($deviation, 6));
                            $this->line("- % chênh lệch: " . number_format($percentDeviation, 3) . "%");
                            
                            if (isset($threshold->conditions[0]['percent'])) {
                                $this->line("- Ngưỡng cho phép: ±" . number_format($threshold->conditions[0]['percent'], 2) . "%");
                            } else {
                                $this->warn("- Chưa cấu hình ngưỡng % cho phép");
                            }
                        }

                        $alertResult = $threshold->checkThreshold($value);
                        if ($alertResult) {
                            $this->error("🔴 Phát hiện cảnh báo");
                        } else {
                            $this->info("✅ Giá trị bình thường");
                        }
                        $this->newLine();
                    }
                } else {
                    $this->error("Không thể lấy dữ liệu từ máy {$machine->name}");
                }
            }

        } catch (\Exception $e) {
            $this->error('Lỗi: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }

        if ($interval > 0) {
            $this->info("\nĐợi {$interval} giây...");
            sleep($interval);
        } else {
            break;
        }
    }
}

/**
 * Lấy mô tả của điều kiện
 * 
 * @param array $condition Thông tin điều kiện
 * @param mixed $value Giá trị hiện tại
 * @return string
 */
protected function getConditionDescription($condition, $value)
{
    switch ($condition['type']) {
        case 'boolean':
            return "Boolean (" . ($condition['value'] ? 'true' : 'false') . ")";
            
        case 'range':
            $desc = "Min-Max";
            if (isset($condition['min'])) {
                $desc .= " min=" . number_format($condition['min'], 2);
            }
            if (isset($condition['max'])) {
                $desc .= " max=" . number_format($condition['max'], 2);
            }
            return $desc;
            
        case 'percent':
            return "Dao động " . $condition['percent'] . "% so với " . number_format($condition['base_value'], 2);
            
        case 'avg':
            return "Dao động " . $condition['percent'] . "% so với trung bình 10p";
            
        default:
            return "Không xác định";
    }
}
}
