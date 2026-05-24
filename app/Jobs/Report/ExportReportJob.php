<?php

namespace App\Jobs\Report;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ExportReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        private readonly array $filters,
        private readonly int $adminId,
    ) {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        $admin  = User::findOrFail($this->adminId);
        $type   = $this->filters['type'] ?? 'transactions';
        $format = $this->filters['format'] ?? 'csv';
        $from   = $this->filters['from'] ?? now()->subDays(30)->toDateString();
        $to     = $this->filters['to'] ?? now()->toDateString();

        $data     = $this->gatherData($type, $from, $to);
        $filename = "{$type}_export_{$from}_to_{$to}.{$format}";
        $path     = "exports/{$filename}";

        if ($format === 'csv') {
            $this->exportCsv($data, $path);
        } else {
            $this->exportCsv($data, $path); // Extend for Excel/PDF
        }

        // Email download link
        Mail::raw(
            "Your {$type} report is ready. Download at: " . Storage::temporaryUrl($path, now()->addHour()),
            fn($msg) => $msg->to($admin->email)->subject("Report Export Ready: {$filename}"),
        );
    }

    private function gatherData(string $type, string $from, string $to): array
    {
        return match ($type) {
            'transactions' => Transaction::whereBetween('created_at', [$from, $to . ' 23:59:59'])
                ->with(['user:id,email,phone', 'provider:id,name'])
                ->get()
                ->map(fn($t) => [
                    'reference'    => $t->reference,
                    'user_email'   => $t->user?->email,
                    'service_type' => $t->service_type,
                    'amount'       => $t->amount,
                    'status'       => $t->status,
                    'phone'        => $t->phone,
                    'provider'     => $t->provider?->name,
                    'created_at'   => $t->created_at->toDateTimeString(),
                ])->toArray(),

            'users' => User::whereBetween('created_at', [$from, $to . ' 23:59:59'])
                ->get()
                ->map(fn($u) => [
                    'id'         => $u->id,
                    'name'       => $u->full_name,
                    'email'      => $u->email,
                    'phone'      => $u->phone,
                    'user_type'  => $u->user_type,
                    'status'     => $u->status,
                    'created_at' => $u->created_at->toDateTimeString(),
                ])->toArray(),

            default => [],
        };
    }

    private function exportCsv(array $data, string $path): void
    {
        if (empty($data)) return;

        $headers = array_keys($data[0]);
        $csv     = implode(',', $headers) . "\n";

        foreach ($data as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $row)) . "\n";
        }

        Storage::put($path, $csv);
    }
}
