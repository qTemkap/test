<?php

namespace App;

use Carbon\Carbon;

class FilterDate {

	// Для комнат и санузлов отдельная басня. Хитрый диапазон.
	static protected $date = ['today', 'yesterday', 'week', 'month', 'quarter', 'n_days',
		'year', 'any_day', 'range_from', 'range_to', 'previous_month', 'previous_week'];
	// Статическая переменная названия таблицы.
	static $table;

	// Формирование фильтра по дате.
	static public function getDate($date, $table) {
		self::$table = $table;
		$key = $date->keys()->get('0');
		$item = $date->get($key);
		switch ($key) {
			case 'today':
				return [[$table . '.created_at', '=', $res = Carbon::today()->toDateString()]];
				break;
			case 'yesterday':
				return [[$table . '.created_at', '=', $res = Carbon::yesterday()->toDateString()]];
				break;
			case 'week':
				return [[$table . '.created_at', '>=', Carbon::now()->startOfWeek()->toDateString()]];
				break;
			case 'month':
				$month = new Carbon('first day of this month');
				return [[$table . '.created_at', '>=', $month->toDateString()]];
				break;
			case 'previous_week':
				return [[$table . '.created_at', '>=', Carbon::now()->startOfWeek()->subWeek()->toDateString()],
					[$table . '.created_at', '<=', Carbon::now()->subWeek()->endOfWeek()->toDateString()]];
				break;
			case 'previous_month':
				return [[$table . '.created_at', '>=', Carbon::now()->startOfMonth()->subMonth()->toDateString()],
					[$table . '.created_at', '<=', Carbon::now()->subMonth()->endOfMonth()->toDateString()]];
				break;
			case 'quarter':
				return static::getQuarter($item);
				break;
//            case 'date_n_days':
//                return [[$table . '.created_at', '>', $res = Carbon::now()->subDays($item)->toDateTimeString()]];
//                break;
//            case 'date_year':
//                $date = Carbon::create($item, 1, 1, 0, 0, 0);
//                return [[$table . '.created_at', '>=', $date->toDateTimeString()], [$table . '.created_at', '<=', $date->addYear()->toDateTimeString()]];
//                break;
			case 'any_day':
				return [[$table . '.created_at', '=', $res = Carbon::parse($item)->toDateString()]];
				break;
			case 'range_from':
				return static::getRange($date);
				break;
			case 'range_to':
				return static::getRange($date);
				break;
			default:
				break;
		}
	}

	// Дата, формирование квартала.
	static private function getQuarter($num) {
		$date = Carbon::createFromDate(Carbon::now()->format('Y'), '1', '1');
		switch ($num) {
			case '1':
				$from = $date->toDateString();
				$to = $date->addQuarter(1)->toDateString();
				return [[self::$table . '.created_at', '>=', $from], [self::$table . '.created_at', '=', $to]];
				break;
			case '2':
				$from = $date->addQuarter(1)->toDateString();
				$to = $date->addQuarter(1)->toDateString();
				return [[self::$table . '.created_at', '>=', $from], [self::$table . '.created_at', '=', $to]];
				break;
			case '3':
				$from = $date->addQuarter(2)->toDateString();
				$to = $date->addQuarter(1)->toDateString();
				return [[self::$table . '.created_at', '>=', $from], [self::$table . '.created_at', '=', $to]];
				break;
			case '4':
				$from = $date->addQuarter(3)->toDateString();
				$to = $date->addQuarter(1)->toDateString();
				return [[self::$table . '.created_at', '>=', $from], [self::$table . '.created_at', '=', $to]];
				break;
			default:
				break;
		}
	}

	// Диапазон дат с переворотом умным.
	static public function getRange($date) {
		$from = collect($date)->get('date_range_from');
		$to = collect($date)->get('date_range_to');
		$result = self::checkRange($from, $to);
		return [[self::$table . '.created_at', '>=', $result['from']], [self::$table . '.created_at', '<', $result['to']]];
	}

	// Проверка правильный ли диапазон.
	static private function checkRange($from, $to) {
		$from = Carbon::parse($from);
		$to = Carbon::parse($to);
		if ($from > $to) {
			$tmp = $to;
			$to = $from;
			$from = $tmp;
		}
		return ['from' => $from->toDateString(), 'to' => $to->toDateString()];
	}

	// Получить массив дат для класса фильтра.
	static public function getDateField() {
		return self::$date;
	}

}