<?php

namespace App\Traits\Filter;

use App\Flat;
use App\House_US;
use App\Land_US;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait ObjectApiFilterTrait {

    public function scopeFilter($query, $data) {
        if ($data->id) $query->filterById($data->id);
        if ($data->owner) $query->filterByOwner($data->owner);
        if ($data->department) $query->filterByDepartment($data->department);
        if ($data->createdAfter) $query->filterCreatedAfter($data->createdAfter);
        if ($data->createdBefore) $query->filterCreatedBefore($data->createdBefore);
        if ($data->updatedAfter) $query->filterUpdatedAfter($data->updatedAfter);
        if ($data->updatedBefore) $query->filterUpdatedBefore($data->updatedBefore);

        if ($data->region_id) $query->filterByAddress($data->region_id, $data->area_id, $data->city_id);
        if ($data->district_id) {
            $query->filterByDistricts(explode(",", $data->district_id));
        }
        if ($data->microarea_id) $query->filterByMicroareas(explode(",", $data->microarea_id));
        if ($data->landmark_id) $query->filterByLandmarks(explode(",", $data->landmark_id));

        if ($data->floor_from || $data->floor_to) $query->filterByFloor($data->floor_from, $data->floor_to);
        if ($data->max_floor_from || $data->max_floor_to) $query->filterByMaxFloor($data->max_floor_from, $data->max_floor_to);
        if ($data->not_first_floor) $query->notFirstFloor();
        if ($data->not_last_floor) $query->notLastFloor();

        if ($data->price_from || $data->price_to) {
            if ($data->price_for == 1 || $data->price_for == null) {
                $query->filterByPrice($data->price_from, $data->price_to);
            }
            elseif ($data->price_for == 2) {
                $query->filterByPriceForMeter($data->price_from, $data->price_to);
            }

        }

        if ($data->currency) $query->filterByCurrency($data->currency);

        if ($data->total_area_from || $data->total_area_to) $query->filterByTotalArea($data->total_area_from, $data->total_area_to);
        if ($data->land_plot_area_from || $data->land_plot_area_to) $query->filterByPlotArea($data->land_plot_area_from, $data->land_plot_area_to);
        if ($data->square_value) $query->filterByPlotUnit($data->square_value);

        if ($data->rooms_count || $data->cnt_room_1 || $data->cnt_room_2 || $data->cnt_room_3 || $data->cnt_room_4) {
            $query->filterByRoomsCount($data->rooms_count, $data->cnt_room_1, $data->cnt_room_2, $data->cnt_room_3, $data->cnt_room_4);
        }

        if ($data->release_date_from || $data->release_date_to) $query->filterByReleaseDate($data->release_date_from, $data->release_date_to);

        if ($data->sort_by) {
            $query->applySort($data->sort_by, $data->sort_order);
        }

        return $query;
    }

    public function scopeApplySort($query, $sort_by, $sort_order = null) {
        switch ($sort_by) {
            case 'total_area': $query->sortByTotalArea($sort_order ?? 'ASC'); break;
            case 'price': $query->sortByPrice($sort_order ?? 'ASC'); break;
            case 'price_for_meter': $query->sortByPriceForMeter($sort_order ?? 'ASC'); break;
            case 'floor': $query->sortByFloor($sort_order ?? 'ASC'); break;
            case 'max_floor': $query->sortByMaxFloor($sort_order ?? 'ASC'); break;
            case 'rooms_count': $query->sortByRoomsCount($sort_order ?? 'ASC'); break;
            case 'created_at': $query->sortByCreatedAt($sort_order ?? 'ASC'); break;
        }
    }

    public function scopeFilterById($query, $id) {
        return $query->where('id', $id);
    }

    public function scopeFilterByOwner($query, $ownerId) {
        return $query->whereHas('responsible', function($query) use ($ownerId) {
            return $query->where('bitrix_id', $ownerId);
        });
    }

    public function scopeFilterByDepartment($query, $departmentId) {
        return $query->whereHas('responsible', function ($query) use ($departmentId) {
            return $query->where('departments->department_bitrix_id', $departmentId);
        });
    }

    public function scopeFilterCreatedAfter($query, $date) {
        return $query->where('created_at', '>=', $date);
    }

    public function scopeFilterCreatedBefore($query, $date) {
        return $query->where('created_at', '<=', $date);
    }

    public function scopeFilterUpdatedAfter($query, $date) {
        return $query->where('updated_at', '>=', $date);
    }

    public function scopeFilterUpdatedBefore($query, $date) {
        return $query->where('updated_at', '<=', $date);
    }

    public function scopeFilterByAddress($query, $region_id, $area_id = null, $city_id = null) {
        return $query->whereHas('building', function ($query) use ($region_id, $area_id, $city_id) {
            return $query->whereHas('address', function($query) use ($region_id, $area_id, $city_id) {
                $query->where('region_id', $region_id);
                if ($area_id) $query->where('area_id', $area_id);
                if ($city_id) $query->where('city_id', $city_id);

                return $query;
            });
        });
    }

    public function scopeFilterByDistricts($query, $district_ids) {
        return $query->whereHas('building', function ($query) use ($district_ids) {
            return $query->whereHas('address', function($query) use ($district_ids) {
                return $query->whereIn('district_id', $district_ids);
            });
        });
    }

    public function scopeFilterByMicroareas($query, $microarea_ids) {
        return $query->whereHas('building', function ($query) use ($microarea_ids) {
            return $query->whereHas('address', function($query) use ($microarea_ids) {
                return $query->whereIn('microarea_id', $microarea_ids);
            });
        });
    }

    public function scopeFilterByLandmarks($query, $landmark_ids) {

        $landmark_id_null = 0;
        $landmark_id = collect($landmark_ids);
        foreach ($landmark_id as $key => $item) {
            if($item == "not_obj") {
                $landmark_id_null = 1;
            }
        }

        $query->whereHas('building',function ($query) use($landmark_id,$landmark_id_null){
                if(count($landmark_id) > 0 && $landmark_id_null>0) {
                    $query->whereIn('landmark_id',$landmark_id)->orWhereNull('landmark_id');
                } else if(count($landmark_id) > 0 && $landmark_id_null==0) {
                    $query->whereIn('landmark_id',$landmark_id);
                } else if(count($landmark_id) == 1 && $landmark_id_null>0) {
                    $query->whereNull('landmark_id');
                }
            });
    }

    public function scopeFilterByFloor($query, $floor_from, $floor_to) {
        if ($this instanceof Land_US || $this instanceof House_US) return $query;
        if ($floor_from) {
            $query->where('floor', '>=', $floor_from);
        }
        if ($floor_to) {
            $query->where('floor', '<=', $floor_to);
        }
    }

    public function scopeFilterByMaxFloor($query, $floor_from, $floor_to) {
        if ($this instanceof Land_US) return $query;
        if ($floor_from) {
            $query->whereHas('building', function($query) use ($floor_from) {
                $query->where('max_floor', '>=', $floor_from);
            });
        }
        if ($floor_to) {
            $query->whereHas('building', function($query) use ($floor_to) {
                $query->where('max_floor', '<=', $floor_to);
            });
        }
    }

    public function scopeNotFirstFloor($query) {
        if ($this instanceof Land_US) return $query;
        return $query->where('floor', '<>', 1);
    }

    public function scopeNotLastFloor($query) {
        if ($this instanceof Land_US) return $query;
        $foreign = $this instanceof Flat ? 'building_id' : 'obj_building_id';
        $query
            ->leftJoin('obj_building', $this->table . '.' . $foreign, '=', 'obj_building.id')
            ->addSelect([
                $this->table . '.*',
                DB::raw('(IF (obj_building.max_floor IS NOT NULL, obj_building.max_floor, (IF (floor IS NOT NULL, floor + 1, 1)))) AS max_floor')
            ])
            ->whereRaw('(IF (floor IS NOT NULL, floor, 0)) < max_floor');
    }

    public function scopeFilterByPrice($query, $price_from, $price_to) {
        if ($price_from) {
            $query->whereHas('price', function($query) use ($price_from) {
                $query->where('price', '>=', $price_from);
            });
        }
        if ($price_to) {
            $query->whereHas('price', function($query) use ($price_to) {
                $query->where('price', '<=', $price_to);
            });
        }
    }

    public function scopeFilterByCurrency($query, $currency) {
        $query->whereHas('price', function ($query) use ($currency) {
            $query->whereHas('currency', function($query) use ($currency) {
                $query->where('name', $currency);
            });
        });
    }

    public function scopeFilterByPriceForMeter($query, $price_from, $price_to) {
        if ($price_from) {
            $query->where('price_for_meter', '>=', $price_from);
        }
        if ($price_to) {
            $query->where('price_for_meter', '<=', $price_to);
        }
    }

    public function scopeFilterByTotalArea($query, $area_from, $area_to) {
        if ($area_from) {
            $query->where('total_area', '>=', $area_from);
        }
        if ($area_to) {
            $query->where('total_area', '<=', $area_to);
        }
    }

    public function scopeFilterByRoomsCount($query, $rooms = null, $cnt_room_1 = null, $cnt_room_2 = null, $cnt_room_3 = null, $cnt_room_4 = null) {
        if ($this instanceof Land_US) return $query;

        $query->where(function($query) use ($rooms, $cnt_room_1, $cnt_room_2, $cnt_room_3, $cnt_room_4) {
            if ($rooms) {
                $query->orWhere('count_rooms_number', $rooms);
            }
            $cnt_room_field = $this instanceof Flat ? 'cnt_room' : 'count_rooms';

            if ($cnt_room_1) {
                $query->orWhere($cnt_room_field, 1);
            }
            if ($cnt_room_2) {
                $query->orWhere($cnt_room_field, 2);
            }
            if ($cnt_room_3) {
                $query->orWhere($cnt_room_field, 3);
            }
            if ($cnt_room_4) {
                $query->orWhere($cnt_room_field, 4);
            }
        });
    }

    public function scopeFilterByPlotArea($query, $area_from, $area_to) {
        if ($this instanceof Flat) return $query;
        if ($area_from) {
            $query->whereHas('land_plot', function ($query) use ($area_from) {
                $query->where('square_of_land_plot', '>=', $area_from);
            });
        }
        if ($area_to) {
            $query->whereHas('land_plot', function ($query) use ($area_to) {
                $query->where('square_of_land_plot', '<=', $area_to);
            });
        }
    }

    public function scopeFilterByPlotUnit($query, $unit) {
        if ($this instanceof Flat) return $query;
        if ($unit) {
            $query->whereHas('land_plot', function ($query) use ($unit) {
                $query->where('spr_land_plot_units_id', $unit);
            });
        }
    }

    public function scopeFilterByReleaseDate($query, $date_from, $date_to) {
        try {
            if ($date_from) {
                $date_from = new Carbon($date_from);

                if ($this instanceof Flat) {
                    $query->whereHas('terms_sale', function ($query) use ($date_from) {
                        $query->where('release_date', '>=', $date_from->format('Y-m-d'));
                    });
                } else {
                    $query->where('release_date', '>=', $date_from->format('Y-m-d'));
                }
            }
            if ($date_to) {
                $date_to = new Carbon($date_to);

                if ($this instanceof Flat) {
                    $query->whereHas('terms_sale', function ($query) use ($date_to) {
                        $query->where('release_date', '<=', $date_to->format('Y-m-d'));
                    });
                } else {
                    $query->where('release_date', '<=', $date_to->format('Y-m-d'));
                }
            }
        }
        catch (\Exception $e) {
            abort(400, 'Неверный формат даты!');
        }
    }

    public function scopeSortByTotalArea($query, $order = "ASC") {
        $query->orderBy('total_area', $order);
    }

    public function scopeSortByPriceForMeter($query, $order = "ASC") {
        $query->orderBy('price_for_meter', $order);
    }

    public function scopeSortByPrice($query, $order = "ASC") {
        if ($this instanceof Flat) {
            $query->select('obj_flat.*')->leftJoin('hst_price', 'hst_price.obj_id', '=', 'obj_flat.id')
                ->orderBy('hst_price.price', $order);
        }
        else {
            $query->select($this->table . '.*')->leftJoin('object_prices', 'object_prices.id', '=', $this->table . '.object_prices_id')
                ->orderBy('object_prices.price', $order);
        }
    }

    public function scopeSortByMaxFloor($query, $order = "ASC") {
        if ($this instanceof Land_US) return $query;
        $foreign = $this instanceof Flat ? 'building_id' : 'obj_building_id';
        $query->select($this->table . '.*')->leftJoin('obj_building', 'obj_building.id', '=', $this->table . '.' . $foreign)
            ->orderBy(DB::raw('ISNULL(obj_building.max_floor)'))
            ->orderBy('obj_building.max_floor', $order);

    }

    public function scopeSortByFloor($query, $order = "ASC") {
        if ($this instanceof Land_US || $this instanceof House_US) return $query;
        $query->orderBy('floor', $order);

    }

    public function scopeSortByRoomsCount($query, $order = "ASC") {
        $query
            ->orderBy(DB::raw('ISNULL(count_rooms_number)'))
            ->orderBy('count_rooms_number', $order);
    }

    public function scopeSortByCreatedAt($query, $order = "ASC") {
        $query->orderBy('created_at', $order);
    }

    public function scopeFilterByIdList($query, $id) {
        return $query->whereIn($this->table . '.id', $id);
    }
}
