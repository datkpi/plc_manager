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
                        <td>{{ date('d/m/Y', strtotime($entry->date)) }}</td>
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
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import dữ liệu từ Excel</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('plc.production.entries.import') }}" method="POST" enctype="multipart/form-data" id="importForm">
                @csrf
                <div class="modal-body">
                    <!-- Hướng dẫn import -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Hướng dẫn import thành công:</h6>
                        <ul class="mb-0">
                            <li><strong>Tải template:</strong> Nhấn nút "Tải mẫu Excel" để có file mẫu chuẩn</li>
                            <li><strong>Định dạng ngày:</strong> <span class="text-danger">MỤC QUAN TRỌNG NHẤT!</span> Nhập ngày theo dạng <code>dd/mm/yyyy</code> (ví dụ: <code>25/12/2024</code>)</li>
                            <li><strong>Tên máy:</strong> Phải khớp chính xác với tên máy trong hệ thống (hoặc chỉ nhập một ký tự như "A", "B")</li>
                            <li><strong>Mã sản phẩm:</strong> Phải khớp với mã sản phẩm đã tạo</li>
                            <li><strong>Số liệu:</strong> Có thể dùng dấu phẩy hoặc chấm làm dấu thập phân</li>
                        </ul>
                    </div>

                    <!-- Troubleshooting nhấn mạnh ngày tháng -->
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle"></i> Lỗi thường gặp khi import từ máy khách khác nhau:</h6>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <strong><i class="fas fa-calendar-alt text-danger"></i> NGÀY THÁNG (90% nguyên nhân lỗi):</strong>
                                <div class="ml-3">
                                    <div class="text-success">✓ Đúng: <code>25/12/2024</code>, <code>01/01/2025</code>, <code>5/3/2024</code></div>
                                    <div class="text-danger">✗ Sai: <code>12-25-2024</code>, <code>2024/12/25</code>, <code>Dec 25, 2024</code></div>
                                    <small class="text-muted">
                                        • Luôn dùng định dạng <strong>ngày/tháng/năm</strong><br>
                                        • Có thể dùng dấu <code>/</code>, <code>-</code> hoặc <code>.</code><br>
                                        • Hệ thống tự động nhận diện Excel date number
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <strong>Khắc phục từ máy khách:</strong>
                                <ul class="small">
                                    <li>Đảm bảo file Excel được lưu đúng định dạng (.xlsx)</li>
                                    <li>Kiểm tra Regional Settings: Control Panel → Region → Short date: dd/MM/yyyy</li>
                                    <li>Trong Excel: Format Cells → Date → Type: 14/03/2012</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <strong>Lỗi khác:</strong>
                                <ul class="small">
                                    <li>Tên máy: <span class="text-success">Máy đùn A</span> ✓ hoặc chỉ <span class="text-success">A</span> ✓</li>
                                    <li>Encoding: Lưu file với UTF-8 encoding</li>
                                    <li>Số liệu: <span class="text-success">1.234,56</span> hoặc <span class="text-success">1234.56</span> ✓</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="excel_file">Chọn file Excel</label>
                        <input type="file" class="form-control-file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                        <small class="form-text text-muted">Chỉ hỗ trợ file .xlsx, .xls, .csv. Kích thước tối đa: 10MB</small>
                    </div>

                    <!-- Thông tin debug cho admin -->
                    @if(auth()->user()->hasRole('admin'))
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <a data-toggle="collapse" href="#debugInfo" role="button" aria-expanded="false">
                                    <i class="fas fa-tools"></i> Thông tin debug (Admin only)
                                </a>
                            </h6>
                        </div>
                        <div class="collapse" id="debugInfo">
                            <div class="card-body">
                                <p><strong>Máy trong hệ thống:</strong></p>
                                <div class="row">
                                    @foreach(\App\Models\Machine::all() as $machine)
                                    <div class="col-md-4">
                                        <code>{{ $machine->name }}</code>
                                    </div>
                                    @endforeach
                                </div>
                                <hr>
                                <p><strong>Sản phẩm mẫu:</strong></p>
                                <div class="row">
                                    @foreach(\App\Models\Product::take(6)->get() as $product)
                                    <div class="col-md-4">
                                        <code>{{ $product->code }}</code>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <a href="{{ route('plc.production.entries.download-template') }}" class="btn btn-info mr-auto">
                        <i class="fas fa-download"></i> Tải mẫu Excel
                    </a>
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
