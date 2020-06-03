<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Traits\DoubleObjectTrait;

class UpdateInfoToDoubleGroup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, DoubleObjectTrait;

    public $tries = 5;
    public $type;
    public $obj_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($type, $obj_id)
    {
        $this->type = $type;
        $this->obj_id = $obj_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->UpdateInfo($this->type, $this->obj_id);
    }
}
