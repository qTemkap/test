<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Traits\SearchOrdersForObjTrait;

class FindOrdersForObjs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SearchOrdersForObjTrait;

    public $tries = 5;
    protected $data = array();
    protected $terms = array();
    protected $price = array();
    protected $house = array();
    protected $address = array();
    protected $type_id;
    protected $land = array();
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $data, array $terms, array $price, array $house, array $address, $type_id = null, array $land = null)
    {
        $this->data = $data;
        $this->terms = $terms;
        $this->price = $price;
        $this->house = $house;
        $this->address = $address;
        $this->type_id = $type_id;
        $this->land = $land;

        $this->SearchOrders($this->data, $this->terms, $this->price, $this->house, $this->address, $this->type_id, $this->land);
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
