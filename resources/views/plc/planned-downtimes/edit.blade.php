@extends('plc.layouts.master')
@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="card-title">Chỉnh sửa thời gian ngừng máy</h3>
                <a href="{{ route('plc.planned-downtimes.index') }}" class="btn btn-secondary">
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

            <form action="{{ route('plc.planned-downtimes.update', $plannedDowntime->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="machine_id">Máy <span class="text-danger">*</span></label>
                            <select name="machine_id" id="machine_id" class="form-control @error('machine_id') is-invalid @enderror" required>
                                <option value="">-- Chọn máy --</option>
                                @foreach($machines as $machine)
                                    <option value="{{ $machine->id }}" {{ old('machine_id', $plannedDowntime->machine_id) == $machine->id ? 'selected' : '' }}>
                                        {{ $machine->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('machine_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="date">Ngày <span class="text-danger">*</span></label>
                            <input type="date" 
                                   name="date" 
                                   id="date" 
                                   class="form-control @error('date') is-invalid @enderror"
                                   value="{{ old('date', $plannedDowntime->date->format('Y-m-d')) }}"
                                   required>
                            @error('date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="shift">Ca làm việc <span class="text-danger">*</span></label>
                            <select name="shift" id="shift" class="form-control @error('shift') is-invalid @enderror" required>
                                <option value="">-- Chọn ca --</option>
                                @foreach($shifts as $value => $label)
                                    <option value="{{ $value }}" {{ old('shift', $plannedDowntime->shift) == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('shift')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="type">Loại ngừng máy <span class="text-danger">*</span></label>
                            <select name="type" id="type" class="form-control @error('type') is-invalid @enderror" required>
                                <option value="">-- Chọn loại --</option>
                                @foreach($types as $value => $label)
                                    <option value="{{ $value }}" {{ old('type', $plannedDowntime->type) == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="hours">Số giờ dự kiến <span class="text-danger">*</span></label>
                            <input type="number" 
                                   name="hours" 
                                   id="hours" 
                                   class="form-control @error('hours') is-invalid @enderror"
                                   value="{{ old('hours', $plannedDowntime->hours) }}"
                                   step="0.01"
                                   min="0"
                                   max="24"
                                   required>
                            @error('hours')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="actual_hours">Số giờ thực tế</label>
                            <input type="number" 
                                   name="actual_hours" 
                                   id="actual_hours" 
                                   class="form-control @error('actual_hours') is-invalid @enderror"
                                   value="{{ old('actual_hours', $plannedDowntime->actual_hours) }}"
                                   step="0.01"
                                   min="0"
                                   max="24">
                            @error('actual_hours')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="reason">Lý do <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="reason" 
                                   id="reason" 
                                   class="form-control @error('reason') is-invalid @enderror"
                                   value="{{ old('reason', $plannedDowntime->reason) }}"
                                   required>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="status">Trạng thái <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-control @error('status') is-invalid @enderror" required>
                                @foreach($statuses as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', $plannedDowntime->status) == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="note">Ghi chú</label>
                            <textarea name="note" 
                                     id="note" 
                                     class="form-control @error('note') is-invalid @enderror"
                                     rows="3">{{ old('note', $plannedDowntime->note) }}</textarea>
                            @error('note')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="text-right mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop 