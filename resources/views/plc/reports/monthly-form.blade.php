@extends('plc.layouts.master')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Kết quả tính toán OEE thángg</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('plc.reports.monthly-oee-form') }}">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Máy:</label>
                                    <select name="machine_id" class="form-control">
                                        <option value="">-- Chọn máy --</option>
                                        @foreach($machines as $machine)
                                            <option value="{{ $machine->id }}" {{ $machineId == $machine->id ? 'selected' : '' }}>
                                                {{ $machine->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Từ tháng:</label>
                                    <input type="month" name="from_date" class="form-control" 
                                           value="{{ request('from_date', $fromDate->format('Y-m')) }}"
                                           min="2020-01" max="{{ now()->format('Y-m') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Đến tháng:</label>
                                    <input type="month" name="to_date" class="form-control" 
                                           value="{{ request('to_date', $toDate->format('Y-m')) }}"
                                           min="2020-01" max="{{ now()->format('Y-m') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div class="btn-group btn-block">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Lọc
                                        </button>
                                        <a href="{{ route('plc.reports.monthly-oee-form.export') }}?{{ http_build_query(request()->all()) }}" 
                                           class="btn btn-success">
                                            <i class="fas fa-file-excel"></i> Excel
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    @if($monthlyOEEs->isNotEmpty())
                        @foreach($monthlyOEEs as $monthlyOEE)
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Tháng {{ $monthlyOEE->month }}/{{ $monthlyOEE->year }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="info-box">
                                            <div class="info-box-content">
                                                <span class="info-box-text">Availability (A)</span>
                                                <span class="info-box-number">{{ number_format($monthlyOEE->availability, 2) }}%</span>
                                                <span class="info-box-text text-sm">
                                                    = ({{ number_format($monthlyOEE->actual_runtime, 2) }} / {{ number_format($monthlyOEE->planned_runtime, 2) }}) × 100
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="info-box">
                                            <div class="info-box-content">
                                                <span class="info-box-text">Performance (P)</span>
                                                <span class="info-box-number">{{ number_format($monthlyOEE->performance, 2) }}%</span>
                                                <span class="info-box-text text-sm">
                                                    = ({{ number_format($monthlyOEE->actual_output, 2) }} / {{ number_format($monthlyOEE->theoretical_output, 2) }}) × 100
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="info-box">
                                            <div class="info-box-content">
                                                <span class="info-box-text">Quality (Q)</span>
                                                <span class="info-box-number">{{ number_format($monthlyOEE->quality, 2) }}%</span>
                                                <span class="info-box-text text-sm">
                                                    = ({{ number_format($monthlyOEE->good_products, 2) }} / {{ number_format($monthlyOEE->monthly_production, 2) }}) × 100
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="info-box bg-success">
                                            <div class="info-box-content">
                                                <span class="info-box-text">OEE</span>
                                                <span class="info-box-number">{{ number_format($monthlyOEE->oee, 2) }}%</span>
                                                <span class="info-box-text text-sm">
                                                    = (A × P × Q) / 100
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive mt-3">
                                    <table class="table table-hover text-nowrap">
                                        <tbody>
                                            <tr>
                                                <td>A. Tổng thời gian</td>
                                                <td class="text-right">{{ number_format($monthlyOEE->total_hours, 2) }} giờ</td>
                                            </tr>
                                            <tr>
                                                <td>Thời gian ngừng máy có kế hoạch</td>
                                                <td class="text-right">{{ number_format($monthlyOEE->planned_downtime, 2) }} giờ</td>
                                            </tr>
                                            <tr>
                                                <td>B. Thời gian chạy máy theo kế hoạch</td>
                                                <td class="text-right">{{ number_format($monthlyOEE->planned_runtime, 2) }} giờ</td>
                                            </tr>
                                            <tr>
                                                <td>Tổn thất do ngừng máy không có kế hoạch</td>
                                                <td class="text-right">{{ number_format($monthlyOEE->unplanned_downtime, 2) }} giờ</td>
                                            </tr>
                                            <tr>
                                                <td>C. Thời gian chạy máy thực tế</td>
                                                <td class="text-right">{{ number_format($monthlyOEE->actual_runtime, 2) }} giờ</td>
                                            </tr>
                                            <tr>
                                                <td>D. Năng suất lý thuyết của máy</td>
                                                <td class="text-right">{{ number_format($monthlyOEE->theoretical_output, 2) }} kg/giờ</td>
                                            </tr>
                                            <tr>
                                                <td>E. Năng suất thực tế của máy</td>
                                                <td class="text-right">{{ number_format($monthlyOEE->actual_output, 2) }} kg/giờ</td>
                                            </tr>
                                            <tr>
                                                <td>F. Sản lượng thực tế của máy trong tháng</td>
                                                <td class="text-right">{{ number_format($monthlyOEE->monthly_production, 2) }} kg</td>
                                            </tr>
                                            <tr>
                                                <td>G. Phế phẩm</td>
                                                <td class="text-right">{{ number_format($monthlyOEE->defective_products, 2) }} kg</td>
                                            </tr>
                                            <tr>
                                                <td>H. Sản phẩm thực tế</td>
                                                <td class="text-right">{{ number_format($monthlyOEE->good_products, 2) }} kg</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @else
                    <div class="alert alert-info">
                        Vui lòng chọn máy và khoảng thời gian để xem kết quả tính toán OEE
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 