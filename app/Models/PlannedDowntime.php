<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlannedDowntime extends Model
{
    protected $fillable = [
        'machine_id',
        'date',
        'shift',
        'hours',
        'actual_hours',
        'type',
        'status',
        'reason',
        'note',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'approved_at' => 'datetime'
    ];

    // Định nghĩa các giá trị enum
    const TYPES = [
        'maintenance' => 'Bảo trì định kỳ',
        'repair' => 'Sửa chữa',
        'holiday' => 'Nghỉ lễ',
        'other' => 'Khác'
    ];

    const STATUSES = [
        'planned' => 'Đã lên kế hoạch',
        'completed' => 'Đã hoàn thành',
        'cancelled' => 'Đã hủy'
    ];

    const SHIFTS = [
        'CA1' => 'Ca 1 (6h-14h)',
        'CA2' => 'Ca 2 (14h-22h)',
        'CA3' => 'Ca 3 (22h-6h)'
    ];

    // Relationships
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Accessors & Mutators
    public function getTypeTextAttribute()
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getStatusTextAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getShiftTextAttribute()
    {
        return self::SHIFTS[$this->shift] ?? $this->shift;
    }
} 