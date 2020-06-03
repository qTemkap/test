<?php

namespace App\Http\Traits;

use App\Logo;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Log;

trait SetDopPhotoTrait {

    public function setWatermark($value){
        $logo = Logo::all()->toArray();
        if($logo) {
            try{
                $img = Image::make(public_path($value));
                $img->insert(public_path(current($logo)['file_name']), 'bottom-right', 10, 10);
                $name_file = explode('/', $value);
                $names = explode('.', end($name_file));
                $name = $names[0];

                $img->save(public_path(str_replace($name, $name.'_watermark', $value)));
                return str_replace($name, $name.'_watermark', $value);
            } catch (\Exception $exception) {
                Log::channel('watermarklog')->info($value." - ".$exception->getMessage());
            }
        }
    }

    public function setText($value){
        try{
            $img = Image::make(public_path($value));
            $width = $img->width();
            $width = ($width/2);
            $height = $img->height();
            $img->text("НЕ ДЛЯ РЕКЛАМЫ", $width, $height/1.7, function($font) use($width) {
                $font->file(public_path('fonts/18888.ttf'));
                $font->size($width/5);
                $font->color('rgba(0, 0, 0, 0.5)');
                $font->align('center');
                $font->valign('bottom');
            });
            $name_file = explode('/', $value);
            $names = explode('.', end($name_file));
            $name = $names[0];

            $img->save(public_path(str_replace($name, $name.'_with_text', $value)));
            return str_replace($name, $name.'_with_text', $value);
        } catch (\Exception $exception) {
            Log::channel('watermarklog')->info($value." - ".$exception->getMessage());
        }
    }

}