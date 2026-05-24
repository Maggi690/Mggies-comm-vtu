<?php
namespace App\Events\Vtu;
use App\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable; use Illuminate\Queue\SerializesModels;
class AirtimeFailed { use Dispatchable, SerializesModels;
    public function __construct(public readonly Transaction $transaction) {} }
