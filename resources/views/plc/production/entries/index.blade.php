{{-- views/plc/production/entries/index.blade.php --}}
@extends('plc.layouts.master')
@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Dữ liệu sản xuất (Form HD08.21)</h3>
        <div class="card-tools">
            <a href="{{ route('plc.production.entries.create') }}" class="btn btn-primary btn-sm mr-2">
                <i class="fas fa-plus"></i> Thêm dữ liệu
            </a>
            <button type="button" class="btn btn-success btn-sm mr-2" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-file-excel"></i> Import từ Excel
            </button>
            <a href="{{ route('plc.production.entries.import.template') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-download"></i> Tải file mẫu
            </a>
        </div>
    </div>

    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Ngày</th>
                        <th>Ca</th>
                        <th>Máy</th>
                        <th>Sản phẩm</th>
                        <th>Ra máy</th>
                        <th>Chính phẩm</th>
                        <th>SL lỗi</th>
                        <th>KL phế phẩm (kg)</th>
                        <th>KL phế liệu (kg)</th>
                        <th>C.dài (m)</th>
                        <th style="width: 100px">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr>
                        <td>{{ $entry->date->format('d/m/Y') }}</td>
                        <td>{{ $entry->shift }}</td>
                        <td>{{ $entry->machine->name }}</td>
                        <td>{{ $entry->product_code }}</td>
                        <td class="text-right">{{ number_format($entry->output_quantity) }}</td>
                        <td class="text-right">{{ number_format($entry->good_quantity) }}</td>
                        <td class="text-right">{{ number_format($entry->output_quantity - $entry->good_quantity) }}</td>
                        <td class="text-right">{{ number_format($entry->defect_weight, 2) }}</td>
                        <td class="text-right">{{ number_format($entry->waste_weight, 2) }}</td>
                        <td class="text-right">{{ number_format($entry->product_length) }}</td>
                        <td class="text-center">
                            <a href="{{ route('plc.production.entries.edit', $entry->id) }}"
                               class="btn btn-warning btn-xs">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('plc.production.entries.destroy', $entry->id) }}"
                                  method="POST" class="d-inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-xs delete-confirm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="text-center">Chưa có dữ liệu sản xuất</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            {{ $entries->links() }}
        </div>
    </div>
</div>

<!-- Modal Import Excel -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('plc.production.entries.import.process') }}" method="POST" enctype="multipart/form-data" id="importForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import dữ liệu sản xuất từ Excel</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="alert alert-info">
                        <p>Hướng dẫn:</p>
                        <ol>
                            <li>Tải xuống <a href="{{ route('plc.production.entries.import.template') }}" class="font-weight-bold">tệp mẫu Excel</a></li>
                            <li>Điền dữ liệu vào tệp mẫu theo đúng định dạng</li>
                            <li>Các trường bắt buộc: Ngày, Ca, Tên máy, Sản phẩm, Ra máy, Chính phẩm, Phế phẩm</li>
                            <li>Tải tệp đã điền dữ liệu lên hệ thống</li>
                        </ol>
                        <p><strong>Lưu ý:</strong></p>
                        <ul>
                            <li>Ngày tháng nhập theo định dạng DD/MM/YYYY (ngày/tháng/năm)</li>
                            <li>Mã ca: CA1, CA2, CA3</li>
                            <li>Tên máy phải khớp với danh sách máy trong hệ thống</li>
                            <li>Mã sản phẩm phải tồn tại trong hệ thống</li>
                            <li>Cột "Số m" dùng để nhập chiều dài thực tế của sản phẩm (mét)</li>
                            <li>Nếu không nhập "Số m", hệ thống sẽ sử dụng chiều dài tiêu chuẩn theo loại sản phẩm</li>
                        </ul>
                    </div>

                    <div class="form-group">
                        <label for="excel_file">Chọn file Excel</label>
                        <input type="file" class="form-control-file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                        <small class="form-text text-muted">Chỉ hỗ trợ file .xlsx, .xls, .csv</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary" id="importButton">
                        <i class="fas fa-upload"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    $('.delete-confirm').click(function(e) {
        e.preventDefault();
        if (confirm('Bạn có chắc muốn xóa dữ liệu này?')) {
            $(this).closest('form').submit();
        }
    });
    
    // Xử lý form submit
    $('#importForm').on('submit', function(e) {
        var fileInput = document.getElementById('excel_file');
        if (fileInput.files.length === 0) {
            e.preventDefault();
            alert('Vui lòng chọn file Excel để import');
            return false;
        }

        // Kiểm tra định dạng file
        var fileName = fileInput.files[0].name;
        var fileExt = fileName.split('.').pop().toLowerCase();
        if (!['xlsx', 'xls', 'csv'].includes(fileExt)) {
            e.preventDefault();
            alert('Vui lòng chọn file Excel (.xlsx, .xls) hoặc CSV');
            return false;
        }

        // Disable nút submit và hiển thị trạng thái loading
        $('#importButton').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang xử lý...');
    });

    // Hiển thị lại modal nếu có lỗi
    @if($errors->any())
        $('#importModal').modal('show');
    @endif
});
</script>
@endpush
@stop
