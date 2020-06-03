<?php

namespace App\Jobs;

use App\Flat;
use App\Commerce_US;
use App\House_US;
use App\Land_US;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Events\WriteHistories;
use Illuminate\Support\Facades\Log;
use App\Country;
use App\Area;
use App\Region;
use App\City;
use App\Street;
use App\District;
use App\Microarea;
use App\Landmark;

class WriteHistoryItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $history;
    public $object;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $data, $object = null)
    {
        $this->history = $data;
        $this->object = $object;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(!is_null($this->object)) {
            $old_1 = array();
            $old_2 = array();
            $old_3 = array();
            $new_1 = array();
            $new_2 = array();
            $new_3 = array();

            if(isset($this->history['old']['country_id'])) {
                $country = Country::find($this->history['old']['country_id']);
                array_push($old_1, $country->name);
            }

            if(isset($this->history['old']['area_id'])) {
                $area = Area::find($this->history['old']['area_id']);
                array_push($old_1, $area->name);
            }

            if(isset($this->history['old']['region_id'])) {
                $region = Region::find($this->history['old']['region_id']);
                array_push($old_1, $region->name);
            }

            if(isset($this->history['old']['city_id'])) {
                $city = City::find($this->history['old']['city_id']);
                array_push($old_1, $city->name);
            }

            if(isset($this->history['old']['street_id'])) {
                $street = Street::find($this->history['old']['street_id']);
                array_push($old_2, $street->full_name());
            }

            if(isset($this->history['old']['house_id'])) {
                array_push($old_2, '№'.$this->history['old']['house_id']);
            }

            if(isset($this->history['old']['district_id'])) {
                $district = District::find($this->history['old']['district_id']);
                if($district) {
                    array_push($old_3, $district->name);
                }
            }

            if(isset($this->history['old']['microarea_id'])) {
                $microarea = Microarea::find($this->history['old']['microarea_id']);
                if($microarea) {
                    array_push($old_3, $microarea->name);
                }
            }

            if(isset($this->history['old']['landmark_id'])) {
                $landMark = Landmark::find($this->history['old']['landmark_id']);
                if($landMark) {
                    array_push($old_3, $landMark->name);
                }
            }

            if(isset($this->history['new']['country_id'])) {
                $country = Country::find($this->history['new']['country_id']);
                array_push($new_1, $country->name);
            }

            if(isset($this->history['new']['area_id'])) {
                $area = Area::find($this->history['new']['area_id']);
                array_push($new_1, $area->name);
            }

            if(isset($this->history['new']['region_id'])) {
                $region = Region::find($this->history['new']['region_id']);
                array_push($new_1, $region->name);
            }

            if(isset($this->history['new']['city_id'])) {
                $city = City::find($this->history['new']['city_id']);
                array_push($new_1, $city->name);
            }

            if(isset($this->history['new']['street_id'])) {
                $street = Street::find($this->history['new']['street_id']);
                array_push($new_2, $street->full_name());
            }

            if(isset($this->history['new']['house_id'])) {
                array_push($new_2, '№'.$this->history['new']['house_id']);
            }

            if(isset($this->history['new']['district_id'])) {
                $district = District::find($this->history['new']['district_id']);
                if($district) {
                    array_push($new_3, $district->name);
                }
            }

            if(isset($this->history['new']['microarea_id'])) {
                $microarea = Microarea::find($this->history['new']['microarea_id']);
                if($microarea) {
                    array_push($new_3, $microarea->name);
                }
            }

            if(isset($this->history['new']['landmark_id'])) {
                $landMark = Landmark::find($this->history['new']['landmark_id']);
                if($landMark) {
                    array_push($new_3, $landMark->name);
                }
            }

            switch (class_basename($this->object)) {
                case "Flat":
                    if(isset($this->history['old']['section_number'])) {
                        array_push($old_2, 'корпус '.$this->history['old']['section_number']);
                    }
                    if(isset($this->history['new']['section_number'])) {
                        array_push($new_2, 'корпус '.$this->history['new']['section_number']);
                    }

                    if(isset($this->history['old']['flat_number'])) {
                        array_push($old_2, 'кв.'.$this->history['old']['flat_number']);
                    } else {
                        array_push($old_2, 'кв.'.$this->object->flat_number);
                    }

                    if(isset($this->history['new']['flat_number'])) {
                        array_push($new_2, 'кв.'.$this->history['new']['flat_number']);
                    } else {
                        array_push($new_2, 'кв.'.$this->object->flat_number);
                    }

                    $result = ['user_id'=>$this->history['user_id'], 'old'=>implode(', ', $old_1)."<br>".implode(', ', $old_2)."<br>".implode(', ', $old_3), 'new'=>implode(', ', $new_1)."<br>".implode(', ', $new_2)."<br>".implode(', ', $new_3)];

                    $history = ['type'=>'change_address', 'model_type'=>'App\\'.class_basename($this->object), 'model_id'=>$this->object->id, 'result'=>collect($result)->toJson()];
                    event(new WriteHistories($history));
                    break;
                case "House_US":
                    if(isset($this->history['old']['section_number'])) {
                        array_push($old_2, 'корпус '.$this->history['old']['section_number']);
                    }
                    if(isset($this->history['new']['section_number'])) {
                        array_push($new_2, 'корпус '.$this->history['new']['section_number']);
                    }
                    $result = ['user_id'=>$this->history['user_id'], 'old'=>implode(', ', $old_1)."<br>".implode(', ', $old_2)."<br>".implode(', ', $old_3), 'new'=>implode(', ', $new_1)."<br>".implode(', ', $new_2)."<br>".implode(', ', $new_3)];

                    $history = ['type'=>'change_address', 'model_type'=>'App\\'.class_basename($this->object), 'model_id'=>$this->object->id, 'result'=>collect($result)->toJson()];
                    event(new WriteHistories($history));
                    break;
                case "Land_US":
                    if(isset($this->history['old']['land_number'])) {
                        array_pop($old_2);
                        array_push($old_2, '№'.$this->history['old']['land_number']);
                    }

                    if(isset($this->history['new']['land_number'])) {
                        array_pop($new_2);
                        array_push($new_2, '№'.$this->history['new']['land_number']);
                    }

                    $result = ['user_id'=>$this->history['user_id'], 'old'=>implode(', ', $old_1)."<br>".implode(', ', $old_2)."<br>".implode(', ', $old_3), 'new'=>implode(', ', $new_1)."<br>".implode(', ', $new_2)."<br>".implode(', ', $new_3)];

                    $history = ['type'=>'change_address', 'model_type'=>'App\\'.class_basename($this->object), 'model_id'=>$this->object->id, 'result'=>collect($result)->toJson()];
                    event(new WriteHistories($history));
                    break;
                case "Commerce_US":
                    if(isset($this->history['old']['section_number'])) {
                        array_push($old_2, 'корпус '.$this->history['old']['section_number']);
                    }
                    if(isset($this->history['new']['section_number'])) {
                        array_push($new_2, 'корпус '.$this->history['new']['section_number']);
                    }

                    if(isset($this->history['old']['office_number'])) {
                        array_push($old_2, 'оф.'.$this->history['old']['office_number']);
                    } else {
                        array_push($old_2, 'оф.'.$this->object->office_number);
                    }

                    if(isset($this->history['new']['office_number'])) {
                        array_push($new_2, 'оф.'.$this->history['new']['office_number']);
                    } else {
                        array_push($new_2, 'оф.'.$this->object->office_number);
                    }

                    $result = ['user_id'=>$this->history['user_id'], 'old'=>implode(', ', $old_1)."<br>".implode(', ', $old_2)."<br>".implode(', ', $old_3), 'new'=>implode(', ', $new_1)."<br>".implode(', ', $new_2)."<br>".implode(', ', $new_3)];

                    $history = ['type'=>'change_address', 'model_type'=>'App\\'.class_basename($this->object), 'model_id'=>$this->object->id, 'result'=>collect($result)->toJson()];
                    event(new WriteHistories($history));
                    break;
            }
        } else {
            if(isset($this->history['building']->list_obj)) {
                $list_obj = json_decode($this->history['building']->list_obj);
                foreach ($list_obj as $object) {
                    if(isset($object->obj->model) && isset($object->obj->obj_id)) {
                        $old_1 = array();
                        $old_2 = array();
                        $old_3 = array();
                        $new_1 = array();
                        $new_2 = array();
                        $new_3 = array();

                        if(isset($this->history['old']['country_id'])) {
                            $country = Country::find($this->history['old']['country_id']);
                            array_push($old_1, $country->name);
                        }

                        if(isset($this->history['old']['area_id'])) {
                            $area = Area::find($this->history['old']['area_id']);
                            array_push($old_1, $area->name);
                        }

                        if(isset($this->history['old']['region_id'])) {
                            $region = Region::find($this->history['old']['region_id']);
                            array_push($old_1, $region->name);
                        }

                        if(isset($this->history['old']['city_id'])) {
                            $city = City::find($this->history['old']['city_id']);
                            array_push($old_1, $city->name);
                        }

                        if(isset($this->history['old']['street_id'])) {
                            $street = Street::find($this->history['old']['street_id']);
                            array_push($old_2, $street->name);
                        }

                        if(isset($this->history['old']['house_id'])) {
                            array_push($old_2, '№'.$this->history['old']['house_id']);
                        }

                        if(isset($this->history['old']['district_id'])) {
                            $district = District::find($this->history['old']['district_id']);
                            if($district) {
                                array_push($old_3, $district->name);
                            }
                        }

                        if(isset($this->history['old']['microarea_id'])) {
                            $microarea = Microarea::find($this->history['old']['microarea_id']);
                            if($microarea) {
                                array_push($old_3, $microarea->name);
                            }
                        }

                        if(isset($this->history['old']['landmark_id'])) {
                            $landMark = Landmark::find($this->history['old']['landmark_id']);
                            if($landMark) {
                                array_push($old_3, $landMark->name);
                            }
                        }

                        if(isset($this->history['new']['country_id'])) {
                            $country = Country::find($this->history['new']['country_id']);
                            array_push($new_1, $country->name);
                        }

                        if(isset($this->history['new']['area_id'])) {
                            $area = Area::find($this->history['new']['area_id']);
                            array_push($new_1, $area->name);
                        }

                        if(isset($this->history['new']['region_id'])) {
                            $region = Region::find($this->history['new']['region_id']);
                            array_push($new_1, $region->name);
                        }

                        if(isset($this->history['new']['city_id'])) {
                            $city = City::find($this->history['new']['city_id']);
                            array_push($new_1, $city->name);
                        }

                        if(isset($this->history['new']['street_id'])) {
                            $street = Street::find($this->history['new']['street_id']);
                            array_push($new_2, $street->name);
                        }

                        if(isset($this->history['new']['house_id'])) {
                            array_push($new_2, '№'.$this->history['new']['house_id']);
                        }

                        if(isset($this->history['new']['district_id'])) {
                            $district = District::find($this->history['new']['district_id']);
                            if($district) {
                                array_push($new_3, $district->name);
                            }
                        }

                        if(isset($this->history['new']['microarea_id'])) {
                            $microarea = Microarea::find($this->history['new']['microarea_id']);
                            if($microarea) {
                                array_push($new_3, $microarea->name);
                            }
                        }

                        if(isset($this->history['new']['landmark_id'])) {
                            $landMark = Landmark::find($this->history['new']['landmark_id']);
                            if($landMark) {
                                array_push($new_3, $landMark->name);
                            }
                        }

                        switch ($object->obj->model) {
                            case "Flat":
                                $flat = Flat::find($object->obj->obj_id);
                                if(isset($this->history['old']['section_number'])) {
                                    array_push($old_2, 'корпус '.$this->history['old']['section_number']);
                                }
                                if(isset($this->history['new']['section_number'])) {
                                    array_push($new_2, 'корпус '.$this->history['new']['section_number']);
                                }

                                array_push($old_2, 'кв.'.$flat->flat_number);
                                array_push($new_2, 'кв.'.$flat->flat_number);

                                $result = ['user_id'=>$this->history['user_id'], 'old'=>implode(', ', $old_1)."<br>".implode(', ', $old_2)."<br>".implode(', ', $old_3), 'new'=>implode(', ', $new_1)."<br>".implode(', ', $new_2)."<br>".implode(', ', $new_3)];

                                $history = ['type'=>'change_address', 'model_type'=>'App\\'.class_basename($object->obj->model), 'model_id'=>$object->obj->obj_id, 'result'=>collect($result)->toJson()];
                                event(new WriteHistories($history));
                                break;
                            case "House_US":
                                if(isset($this->history['old']['section_number'])) {
                                    array_push($old_2, 'корпус '.$this->history['old']['section_number']);
                                }
                                if(isset($this->history['new']['section_number'])) {
                                    array_push($new_2, 'корпус '.$this->history['new']['section_number']);
                                }
                                $result = ['user_id'=>$this->history['user_id'], 'old'=>implode(', ', $old_1)."<br>".implode(', ', $old_2)."<br>".implode(', ', $old_3), 'new'=>implode(', ', $new_1)."<br>".implode(', ', $new_2)."<br>".implode(', ', $new_3)];

                                $history = ['type'=>'change_address', 'model_type'=>'App\\'.class_basename($object->obj->model), 'model_id'=>$object->obj->obj_id, 'result'=>collect($result)->toJson()];
                                event(new WriteHistories($history));
                                break;
                            case "Land_US":
                                $land = Land_US::find($object->obj->obj_id);
                                $result = ['user_id'=>$this->history['user_id'], 'old'=>implode(', ', $old_1)."<br>".implode(', ', $old_2)."<br>".implode(', ', $old_3), 'new'=>implode(', ', $new_1)."<br>".implode(', ', $new_2)."<br>".implode(', ', $new_3)];

                                $history = ['type'=>'change_address', 'model_type'=>'App\\'.class_basename($object->obj->model), 'model_id'=>$object->obj->obj_id, 'result'=>collect($result)->toJson()];
                                event(new WriteHistories($history));
                                break;
                            case "Commerce_US":
                                $commerce = Commerce_US::find($object->obj->obj_id);
                                array_push($old_2, 'оф.'.$commerce->office_number);
                                array_push($new_2, 'оф.'.$commerce->office_number);
                                if(isset($this->history['old']['section_number'])) {
                                    array_push($old_2, 'корпус '.$this->history['old']['section_number']);
                                }
                                if(isset($this->history['new']['section_number'])) {
                                    array_push($new_2, 'корпус '.$this->history['new']['section_number']);
                                }
                                $result = ['user_id'=>$this->history['user_id'], 'old'=>implode(', ', $old_1)."<br>".implode(', ', $old_2)."<br>".implode(', ', $old_3), 'new'=>implode(', ', $new_1)."<br>".implode(', ', $new_2)."<br>".implode(', ', $new_3)];

                                $history = ['type'=>'change_address', 'model_type'=>'App\\'.class_basename($object->obj->model), 'model_id'=>$object->obj->obj_id, 'result'=>collect($result)->toJson()];
                                event(new WriteHistories($history));
                                break;
                        }
                    }
                }
            }
        }
    }
}
