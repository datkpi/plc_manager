@extends('plc.layouts.master')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Báo cáo OEE thángg</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('plc.reports.monthly-oee') }}" id="filter-form">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Máy</label>
                            <select name="machine_id" class="form-control" required>
                                <option value="">-- Chọn máy --</option>
                                @foreach($machines as $machine)
                                    <option value="{{ $machine->id }}" {{ request('machine_id') == $machine->id ? 'selected' : '' }}>
                                        {{ $machine->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Từ tháng</label>
                            <input type="month" name="from_month" class="form-control" value="{{ request('from_month', now()->format('Y-m')) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Đến tháng</label>
                            <input type="month" name="to_month" class="form-control" value="{{ request('to_month', now()->format('Y-m')) }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i> Xem báo cáo
                            </button>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <a href="{{ route('plc.reports.monthly-oee.export', [
                                'machine_id' => request('machine_id'),
                                'from_month' => request('from_month', now()->format('Y-m')),
                                'to_month' => request('to_month', now()->format('Y-m'))
                            ]) }}" class="btn btn-success btn-block">
                                <i class="fas fa-file-excel"></i> Excel
                            </a>
                        </div>
                    </div>
                </div>
            </form>

            @if($selectedMachine && $monthlyOEEList)
                @foreach($monthlyOEEList as $item)
                    <h4>Báo cáo OEE tháng {{ $item['month']->format('m/Y') }} - {{ $selectedMachine->name }}</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="70%">Chỉ số</th>
                                    <th width="30%" class="text-right">Giá trị</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="bg-light">
                                    <td><strong>A. Tổng thời gian tháng (giờ)</strong></td>
                                    <td class="text-right"><strong>{{ number_format($item['oee']['parameters']['total_hours'] ?? 0, 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Thời gian ngừng máy có kế hoạch (giờ)</td>
                                    <td class="text-right">{{ number_format($item['oee']['parameters']['planned_downtime'] ?? 0, 2) }}</td>
                                </tr>
                                <tr class="bg-light">
                                    <td><strong>B. Thời gian chạy máy theo kế hoạch (giờ)</strong></td>
                                    <td class="text-right"><strong>{{ number_format($item['oee']['parameters']['planned_runtime'] ?? 0, 2) }}</strong></td>
                                </tr>
                                {{-- <tr>
                                    <td>Thời gian ngừng máy không có kế hoạch (giờ)</td>
                                    <td class="text-right">{{ number_format($item['oee']['parameters']['unplanned_downtime'] ?? 0, 2) }}</td>
                                </tr> --}}
                                <tr>
                                    <td>Tổn thất do ngừng máy không có kế hoạch (giờ)</td>
                                    <td class="text-right">{{ number_format($item['oee']['parameters']['unplanned_downtime'] ?? 0, 2) }}</td>
                                </tr>
                                <tr class="bg-light">
                                    <td><strong>C. Thời gian chạy máy thực tế (giờ)</strong></td>
                                    <td class="text-right"><strong>{{ number_format($item['oee']['parameters']['actual_runtime'] ?? 0, 2) }}</strong></td>
                                </tr>
                                <tr class="bg-light">
                                    <td><strong>D. Năng suất thiết kế máy (kg/giờ)</strong></td>
                                    <td class="text-right"><strong>{{ number_format($item['oee']['parameters']['max_speed'] ?? 0, 2) }}</strong></td>
                                </tr>
                                <tr class="bg-light">
                                    <td><strong>E. Năng suất thực tế của máy (kg/giờ)</strong></td>
                                    <td class="text-right"><strong>{{ number_format($item['oee']['parameters']['actual_productivity'] ?? 0, 2) }}</strong></td>
                                </tr>
                                <tr class="bg-light">
                                    <td><strong>F. Sản lượng thực tế của máy trong tháng (kg)</strong></td>
                                    <td class="text-right"><strong>{{ number_format($item['oee']['parameters']['total_output_kg'] ?? 0, 2) }}</strong></td>
                                </tr>
                                <tr class="bg-light">
                                    <td><strong>G. Phế phẩm (kg)</strong></td>
                                    <td class="text-right"><strong>{{ number_format($item['oee']['parameters']['total_defect_weight'] ?? 0, 2) }}</strong></td>
                                </tr>
                                <tr class="bg-light">
                                    <td><strong>H. Sản phẩm thực tế (kg)</strong></td>
                                    <td class="text-right"><strong>{{ number_format($item['oee']['parameters']['total_good_output_kg'] ?? 0, 2) }}</strong></td>
                                </tr>
                                <tr class="table-info">
                                    <td><strong>Availability (A)</strong></td>
                                    <td class="text-right"><strong>{{ number_format($item['oee']['metrics']['availability'] ?? 0, 2) }}%</strong></td>
                                </tr>
                                <tr class="table-info">
                                    <td><strong>Performance (P)</strong></td>
                                    <td class="text-right"><strong>{{ number_format($item['oee']['metrics']['performance'] ?? 0, 2) }}%</strong></td>
                                </tr>
                                <tr class="table-info">
                                    <td><strong>Quality (Q)</strong></td>
                                    <td class="text-right"><strong>{{ number_format($item['oee']['metrics']['quality'] ?? 0, 2) }}%</strong></td>
                                </tr>
                                <tr class="table-primary">
                                    <td><strong>OEE</strong></td>
                                    <td class="text-right"><strong>{{ number_format($item['oee']['metrics']['oee'] ?? 0, 2) }}%</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
@stop 