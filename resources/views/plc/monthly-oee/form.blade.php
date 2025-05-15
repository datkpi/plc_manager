@extends('plc.layouts.master')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="card-title">Nhập liệu OEE tháng</h3>
                <a href="{{ route('plc.reports.monthly-oee.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Quay lại
                </a>
            </div>
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

            <form action="{{ route('plc.reports.monthly-oee.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Máy</label>
                            <select name="machine_id" class="form-control" required>
                                <option value="">-- Chọn máy --</option>
                                @foreach($machines as $machine)
                                    <option value="{{ $machine->id }}" {{ old('machine_id') == $machine->id ? 'selected' : '' }}>
                                        {{ $machine->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Năm</label>
                            <select name="year" class="form-control" required>
                                @for($y = now()->year; $y >= 2020; $y--)
                                    <option value="{{ $y }}" {{ old('year', now()->year) == $y ? 'selected' : '' }}>
                                        {{ $y }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Tháng</label>
                            <select name="month" class="form-control" required>
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ old('month', now()->month) == $m ? 'selected' : '' }}>
                                        {{ str_pad($m, 2, '0', STR_PAD_LEFT) }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    {{-- <div class="col-md-6">
                        <div class="form-group">
                            <label>A. Tổng thời gian (giờ)</label>
                            <input type="number" name="total_hours" class="form-control" 
                                   value="{{ old('total_hours') }}" step="0.01" required>
                        </div>
                    </div> --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Thời gian ngừng máy có kế hoạch (giờ)</label>
                            <input type="number" name="planned_downtime" class="form-control" 
                                   value="{{ old('planned_downtime') }}" step="0.01" required>
                        </div>
                    </div>
                </div>
{{-- 
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>B. Thời gian chạy máy theo kế hoạch (giờ)</label>
                            <input type="number" name="planned_runtime" class="form-control" 
                                   value="{{ old('planned_runtime') }}" step="0.01" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Tổn thất do ngừng máy không có kế hoạch (giờ)</label>
                            <input type="number" name="unplanned_downtime" class="form-control" 
                                   value="{{ old('unplanned_downtime') }}" step="0.01" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>C. Thời gian chạy máy thực tế (giờ)</label>
                            <input type="number" name="actual_runtime" class="form-control" 
                                   value="{{ old('actual_runtime') }}" step="0.01" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>D. Năng suất lý thuyết của máy (kg/giờ)</label>
                            <input type="number" name="theoretical_output" class="form-control" 
                                   value="{{ old('theoretical_output') }}" step="0.01" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>E. Năng suất thực tế của máy (kg/giờ)</label>
                            <input type="number" name="actual_output" class="form-control" 
                                   value="{{ old('actual_output') }}" step="0.01" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>F. Sản lượng thực tế của máy trong tháng (kg)</label>
                            <input type="number" name="monthly_production" class="form-control" 
                                   value="{{ old('monthly_production') }}" step="0.01" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>G. Phế phẩm (kg)</label>
                            <input type="number" name="defective_products" class="form-control" 
                                   value="{{ old('defective_products') }}" step="0.01" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>H. Sản phẩm thực tế (kg)</label>
                            <input type="number" name="good_products" class="form-control" 
                                   value="{{ old('good_products') }}" step="0.01" required>
                        </div>
                    </div>
                </div> --}}

                <div class="text-right mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Tự động tính B = A - Thời gian ngừng máy có kế hoạch
    $('input[name="total_hours"], input[name="planned_downtime"]').on('change', function() {
        var totalHours = parseFloat($('input[name="total_hours"]').val()) || 0;
        var plannedDowntime = parseFloat($('input[name="planned_downtime"]').val()) || 0;
        var plannedRuntime = totalHours - plannedDowntime;
        $('input[name="planned_runtime"]').val(plannedRuntime.toFixed(2));
    });

    // Tự động tính H = F - G
    $('input[name="monthly_production"], input[name="defective_products"]').on('change', function() {
        var monthlyProduction = parseFloat($('input[name="monthly_production"]').val()) || 0;
        var defectiveProducts = parseFloat($('input[name="defective_products"]').val()) || 0;
        var goodProducts = monthlyProduction - defectiveProducts;
        $('input[name="good_products"]').val(goodProducts.toFixed(2));
    });

    // Tự động tính E = F/C
    $('input[name="monthly_production"], input[name="actual_runtime"]').on('change', function() {
        var monthlyProduction = parseFloat($('input[name="monthly_production"]').val()) || 0;
        var actualRuntime = parseFloat($('input[name="actual_runtime"]').val()) || 0;
        if (actualRuntime > 0) {
            var actualOutput = monthlyProduction / actualRuntime;
            $('input[name="actual_output"]').val(actualOutput.toFixed(2));
        }
    });
});
</script>
@endpush
@stop 