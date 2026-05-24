<?php
namespace App\Events\Wallet;
use App\Models\Wallet; use App\Models\WalletTransaction;
use Illuminate\Foundation\Events\Dispatchable; use Illuminate\Queue\SerializesModels;
class WalletDebited { use Dispatchable, SerializesModels;
    public function __construct(public readonly Wallet $wallet, public readonly WalletTransaction $transaction) {} }
