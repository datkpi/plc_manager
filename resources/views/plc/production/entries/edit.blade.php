{{-- views/plc/production/entries/edit.blade.php --}}
@extends('plc.layouts.master')
@section('content')
<div class="card">
   <div class="card-header">
       <h3 class="card-title">Cập nhật dữ liệu sản xuất (Form HD08.21)</h3>
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

       <form action="{{ route('plc.production.entries.update', $entry->id) }}" method="POST">
           @csrf

           <div class="row">
               <div class="col-md-4">
                   <div class="form-group">
                       <label>Ngày <span class="text-danger">*</span></label>
                       <input type="date" class="form-control" name="date" 
                              value="{{ old('date', $entry->date_for_input) }}"
                              min="{{ date('Y-m-d', strtotime('-1 year')) }}"
                              max="{{ date('Y-m-d') }}"
                              required>
                   </div>
               </div>
               <div class="col-md-4">
                <div class="form-group">
                    <label>Ca <span class="text-danger">*</span></label>
                    <select class="form-control" name="shift" required>
                        <option value="">-- Chọn ca --</option>
                        <option value="CA1" {{ old('shift', $entry->shift) == 'CA1' ? 'selected' : '' }}>Ca 1</option>
                        <option value="CA2" {{ old('shift', $entry->shift) == 'CA2' ? 'selected' : '' }}>Ca 2</option>
                        <option value="CA3" {{ old('shift', $entry->shift) == 'CA3' ? 'selected' : '' }}>Ca 3</option>
                    </select>
                </div>
             </div>
               <div class="col-md-4">
                   <div class="form-group">
                       <label>Máy <span class="text-danger">*</span></label>
                       <select class="form-control" name="machine_id" required>
                           <option value="">-- Chọn máy --</option>
                           @foreach($machines as $machine)
                               <option value="{{ $machine->id }}" {{ old('machine_id', $entry->machine_id) == $machine->id ? 'selected' : '' }}>
                                   {{ $machine->name }}
                               </option>
                           @endforeach
                       </select>
                   </div>
               </div>
           </div>

           <div class="row">
               <div class="col-md-6">
                   <div class="form-group">
                       <label>Sản phẩm <span class="text-danger">*</span></label>
                       <select class="form-control select2" name="product_code" id="product_code" required>
                           <option value="">-- Chọn sản phẩm --</option>
                           @foreach($products as $product)
                               <option value="{{ $product->code }}" 
                                    data-name="{{ $product->name }}"
                                    data-gm="{{ $product->gm_spec }}"
                                    {{ old('product_code', $entry->product_code) == $product->code ? 'selected' : '' }}>
                                   {{ $product->code }}
                               </option>
                           @endforeach
                       </select>
                   </div>
               </div>
               <div class="col-md-6">
                   <div class="form-group">
                       <label>Chiều dài sản phẩm (m) <span class="text-danger">*</span></label>
                       <input type="number" class="form-control" id="product_length" name="product_length" 
                              value="{{ old('product_length', $entry->product_length ?? '') }}" min="1" required>
                   </div>
               </div>
               <div class="col-md-6">
                   <div class="form-group">
                       <label>Tổ/bộ phận</label>
                       <input type="text" class="form-control" name="operator_team" value="{{ old('operator_team', $entry->operator_team) }}">
                   </div>
               </div>
               <div class="col-md-6">
               <div class="form-group">
                <label>Công nhân vận hành</label>
                <input type="text" class="form-control" name="operator_name"
                       value="{{ old('operator_name', $entry->operator_name) }}">
                </div>
               </div>
           </div>

           <div class="row">
               <div class="col-md-3">
                   <div class="form-group">
                       <label>Ra máy (cây/cuộn) <span class="text-danger">*</span></label>
                       <input type="number" class="form-control" name="output_quantity" value="{{ old('output_quantity', $entry->output_quantity) }}" required>
                   </div>
               </div>
               <div class="col-md-3">
                   <div class="form-group">
                       <label>Chính phẩm (cây/cuộn) <span class="text-danger">*</span></label>
                       <input type="number" class="form-control" name="good_quantity" value="{{ old('good_quantity', $entry->good_quantity) }}" required>
                   </div>
               </div>
               <div class="col-md-3">
                   <div class="form-group">
                       <label>Phế phẩm (kg) <span class="text-danger">*</span></label>
                       <input type="number" step="0.01" class="form-control" name="defect_weight" value="{{ old('defect_weight', $entry->defect_weight) }}" required>
                   </div>
               </div>
               <div class="col-md-3">
                   <div class="form-group">
                       <label>Phế liệu (kg) <span class="text-danger">*</span></label>
                       <input type="number" step="0.01" class="form-control" name="waste_weight" value="{{ old('waste_weight', $entry->waste_weight) }}" required>
                   </div>
               </div>
           </div>

           <div class="row">
               <div class="col-md-4">
                   <div class="form-group">
                       <label>CN chạy máy</label>
                       <input type="text" class="form-control" name="machine_operator" value="{{ old('machine_operator', $entry->machine_operator) }}">
                   </div>
               </div>
               <div class="col-md-4">
                   <div class="form-group">
                       <label>CN kiểm</label>
                       <input type="text" class="form-control" name="quality_checker" value="{{ old('quality_checker', $entry->quality_checker) }}">
                   </div>
               </div>
               <div class="col-md-4">
                   <div class="form-group">
                       <label>CN kho</label>
                       <input type="text" class="form-control" name="warehouse_staff" value="{{ old('warehouse_staff', $entry->warehouse_staff) }}">
                   </div>
               </div>
           </div>

           <div class="form-group">
               <label>Ghi chú</label>
               <textarea class="form-control" name="notes" rows="3">{{ old('notes', $entry->notes) }}</textarea>
           </div>

           <div class="mt-4">
               <button type="submit" class="btn btn-primary">
                   <i class="fas fa-save"></i> Cập nhật
               </button>
               <a href="{{ route('plc.production.entries.index') }}" class="btn btn-secondary">
                   <i class="fas fa-times"></i> Hủy
               </a>
           </div>
       </form>
   </div>
</div>


<script>
$(function() {
    // Đảm bảo Select2 được khởi tạo
    $('.select2').select2({
        width: '100%'
    });
    
    // Debug hiện trạng khi trang load
    console.log('Trang đã load, sản phẩm hiện tại:', $('#product_code').val());
    
    // Lưu giá trị chiều dài ban đầu khi trang load
    const initialProductCode = $('#product_code').val();
    const initialLength = $('#product_length').val();
    console.log('Giá trị ban đầu:', initialProductCode, initialLength);
    
    // Khi chọn sản phẩm
    $('#product_code').change(function() {
        const productCode = $(this).val();
        const productName = $(this).find('option:selected').data('name');
        const productGm = $(this).find('option:selected').data('gm');
        
        console.log('Đã chọn sản phẩm:', productCode, productName, productGm);
        
        if (productCode) {
            // Lấy đường kính và vật liệu từ tên sản phẩm
            let diameter = 0;
            let material = '';
            
            if (productName) {
                const parts = productName.split(' ');
                if (parts.length >= 3) {
                    diameter = parseInt(parts[0]);
                    material = parts[2];
                }
                console.log('Phân tích tên sản phẩm:', parts, diameter, material);
            }
            
            // Hiển thị thông tin định mức g/m
            console.log(`Sản phẩm: ${productName}, Định mức g/m: ${productGm}`);
            
            // Kiểm tra nếu đang giữ nguyên sản phẩm ban đầu
            if (productCode === initialProductCode) {
                console.log('Giữ nguyên sản phẩm ban đầu, giữ giá trị chiều dài:', initialLength);
                $('#product_length').val(initialLength);
                return;
            }
            
            // Nếu chọn sản phẩm mới, gọi API để lấy chiều dài tiêu chuẩn
            console.log('Sản phẩm mới, gọi API với tham số:', {
                diameter: diameter,
                material: material,
                product_code: productCode
            });
            
            $.ajax({
                url: '{{ route("plc.api.products.standard-length") }}',
                method: 'GET',
                data: {
                    diameter: diameter,
                    material: material,
                    product_code: productCode
                },
                success: function(response) {
                    console.log('Nhận được response từ API:', response);
                    
                    // Cập nhật giá trị chiều dài
                    $('#product_length').val(response.length);
                    
                    // Log kết quả để debug
                    console.log(`Chiều dài tiêu chuẩn: ${response.length}m cho ${diameter}mm ${material}`);
                },
                error: function(xhr, status, error) {
                    console.error("Lỗi khi lấy chiều dài tiêu chuẩn:", error);
                    console.error("Chi tiết:", xhr.responseText);
                    
                    // Nếu không có API, áp dụng logic mặc định
                    let standardLength = 100;
                    
                    if (material === 'PPR') {
                        standardLength = 4;
                    } else if (material === 'PE80' || material === 'PE100') {
                        if (diameter <= 90) {
                            switch(diameter) {
                                case 90: standardLength = 25; break;
                                case 75: standardLength = 25; break;
                                case 63: standardLength = 50; break;
                                case 50: standardLength = 100; break;
                                case 40: standardLength = 100; break;
                                case 32: standardLength = 200; break;
                                case 25: standardLength = 300; break;
                                case 20: standardLength = 300; break;
                                case 16: standardLength = 300; break;
                                default: standardLength = 100;
                            }
                        } else {
                            standardLength = 6;
                        }
                    } else if (material.includes('PSU')) {
                        standardLength = 6;
                    }
                    
                    $('#product_length').val(standardLength);
                    console.log(`Sử dụng chiều dài mặc định: ${standardLength}m cho ${diameter}mm ${material}`);
                }
            });
        } else {
            $('#product_length').val('');
        }
    });
    
    // Trigger change event nếu có sản phẩm được chọn sẵn
    console.log('Kiểm tra sản phẩm được chọn sẵn:', $('#product_code').val());
    if ($('#product_code').val()) {
        console.log('Trigger change event cho sản phẩm đã chọn');
        $('#product_code').trigger('change');
    }
});
</script>

@stop
