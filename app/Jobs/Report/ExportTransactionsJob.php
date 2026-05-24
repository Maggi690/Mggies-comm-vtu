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

class ExportTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        private readonly array $filters,
        private readonly int $userId,
        private readonly string $format = 'csv',
    ) {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        $query = Transaction::with(['user:id,email,phone', 'provider:id,name'])
            ->when($this->filters['status'] ?? null, fn($q) => $q->where('status', $this->filters['status']))
            ->when($this->filters['service_type'] ?? null, fn($q) => $q->where('service_type', $this->filters['service_type']))
            ->when($this->filters['from'] ?? null, fn($q) => $q->whereDate('created_at', '>=', $this->filters['from']))
            ->when($this->filters['to'] ?? null, fn($q) => $q->whereDate('created_at', '<=', $this->filters['to']))
            ->when($this->filters['user_id'] ?? null, fn($q) => $q->where('user_id', $this->filters['user_id']));

        $rows = $query->orderByDesc('created_at')->get()->map(fn($t) => [
            'ID'                => $t->id,
            'Reference'         => $t->reference,
            'User Email'        => $t->user?->email,
            'User Phone'        => $t->user?->phone,
            'Service'           => $t->service_type,
            'Amount (NGN)'      => $t->amount,
            'Fee (NGN)'         => $t->fee,
            'Status'            => $t->status,
            'Phone/Beneficiary' => $t->phone,
            'Provider'          => $t->provider?->name,
            'Provider Ref'      => $t->provider_reference,
            'Date'              => $t->created_at?->toDateTimeString(),
        ])->toArray();

        $filename = 'transactions_export_' . now()->format('Y-m-d_H-i-s') . '.' . $this->format;
        $path     = 'exports/' . $filename;

        $this->writeCsv($rows, $path);

        $user = User::findOrFail($this->userId);
        $url  = Storage::temporaryUrl($path, now()->addHours(24));

        Mail::raw(
            "Your transaction export is ready.\n\nDownload link (expires in 24 hours):\n{$url}\n\nRecords exported: " . count($rows),
            fn($m) => $m->to($user->email)->subject('Transaction Export Ready')
        );
    }

    private function writeCsv(array $rows, string $path): void
    {
        if (empty($rows)) {
            Storage::put($path, "No data found for the selected filters.\n");
            return;
        }

        $headers = array_keys($rows[0]);
        $csv     = implode(',', $headers) . "\n";

        foreach ($rows as $row) {
            $escaped = array_map(fn($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"', $row);
            $csv    .= implode(',', $escaped) . "\n";
        }

        Storage::put($path, $csv);
    }
}
