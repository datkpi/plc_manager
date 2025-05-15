<!-- Zone nhiệt phần 1 -->
<div class="zone-container mb-4">
    <table class="table table-bordered m-0">
        <tr class="bg-light-green">
            <th width="120">Zone nhiệt</th>
            @foreach(['CL', 'XL1', 'XL2', 'XL3', 'XL4', 'XL5', 'CN', 'XLMC1', 'XLMC2'] as $zone)
                <td class="text-center">
                    <div class="zone-button {{ $data->{"bat_" . strtolower(str_replace('MC', '_may_chi_', $zone))} ? 'active' : '' }}">
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
                    <div class="zone-button {{ $data->{"bat_" . strtolower(str_replace(['MC', 'DH'], ['may_chi_', 'dh_'], $zone))} ? 'active' : '' }}">
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