@extends('plc.layouts.master')
@section('content')
<div class="container">
    <h3>Danh sách OEE tháng</h3>
    <a href="{{ route('plc.reports.monthly-oee.create') }}" class="btn btn-success mb-3">Thêm mới</a>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Máy</th>
                <th>Năm</th>
                <th>Tháng</th>
                <th>Thời gian ngừng máy không có kế hoạch (giờ)</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            @foreach($monthlyOEEs as $oee)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $oee->machine->name ?? '' }}</td>
                    <td>{{ $oee->year }}</td>
                    <td>{{ $oee->month }}</td>
                    <td>{{ number_format($oee->planned_downtime, 2) }}</td>
                    <td>
                        <a href="{{ route('plc.reports.monthly-oee.edit', $oee->id) }}" class="btn btn-primary btn-sm">Sửa</a>
                        <form action="{{ route('plc.reports.monthly-oee.destroy', $oee->id) }}" method="POST" style="display:inline-block;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc muốn xóa?')">Xóa</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $monthlyOEEs->links() }}
</div>
@endsection 