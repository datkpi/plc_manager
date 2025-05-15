@extends('plc.layouts.master')
@section('content')
<div class="container-fluid">
    <!-- Dashboard Title -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="text-center">Tổng quan hệ thống</h1>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <!-- Máy đang hoạt động -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Máy đang hoạt động</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="active-machines-count">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-industry fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sản lượng hôm nay -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Sản lượng hôm nay</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="today-production">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-box fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- OEE Trung bình -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">OEE Trung bình
                            </div>
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800" id="avg-oee">0%</div>
                                </div>
                                <div class="col">
                                    <div class="progress progress-sm mr-2">
                                        <div class="progress-bar bg-info" role="progressbar"
                                            style="width: 0%" id="avg-oee-bar" aria-valuenow="0" aria-valuemin="0"
                                            aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cảnh báo -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Cảnh báo đang hoạt động</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="active-alerts">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Nội dung chính -->
    <div class="row">
        <!-- Tình trạng máy -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Tình trạng máy</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="machineDropdown"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in"
                            aria-labelledby="machineDropdown">
                            <a class="dropdown-item" href="{{ route('plc.machine.index') }}">Xem tất cả</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="machines-table" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Máy</th>
                                    <th>Trạng thái</th>
                                    <th>Sản phẩm</th>
                                    <th>Tốc độ</th>
                                </tr>
                            </thead>
                            <tbody id="machines-table-body">
                                <!-- Dữ liệu sẽ được thêm vào bằng JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top sản phẩm -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top 5 sản phẩm (tuần này)</h6>
                </div>
                <div class="card-body">
                    <div id="top-products-chart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Xu hướng sản xuất & OEE -->
    <div class="row">
        <!-- Xu hướng sản xuất -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Sản lượng 7 ngày gần đây</h6>
                </div>
                <div class="card-body">
                    <div id="production-trend-chart" style="height: 300px;"></div>
                </div>
            </div>
        </div>

        <!-- OEE theo máy -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">OEE theo máy</h6>
                </div>
                <div class="card-body">
                    <div id="oee-by-machine" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@stop

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    $(function() {
        // Hàm cập nhật dữ liệu dashboard
        function updateDashboard() {
            // Cập nhật số liệu tổng quan
            $.get("{{ url('plc/api/dashboard/stats') }}", function(data) {
                $('#active-machines-count').text(data.machine_count);
                $('#today-production').text(data.today_production.toLocaleString('vi-VN'));
                $('#avg-oee').text(data.oee.toFixed(1) + '%');
                $('#avg-oee-bar').css('width', data.oee + '%').attr('aria-valuenow', data.oee);
                // Cập nhật số cảnh báo đang hoạt động
                $('#active-alerts').text(data.active_alerts || 0);
            });

            // Cập nhật bảng tình trạng máy
            $.get("{{ url('plc/api/dashboard/machine-status') }}", function(data) {
                var tbody = $('#machines-table-body');
                tbody.empty();
                
                $.each(data, function(index, machine) {
                    if (index >= 5) return false; // Chỉ hiển thị 5 máy đầu tiên
                    
                    var statusClass = machine.status ? 'success' : 'secondary';
                    var statusText = machine.status ? 'Đang chạy' : 'Dừng';
                    
                    var row = $('<tr></tr>');
                    row.append('<td>' + machine.name + '</td>');
                    row.append('<td><span class="badge badge-' + statusClass + '">' + statusText + '</span></td>');
                    row.append('<td>' + (machine.product_code || '-') + '</td>');
                    row.append('<td>' + (machine.speed ? machine.speed.toFixed(1) : '0') + '</td>');
                    
                    tbody.append(row);
                });
            });

            // Vẽ biểu đồ top sản phẩm
            $.get("{{ url('plc/api/dashboard/top-products') }}", function(data) {
                var ctx = document.getElementById('top-products-chart');
                
                // Kiểm tra xem biểu đồ đã tồn tại chưa
                if (window.topProductsChart) {
                    window.topProductsChart.destroy();
                }
                
                var labels = [];
                var values = [];
                
                $.each(data, function(index, product) {
                    labels.push(product.name);
                    values.push(product.quantity);
                });
                
                window.topProductsChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Sản lượng',
                            data: values,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });

            // Vẽ biểu đồ xu hướng sản xuất
            $.get("{{ url('plc/api/dashboard/production-trend') }}", function(data) {
                var ctx = document.getElementById('production-trend-chart');
                
                // Kiểm tra xem biểu đồ đã tồn tại chưa
                if (window.productionTrendChart) {
                    window.productionTrendChart.destroy();
                }
                
                var labels = [];
                var values = [];
                
                $.each(data, function(index, day) {
                    // Định dạng ngày theo dd/MM
                    var date = new Date(day.date);
                    var formattedDate = ('0' + date.getDate()).slice(-2) + '/' + ('0' + (date.getMonth() + 1)).slice(-2);
                    
                    labels.push(formattedDate);
                    values.push(day.quantity);
                });
                
                window.productionTrendChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Sản lượng',
                            data: values,
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 2,
                            tension: 0.1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });

            // Vẽ biểu đồ OEE theo máy
            $.get("{{ url('plc/api/dashboard/oee') }}", function(data) {
                var ctx = document.getElementById('oee-by-machine');
                
                // Kiểm tra xem biểu đồ đã tồn tại chưa
                if (window.oeeByMachineChart) {
                    window.oeeByMachineChart.destroy();
                }
                
                var labels = [];
                var values = [];
                
                $.each(data, function(index, machine) {
                    labels.push(machine.machine);
                    values.push(machine.oee);
                });
                
                window.oeeByMachineChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.5)',
                                'rgba(54, 162, 235, 0.5)',
                                'rgba(255, 206, 86, 0.5)',
                                'rgba(75, 192, 192, 0.5)',
                                'rgba(153, 102, 255, 0.5)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var label = context.label || '';
                                        var value = context.raw || 0;
                                        return label + ': ' + value.toFixed(1) + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            });
        }

        // Cập nhật dữ liệu ngay khi trang tải xong
        updateDashboard();

        // Cập nhật dữ liệu mỗi 30 giây
        setInterval(updateDashboard, 30000);
    });
</script>
@endpush
