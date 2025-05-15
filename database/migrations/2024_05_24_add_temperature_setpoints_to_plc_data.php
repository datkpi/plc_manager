<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plc_data', function (Blueprint $table) {
            // Bổ sung các cột nhiệt độ đặt nếu chưa tồn tại
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_co_cl')) {
                $table->float('nhiet_do_dat_co_cl')->nullable()->after('bat_cn');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_xl_1')) {
                $table->float('nhiet_do_dat_xl_1')->nullable()->after('nhiet_do_dat_co_cl');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_xl_2')) {
                $table->float('nhiet_do_dat_xl_2')->nullable()->after('nhiet_do_dat_xl_1');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_xl_3')) {
                $table->float('nhiet_do_dat_xl_3')->nullable()->after('nhiet_do_dat_xl_2');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_xl_4')) {
                $table->float('nhiet_do_dat_xl_4')->nullable()->after('nhiet_do_dat_xl_3');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_xl_5')) {
                $table->float('nhiet_do_dat_xl_5')->nullable()->after('nhiet_do_dat_xl_4');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_cn')) {
                $table->float('nhiet_do_dat_cn')->nullable()->after('nhiet_do_dat_xl_5');
            }
            
            // Nhiệt độ đặt máy chỉ
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_may_chi_xl_1')) {
                $table->float('nhiet_do_dat_may_chi_xl_1')->nullable()->after('nhiet_do_thuc_te_may_chi_xl_4');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_may_chi_xl_2')) {
                $table->float('nhiet_do_dat_may_chi_xl_2')->nullable()->after('nhiet_do_dat_may_chi_xl_1');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_may_chi_xl_3')) {
                $table->float('nhiet_do_dat_may_chi_xl_3')->nullable()->after('nhiet_do_dat_may_chi_xl_2');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_may_chi_xl_4')) {
                $table->float('nhiet_do_dat_may_chi_xl_4')->nullable()->after('nhiet_do_dat_may_chi_xl_3');
            }
            
            // Nhiệt độ đặt đầu hơi
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_dh_1')) {
                $table->float('nhiet_do_dat_dh_1')->nullable()->after('nhiet_do_thuc_te_dh_12');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_dh_2')) {
                $table->float('nhiet_do_dat_dh_2')->nullable()->after('nhiet_do_dat_dh_1');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_dh_3')) {
                $table->float('nhiet_do_dat_dh_3')->nullable()->after('nhiet_do_dat_dh_2');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_dh_4')) {
                $table->float('nhiet_do_dat_dh_4')->nullable()->after('nhiet_do_dat_dh_3');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_dh_5')) {
                $table->float('nhiet_do_dat_dh_5')->nullable()->after('nhiet_do_dat_dh_4');
            }
            
            if (!Schema::hasColumn('plc_data', 'nhiet_do_dat_dh_6')) {
                $table->float('nhiet_do_dat_dh_6')->nullable()->after('nhiet_do_dat_dh_5');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plc_data', function (Blueprint $table) {
            // Xóa các cột nhiệt độ đặt nếu tồn tại
            $columns = [
                'nhiet_do_dat_co_cl',
                'nhiet_do_dat_xl_1',
                'nhiet_do_dat_xl_2',
                'nhiet_do_dat_xl_3',
                'nhiet_do_dat_xl_4',
                'nhiet_do_dat_xl_5',
                'nhiet_do_dat_cn',
                'nhiet_do_dat_may_chi_xl_1',
                'nhiet_do_dat_may_chi_xl_2',
                'nhiet_do_dat_may_chi_xl_3',
                'nhiet_do_dat_may_chi_xl_4',
                'nhiet_do_dat_dh_1',
                'nhiet_do_dat_dh_2',
                'nhiet_do_dat_dh_3',
                'nhiet_do_dat_dh_4',
                'nhiet_do_dat_dh_5',
                'nhiet_do_dat_dh_6',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('plc_data', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}; 