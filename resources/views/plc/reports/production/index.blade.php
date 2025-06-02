@extends('plc.layouts.master')
@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Báo cáo sản xuất (THSX)</h3>
    </div>
    <div class="card-body">
        <!-- Form lọc -->
        <form action="{{ route('plc.reports.production.index') }}" method="GET" class="mb-4">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="from_date">Từ ngày</label>
                        <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $fromDate }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="to_date">Đến ngày</label>
                        <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $toDate }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="machine_id">Máy</label>
                        <select name="machine_id" id="machine_id" class="form-control select2">
                            <option value="">Tất cả</option>
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
                        <label for="product_code">Sản phẩm</label>
                        <select name="product_code" id="product_code" class="form-control select2">
                            <option value="">Tất cả</option>
                            @foreach($products as $product)
                                <option value="{{ $product->code }}" {{ $productCode == $product->code ? 'selected' : '' }}>
                                    {{ $product->code }} - {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search"></i> Lọc
                    </button>
                    <a href="{{ route('plc.reports.production.export', [
                        'from_date' => $fromDate,
                        'to_date' => $toDate,
                        'machine_id' => $machineId,
                        'product_code' => $productCode
                    ]) }}" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Xuất Excel
                    </a>
                </div>
            </div>
        </form>

        <!-- Tiêu đề báo cáo -->
        <div class="text-center mb-4">
            <h4 class="font-weight-bold">TÌNH HÌNH SẢN XUẤT NGÀY CHI TIẾT</h4>
            <p>Tháng {{ $reportMonth }} năm {{ $reportYear }}</p>
        </div>

        <!-- Bảng dữ liệu -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="thead-light">
                    <tr>
                        <th rowspan="2" class="align-middle text-center">Ngày</th>
                        <th rowspan="2" class="align-middle text-center">Mã máy</th>
                        <th rowspan="2" class="align-middle text-center">Tên máy</th>
                        <th rowspan="2" class="align-middle text-center">Mã sản phẩm</th>
                        <th rowspan="2" class="align-middle text-center">Tên sản phẩm</th>
                        <th rowspan="2" class="align-middle text-center">ĐVT</th>
                        <th rowspan="2" class="align-middle text-center">Nhóm nguyên liệu</th>
                        <th colspan="3" class="text-center">Số lượng (cây/cuộn)</th>
                        <th rowspan="2" class="align-middle text-center">Số m</th>
                        <th rowspan="2" class="align-middle text-center">Định mức (Kg)</th>
                        <th colspan="3" class="text-center">Trọng lượng (Gr)</th>
                        <th rowspan="2" class="align-middle text-center">Trọng lượng (Kg)</th>
                        <th rowspan="2" class="align-middle text-center">Giờ ngừng hỏ</th>
                        <th rowspan="2" class="align-middle text-center">Mã nguyên nhân</th>
                        <th rowspan="2" class="align-middle text-center">Nguyên nhân dừng</th>
                        <th rowspan="2" class="align-middle text-center">Ghi chú</th>
                    </tr>
                    <tr>
                        <th class="text-center">CA 1</th>
                        <th class="text-center">CA 2</th>
                        <th class="text-center">CA 3</th>
                        <th class="text-center">CA 1</th>
                        <th class="text-center">CA 2</th>
                        <th class="text-center">CA 3</th>
                    </tr>
                </thead>
                <tbody>
                    @php $prevDate = null; @endphp
                    
                    @forelse($reportData as $dateKey => $dayData)
                        @php $date = $dayData['date']; @endphp
                        
                        @foreach($dayData['entries'] as $index => $entry)
                            <tr>
                                @if($index === 0)
                                    <td rowspan="{{ count($dayData['entries']) }}" class="align-middle text-center">
                                        {{ $date->format('d/m/Y') }}
                                    </td>
                                @endif
                                
                                <td class="text-center">{{ $entry->machine->id ?? '' }}</td>
                                <td>{{ $entry->machine->name ?? '' }}</td>
                                <td>{{ $entry->product_code }}</td>
                                <td>{{ $entry->product->name ?? '' }}</td>
                                <td class="text-center">M</td>
                                <td class="text-center">{{ $entry->product->material ?? '' }}</td>
                                
                                <!-- Số lượng theo ca -->
                                <td class="text-right">{{ $entry->shift === 'CA1' ? number_format($entry->output_quantity) : '' }}</td>
                                <td class="text-right">{{ $entry->shift === 'CA2' ? number_format($entry->output_quantity) : '' }}</td>
                                <td class="text-right">{{ $entry->shift === 'CA3' ? number_format($entry->output_quantity) : '' }}</td>
                                
                                <td class="text-right">{{ number_format($entry->product_length) }}</td>
                                <td class="text-right">{{ number_format($entry->product->gm_spec ?? 0, 2) }}</td>
                                
                                <!-- Trọng lượng theo ca -->
                                <td class="text-right">{{ $entry->shift === 'CA1' ? number_format($entry->product_weight, 2) : '' }}</td>
                                <td class="text-right">{{ $entry->shift === 'CA2' ? number_format($entry->product_weight, 2) : '' }}</td>
                                <td class="text-right">{{ $entry->shift === 'CA3' ? number_format($entry->product_weight, 2) : '' }}</td>
                                
                                <td class="text-right">{{ number_format($entry->product_weight, 2) }}</td>
                                <td class="text-center"></td>
                                <td class="text-center"></td>
                                <td></td>
                                <td>{{ $entry->notes }}</td>
                            </tr>
                        @endforeach
                        
                        <!-- Tổng theo ngày -->
                        <tr class="table-secondary">
                            <td colspan="7" class="font-weight-bold text-right">Tổng ngày {{ $date->format('d/m/Y') }}</td>
                            <td class="text-right font-weight-bold">
                                {{ number_format($dayData['daily_totals']['CA1']['output_quantity'] ?? 0) }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format($dayData['daily_totals']['CA2']['output_quantity'] ?? 0) }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format($dayData['daily_totals']['CA3']['output_quantity'] ?? 0) }}
                            </td>
                            <td></td>
                            <td></td>
                            <td class="text-right font-weight-bold">
                                {{ number_format($dayData['daily_totals']['CA1']['total_weight'] ?? 0, 2) }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format($dayData['daily_totals']['CA2']['total_weight'] ?? 0, 2) }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format($dayData['daily_totals']['CA3']['total_weight'] ?? 0, 2) }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format(
                                    ($dayData['daily_totals']['CA1']['total_weight'] ?? 0) +
                                    ($dayData['daily_totals']['CA2']['total_weight'] ?? 0) +
                                    ($dayData['daily_totals']['CA3']['total_weight'] ?? 0), 2
                                ) }}
                            </td>
                            <td colspan="4"></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="20" class="text-center">Không có dữ liệu</td>
                        </tr>
                    @endforelse
                    
                    <!-- Tổng cộng -->
                    @if(count($reportData) > 0)
                        <tr class="table-primary">
                            <td colspan="7" class="font-weight-bold text-right">TỔNG CỘNG</td>
                            <td class="text-right font-weight-bold">
                                {{ number_format(collect($reportData)->flatMap(function($dayData) {
                                    return collect($dayData['entries'])->where('shift', 'CA1');
                                })->sum('output_quantity')) }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format(collect($reportData)->flatMap(function($dayData) {
                                    return collect($dayData['entries'])->where('shift', 'CA2');
                                })->sum('output_quantity')) }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format(collect($reportData)->flatMap(function($dayData) {
                                    return collect($dayData['entries'])->where('shift', 'CA3');
                                })->sum('output_quantity')) }}
                            </td>
                            <td></td>
                            <td></td>
                            <td class="text-right font-weight-bold">
                                {{ number_format(collect($reportData)->flatMap(function($dayData) {
                                    return collect($dayData['entries'])->where('shift', 'CA1');
                                })->sum('product_weight'), 2) }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format(collect($reportData)->flatMap(function($dayData) {
                                    return collect($dayData['entries'])->where('shift', 'CA2');
                                })->sum('product_weight'), 2) }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format(collect($reportData)->flatMap(function($dayData) {
                                    return collect($dayData['entries'])->where('shift', 'CA3');
                                })->sum('product_weight'), 2) }}
                            </td>
                            <td class="text-right font-weight-bold">
                                {{ number_format($totals['total_weight'], 2) }}
                            </td>
                            <td colspan="4"></td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .table th, .table td {
        vertical-align: middle;
        padding: 0.5rem;
    }
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    @media print {
        .form-group, .btn, .card-header {
            display: none;
        }
        .card {
            border: none;
        }
        .card-body {
            padding: 0;
        }
    }
</style>
@endpush

@push('scripts')
<script>
$(function() {
    $('.select2').select2({
        width: '100%'
    });
    
    // Xử lý chức năng in
    $('.btn-print').on('click', function(e) {
        e.preventDefault();
        window.print();
    });
});
</script>
@endpush 