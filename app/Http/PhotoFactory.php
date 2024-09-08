<?php
/**
 * Created by PhpStorm.
 * User: judi
 * Date: 06/01/2017
 * Time: 00:44
 */

namespace App\Http;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Image;

class PhotoFactory
{
    public $baseDirectory;

    public function makeThumbnail($file, $image_path)
    {
        $this->createPathIfNotExist($this->baseDirectory);

        $thumb_path = $image_path . '_thumb';

        $thumb = Image::make($file)->fit(300, 300)->encode('jpg',80);;

        Storage::disk('public')->put($thumb_path, $thumb);
        return $thumb_path;
    }

    private function createPathIfNotExist($path)
    {
        if (!File::exists($path)) {
            File::makeDirectory($path, 0775, true);
        }

    }
}
