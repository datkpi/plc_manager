@extends('plc.layouts.master')
@section('content')
<!-- Modal hiển thị chi tiết cảnh báo -->
<div class="modal fade" id="alertModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết cảnh báo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="alertDetails"></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <!-- Bộ lọc thời gian -->
        <div class="filter-container mb-4">
            <form id="filterForm" class="row align-items-end g-3">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="form-label">Chế độ xem</label>
                        <select name="mode" class="form-select" id="viewMode">
                            <option value="realtime" {{ $isRealtime ? 'selected' : '' }}>Realtime</option>
                            <option value="historical" {{ !$isRealtime ? 'selected' : '' }}>Lịch sử</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-3 historical-filter" style="{{ $isRealtime ? 'display:none' : '' }}">
                    <div class="form-group">
                        <label class="form-label">Ngày</label>
                        <input type="date" name="date" class="form-control"
                               value="{{ $filterDate }}" max="{{ date('Y-m-d') }}">
                    </div>
                </div>

                <div class="col-md-3 historical-filter" style="{{ $isRealtime ? 'display:none' : '' }}">
                    <div class="form-group">
                        <label class="form-label">Thời gian</label>
                        <input type="time" name="time" class="form-control"
                               value="{{ $filterTime }}">
                    </div>
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary historical-filter w-100"
                            style="{{ $isRealtime ? 'display:none' : '' }}">
                        <i class="fas fa-search"></i> Xem dữ liệu
                    </button>

                    <div class="realtime-indicator text-center" style="{{ !$isRealtime ? 'display:none' : '' }}">
                        <span class="badge bg-success py-2 px-3">
                            <i class="fas fa-circle-notch fa-spin me-1"></i> Realtime
                        </span>
                        <div class="small text-muted mt-1">Tự động cập nhật sau <span id="countdown">5</span>s</div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-clock me-1"></i>
                        @if($isRealtime)
                            Dữ liệu realtime - Cập nhật lúc: <strong>{{ $lastUpdate }}</strong>
                        @else
                            Dữ liệu lịch sử - Thời điểm: <strong>{{ Carbon\Carbon::createFromFormat('Y-m-d H:i', $filterDate . ' ' . $filterTime)->format('d/m/Y H:i') }}</strong>
                            @if($lastUpdate)
                                (Bản ghi gần nhất: {{ $lastUpdate }})
                            @else
                                (Không có dữ liệu)
                            @endif
                        @endif
                    </div>
                </div>
            </form>
        </div>

        <h4 class="text-center mb-4">Tham số công nghệ AMUT</h4>

        <!-- Bắt đầu container cho dữ liệu PLC -->
        <div id="plcDataContainer">
        <!-- Zone nhiệt phần 1 -->
        <div class="zone-container mb-4">
            <table class="table table-bordered m-0">
                <tr class="bg-light-green">
                    <th width="120">Zone nhiệt</th>
                    @foreach(['CL', 'XL1', 'XL2', 'XL3', 'XL4', 'XL5', 'CN', 'XLMC1', 'XLMC2'] as $zone)
                        <td class="text-center">
                            @php
                                $status = '';
                                if (strpos($zone, 'XLMC') !== false) {
                                    $status = $data->{"bat_tat_xl_may_chi_" . substr($zone, -1)};
                                } elseif (strpos($zone, 'XL') !== false) {
                                    $status = $data->{"bat_xl_" . substr($zone, -1)};
                                } elseif ($zone === 'CL') {
                                    $status = $data->bat_cl;
                                } elseif ($zone === 'CN') {
                                    $status = $data->bat_cn;
                                }
                            @endphp
                            <div class="zone-button {{ $status == 1 ? 'active' : '' }}">
                                {{ $zone }}
                            </div>
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <th>Nhiệt độ đặt</th>
                    @foreach(['co_cl', 'xl_1', 'xl_2', 'xl_3', 'xl_4', 'xl_5', 'cn', 'may_chi_xl_1', 'may_chi_xl_2'] as $zone)
                        <td>
                            <input type="text" class="form-control text-center"
                                   value="{{ number_format($data->{"nhiet_do_dat_$zone"} ?? 0, 2) }}" readonly>
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <th>Nhiệt độ thực tế</th>
                    @foreach(['co_cl', 'xl_1', 'xl_2', 'xl_3', 'xl_4', 'xl_5', 'cn', 'may_chi_xl_1', 'may_chi_xl_2'] as $zone)
                        <td>
                            @php $key = "nhiet_do_thuc_te_$zone"; @endphp
                            <input type="text"
                                   class="form-control text-center temp-actual {{ isset($alerts[$key]) ? 'alert-value' : '' }}"
                                   value="{{ number_format($data->$key ?? 0, 2) }}"
                                   {{ isset($alerts[$key]) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts[$key])) . '"' : '' }}
                                   readonly>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>

        <!-- Zone nhiệt phần 2 -->
        <div class="zone-container mb-4">
            <table class="table table-bordered m-0">
                <tr class="bg-light-green">
                    <th width="120">Zone nhiệt</th>
                    @foreach(['XLMC3', 'XLMC4', 'DH1', 'DH2', 'DH3', 'DH4', 'DH5', 'DH6'] as $zone)
                        <td class="text-center">
                            @php
                                $status = '';
                                if (strpos($zone, 'XLMC') !== false) {
                                    $status = $data->{"bat_tat_xl_may_chi_" . substr($zone, -1)};
                                } elseif (strpos($zone, 'DH') !== false) {
                                    $status = $data->{"bat_dh_" . substr($zone, -1)};
                                }
                            @endphp
                            <div class="zone-button {{ $status == 1 ? 'active' : '' }}">
                                {{ $zone }}
                            </div>
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <th>Nhiệt độ đặt</th>
                    @foreach(['may_chi_xl_3', 'may_chi_xl_4', 'dh_1', 'dh_2', 'dh_3', 'dh_4', 'dh_5', 'dh_6'] as $zone)
                        <td>
                            <input type="text" class="form-control text-center"
                                   value="{{ number_format($data->{"nhiet_do_dat_$zone"} ?? 0, 2) }}" readonly>
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <th>Nhiệt độ thực tế</th>
                    @foreach(['may_chi_xl_3', 'may_chi_xl_4', 'dh_1', 'dh_2', 'dh_3', 'dh_4', 'dh_5', 'dh_6'] as $zone)
                        <td>
                            @php $key = "nhiet_do_thuc_te_$zone"; @endphp
                            <input type="text"
                                   class="form-control text-center temp-actual {{ isset($alerts[$key]) ? 'alert-value' : '' }}"
                                   value="{{ number_format($data->$key ?? 0, 2) }}"
                                   {{ isset($alerts[$key]) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts[$key])) . '"' : '' }}
                                   readonly>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>

        <!-- Thông số vận hành -->
        <div class="parameters-container mb-4">
            <table class="table table-bordered m-0">
                <tr>
                    <td width="150">Tốc độ VX</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['toc_do_thuc_te_vx']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->toc_do_thuc_te_vx ?? 0, 2) }}"
                               {{ isset($alerts['toc_do_thuc_te_vx']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['toc_do_thuc_te_vx'])) . '"' : '' }}
                               readonly>
                    </td>
                    <td width="150">Tốc độ máy chỉ</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['toc_do_thuc_te_may_chi']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->toc_do_thuc_te_may_chi ?? 0, 2) }}"
                               {{ isset($alerts['toc_do_thuc_te_may_chi']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['toc_do_thuc_te_may_chi'])) . '"' : '' }}
                               readonly>
                    </td>
                    <td width="150">Nhiệt độ CK1</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['nhiet_do_ck1']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->nhiet_do_ck1 ?? 0, 2) }}"
                               {{ isset($alerts['nhiet_do_ck1']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['nhiet_do_ck1'])) . '"' : '' }}
                               readonly>
                    </td>
                    <td width="150">Năng suất(kg/h)</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['nang_suatkg_h']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->nang_suatkg_h ?? 0, 2) }}"
                               {{ isset($alerts['nang_suatkg_h']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['nang_suatkg_h'])) . '"' : '' }}
                               readonly>
                    </td>
                </tr>
                <tr>
                    <td>Tải VX</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['tai_thuc_te_dc_chinh']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->tai_thuc_te_dc_chinh ?? 0, 2) }}"
                               {{ isset($alerts['tai_thuc_te_dc_chinh']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['tai_thuc_te_dc_chinh'])) . '"' : '' }}
                               readonly>
                    </td>
                    <td>Tải máy chỉ</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['tai_thuc_te_may_chi']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->tai_thuc_te_may_chi ?? 0, 2) }}"
                               {{ isset($alerts['tai_thuc_te_may_chi']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['tai_thuc_te_may_chi'])) . '"' : '' }}
                               readonly>
                    </td>
                    <td>Định lượng đặt</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['dinh_luong_dat_g_m']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->dinh_luong_dat_g_m ?? 0, 2) }}"
                               {{ isset($alerts['dinh_luong_dat_g_m']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['dinh_luong_dat_g_m'])) . '"' : '' }}
                               readonly>
                    </td>
                    {{-- <td>Năng suất thực tế (kg/h)</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['nang_suat_kg_h']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->nang_suat_kg_h ?? 0, 2) }}"
                               {{ isset($alerts['nang_suat_kg_h']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['nang_suat_kg_h'])) . '"' : '' }}
                               readonly>
                    </td> --}}
                </tr>
                <tr>
                    <td>Cân thực tế</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['can_thuc_te']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->can_thuc_te ?? 0, 2) }}"
                               {{ isset($alerts['can_thuc_te']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['can_thuc_te'])) . '"' : '' }}
                               readonly>
                    </td>
                    <td>Tốc độ dàn kéo</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['toc_do_thuc_te_dan_keo_m_p']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->toc_do_thuc_te_dan_keo_m_p ?? 0, 2) }}"
                               {{ isset($alerts['toc_do_thuc_te_dan_keo_m_p']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['toc_do_thuc_te_dan_keo_m_p'])) . '"' : '' }}
                               readonly>
                    </td>
                    <td>Áp suất bể CK1</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['ap_suat_be_ck1']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->ap_suat_be_ck1 ?? 0, 2) }}"
                               {{ isset($alerts['ap_suat_be_ck1']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['ap_suat_be_ck1'])) . '"' : '' }}
                               readonly>
                    </td>
                    <td>Nhiệt độ nhựa</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['nhiet_do_nhua']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->nhiet_do_nhua ?? 0, 2) }}"
                               {{ isset($alerts['nhiet_do_nhua']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['nhiet_do_nhua'])) . '"' : '' }}
                               readonly>
                    </td>
                </tr>
                <tr>
                    <td>Áp lực nhựa</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['app_luc_nhua']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->app_luc_nhua ?? 0, 2) }}"
                               {{ isset($alerts['app_luc_nhua']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['app_luc_nhua'])) . '"' : '' }}
                               readonly>
                    </td>
                    {{-- <td>Năng suất cài đặt (kg/h)</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['nang_suat_kg_h']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->nang_suat_kg_h ?? 0, 2) }}"
                               {{ isset($alerts['nang_suat_kg_h']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['nang_suat_kg_h'])) . '"' : '' }}
                               readonly>
                    </td> --}}
                    <td>Định lượng g/m TT</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['dinh_luong_g_m']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->dinh_luong_g_m ?? 0, 2) }}"
                               {{ isset($alerts['dinh_luong_g_m']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['dinh_luong_g_m'])) . '"' : '' }}
                               readonly>
                    </td>
                    <td>Cài đặt cân định lượng</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['dinh_luong_dat_g_m']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->dinh_luong_dat_g_m ?? 0, 2) }}"
                               {{ isset($alerts['dinh_luong_dat_g_m']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['dinh_luong_dat_g_m'])) . '"' : '' }}
                               readonly>
                    </td>
                </tr>
                <tr>
                    <td>Suất tiêu hao</td>
                    <td>
                        <input type="text"
                               class="form-control text-center {{ isset($alerts['datalog_data_sth']) ? 'alert-value' : '' }}"
                               value="{{ number_format($data->datalog_data_sth ?? 0, 2) }}"
                               {{ isset($alerts['datalog_data_sth']) ? 'data-alerts="' . htmlspecialchars(json_encode($alerts['datalog_data_sth'])) . '"' : '' }}
                               readonly>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Thông tin sản xuất -->
        <div class="info-container">
            <table class="table table-bordered m-0">
                <tr>
                    <td width="80">Ngày</td>
                    <td width="200">
                        <div class="d-flex">
                            <input type="text" class="form-control text-center" value="{{ Carbon\Carbon::parse($data->datalog_date)->format('d') }}" readonly>
                            <input type="text" class="form-control text-center mx-1" value="{{ Carbon\Carbon::parse($data->datalog_date)->format('m') }}" readonly>
                            <input type="text" class="form-control text-center" value="{{ Carbon\Carbon::parse($data->datalog_date)->format('Y') }}" readonly>
                        </div>
                    </td>
                    <td width="100">Sản phẩm</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_ma_sp }}" readonly></td>
                    <td width="100">Chuột</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_chuot }}" readonly></td>
                    <td width="100">Giờ dừng</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_gio_dung }}" readonly></td>
                </tr>
                <tr>
                    <td>Ca</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_ca }}" readonly></td>
                    <td>Loại NL</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_nl1 }}" readonly></td>
                    <td>Nguyên liệu 1</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_nl1 }}" readonly></td>
                    <td>Giờ gia nhiệt</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_gio_gia_nhiet }}" readonly></td>
                </tr>
                <tr>
                    <td>Tổ</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_to }}" readonly></td>
                    <td>Đường kính</td>
                    <td><input type="text" class="form-control" value="125" readonly></td>
                    <td>Nguyên liệu 2</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_nl2 }}" readonly></td>
                    <td>Giờ chạy 1</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_gio_chay_1 }}" readonly></td>
                </tr>
                <tr>
                    <td>CNCN 1</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_cn1 }}" readonly></td>
                    <td>PN</td>
                    <td><input type="text" class="form-control" value="8" readonly></td>
                    <td>Thành hình trong</td>
                    <td><input type="text" class="form-control" value="125" readonly></td>
                    <td>Giờ chạy 2</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_gio_chay_2 }}" readonly></td>
                </tr>
                <tr>
                    <td>CNCN 2</td>
                    <td><input type="text" class="form-control" value="{{ $data->datalog_data_cn2 }}" readonly></td>
                    <td></td>
                    <td></td>
                    <td>Thành hình ngoài</td>
                    <td><input type="text" class="form-control" value="12.5" readonly></td>
                    <td></td>
                    <td></td>
                </tr>
            </table>
        </div>
        </div><!-- Kết thúc plcDataContainer -->
    </div>
</div>

<style>
.bg-light-green {
    background-color: #e8ffe8;
}
.zone-button {
    padding: 5px;
    border-radius: 4px;
    background: #ccc;
    color: white;
    font-weight: bold;
}
.zone-button.active {
    background: #28a745;
}
.form-control {
    height: 30px;
    padding: 2px 5px;
    background-color: #f8fff8 !important;
}
.temp-actual {
    background-color: #e8ffe8 !important;
}
.alert-value {
    background-color: #ffebeb !important;
    border-color: #dc3545 !important;
    color: #dc3545;
    cursor: pointer;
    animation: blink 1s infinite;
}
@keyframes blink {
    0% { opacity: 1; }
    50% { opacity: 0.8; }
    100% { opacity: 1; }
}
th {
    background-color: #f8fff8;
    vertical-align: middle !important;
}
.table td {
    padding: 4px;
    vertical-align: middle;
}
.modal-body {
    max-height: 80vh;
    overflow-y: auto;
}
.filter-container {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
}
.realtime-indicator {
    text-align: center;
}
.realtime-indicator .badge {
    font-size: 14px;
    padding: 8px 15px;
}
.form-label {
    font-weight: 500;
    margin-bottom: 5px;
}
</style>

<script>
$(document).ready(function() {
    // Click vào giá trị có cảnh báo
    $('.alert-value').click(function() {
        showAlertDetails($(this));
    });
    
    // Xử lý chuyển đổi chế độ xem
    $('#viewMode').change(function() {
        let isRealtime = $(this).val() === 'realtime';
        $('.historical-filter')[isRealtime ? 'hide' : 'show']();
        $('.realtime-indicator')[isRealtime ? 'show' : 'hide']();

        if (isRealtime) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });

    // Countdown và auto refresh
    let countdown = 5;
    let countdownInterval;
    let refreshInterval;

    function startAutoRefresh() {
        countdown = 10;
        updateCountdown();

        countdownInterval = setInterval(function() {
            countdown--;
            updateCountdown();
            
            if (countdown <= 0) {
                countdown = 10;
            }
        }, 1000);

        refreshInterval = setInterval(function() {
            if (!$('#alertModal').hasClass('show')) {
                // Thay thế location.reload() bằng Ajax request
                fetchLatestData();
            }
        }, 5000);
    }

    function fetchLatestData() {
        // Reload trang thay vì sử dụng Ajax
        location.reload();
    }
    
    // Hàm cập nhật các thuộc tính cảnh báo cho các phần tử
    function applyAlerts(alerts) {
        if (!alerts || Object.keys(alerts).length === 0) {
            return;
        }
        
        // Xóa tất cả các cảnh báo cũ
        $('.alert-value').removeClass('alert-value').removeAttr('data-alerts');
        
        // Duyệt qua tất cả các key cảnh báo
        Object.keys(alerts).forEach(function(key) {
            // Tìm input có đúng key
            $('input.form-control').each(function() {
                const input = $(this);
                const parentTd = input.closest('td');
                const prevTd = parentTd.prev('td');
                
                // Kiểm tra nếu là các trường nhiệt độ
                if (key.startsWith('nhiet_do_thuc_te_')) {
                    const zone = key.replace('nhiet_do_thuc_te_', '');
                    const header = input.closest('tr').find('th').text();
                    
                    if (header.includes('Nhiệt độ thực tế') && input.hasClass('temp-actual')) {
                        // Xem xét vị trí của input trong hàng
                        const index = input.closest('td').index();
                        if (index > 0) {
                            const thIndex = input.closest('tr').children().eq(index).index();
                            const headerRow = input.closest('table').find('tr:first-child').children().eq(thIndex);
                            if (headerRow.text().toLowerCase().includes(zone) || 
                                input.val().toString().includes(key)) {
                                input.addClass('alert-value');
                                input.attr('data-alerts', JSON.stringify(alerts[key]));
                            }
                        }
                    }
                } 
                // Kiểm tra các trường đơn lẻ khác
                else if (prevTd.length > 0 && prevTd.text().toLowerCase().includes(key.replace(/_/g, ' '))) {
                    input.addClass('alert-value');
                    input.attr('data-alerts', JSON.stringify(alerts[key]));
                }
                // Kiểm tra trực tiếp dựa trên giá trị/id
                else if (input.attr('id') && input.attr('id').includes(key) || 
                         input.attr('name') && input.attr('name').includes(key)) {
                    input.addClass('alert-value');
                    input.attr('data-alerts', JSON.stringify(alerts[key]));
                }
            });
        });
        
        // Khởi tạo lại sự kiện click cho các giá trị cảnh báo
        reinitAlertHandlers();
    }

    // Hàm xử lý sự kiện cho cảnh báo
    function showAlertDetails(element) {
        let alerts = element.data('alerts');
        
        if (!alerts || !Array.isArray(alerts)) {
            console.log('Không có dữ liệu cảnh báo hợp lệ');
            return;
        }
        
        let html = '<ul class="list-unstyled">';

        alerts.forEach(alert => {
            html += `<li class="mb-3">
                <div class="alert alert-${alert.type} mb-1">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="text-danger">CẢNH BÁO</strong>
                        <small class="text-muted">${new Date(alert.created_at).toLocaleString()}</small>
                    </div>
                    <div><strong>Giá trị:</strong> ${alert.value}</div>
                    <div><strong>Thông báo:</strong> ${alert.message}</div>
                </div>
            </li>`;
        });

        html += '</ul>';

        $('#alertDetails').html(html);
        $('#alertModal').modal('show');
    }

    // Hàm cập nhật xử lý sự kiện cho các giá trị cảnh báo sau khi nội dung được tải bằng AJAX
    function reinitAlertHandlers() {
        $('.alert-value').off('click').on('click', function() {
            showAlertDetails($(this));
        });
    }

    function stopAutoRefresh() {
        clearInterval(countdownInterval);
        clearInterval(refreshInterval);
    }

    function updateCountdown() {
        $('#countdown').text(countdown);
    }

    // Init
    if ($('#viewMode').val() === 'realtime') {
        startAutoRefresh();
    }

    // Dừng refresh khi mở modal
    $('#alertModal').on('show.bs.modal', stopAutoRefresh);

    // Tiếp tục refresh khi đóng modal nếu đang ở chế độ realtime
    $('#alertModal').on('hidden.bs.modal', function() {
        if ($('#viewMode').val() === 'realtime') {
            startAutoRefresh();
        }
    });

    // Form submit
    $('#filterForm').submit(function(e) {
        if ($('#viewMode').val() === 'realtime') {
            e.preventDefault();
            location.reload();
        }
    });
});
</script>

@stop
