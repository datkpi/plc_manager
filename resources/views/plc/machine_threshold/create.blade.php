@extends('plc.layouts.master')
@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Thêm cảnh báo mới</h3>
    </div>

    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('plc.machine.thresholds.store') }}" method="POST">
            @csrf
            <div class="form-group mb-3">
                <label class="form-label">Máy <span class="text-danger">*</span></label>
                <select name="machine_id" class="form-control select2" required>
                    <option value="">Chọn máy</option>
                    @foreach($machines as $machine)
                        <option value="{{ $machine->id }}"
                            {{ old('machine_id', $machine->id) == $machine->id ? 'selected' : '' }}>
                            {{ $machine->name }} ({{ $machine->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Tag PLC <span class="text-danger">*</span></label>
                <select name="plc_data_key" class="form-control select2" required>
                    <option value="">Chọn tag</option>
                    <optgroup label="Boolean - Trạng thái">
                        @foreach($availableColumns['boolean'] as $col)
                            <option value="{{ $col['key'] }}" data-type="boolean"
                                {{ old('plc_data_key') == $col['key'] ? 'selected' : '' }}>
                                {{ $col['label'] }}
                            </option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Parameter - Thông số">
                        @foreach($availableColumns['parameter'] as $col)
                            <option value="{{ $col['key'] }}" data-type="parameter"
                                {{ old('plc_data_key') == $col['key'] ? 'selected' : '' }}>
                                {{ $col['label'] }}
                            </option>
                        @endforeach
                    </optgroup>
                </select>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Tên hiển thị <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control"
                       value="{{ old('name') }}" required>
            </div>
            
            <input type="hidden" name="type" value="boolean">

            <div class="form-group mb-3">
                <label class="form-label">Màu sắc trên biểu đồ <span class="text-danger">*</span></label>
                <input type="color" name="color" class="form-control"
                       value="{{ old('color', '#FF0000') }}" required>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Điều kiện cảnh báo</label>
                <div class="row">
                    <!-- Boolean condition -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Boolean</h5>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" name="use_boolean"
                                        id="use_boolean" value="1" {{ old('use_boolean') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="use_boolean">Sử dụng</label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label class="form-label">Giá trị cảnh báo</label>
                                    <select name="boolean_value" class="form-control">
                                        <option value="1" {{ old('boolean_value', '1') === '1' ? 'selected' : '' }}>
                                            TRUE - Cảnh báo khi bật
                                        </option>
                                        <option value="0" {{ old('boolean_value') === '0' ? 'selected' : '' }}>
                                            FALSE - Cảnh báo khi tắt
                                        </option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Nội dung cảnh báo</label>
                                    <input type="text" name="warning_message" class="form-control"
                                           value="{{ old('warning_message') }}"
                                           placeholder="VD: Cảnh báo dừng khẩn cấp">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Range condition -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Khoảng Min-Max</h5>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" name="use_range"
                                        id="use_range" value="1" {{ old('use_range') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="use_range">Sử dụng</label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label class="form-label">Giới hạn dưới</label>
                                    <input type="number" step="0.01" name="min_value" class="form-control"
                                           value="{{ old('min_value') }}"
                                           placeholder="Để trống nếu không giới hạn">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Giới hạn trên</label>
                                    <input type="number" step="0.01" name="max_value" class="form-control"
                                           value="{{ old('max_value') }}"
                                           placeholder="Để trống nếu không giới hạn">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Percent condition -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">% Dao động</h5>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" name="use_percent"
                                        id="use_percent" value="1" {{ old('use_percent') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="use_percent">Sử dụng</label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label class="form-label">Giá trị cơ sở</label>
                                    <input type="number" step="0.01" name="base_value" class="form-control"
                                           value="{{ old('base_value') }}"
                                           placeholder="VD: 100">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">% dao động cho phép</label>
                                    <input type="number" step="0.1" name="percent" class="form-control"
                                           value="{{ old('percent') }}"
                                           placeholder="VD: 20">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Average condition -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Trung bình 10 phút</h5>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" name="use_avg"
                                        id="use_avg" value="1" {{ old('use_avg') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="use_avg">Sử dụng</label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label">% dao động cho phép</label>
                                    <div class="input-group">
                                        <input type="number" step="0.001" name="avg_percent" class="form-control"
                                               value="{{ old('avg_percent') }}"
                                               placeholder="VD: 0.001">
                                        <div class="input-group-append">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Giá trị trung bình 10 phút sẽ được tính tự động. Cảnh báo khi giá trị hiện tại dao động quá % này so với giá trị trung bình. Có thể cài đặt tới 0.001%.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <input type="hidden" name="operator" value="OR">

            <div class="form-group mb-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="show_on_chart"
                           id="show_on_chart" value="1"
                           {{ old('show_on_chart', '1') ? 'checked' : '' }}>
                    <label class="custom-control-label" for="show_on_chart">Hiển thị trên biểu đồ</label>
                </div>
            </div>

            <div class="form-group mb-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="status"
                           id="status" value="1"
                           {{ old('status', '1') ? 'checked' : '' }}>
                    <label class="custom-control-label" for="status">Kích hoạt</label>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Lưu
                </button>
                <a href="{{ route('plc.machine.thresholds.show', $machine->id) }}"
                   class="btn btn-secondary">
                    <i class="fas fa-times"></i> Hủy
                </a>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Init select2
    $('.select2').select2({
        width: '100%',
        dropdownParent: $('body')
    });

    // When PLC tag changes
    $('select[name="plc_data_key"]').change(function() {
        const type = $(this).find(':selected').data('type');
        // Auto fill name if empty
        if (!$('input[name="name"]').val()) {
            $('input[name="name"]').val($(this).find(':selected').text().trim());
        }
        
        // Auto check appropriate checkboxes
        if (type === 'boolean') {
            $('#use_boolean').prop('checked', true);
        } else {
            $('#use_range').prop('checked', true);
        }
    });
});
</script>

@stop
