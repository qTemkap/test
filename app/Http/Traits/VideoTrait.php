<?php


namespace App\Http\Traits;



trait VideoTrait
{

    private $video;

    public function createVideoLink($video)
    {
        if (!empty($video)){
            $video = explode('=',$video);
            $this->video = $video[1];
        }
        else
        {
            $this->video = '';
        }

        return $this->video;
    }

}
