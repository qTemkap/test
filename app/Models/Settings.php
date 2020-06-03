<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'option',
        'value'
    ];

    public $timestamps = false;

    /**
     * @param string $option
     * @param $value
     */
    public static function set(string $option, $value)
    {
        self::updateOrCreate([
            'option' => $option
        ], [
            'value' => $value
        ]);
    }

    /**
     * get setting value
     * @param string $option
     * @return bool
     */
    public static function value($option) {
        return self::where('option', $option)->value('value') ?? false;
    }
}
