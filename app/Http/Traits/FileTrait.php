<?php


namespace App\Http\Traits;



use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait FileTrait
{

    public function upload($file,$type)
    {
        $directory = 'images/'.$type;
        $filename = time().Str::random(30).'.'.$file->getClientOriginalExtension();
        $img = [
            'date' => time(),
            'name' => $file->getClientOriginalName(),
            'url' => 'storage/'.$directory.'/'.$filename
        ];
        Storage::disk('public')->putFileAs($directory,$file,$filename);
        return $img;
    }

    public function uploadDocuments($file,$type) {
        $directory = 'documents/'.$type;
        $filename = time().Str::random(30).'.'.$file->getClientOriginalExtension();
        $img = [
            'date' => time(),
            'name' => $file->getClientOriginalName(),
            'url' => 'storage/'.$directory.'/'.$filename
        ];
        Storage::disk('public')->putFileAs($directory,$file,$filename);
        return $img;
    }

    public function delete($path_to_file)
    {
        Storage::disk('public')->delete($path_to_file);
        return true;
    }

    public function createFromBase64(string $base64, $name = 'image',$type)
    {
        $image = substr($base64, strpos($base64, ',') + 1);
        $directory = 'images/'.$type;
        $filename = time().Str::random(30).'.'.'png';
        $img = [
            'date' => time(),
            'name' => $name.time(),
            'url' => 'storage/'.$directory.'/'.$filename
        ];
        Storage::disk('public')->put($directory.'/'.$filename,base64_decode($image));
        return $img;
    }

}
