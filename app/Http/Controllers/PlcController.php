<?php

namespace App\Http\Controllers;

use App\Models\PlcData;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PlcController extends Controller
{
    public function monitor(Request $request)
    {
        $isRealtime = $request->input('mode', 'realtime') === 'realtime';
        $filterDate = $request->input('date', now()->format('Y-m-d'));
        $filterTime = $request->input('time', now()->format('H:i'));
        
        // Lấy dữ liệu PLC mới nhất
        $machineId = $request->input('machine_id', 1);
        
        $query = PlcData::where('machine_id', $machineId);
        
        if ($isRealtime) {
            // Chế độ realtime: lấy bản ghi mới nhất
            $data = $query->latest()->first();
            $lastUpdate = $data ? Carbon::parse($data->created_at)->format('d/m/Y H:i:s') : 'N/A';
        } else {
            // Chế độ historical: lấy bản ghi gần nhất với thời điểm đã chọn
            $targetDatetime = Carbon::createFromFormat('Y-m-d H:i', $filterDate . ' ' . $filterTime);
            
            $data = $query->where('created_at', '<=', $targetDatetime)
                ->orderBy('created_at', 'desc')
                ->first();
                
            if (!$data) {
                $data = $query->where('created_at', '>=', $targetDatetime)
                    ->orderBy('created_at', 'asc')
                    ->first();
            }
            
            $lastUpdate = $data ? Carbon::parse($data->created_at)->format('d/m/Y H:i:s') : null;
        }
        
        // Lấy thông tin cảnh báo
        $alerts = $this->getAlerts($data);
        
        // Trả về kết quả Ajax nếu được yêu cầu
        if ($request->input('ajax') == 1) {
            $view = view('plc.plc.partials.plc_data', compact('data', 'alerts'))->render();
            
            // Biến đổi thời gian để tránh lỗi JSON
            $fixedAlerts = [];
            foreach ($alerts as $key => $alertsList) {
                $fixedAlerts[$key] = array_map(function($alert) {
                    $alert['created_at'] = date('Y-m-d H:i:s', strtotime($alert['created_at']));
                    return $alert;
                }, $alertsList);
            }
            
            // Lấy các thông tin boolean để debug
            $booleanData = [];
            if ($data) {
                foreach ($data->getAttributes() as $key => $value) {
                    if (strpos($key, 'bat_') === 0) {
                        $booleanData[$key] = [
                            'raw_value' => $value,
                            'is_true' => (bool)$value,
                            'type' => gettype($value)
                        ];
                    }
                }
            }
            
            // Lấy các thông tin nhiệt độ đặt để debug
            $debugData = [];
            if ($data) {
                foreach ($data->getAttributes() as $key => $value) {
                    if (strpos($key, 'nhiet_do_dat_') === 0) {
                        $debugData[$key] = $value;
                    }
                }
            }
            
            return response()->json([
                'html' => $view,
                'lastUpdate' => $lastUpdate,
                'alerts' => $fixedAlerts,
                'debug' => $debugData,
                'booleanData' => $booleanData
            ]);
        }
        
        return view('plc.plc.monitor', compact(
            'data', 
            'isRealtime', 
            'filterDate', 
            'filterTime', 
            'lastUpdate',
            'alerts'
        ));
    }
    
    /**
     * Lấy thông tin cảnh báo cho dữ liệu PLC
     */
    private function getAlerts($data)
    {
        if (!$data) {
            return [];
        }
        
        $alerts = [];
        
        // Cảnh báo nhiệt độ các vùng
        $temperatureZones = [
            'nhiet_do_thuc_te_co_cl', 'nhiet_do_thuc_te_xl_1', 'nhiet_do_thuc_te_xl_2', 
            'nhiet_do_thuc_te_xl_3', 'nhiet_do_thuc_te_xl_4', 'nhiet_do_thuc_te_xl_5',
            'nhiet_do_thuc_te_cn', 'nhiet_do_thuc_te_may_chi_xl_1', 'nhiet_do_thuc_te_may_chi_xl_2',
            'nhiet_do_thuc_te_may_chi_xl_3', 'nhiet_do_thuc_te_may_chi_xl_4',
            'nhiet_do_thuc_te_dh_1', 'nhiet_do_thuc_te_dh_2', 'nhiet_do_thuc_te_dh_3',
            'nhiet_do_thuc_te_dh_4', 'nhiet_do_thuc_te_dh_5', 'nhiet_do_thuc_te_dh_6'
        ];
        
        foreach ($temperatureZones as $zone) {
            $setpointKey = str_replace('thuc_te', 'dat', $zone);
            if (isset($data->$zone) && isset($data->$setpointKey)) {
                $actual = $data->$zone;
                $setpoint = $data->$setpointKey;
                
                // Sai lệch nhiệt độ 5%
                $deviation = abs($actual - $setpoint);
                $percentDeviation = ($setpoint > 0) ? ($deviation / $setpoint) * 100 : 0;
                
                if ($percentDeviation > 5) {
                    $alerts[$zone] = [
                        [
                            'type' => 'danger',
                            'value' => number_format($actual, 2),
                            'message' => 'Nhiệt độ sai lệch quá 5% so với giá trị đặt',
                            'created_at' => now()
                        ]
                    ];
                }
            }
        }
        
        // Cảnh báo tốc độ
        if (isset($data->toc_do_thuc_te_vx) && $data->toc_do_thuc_te_vx > 120) {
            $alerts['toc_do_thuc_te_vx'] = [
                [
                    'type' => 'warning',
                    'value' => number_format($data->toc_do_thuc_te_vx, 2),
                    'message' => 'Tốc độ vít xoắn vượt ngưỡng an toàn',
                    'created_at' => now()
                ]
            ];
        }
        
        // Cảnh báo tải
        if (isset($data->tai_thuc_te_dc_chinh) && $data->tai_thuc_te_dc_chinh > 85) {
            $alerts['tai_thuc_te_dc_chinh'] = [
                [
                    'type' => 'danger',
                    'value' => number_format($data->tai_thuc_te_dc_chinh, 2),
                    'message' => 'Tải động cơ vít xoắn quá cao',
                    'created_at' => now()
                ]
            ];
        }
        
        // Cảnh báo áp suất
        if (isset($data->ap_suat_be_ck1) && $data->ap_suat_be_ck1 > 150) {
            $alerts['ap_suat_be_ck1'] = [
                [
                    'type' => 'danger',
                    'value' => number_format($data->ap_suat_be_ck1, 2),
                    'message' => 'Áp suất bể CK1 vượt ngưỡng an toàn',
                    'created_at' => now()
                ]
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Cập nhật dữ liệu từ PLC vào database
     */
    public function updateFromPLC(Request $request)
    {
        try {
            $machine_id = $request->input('machine_id', 1);
            $data = $request->input('data', []);
            
            if (empty($data)) {
                return response()->json(['error' => 'Không có dữ liệu'], 400);
            }
            
            $mappedData = [
                'machine_id' => $machine_id,
                'datalog_date' => now()->toDateString(),
                'datalog_data_ca' => $data['Datalog.DATA.Ca'] ?? 'CA1',
                'data_trang_thai' => $data['DATA.Trạng_thái'] ?? null,
                
                // Thông tin công nhân và sản phẩm
                'datalog_data_cn1' => $data['Datalog.DATA.CN1'] ?? null,
                'datalog_data_cn2' => $data['Datalog.DATA.CN2'] ?? null,
                'datalog_data_to' => $data['Datalog.DATA.TO'] ?? null,
                'datalog_data_ma_sp' => $data['Datalog.DATA.MA_SP'] ?? null,
                'datalog_data_nl1' => $data['Datalog.DATA.NL1'] ?? null,
                'datalog_data_nl2' => $data['Datalog.DATA.NL2'] ?? null,
                'datalog_data_nl_chi' => $data['Datalog.DATA.NL_CHI'] ?? null,
                'datalog_data_mau_chi' => $data['Datalog.DATA.MAU_CHI'] ?? null,
                'datalog_data_ma_dh' => $data['Datalog.DATA.MA_DH'] ?? null,
                'datalog_data_thn' => $data['Datalog.DATA.THN'] ?? null,
                'datalog_data_tht' => $data['Datalog.DATA.THT'] ?? null,
                'datalog_data_chuot' => $data['Datalog.DATA.CHUOT'] ?? null,
                'datalog_data_sth' => $data['Datalog.DATA.STH'] ?? null,
                'datalog_data_ct' => $data['Datalog.DATA.CT'] ?? null,
                
                // Thông số vận hành
                'toc_do_thuc_te_vx' => $data['Tốc_độ_thực_tế_VX'] ?? null,
                'toc_do_thuc_te_may_chi' => $data['Tốc_độ_thực_tế_máy_chỉ'] ?? null,
                'toc_do_thuc_te_dan_keo_m_p' => $data['Tốc_độ_thực_tế_dàn_kéo_m/p'] ?? null,
                'toc_do_dat_vx' => $data['Tốc_độ_đặt_VX'] ?? null,
                'toc_do_dat_may_chi' => $data['Tốc_độ_đặt_máy_chỉ'] ?? null,
                'toc_do_dat_dan_keo' => $data['Tốc_độ_đặt_dàn_kéo'] ?? null,
                'app_luc_nhua' => $data['Ápp_lực_nhựa'] ?? null,
                'nhiet_do_nhua' => $data['Nhiệt_độ_nhựa'] ?? null,
                'tai_thuc_te_dc_chinh' => $data['Tải_thực_tế_DC_chính'] ?? null,
                'tai_thuc_te_may_chi' => $data['Tải_thực_tế_máy_chỉ'] ?? null,
                'can_thuc_te' => $data['Cân_thực_tế'] ?? null,
                'nang_suatkg_h' => $data['Năng_suất(kg/h)'] ?? null,
                'dinh_luong_g_m' => $data['Định_lượng_g/m'] ?? null,
                'dinh_luong_dat_g_m' => $data['Định_lượng_đặt_g/m'] ?? null,
                'hmi_toc_do_dat_bom_ck' => $data['HMI_tốc_độ_đặt_bơm_CK'] ?? null,
                
                // Nhiệt độ thực tế XL và CN
                'nhiet_do_thuc_te_co_cl' => $data['Nhiệt_độ_thực_tế_cổ_CL'] ?? null,
                'nhiet_do_thuc_te_xl_1' => $data['Nhiệt_độ_thực_tế_XL_1'] ?? null,
                'nhiet_do_thuc_te_xl_2' => $data['Nhiệt_độ_thực_tế_XL_2'] ?? null,
                'nhiet_do_thuc_te_xl_3' => $data['Nhiệt_độ_thực_tế_XL_3'] ?? null,
                'nhiet_do_thuc_te_xl_4' => $data['Nhiệt_độ_thực_tế_XL_4'] ?? null,
                'nhiet_do_thuc_te_xl_5' => $data['Nhiệt_độ_thực_tế_XL_5'] ?? null,
                'nhiet_do_thuc_te_cn' => $data['Nhiệt_độ_thực_tế_CN'] ?? null,
                
                // Nhiệt độ đặt XL và CN
                'nhiet_do_dat_co_cl' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_CL'] ?? null,
                'nhiet_do_dat_xl_1' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_XL_1'] ?? null,
                'nhiet_do_dat_xl_2' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_XL_2'] ?? null,
                'nhiet_do_dat_xl_3' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_XL_3'] ?? null,
                'nhiet_do_dat_xl_4' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_XL_4'] ?? null,
                'nhiet_do_dat_xl_5' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_XL_5'] ?? null,
                'nhiet_do_dat_cn' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_CN'] ?? null,
                
                // Nhiệt độ các ĐH
                'nhiet_do_thuc_te_dh_1' => $data['Nhiệt_độ_thực_tế_ĐH_1'] ?? null,
                'nhiet_do_thuc_te_dh_2' => $data['Nhiệt_độ_thực_tế_ĐH_2'] ?? null,
                'nhiet_do_thuc_te_dh_3' => $data['Nhiệt_độ_thực_tế_ĐH_3'] ?? null,
                'nhiet_do_thuc_te_dh_4' => $data['Nhiệt_độ_thực_tế_ĐH_4'] ?? null,
                'nhiet_do_thuc_te_dh_5' => $data['Nhiệt_độ_thực_tế_ĐH_5'] ?? null,
                'nhiet_do_thuc_te_dh_6' => $data['Nhiệt_độ_thực_tế_ĐH_6'] ?? null,
                
                // Nhiệt độ đặt ĐH
                'nhiet_do_dat_dh_1' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_ĐH_1'] ?? null,
                'nhiet_do_dat_dh_2' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_ĐH_2'] ?? null,
                'nhiet_do_dat_dh_3' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_ĐH_3'] ?? null,
                'nhiet_do_dat_dh_4' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_ĐH_4'] ?? null,
                'nhiet_do_dat_dh_5' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_ĐH_5'] ?? null,
                'nhiet_do_dat_dh_6' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_ĐH_6'] ?? null,
                
                // Nhiệt độ máy chỉ
                'nhiet_do_thuc_te_may_chi_xl_1' => $data['Nhiệt_độ_thực_tế_máy_chỉ_XL_1'] ?? null,
                'nhiet_do_thuc_te_may_chi_xl_2' => $data['Nhiệt_độ_thực_tế_máy_chỉ_XL_2'] ?? null,
                'nhiet_do_thuc_te_may_chi_xl_3' => $data['Nhiệt_độ_thực_tế_máy_chỉ_XL_3'] ?? null,
                'nhiet_do_thuc_te_may_chi_xl_4' => $data['Nhiệt_độ_thực_tế_máy_chỉ_XL_4'] ?? null,
                
                // Nhiệt độ đặt máy chỉ
                'nhiet_do_dat_may_chi_xl_1' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_MayChiXL_1'] ?? null,
                'nhiet_do_dat_may_chi_xl_2' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_MayChiXL_2'] ?? null,
                'nhiet_do_dat_may_chi_xl_3' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_MayChiXL_3'] ?? null,
                'nhiet_do_dat_may_chi_xl_4' => $data['Cài_đặt_tham_số.HMI_nhiệt_độ_đặt_MayChiXL_4'] ?? null,
                
                // Thông số vacuum và CK
                'nhiet_do_nuoc_vacuum_1' => $data['Nhiệt_độ_nước_vacuum_1'] ?? null,
                'nhiet_do_nuoc_vacuum_2' => $data['Nhiệt_độ_nước_vacuum_2'] ?? null,
                'ap_suat_be_ck1' => $data['Áp_suất_bể_CK1'] ?? null,
                
                // Thời gian
                'datalog_data_gio_dung' => (int)($data['Datalog.DATA.Gio_dung'] ?? 0),
                'datalog_data_gio_gia_nhiet' => (int)($data['Datalog.DATA.Gio_gia_nhiet'] ?? 0),
                'datalog_data_gio_chay_1' => (int)($data['Datalog.DATA.Gio_chay_1'] ?? 0),
                'datalog_data_gio_chay_2' => (int)($data['Datalog.DATA.Gio_chay_2'] ?? 0),
                
                // Trạng thái hoạt động
                'bat_cl' => $data['Bật_CL'] ?? false,
                'bat_xl_1' => $data['Bật_XL_1'] ?? false,
                'bat_xl_2' => $data['Bật_XL_2'] ?? false,
                'bat_xl_3' => $data['Bật_XL_3'] ?? false,
                'bat_xl_4' => $data['Bật_XL_4'] ?? false,
                'bat_xl_5' => $data['Bật_XL_5'] ?? false,
                'bat_cn' => $data['Bật_CN'] ?? false,
                'bat_dh_1' => $data['Bật_ĐH_1'] ?? false,
                'bat_dh_2' => $data['Bật_ĐH_2'] ?? false,
                'bat_dh_3' => $data['Bật_ĐH_3'] ?? false,
                'bat_dh_4' => $data['Bật_ĐH_4'] ?? false,
                'bat_dh_5' => $data['Bật_ĐH_5'] ?? false,
                'bat_dh_6' => $data['Bật_ĐH_6'] ?? false,
                'bat_tat_xl_may_chi_1' => $data['Bật_tắt_xl_máy_chỉ_1'] ?? false,
                'bat_tat_xl_may_chi_2' => $data['Bật_tắt_xl_máy_chỉ_2'] ?? false,
                'bat_tat_xl_may_chi_3' => $data['Bật_tắt_xl_máy_chỉ_3'] ?? false,
                'bat_tat_xl_may_chi_4' => $data['Bật_tắt_xl_máy_chỉ_4'] ?? false,
            ];
            
            $plcData = PlcData::create($mappedData);
            
            return response()->json([
                'success' => true,
                'message' => 'Dữ liệu đã được cập nhật',
                'data' => $plcData
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi cập nhật dữ liệu: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
} 