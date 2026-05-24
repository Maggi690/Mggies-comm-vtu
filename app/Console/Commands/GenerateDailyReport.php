<?php

namespace App\Console\Commands;

use App\Models\DailyReport;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateDailyReport extends Command
{
    protected $signature   = 'reports:daily {--date= : Date in Y-m-d format (defaults to yesterday)}';
    protected $description = 'Generate daily summary report';

    public function handle(): int
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();

        $this->info("Generating daily report for: {$date}");

        $start = $date . ' 00:00:00';
        $end   = $date . ' 23:59:59';

        $serviceBreakdown = Transaction::whereBetween('created_at', [$start, $end])
            ->select('service_type', 'status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total_amount'))
            ->groupBy('service_type', 'status')
            ->get()
            ->groupBy('service_type')
            ->map(fn($items) => $items->keyBy('status'))
            ->toArray();

        DailyReport::updateOrCreate(
            ['report_date' => $date],
            [
                'total_users'              => User::whereDate('created_at', '<=', $date)->count(),
                'new_users'                => User::whereDate('created_at', $date)->count(),
                'total_transactions'       => Transaction::whereBetween('created_at', [$start, $end])->count(),
                'successful_transactions'  => Transaction::whereBetween('created_at', [$start, $end])->where('status', 'successful')->count(),
                'failed_transactions'      => Transaction::whereBetween('created_at', [$start, $end])->where('status', 'failed')->count(),
                'total_revenue'            => Transaction::whereBetween('created_at', [$start, $end])->where('status', 'successful')->sum('amount'),
                'total_funding'            => WalletTransaction::whereBetween('created_at', [$start, $end])->where('type', 'funding')->sum('amount'),
                'service_breakdown'        => $serviceBreakdown,
            ]
        );

        $this->info("Daily report generated for {$date}.");
        return Command::SUCCESS;
    }
}
