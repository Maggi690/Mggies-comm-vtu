<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::with(['user:id,first_name,last_name,email', 'messages' => fn($q) => $q->latest()->limit(1)])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->priority, fn($q) => $q->where('priority', $request->priority))
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('ticket_number', 'like', "%{$request->search}%")
                  ->orWhere('subject', 'like', "%{$request->search}%");
            }))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = SupportTicket::with(['user', 'messages.user'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $ticket]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $request->validate(['message' => 'required|string|min:5']);
        $ticket = SupportTicket::findOrFail($id);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $request->user()->id,
            'message'   => $request->message,
            'sender'    => 'support',
        ]);

        $ticket->update(['status' => 'pending_user']);

        return response()->json(['success' => true, 'message' => 'Reply sent.']);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);
        $ticket->update(['status' => 'closed']);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $request->user()->id,
            'message'   => $request->message ?? 'Ticket has been closed.',
            'sender'    => 'system',
        ]);

        return response()->json(['success' => true, 'message' => 'Ticket closed.']);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $request->validate(['agent_id' => 'required|integer|exists:users,id']);
        SupportTicket::findOrFail($id)->update(['assigned_to' => $request->agent_id]);
        return response()->json(['success' => true, 'message' => 'Ticket assigned.']);
    }
}
