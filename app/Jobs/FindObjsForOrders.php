<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Traits\SearchObjsForOrderTrait;
use Illuminate\Support\Facades\Log;

class FindObjsForOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SearchObjsForOrderTrait;

    public $tries = 5;
    protected $data = array();
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
        $this->SearchObjects($this->data);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

    }
}
