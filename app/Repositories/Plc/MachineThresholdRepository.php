<?php

namespace App\Repositories\Plc;

use App\Repositories\Support\AbstractRepository;

class MachineThresholdRepository extends AbstractRepository
{

    public function __construct(\Illuminate\Container\Container $app) {
        parent::__construct($app);
    }

    public function model() {
        return 'App\Models\MachineThreshold';
    }

    public function validateCreate($data)
    {
        $rules = [
            'machine_id' => 'required|integer',
            'plc_data_key' => 'required|string',
            'name' => 'required|string|max:255',
            'type' => 'required|in:boolean,range,percent,avg',
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
        ];

        return $rules;
    }

    public function validateUpdate($data)
    {
        return $this->validateCreate($data);
    }

    public function create($data)
    {
        $data = $this->cleanData($data);
        return parent::create($data);
    }

    public function update($id, $data)
    {
        $model = $this->model->find($id);
        if($model) {
            $data = $this->cleanData($data);
            return $model->update($data);
        }
        return false;
    }

    protected function cleanData($data)
    {
        // Đảm bảo $data là một mảng
        if (!is_array($data)) {
            if (is_object($data)) {
                $data = (array) $data;
            } else {
                return []; // Trả về mảng rỗng nếu $data không phải mảng hay object
            }
        }

        // Convert boolean values
        $data['show_on_chart'] = isset($data['show_on_chart']);
        $data['status'] = isset($data['status']);
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
        $data['operator'] = isset($data['operator']) ? $data['operator'] : 'AND';

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
