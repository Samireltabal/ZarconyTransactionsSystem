<?php

namespace Zarcony\ReportsManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Zarcony\ReportsManager\Controllers\ReportController;
use Zarcony\ReportsManager\Models\Report;
use Zarcony\ReportsManager\Notifications\reportGenerated;

class generateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public $report;

    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    public function boot()
    {

    }
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        ReportController::HandleReport($this->report);
    }
}
