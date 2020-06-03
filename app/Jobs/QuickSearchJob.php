<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Traits\FlatTrait;
use App\Http\Traits\LandTrait;
use App\Http\Traits\CommerceTrait;
use App\Http\Traits\PrivateHouseTrait;

class QuickSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels,FlatTrait,LandTrait,CommerceTrait,PrivateHouseTrait;

    public $tries = 5;
    protected $type;
    protected $object;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($type, $object)
    {
        $this->type = $type;
        $this->object = $object;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        switch ($this->type){

            case 'flat':
                $this->Flatindex($this->object);
                break;

            case 'land':
                $this->Landindex($this->object);
                break;

            case 'commerce':
                $this->Commerceindex($this->object);
                break;

            case 'private-house':
                $this->PrivateHouseindex($this->object);
                break;
        }
    }
}
