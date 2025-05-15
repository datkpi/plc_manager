<div class="form-group">
    <label for="max_speed">Năng suất tối đa (kg/h)</label>
    <input type="number" 
           step="0.01" 
           class="form-control @error('max_speed') is-invalid @enderror" 
           id="max_speed" 
           name="max_speed" 
           value="{{ old('max_speed', $machine->max_speed ?? '') }}"
           placeholder="Nhập năng suất tối đa của máy (kg/h)">
    @error('max_speed')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <small class="form-text text-muted">Năng suất tối đa được sử dụng để tính chỉ số Performance (P) trong OEE</small>
</div> 