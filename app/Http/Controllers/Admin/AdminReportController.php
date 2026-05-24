<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Admin - Reports & Analytics
 * @authenticated
 */
class AdminReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin|assistant_admin');
    }

    /**
     * Dashboard summary stats
     */
    public function dashboard(): JsonResponse
    {
        $today     = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        return response()->json([
            'success' => true,
            'data'    => [
                'users' => [
                    'total'          => User::count(),
                    'today'          => User::whereDate('created_at', today())->count(),
                    'this_month'     => User::where('created_at', '>=', $thisMonth)->count(),
                    'active'         => User::where('status', 'active')->count(),
                ],
                'transactions' => [
                    'total_today'         => Transaction::where('created_at', '>=', $today)->count(),
                    'successful_today'    => Transaction::where('created_at', '>=', $today)->where('status', 'successful')->count(),
                    'revenue_today'       => Transaction::where('created_at', '>=', $today)->where('status', 'successful')->sum('amount'),
                    'revenue_this_month'  => Transaction::where('created_at', '>=', $thisMonth)->where('status', 'successful')->sum('amount'),
                ],
                'service_breakdown' => Transaction::where('created_at', '>=', $thisMonth)
                    ->where('status', 'successful')
                    ->select('service_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as revenue'))
                    ->groupBy('service_type')
                    ->get(),
                'wallet_funding_today' => WalletTransaction::where('type', 'funding')
                    ->where('created_at', '>=', $today)->sum('amount'),
            ],
        ]);
    }

    /**
     * Revenue report by date range
     */
    public function revenue(Request $request): JsonResponse
    {
        $request->validate([
            'from'  => 'required|date',
            'to'    => 'required|date|after_or_equal:from',
            'group' => 'nullable|in:day,week,month',
        ]);

        $group = $request->group ?? 'day';

        $format = match ($group) {
            'month' => '%Y-%m',
            'week'  => '%Y-%u',
            default => '%Y-%m-%d',
        };

        $data = Transaction::where('status', 'successful')
            ->whereBetween('created_at', [$request->from, $request->to . ' 23:59:59'])
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$format}') as period"),
                DB::raw('COUNT(*) as transactions'),
                DB::raw('SUM(amount) as revenue'),
                'service_type',
            )
            ->groupBy('period', 'service_type')
            ->orderBy('period')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Transaction summary by service type
     */
    public function byService(Request $request): JsonResponse
    {
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        $data = Transaction::whereBetween('created_at', [$request->from, $request->to . ' 23:59:59'])
            ->select(
                'service_type',
                'status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total_amount'),
            )
            ->groupBy('service_type', 'status')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Provider performance report
     */
    public function providerPerformance(Request $request): JsonResponse
    {
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        $data = Transaction::whereBetween('created_at', [$request->from, $request->to . ' 23:59:59'])
            ->whereNotNull('provider_id')
            ->with('provider:id,name,slug')
            ->select(
                'provider_id',
                'service_type',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "successful" THEN 1 ELSE 0 END) as successful'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'),
                DB::raw('AVG(CASE WHEN status = "successful" THEN amount END) as avg_amount'),
            )
            ->groupBy('provider_id', 'service_type')
            ->get()
            ->map(function ($item) {
                $item->success_rate = $item->total > 0 ? round(($item->successful / $item->total) * 100, 2) : 0;
                return $item;
            });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * User growth report
     */
    public function userGrowth(Request $request): JsonResponse
    {
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        $data = User::whereBetween('created_at', [$request->from, $request->to . ' 23:59:59'])
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as date"),
                DB::raw('COUNT(*) as registrations'),
                'user_type',
            )
            ->groupBy('date', 'user_type')
            ->orderBy('date')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Funding report
     */
    public function funding(Request $request): JsonResponse
    {
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        $data = WalletTransaction::where('type', 'funding')
            ->whereBetween('created_at', [$request->from, $request->to . ' 23:59:59'])
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as date"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Export report
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'type'   => 'required|in:transactions,users,revenue,funding',
            'format' => 'required|in:csv,excel,pdf',
            'from'   => 'required|date',
            'to'     => 'required|date',
        ]);

        \App\Jobs\Report\ExportReportJob::dispatch($request->all(), auth()->id());

        return response()->json(['success' => true, 'message' => 'Report export queued. You will receive it via email shortly.']);
    }
}
