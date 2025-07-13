@extends('plc.layouts.master')
@section('content')
<div class="container">
    <h3>Thêm mới OEE tháng</h3>
    <form method="POST" action="{{ route('plc.reports.monthly-oee.store') }}">
        @csrf
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
        <div class="form-group">
            <label>Tháng</label>
            <input type="month" name="month_year" class="form-control" required value="{{ old('month_year') }}">
        </div>
        <div class="form-group">
            <label>Thời gian ngừng máy có kế hoạch (giờ)</label>
            <input type="number" step="0.01" name="planned_runtime" class="form-control" required value="{{ old('planned_runtime') }}">
        </div>
        <button type="submit" class="btn btn-primary">Lưu</button>
        <a href="{{ route('plc.reports.monthly-oee.index') }}" class="btn btn-secondary">Hủy</a>
    </form>
</div>
@endsection 