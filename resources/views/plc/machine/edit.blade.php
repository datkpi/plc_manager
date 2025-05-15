@extends('plc.layouts.master')
@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Cập nhật máy</h3>
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

        <form action="{{ route('plc.machine.update', $machine->id) }}" method="POST">
            @csrf
            @method('POST')

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Tên máy <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name"
                               value="{{ old('name', $machine->name) }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Mã máy <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code"
                               value="{{ old('code', $machine->code) }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Địa chỉ IP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ip_address"
                               value="{{ old('ip_address', $machine->ip_address) }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Năng suất tối đa (kg/h) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="max_speed"
                               value="{{ old('max_speed', $machine->max_speed) }}" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Mô tả</label>
                <textarea class="form-control" name="description" rows="3">{{ old('description', $machine->description) }}</textarea>
            </div>

            <div class="form-group">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="status" value="1"
                           id="status" {{ $machine->status ? 'checked' : '' }}>
                    <label class="custom-control-label" for="status">Kích hoạt</label>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Cập nhật
                </button>
                <a href="{{ route('plc.machine.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Hủy
                </a>
            </div>
        </form>
    </div>
</div>
@stop
