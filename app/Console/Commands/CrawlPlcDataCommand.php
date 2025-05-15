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
                        $plcDataKey = $threshold->plc_data_key;
                        $value = $data->{$plcDataKey};
                        $this->info("Kiểm tra {$threshold->name}:");
                        $this->info("- Key: {$plcDataKey}");
                        $this->info("- Giá trị hiện tại: {$value}");
                        
                        if (empty($threshold->conditions)) {
                            $this->info("✅ Không có điều kiện cảnh báo nào");
                            continue;
                        }
                        
                        // Kiểm tra điều kiện vượt ngưỡng
                        $alertResult = $threshold->checkThreshold($value);
                        
                        if ($alertResult) {
                            $this->error("🔴 Phát hiện cảnh báo cho {$threshold->name}, giá trị hiện tại: {$value}");
                            
                            // Hiển thị các điều kiện
                            foreach ($threshold->conditions as $condition) {
                                $this->warn("  - Điều kiện: " . $this->getConditionDescription($condition, $value));
                            }
                        } else {
                            $this->info("✅ Giá trị bình thường");
                        }
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
