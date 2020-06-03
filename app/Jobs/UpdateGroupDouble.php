<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Traits\DoubleObjectTrait;

class UpdateGroupDouble implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, DoubleObjectTrait;

    public $tries = 5;
    protected $type;
    protected $house_id;
    protected $number;
    protected $object;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($type, $house_id, $object, $number = null)
    {
        $this->type = $type;
        $this->house_id = $house_id;
        $this->object = $object;
        $this->number = $number;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if($this->type == "Flat") {
            $this->updateGroupDoubleFlat($this->house_id, $this->number,$this->object);
        } else if($this->type == "Commerce_US") {
            $this->updateGroupDoubleCommerce($this->house_id, $this->number,$this->object);
        } else if($this->type == "Land_US") {
            $this->updateGroupDoubleLand($this->house_id, $this->number,$this->object);
        } else if($this->type == "House_US") {
            $this->updateGroupDoubleHouse($this->house_id, $this->number,$this->object);
        }
    }
}
