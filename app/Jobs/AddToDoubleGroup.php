<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Traits\DoubleObjectTrait;

class AddToDoubleGroup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, DoubleObjectTrait;

    public $tries = 5;
    public $type;
    public $obj_id;
    public $list_objects;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($type, $obj_id, $list_objects)
    {
        $this->type = $type;
        $this->obj_id = $obj_id;
        $this->list_objects = $list_objects;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if($this->type != "Building") {
            $this->AddObjectToGroup($this->type, $this->obj_id, $this->list_objects);
        } else {
            $this->AddBuildingToGroup($this->obj_id, $this->list_objects);
        }

    }
}
