<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\InventorySync;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FinalizeSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchId;
    protected $syncRecordId;
    protected $maxAttempts = 3;
    protected $currentAttempt = 1;

    /**
     * Create a new job instance.
     *
     * @param string $batchId
     * @param int $syncRecordId
     * @param int $currentAttempt
     */
    public function __construct($batchId, $syncRecordId, $currentAttempt = 1)
    {
        $this->batchId = $batchId;
        $this->syncRecordId = $syncRecordId;
        $this->currentAttempt = $currentAttempt;

        // Ensure this job runs after other processing jobs
        $this->onQueue('finalize-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Finalizing sync batch", [
            'batch_id' => $this->batchId,
            'attempt' => $this->currentAttempt
        ]);

        try {
            DB::beginTransaction();

            // Use lockForUpdate to prevent race conditions
            $syncRecord = InventorySync::where('id', $this->syncRecordId)
                ->lockForUpdate()
                ->first();

            if (!$syncRecord) {
                DB::rollBack();
                Log::error("Sync record not found", [
                    'batch_id' => $this->batchId,
                    'sync_record_id' => $this->syncRecordId
                ]);
                return;
            }

            // Check if all pages have been processed
            $progress = ($syncRecord->processed_pages / max(1, $syncRecord->total_pages)) * 100;

            if ($syncRecord->processed_pages >= $syncRecord->total_pages) {
                // All pages processed successfully
                $syncRecord->status = 'completed';
                $syncRecord->completed_at = now();
                $syncRecord->save();

                Log::info("Sync batch completed successfully", [
                    'batch_id' => $this->batchId,
                    'total_processed' => $syncRecord->processed_pages,
                    'total_pages' => $syncRecord->total_pages,
                    'progress' => round($progress, 2) . '%'
                ]);

                DB::commit();
            } else if ($this->currentAttempt >= $this->maxAttempts) {
                // Max attempts reached, mark as partially completed
                $syncRecord->status = 'partially_completed';
                $syncRecord->completed_at = now();
                $syncRecord->save();

                Log::warning("Sync batch partially completed", [
                    'batch_id' => $this->batchId,
                    'processed_pages' => $syncRecord->processed_pages,
                    'total_pages' => $syncRecord->total_pages,
                    'progress' => round($progress, 2) . '%',
                    'missing_pages' => $syncRecord->total_pages - $syncRecord->processed_pages
                ]);

                DB::commit();
            } else {
                // Not all pages processed yet, reschedule for later check
                DB::rollBack();

                Log::info("Sync batch still in progress, rescheduling finalize check", [
                    'batch_id' => $this->batchId,
                    'processed_pages' => $syncRecord->processed_pages,
                    'total_pages' => $syncRecord->total_pages,
                    'progress' => round($progress, 2) . '%',
                    'next_check_attempt' => $this->currentAttempt + 1
                ]);

                // Reschedule this job with increasing delay
                self::dispatch(
                    $this->batchId,
                    $this->syncRecordId,
                    $this->currentAttempt + 1
                )->delay(now()->addMinutes(5 * $this->currentAttempt))
                    ->onQueue('finalize-sync');
            }
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Error finalizing sync", [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Retry if not exceeding max attempts
            if ($this->currentAttempt < $this->maxAttempts) {
                self::dispatch(
                    $this->batchId,
                    $this->syncRecordId,
                    $this->currentAttempt + 1
                )->delay(now()->addMinutes(5))
                    ->onQueue('finalize-sync');
            }
        }
    }
}
