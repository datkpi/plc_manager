<?php

namespace App\Models;

class Shift
{
    const CA1 = 'CA1';
    const CA2 = 'CA2';
    const CA3 = 'CA3';

    public static function all()
    {
        return collect([
            ['id' => self::CA1, 'name' => 'Ca 1'],
            ['id' => self::CA2, 'name' => 'Ca 2'],
            ['id' => self::CA3, 'name' => 'Ca 3'],
        ]);
    }

    public static function find($id)
    {
        return static::all()->firstWhere('id', $id);
    }

    public static function pluck($value, $key = null)
    {
        return static::all()->pluck($value, $key);
    }
} 