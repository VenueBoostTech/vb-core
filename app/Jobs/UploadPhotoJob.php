<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Photo;
use GuzzleHttp\Client;

class UploadPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $model;
    protected $photoUrl;
    protected $fieldName;
    protected $venue;

    public function __construct($model, $photoUrl, $fieldName, $venue)
    {
        $this->model = $model;
        $this->photoUrl = $photoUrl;
        $this->fieldName = $fieldName;
        $this->venue = $venue;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            \Log::info('Processing UploadPhotoJob', [
                'model_id' => $this->model->id,
                'photo_url' => $this->photoUrl
            ]);
            error_log("Processing UploadPhotoJob $this->photoUrl");

            // $photoContents = file_get_contents($this->file_url($this->photoUrl));
            $client = new Client();
            $response = $client->getAsync($this->file_url($this->photoUrl))->wait();
            $photoContents = $response->getBody()->getContents();
            if ($photoContents !== false) {
                $filename = Str::random(20) . '.jpg';
                $requestType = 'other';
                $path = 'venue_gallery_photos/' . $this->venue->venueType->short_name . '/' . $requestType . '/' .
                    strtolower(str_replace(' ', '-', $this->venue->name . '-' . $this->venue->short_code)) . '/' . $filename;

                Storage::disk('s3')->put($path, $photoContents);

                $photo = new Photo();
                $photo->venue_id = $this->venue->id;
                $photo->image_path = $path;
                $photo->type = $requestType;
                $photo->save();

                error_log("DONE UploadPhotoJob $path");

                $this->model->update([$this->fieldName => $path]);

                \Log::info('UploadPhotoJob completed successfully', [
                    'model_id' => $this->model->id,
                    'photo_path' => $path
                ]);
            } else {
                \Log::error('Failed to fetch photo contents', [
                    'photo_url' => $this->photoUrl
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error processing UploadPhotoJob', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function file_url($url)
    {
        $parts = parse_url($url);
        $path_parts = array_map('rawurldecode', explode('/', $parts['path']));
        return
            $parts['scheme'] . '://' .
            $parts['host'] .
            implode('/', array_map('rawurlencode', $path_parts));
    }
}
