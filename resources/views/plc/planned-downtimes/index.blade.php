@extends('plc.layouts.master')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="card-title">Thời gian ngừng máy có kế hoạch</h3>
                <a href="{{ route('plc.planned-downtimes.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Thêm mới
                </a>
            </div>
        </div>
        <div class="card-body">
            <!-- Form lọc -->
            <form method="GET" action="{{ route('plc.planned-downtimes.index') }}" class="mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Máy</label>
                            <select name="machine_id" class="form-control">
                                <option value="">-- Tất cả máy --</option>
                                @foreach($machines as $machine)
                                    <option value="{{ $machine->id }}" {{ request('machine_id') == $machine->id ? 'selected' : '' }}>
                                        {{ $machine->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Năm</label>
                            <select name="year" class="form-control">
                                @for($y = date('Y'); $y >= 2020; $y--)
                                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Tháng</label>
                            <select name="month" class="form-control">
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Trạng thái</label>
                            <select name="status" class="form-control">
                                <option value="">-- Tất cả trạng thái --</option>
                                @foreach(App\Models\PlannedDowntime::STATUSES as $value => $label)
                                    <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i> Lọc
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Hiển thị thông báo -->
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Bảng dữ liệu -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Máy</th>
                            <th>Ngày</th>
                            <th>Ca</th>
                            <th>Loại</th>
                            <th>Số giờ dự kiến</th>
                            <th>Số giờ thực tế</th>
                            <th>Lý do</th>
                            <th>Trạng thái</th>
                            <th>Người tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($downtimes as $downtime)
                            <tr>
                                <td>{{ $downtime->machine->name }}</td>
                                <td>{{ $downtime->date->format('d/m/Y') }}</td>
                                <td>{{ $downtime->shift_text }}</td>
                                <td>{{ $downtime->type_text }}</td>
                                <td>{{ number_format($downtime->hours, 2) }}</td>
                                <td>{{ $downtime->actual_hours ? number_format($downtime->actual_hours, 2) : '-' }}</td>
                                <td>{{ $downtime->reason }}</td>
                                <td>
                                    <span class="badge badge-{{ $downtime->status === 'completed' ? 'success' : ($downtime->status === 'cancelled' ? 'danger' : 'warning') }}">
                                        {{ $downtime->status_text }}
                                    </span>
                                </td>
                                <td>{{ $downtime->creator->name }}</td>
                                <td>
                                    <a href="{{ route('plc.planned-downtimes.edit', $downtime) }}" 
                                       class="btn btn-sm btn-info">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('plc.planned-downtimes.destroy', $downtime) }}" 
                                          method="POST" 
                                          class="d-inline"
                                          onsubmit="return confirm('Bạn có chắc chắn muốn xóa?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center">Không có dữ liệu</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Phân trang -->
            <div class="mt-3">
                {{ $downtimes->links() }}
            </div>
        </div>
    </div>
</div>
@stop 