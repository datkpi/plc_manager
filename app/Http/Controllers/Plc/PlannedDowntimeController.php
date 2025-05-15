<?php

namespace App\Http\Controllers\Plc;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\PlannedDowntime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlannedDowntimeController extends Controller
{
    public function index(Request $request)
    {
        $machines = Machine::where('status', 1)->get();
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);
        $machineId = $request->input('machine_id');
        $status = $request->input('status');

        $query = PlannedDowntime::with(['machine', 'creator', 'approver'])
            ->when($machineId, function($q) use ($machineId) {
                return $q->where('machine_id', $machineId);
            })
            ->when($year && $month, function($q) use ($year, $month) {
                return $q->whereYear('date', $year)
                    ->whereMonth('date', $month);
            })
            ->when($status, function($q) use ($status) {
                return $q->where('status', $status);
            })
            ->orderBy('date', 'desc')
            ->orderBy('shift', 'asc');

        $downtimes = $query->paginate(20);

        return view('plc.planned-downtimes.index', compact(
            'machines',
            'year',
            'month',
            'downtimes',
            'status'
        ));
    }

    public function create()
    {
        $machines = Machine::where('status', 1)->get();
        $types = PlannedDowntime::TYPES;
        $shifts = PlannedDowntime::SHIFTS;
        
        return view('plc.planned-downtimes.create', compact('machines', 'types', 'shifts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'date' => 'required|date',
            'shift' => 'required|in:' . implode(',', array_keys(PlannedDowntime::SHIFTS)),
            'hours' => 'required|numeric|min:0|max:24',
            'type' => 'required|in:' . implode(',', array_keys(PlannedDowntime::TYPES)),
            'reason' => 'required|string|max:255',
            'note' => 'nullable|string'
        ]);

        $data = $request->all();
        $data['created_by'] = Auth::id();
        $data['status'] = 'planned';

        PlannedDowntime::create($data);

        return redirect()
            ->route('plc.planned-downtimes.index')
            ->with('success', 'Đã thêm thời gian ngừng máy thành công');
    }

    public function edit(PlannedDowntime $plannedDowntime)
    {
        $machines = Machine::where('status', 1)->get();
        $types = PlannedDowntime::TYPES;
        $shifts = PlannedDowntime::SHIFTS;
        $statuses = PlannedDowntime::STATUSES;
        
        return view('plc.planned-downtimes.edit', compact(
            'plannedDowntime',
            'machines',
            'types',
            'shifts',
            'statuses'
        ));
    }

    public function update(Request $request, PlannedDowntime $plannedDowntime)
    {
        $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'date' => 'required|date',
            'shift' => 'required|in:' . implode(',', array_keys(PlannedDowntime::SHIFTS)),
            'hours' => 'required|numeric|min:0|max:24',
            'actual_hours' => 'nullable|numeric|min:0|max:24',
            'type' => 'required|in:' . implode(',', array_keys(PlannedDowntime::TYPES)),
            'status' => 'required|in:' . implode(',', array_keys(PlannedDowntime::STATUSES)),
            'reason' => 'required|string|max:255',
            'note' => 'nullable|string'
        ]);

        $data = $request->all();
        
        // Nếu trạng thái chuyển sang completed, cập nhật người duyệt và thời gian
        if ($request->status === 'completed' && $plannedDowntime->status !== 'completed') {
            $data['approved_by'] = Auth::id();
            $data['approved_at'] = now();
        }

        $plannedDowntime->update($data);

        return redirect()
            ->route('plc.planned-downtimes.index')
            ->with('success', 'Đã cập nhật thời gian ngừng máy thành công');
    }

    public function destroy(PlannedDowntime $plannedDowntime)
    {
        $plannedDowntime->delete();

        return redirect()
            ->route('plc.planned-downtimes.index')
            ->with('success', 'Đã xóa thời gian ngừng máy thành công');
    }
} 