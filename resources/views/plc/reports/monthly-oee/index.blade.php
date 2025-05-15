@extends('plc.layouts.master')

@section('title', 'Danh sách OEE tháng')

@section('content')
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Danh sách OEE tháng</h1>
                </div>
                <div class="col-sm-6">
                    <div class="float-sm-right">
                        <a href="{{ route('plc.reports.monthly-oee.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Thêm mới
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            @if(session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                {{ session('success') }}
            </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 50px">STT</th>
                                    <th>Máy</th>
                                    <th>Năm</th>
                                    <th>Tháng</th>
                                    <th class="text-right">Tổng giờ (A)</th>
                                    <th class="text-right">Planned Runtime (B)</th>
                                    <th class="text-right">Actual Runtime (C)</th>
                                    <th class="text-right">Theoretical Output (D)</th>
                                    <th class="text-right">Actual Output (E)</th>
                                    <th class="text-right">Monthly Production (F)</th>
                                    <th class="text-right">Defective Products (G)</th>
                                    <th class="text-right">Good Products (H)</th>
                                    <th class="text-right">Availability (%)</th>
                                    <th class="text-right">Performance (%)</th>
                                    <th class="text-right">Quality (%)</th>
                                    <th class="text-right">OEE (%)</th>
                                    <th style="width: 120px">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($monthlyOEEs as $key => $oee)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $oee->machine->name }}</td>
                                    <td>{{ $oee->year }}</td>
                                    <td>{{ $oee->month }}</td>
                                    <td class="text-right">{{ number_format($oee->total_hours, 2) }}</td>
                                    <td class="text-right">{{ number_format($oee->planned_runtime, 2) }}</td>
                                    <td class="text-right">{{ number_format($oee->actual_runtime, 2) }}</td>
                                    <td class="text-right">{{ number_format($oee->theoretical_output, 2) }}</td>
                                    <td class="text-right">{{ number_format($oee->actual_output, 2) }}</td>
                                    <td class="text-right">{{ number_format($oee->monthly_production, 2) }}</td>
                                    <td class="text-right">{{ number_format($oee->defective_products, 2) }}</td>
                                    <td class="text-right">{{ number_format($oee->good_products, 2) }}</td>
                                    <td class="text-right">{{ number_format($oee->availability * 100, 2) }}</td>
                                    <td class="text-right">{{ number_format($oee->performance * 100, 2) }}</td>
                                    <td class="text-right">{{ number_format($oee->quality * 100, 2) }}</td>
                                    <td class="text-right">{{ number_format($oee->oee * 100, 2) }}</td>
                                    <td>
                                        <a href="{{ route('plc.reports.monthly-oee.edit', $oee->id) }}" 
                                           class="btn btn-sm btn-info" 
                                           title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('plc.reports.monthly-oee.destroy', $oee->id) }}" 
                                              method="POST" 
                                              style="display: inline;"
                                              onsubmit="return confirm('Bạn có chắc chắn muốn xóa?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="btn btn-sm btn-danger" 
                                                    title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="17" class="text-center">Không có dữ liệu</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $monthlyOEEs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('styles')
<style>
    .table-responsive {
        overflow-x: auto;
    }
    .table th, .table td {
        white-space: nowrap;
    }
</style>
@endpush 