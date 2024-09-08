<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Collection;
use App\Models\Photo;
use App\Models\Restaurant;

class UploadCollectionPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $collection;
    protected $photoUrl;
    protected $venue;

    /**
     * Create a new job instance.
     *
     * @param Collection $collection
     * @param string $photoUrl
     * @param Restaurant $venue
     */
    public function __construct(Collection $collection, string $photoUrl, Restaurant $venue)
    {
        $this->collection = $collection;
        $this->photoUrl = $photoUrl;
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
            \Log::info('Processing UploadCollectionPhotoJob', [
                'collection_id' => $this->collection->id,
                'photo_url' => $this->photoUrl
            ]);

            $photoContents = file_get_contents($this->photoUrl);
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

                $this->collection->update(['logo_path' => $path]);

                \Log::info('UploadCollectionPhotoJob completed successfully', [
                    'collection_id' => $this->collection->id,
                    'photo_path' => $path
                ]);
            } else {
                \Log::error('Failed to fetch photo contents', [
                    'photo_url' => $this->photoUrl
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error processing UploadCollectionPhotoJob', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }


}
