{{-- views/plc/dashboard/index.blade.php --}}
@extends('plc.layouts.master')
@section('content')

<div class="container-fluid">
    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Nhà máy</label>
                        <input type="text" class="form-control" value="Nhà máy 1" readonly>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Khu vực</label>
                        <input type="text" class="form-control" value="Khu vực 1" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Thời gian</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="fromDate">
                            <div class="input-group-append">
                                <span class="input-group-text">đến</span>
                            </div>
                            <input type="date" class="form-control" id="toDate">
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary btn-block" id="btnFilter">
                            <i class="fas fa-filter"></i> Lọc
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Machine Grid -->
    <div class="row">
        @foreach($machines as $machine)
        <div class="col-md-4 mb-4">
            <div class="card machine-card h-100" data-machine-id="{{ $machine->id }}">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">{{ $machine->name }}</h5>
                </div>
                <div class="card-body">
                    <!-- OEE Gauge -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div id="oeeGauge{{ $machine->id }}" style="height: 200px;"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="oee-components">
                                <div class="mb-2">
                                    <label>Availability:</label>
                                    <div class="progress">
                                        <div class="progress-bar bg-info availability-bar" role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label>Performance:</label>
                                    <div class="progress">
                                        <div class="progress-bar bg-warning performance-bar" role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label>Quality:</label>
                                    <div class="progress">
                                        <div class="progress-bar bg-success quality-bar" role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Production Info -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <label>Sản phẩm:</label>
                                <span class="current-product">-</span>
                            </div>
                            <div class="info-group">
                                <label>Kế hoạch:</label>
                                <span class="plan-quantity">0</span>
                            </div>
                            <div class="info-group">
                                <label>Thực hiện:</label>
                                <span class="actual-quantity">0</span>
                            </div>
                            <div class="info-group">
                                <label>Tỷ lệ:</label>
                                <span class="completion-rate">0%</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <label>Năng suất hiện tại:</label>
                                <span class="current-speed">0</span> kg/h
                            </div>
                            <div class="info-group">
                                <label>Năng suất cài đặt:</label>
                                <span class="max-speed">0</span> kg/h
                            </div>
                            <div class="info-group">
                                <label>Trạng thái:</label>
                                <span class="status-badge badge badge-success">Đang chạy</span>
                            </div>
                            <div class="info-group">
                                <label>Ca hiện tại:</label>
                                <span class="current-shift">-</span>
                            </div>
                            <div class="info-group">
                                <label>Thời gian dự kiến hoàn thành:</label>
                                <span class="estimated-completion-time">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

<style>
.machine-card {
    transition: all 0.3s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.machine-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
.info-group {
    margin-bottom: 10px;
}
.info-group label {
    font-weight: bold;
    margin-right: 10px;
    min-width: 120px;
    display: inline-block;
}
.progress {
    height: 20px;
    margin-bottom: 10px;
}
.progress-bar {
    line-height: 20px;
    font-size: 12px;
}
.status-badge {
    padding: 5px 10px;
    border-radius: 15px;
}
.oee-components {
    padding: 10px;
}
</style>

<script src="https://cdn3.devexpress.com/jslib/23.1.3/js/dx.all.js"></script>
<script>
$(function() {
    const gauges = {};
    
    // Khởi tạo gauge cho từng máy
    $('.machine-card').each(function() {
        const machineId = $(this).data('machine-id');
        gauges[machineId] = $("#oeeGauge" + machineId).dxCircularGauge({
            scale: {
                startValue: 0,
                endValue: 100,
                tickInterval: 10,
            },
            rangeContainer: {
                ranges: [{
                    startValue: 0,
                    endValue: 40,
                    color: "#ff5c5c"
                }, {
                    startValue: 40,
                    endValue: 70,
                    color: "#ffd700"
                }, {
                    startValue: 70,
                    endValue: 100,
                    color: "#2ecc71"
                }]
            },
            title: {
                text: "OEE",
                font: { size: 16 }
            },
            value: 0,
            size: {
                width: 200,
                height: 200
            }
        }).dxCircularGauge("instance");
    });

    // Hàm cập nhật dữ liệu cho máy
    function updateMachineData(machineId) {
        $.ajax({
            url: "{{ route('plc.dashboard.get-oee-data') }}",
            data: {
                machine_id: machineId,
                from_date: $("#fromDate").val(),
                to_date: $("#toDate").val()
            },
            success: function(response) {
                console.log('OEE data for machine ' + machineId + ':', response);
                
                const machineCard = $(`.machine-card[data-machine-id="${machineId}"]`);
                
                // Cập nhật thông tin máy
                machineCard.find('.current-product').text(response.machine.current_product || 'N/A');
                machineCard.find('.plan-quantity').text(response.machine.plan_quantity);
                machineCard.find('.actual-quantity').text(response.machine.actual_quantity);
                machineCard.find('.completion-rate').text(response.machine.completion_rate + '%');
                machineCard.find('.current-speed').text(response.machine.current_speed);
                machineCard.find('.max-speed').text(response.machine.max_speed);
                
                // Cập nhật thông tin ca
                machineCard.find('.current-shift').text(response.current_oee.shift || '-');
                
                // Cập nhật thời gian dự kiến hoàn thành
                if (response.realtime_info.estimated_completion_time) {
                    machineCard.find('.estimated-completion-time').text(response.realtime_info.estimated_completion_time);
                } else {
                    machineCard.find('.estimated-completion-time').text('-');
                }

                // Cập nhật OEE
                gauges[machineId].value(response.current_oee.oee);
                
                // Cập nhật progress bars và đảm bảo hiển thị đúng
                const availability = response.current_oee.availability || 0;
                const performance = response.current_oee.performance || 0;
                const quality = response.current_oee.quality || 0;
                
                machineCard.find('.availability-bar')
                    .css('width', availability + '%')
                    .text(availability + '%');
                machineCard.find('.performance-bar')
                    .css('width', performance + '%')
                    .text(performance + '%');
                machineCard.find('.quality-bar')
                    .css('width', quality + '%')
                    .text(quality + '%');

                // Cập nhật trạng thái
                const statusBadge = machineCard.find('.status-badge');
                if (response.machine.current_speed > 0) {
                    statusBadge.removeClass('badge-danger badge-warning').addClass('badge-success').text('Đang chạy');
                } else {
                    statusBadge.removeClass('badge-success badge-warning').addClass('badge-danger').text('Dừng');
                }
            }
        });
    }

    // Khởi tạo ngày
    const today = new Date();
    $("#fromDate").val(today.toISOString().split('T')[0]);
    $("#toDate").val(today.toISOString().split('T')[0]);

    // Cập nhật dữ liệu ban đầu sau 500ms để đảm bảo trang đã tải xong
    setTimeout(function() {
        $('.machine-card').each(function() {
            updateMachineData($(this).data('machine-id'));
        });
    }, 500);

    // Cập nhật khi nhấn nút lọc
    $('#btnFilter').click(function() {
        $('.machine-card').each(function() {
            updateMachineData($(this).data('machine-id'));
        });
    });

    // Cập nhật tự động mỗi phút
    setInterval(function() {
        $('.machine-card').each(function() {
            updateMachineData($(this).data('machine-id'));
        });
    }, 60000);
});
</script>
@stop
