<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin - Users
 * @authenticated
 */
class AdminUserController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin|assistant_admin|customer_support');
    }

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('username', 'like', "%{$request->search}%");
            }))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->user_type, fn($q) => $q->where('user_type', $request->user_type))
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->with(['wallet'])
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => UserResource::collection($users),
            'meta'    => [
                'total'        => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with(['wallet', 'transactions', 'roles', 'apiKeys'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => new UserResource($user)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $request->validate([
            'status'    => 'sometimes|in:active,suspended,banned',
            'user_type' => 'sometimes|in:user,agent,vendor,sub_reseller,api_user',
            'kyc_status'=> 'sometimes|in:pending,verified,rejected',
        ]);

        $user->update($request->only(['status', 'user_type', 'kyc_status']));

        // Update role if user_type changed
        if ($request->has('user_type')) {
            $user->syncRoles([$request->user_type]);
        }

        return response()->json(['success' => true, 'message' => 'User updated.', 'data' => new UserResource($user)]);
    }

    public function suspend(int $id): JsonResponse
    {
        User::findOrFail($id)->update(['status' => 'suspended']);
        return response()->json(['success' => true, 'message' => 'User suspended.']);
    }

    public function activate(int $id): JsonResponse
    {
        User::findOrFail($id)->update(['status' => 'active']);
        return response()->json(['success' => true, 'message' => 'User activated.']);
    }

    public function creditWallet(Request $request, int $id): JsonResponse
    {
        $this->middleware('role:admin');

        $request->validate([
            'amount'      => 'required|numeric|min:1',
            'description' => 'required|string',
        ]);

        $user    = User::findOrFail($id);
        $service = app(\App\Services\Wallet\WalletService::class);
        $txn     = $service->credit(new \App\DTOs\Wallet\CreditWalletDTO(
            userId:      $user->id,
            amount:      $request->amount,
            reference:   'ADMIN-' . strtoupper(uniqid()),
            description: $request->description,
            type:        'admin_credit',
            meta:        ['admin_id' => auth()->id()],
        ));

        return response()->json([
            'success' => true,
            'message' => "₦{$request->amount} credited to {$user->full_name}'s wallet.",
            'data'    => $txn,
        ]);
    }

    public function debitWallet(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount'      => 'required|numeric|min:1',
            'description' => 'required|string',
        ]);

        $user    = User::findOrFail($id);
        $service = app(\App\Services\Wallet\WalletService::class);
        $txn     = $service->debitInternal(
            $user->id, $request->amount,
            'ADMIN-DEB-' . strtoupper(uniqid()),
            $request->description,
            ['admin_id' => auth()->id()],
        );

        return response()->json(['success' => true, 'message' => 'Wallet debited.', 'data' => $txn]);
    }

    public function transactions(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $txns = $user->transactions()
            ->when($request->service_type, fn($q) => $q->where('service_type', $request->service_type))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => \App\Http\Resources\Transaction\TransactionResource::collection($txns),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->middleware('role:admin');
        User::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'User deleted.']);
    }
}
