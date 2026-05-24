<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlacklistedBvn;
use App\Models\BlacklistedEmail;
use App\Models\BlacklistedIp;
use App\Models\BlacklistedNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin - Blacklist
 * @authenticated
 */
class AdminBlacklistController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin|assistant_admin');
    }

    public function index(Request $request): JsonResponse
    {
        $type = $request->type ?? 'all';

        $data = [];

        if (in_array($type, ['all', 'ip'])) {
            $data['ips'] = BlacklistedIp::latest()->paginate(20);
        }
        if (in_array($type, ['all', 'email'])) {
            $data['emails'] = BlacklistedEmail::latest()->paginate(20);
        }
        if (in_array($type, ['all', 'number'])) {
            $data['numbers'] = BlacklistedNumber::latest()->paginate(20);
        }
        if (in_array($type, ['all', 'bvn'])) {
            $data['bvns'] = BlacklistedBvn::latest()->paginate(20);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function blacklistIp(Request $request): JsonResponse
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'reason'     => 'required|string',
        ]);

        $record = BlacklistedIp::updateOrCreate(
            ['ip_address' => $request->ip_address],
            ['reason' => $request->reason, 'blacklisted_by' => auth()->id()],
        );

        return response()->json(['success' => true, 'message' => 'IP blacklisted.', 'data' => $record], 201);
    }

    public function blacklistEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email'  => 'required|email',
            'reason' => 'required|string',
        ]);

        $record = BlacklistedEmail::updateOrCreate(
            ['email' => $request->email],
            ['reason' => $request->reason, 'blacklisted_by' => auth()->id()],
        );

        return response()->json(['success' => true, 'message' => 'Email blacklisted.', 'data' => $record], 201);
    }

    public function blacklistNumber(Request $request): JsonResponse
    {
        $request->validate([
            'phone'  => 'required|string',
            'reason' => 'required|string',
        ]);

        $record = BlacklistedNumber::updateOrCreate(
            ['phone' => $request->phone],
            ['reason' => $request->reason, 'blacklisted_by' => auth()->id()],
        );

        return response()->json(['success' => true, 'message' => 'Phone number blacklisted.', 'data' => $record], 201);
    }

    public function blacklistBvn(Request $request): JsonResponse
    {
        $request->validate([
            'bvn'    => 'required|string|digits:11',
            'reason' => 'required|string',
        ]);

        $record = BlacklistedBvn::updateOrCreate(
            ['bvn' => $request->bvn],
            ['reason' => $request->reason, 'blacklisted_by' => auth()->id()],
        );

        return response()->json(['success' => true, 'message' => 'BVN blacklisted.', 'data' => $record], 201);
    }

    public function remove(Request $request, string $type, int $id): JsonResponse
    {
        $model = match ($type) {
            'ip'     => BlacklistedIp::findOrFail($id),
            'email'  => BlacklistedEmail::findOrFail($id),
            'number' => BlacklistedNumber::findOrFail($id),
            'bvn'    => BlacklistedBvn::findOrFail($id),
            default  => abort(422, 'Invalid blacklist type.'),
        };

        $model->delete();

        return response()->json(['success' => true, 'message' => ucfirst($type) . ' removed from blacklist.']);
    }
}
