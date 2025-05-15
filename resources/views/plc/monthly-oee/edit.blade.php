@extends('plc.layouts.master')
@section('content')
<div class="container">
    <h3>Chỉnh sửa OEE tháng</h3>
    <form method="POST" action="{{ route('plc.reports.monthly-oee.update', $monthlyOEE->id) }}">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label>Máy</label>
            <select name="machine_id" class="form-control" required>
                <option value="">-- Chọn máy --</option>
                @foreach($machines as $machine)
                    <option value="{{ $machine->id }}" {{ old('machine_id', $monthlyOEE->machine_id) == $machine->id ? 'selected' : '' }}>
                        {{ $machine->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Tháng</label>
            <input type="month" name="month_year" class="form-control" required value="{{ old('month_year', $monthlyOEE->year . '-' . str_pad($monthlyOEE->month, 2, '0', STR_PAD_LEFT)) }}">
        </div>
        <div class="form-group">
            <label>Thời gian ngừng máy có kế hoạch (giờ)</label>
            <input type="number" step="0.01" name="unplanned_downtime" class="form-control" required value="{{ old('unplanned_downtime', $monthlyOEE->unplanned_downtime) }}">
        </div>
        <button type="submit" class="btn btn-primary">Cập nhật</button>
        <a href="{{ route('plc.reports.monthly-oee.index') }}" class="btn btn-secondary">Hủy</a>
    </form>
</div>
@endsection 