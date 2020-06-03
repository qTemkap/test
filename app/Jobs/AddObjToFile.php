<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use App\Http\Traits\XMLTrait;

class AddObjToFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, XMLTrait;

    public $tries = 5;
    protected $site_id;
    protected $type;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($site_id, $type = false)
    {
        $this->site_id = $site_id;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if($this->type == false) {
            $this->CreateFileXML_new($this->site_id);
        } else {
            $this->CreateFileXML_withType($this->site_id, $this->type);
        }
    }
}
