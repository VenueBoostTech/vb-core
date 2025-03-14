<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\InventorySync;
use Illuminate\Support\Facades\Log;

class FinalizeSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchId;
    protected $syncRecordId;

    /**
     * Create a new job instance.
     */
    public function __construct($batchId, $syncRecordId)
    {
        $this->batchId = $batchId;
        $this->syncRecordId = $syncRecordId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Finalizing sync batch", ['batch_id' => $this->batchId]);

        $syncRecord = InventorySync::find($this->syncRecordId);
        if ($syncRecord) {
            $syncRecord->status = 'completed';
            $syncRecord->completed_at = now();
            $syncRecord->save();

            Log::info("Sync batch marked as complete", [
                'batch_id' => $this->batchId,
                'total_processed' => $syncRecord->processed_pages
            ]);
        } else {
            Log::error("Sync record not found", [
                'batch_id' => $this->batchId,
                'sync_record_id' => $this->syncRecordId
            ]);
        }
    }
}
