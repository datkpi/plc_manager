<?php

namespace App\Http\Controllers\Plc;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\PlcData;
use App\Models\MachineThreshold;
use Illuminate\Http\Request;

class MachineThresholdController extends Controller
{
   /**
    * Hiển thị danh sách cảnh báo của máy
    */
   public function show($machine_id)
   {
       $machine = Machine::findOrFail($machine_id);
       $thresholds = MachineThreshold::where('machine_id', $machine_id)->get();
       $availableColumns = PlcData::getAlertableColumns();

       return view('plc.machine_threshold.show', compact('machine', 'thresholds', 'availableColumns'));
   }

   /**
    * Form tạo cảnh báo mới cho máy
    */
   public function create($machine_id)
   {
       $machine = Machine::findOrFail($machine_id);
       $machines = Machine::active()->get();
       $availableColumns = PlcData::getAlertableColumns();

       return view('plc.machine_threshold.create', compact('machine', 'machines', 'availableColumns'));
   }

   /**
    * Lưu cảnh báo mới
    */
   public function store(Request $request)
   {
       $data = $request->validate([
           'machine_id' => 'required|integer',
           'plc_data_key' => 'required|string',
           'name' => 'required|string|max:255',
           'color' => 'required|string|max:7',
           'show_on_chart' => 'boolean',
           'status' => 'boolean',
           'use_boolean' => 'nullable|boolean',
           'boolean_value' => 'nullable|boolean',
           'warning_message' => 'nullable|string|max:255',
           'use_range' => 'nullable|boolean',
           'min_value' => 'nullable|numeric',
           'max_value' => 'nullable|numeric',
           'use_percent' => 'nullable|boolean',
           'base_value' => 'nullable|numeric',
           'percent' => 'nullable|numeric|min:0|max:100',
           'use_avg' => 'nullable|boolean',
           'avg_base_value' => 'nullable|numeric',
           'avg_percent' => 'nullable|numeric|min:0|max:100'
       ]);

       try {
           // Set default values
           $data['status'] = $request->has('status');
           $data['show_on_chart'] = $request->has('show_on_chart');
           $data['type'] = 'boolean'; // Mặc định type là boolean
           $data['operator'] = 'OR'; // Mặc định operator là OR

           // Auto set name if empty
           if (empty($data['name'])) {
               $columns = PlcData::getAlertableColumns();
               $type = $data['plc_data_key'] === 'boolean' ? 'boolean' : 'parameter';
               foreach ($columns[$type] as $col) {
                   if ($col['key'] === $data['plc_data_key']) {
                       $data['name'] = $col['label'];
                       break;
                   }
               }
           }

           // Process data to create conditions array
           $data = $this->processThresholdData($data);

           // Create threshold
           MachineThreshold::create($data);

           return redirect()
               ->route('plc.machine.thresholds.show', $request->machine_id)
               ->with('success', 'Thêm cảnh báo thành công');

       } catch (\Exception $e) {
           return back()
               ->withInput()
               ->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
       }
   }

   /**
    * Form sửa cảnh báo
    */
   public function edit($id)
   {
       $threshold = MachineThreshold::findOrFail($id);
       $machines = Machine::active()->get();
       $availableColumns = PlcData::getAlertableColumns();

       return view('plc.machine_threshold.edit', compact('threshold', 'machines', 'availableColumns'));
   }

   /**
    * Cập nhật cảnh báo
    */
   public function update(Request $request, $id)
   {
       $threshold = MachineThreshold::findOrFail($id);
       $data = $request->validate([
           'machine_id' => 'required|integer',
           'plc_data_key' => 'required|string',
           'name' => 'required|string|max:255',
           'color' => 'required|string|max:7',
           'show_on_chart' => 'boolean',
           'status' => 'boolean',
           'use_boolean' => 'nullable|boolean',
           'boolean_value' => 'nullable|boolean',
           'warning_message' => 'nullable|string|max:255',
           'use_range' => 'nullable|boolean',
           'min_value' => 'nullable|numeric',
           'max_value' => 'nullable|numeric',
           'use_percent' => 'nullable|boolean',
           'base_value' => 'nullable|numeric',
           'percent' => 'nullable|numeric|min:0|max:100',
           'use_avg' => 'nullable|boolean',
           'avg_base_value' => 'nullable|numeric',
           'avg_percent' => 'nullable|numeric|min:0|max:100'
       ]);

       try {
           // Set checkbox values
           $data['status'] = $request->has('status');
           $data['show_on_chart'] = $request->has('show_on_chart');
           $data['type'] = 'boolean'; // Mặc định type là boolean
           $data['operator'] = 'OR'; // Mặc định operator là OR

           // Process data to create conditions array
           $data = $this->processThresholdData($data);

           // Update threshold
           $threshold->update($data);

           return redirect()
               ->route('plc.machine.thresholds.show', $threshold->machine_id)
               ->with('success', 'Cập nhật cảnh báo thành công');

       } catch (\Exception $e) {
           return back()
               ->withInput()
               ->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
       }
   }

   /**
    * Xóa cảnh báo
    */
   public function destroy($id)
   {
       try {
           $threshold = MachineThreshold::findOrFail($id);
           $machine_id = $threshold->machine_id;
           
           $threshold->delete();

           return redirect()
               ->route('plc.machine.thresholds.show', $machine_id)
               ->with('success', 'Xóa cảnh báo thành công');

       } catch (\Exception $e) {
           return back()->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
       }
   }

   /**
    * Toggle hiển thị trên biểu đồ
    */
   public function toggleChart($id)
   {
       try {
           $threshold = MachineThreshold::findOrFail($id);
           $threshold->show_on_chart = !$threshold->show_on_chart;
           $threshold->save();

           return response()->json([
               'success' => true,
               'show_on_chart' => $threshold->show_on_chart
           ]);

       } catch (\Exception $e) {
           return response()->json([
               'success' => false,
               'message' => $e->getMessage()
           ], 500);
       }
   }

   /**
    * Toggle trạng thái active
    */
   public function toggleStatus($id)
   {
       try {
           $threshold = MachineThreshold::findOrFail($id);
           $threshold->status = !$threshold->status;
           $threshold->save();

           return response()->json([
               'success' => true,
               'status' => $threshold->status
           ]);

       } catch (\Exception $e) {
           return response()->json([
               'success' => false,
               'message' => $e->getMessage()
           ], 500);
       }
   }

   /**
    * Process form data to create conditions array
    */
   protected function processThresholdData($data)
   {
       // Convert boolean values
       $data['boolean_value'] = isset($data['boolean_value']) ? (bool)$data['boolean_value'] : null;

       // Convert checkboxes
       $data['use_boolean'] = isset($data['use_boolean']);
       $data['use_range'] = isset($data['use_range']);
       $data['use_percent'] = isset($data['use_percent']);
       $data['use_avg'] = isset($data['use_avg']);

       // Convert numeric values
       $numericFields = ['min_value', 'max_value', 'base_value', 'percent', 'avg_base_value', 'avg_percent'];
       foreach ($numericFields as $field) {
           if (isset($data[$field]) && $data[$field] !== '') {
               $data[$field] = (float)$data[$field];
           } else {
               $data[$field] = null;
           }
       }

       // Build conditions array
       $conditions = [];

       // Boolean condition
       if (isset($data['use_boolean']) && $data['use_boolean'] && isset($data['boolean_value'])) {
           $conditions[] = [
               'type' => 'boolean',
               'value' => $data['boolean_value'],
               'message' => isset($data['warning_message']) ? $data['warning_message'] : 'Cảnh báo trạng thái boolean'
           ];
       }

       // Range condition
       if (isset($data['use_range']) && $data['use_range'] && (isset($data['min_value']) || isset($data['max_value']))) {
           $conditions[] = [
               'type' => 'range',
               'min' => isset($data['min_value']) ? $data['min_value'] : null,
               'max' => isset($data['max_value']) ? $data['max_value'] : null
           ];
       }

       // Percent condition
       if (isset($data['use_percent']) && $data['use_percent'] && isset($data['base_value']) && isset($data['percent'])) {
           $conditions[] = [
               'type' => 'percent',
               'base_value' => $data['base_value'],
               'percent' => $data['percent']
           ];
       }

       // Average condition
       if (isset($data['use_avg']) && $data['use_avg'] && isset($data['avg_base_value']) && isset($data['avg_percent'])) {
           $conditions[] = [
               'type' => 'avg',
               'base_value' => $data['avg_base_value'],
               'percent' => $data['avg_percent']
           ];
       }

       // Set conditions and operator
       $data['conditions'] = $conditions;

       // Remove fields not in model if they exist
       $fieldsToUnset = [
           'use_boolean', 'use_range', 'use_percent', 'use_avg',
           'boolean_value', 'warning_message', 'min_value', 'max_value',
           'base_value', 'percent', 'avg_base_value', 'avg_percent'
       ];
       
       foreach ($fieldsToUnset as $field) {
           if (isset($data[$field])) {
               unset($data[$field]);
           }
       }

       return $data;
   }
}
