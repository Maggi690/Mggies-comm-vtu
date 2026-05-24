<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Support
 * @authenticated
 */
class SupportController extends Controller
{
    /**
     * Create a support ticket
     *
     * @bodyParam subject string required Ticket subject. Example: Unable to purchase airtime
     * @bodyParam message string required Detailed description. Example: I tried to buy airtime but...
     * @bodyParam category string required Category (billing, technical, general). Example: billing
     * @bodyParam transaction_reference string optional Related transaction reference.
     */
    public function createTicket(Request $request): JsonResponse
    {
        $request->validate([
            'subject'                => 'required|string|max:255',
            'message'                => 'required|string|min:10',
            'category'               => 'required|in:billing,technical,general,complaint',
            'transaction_reference'  => 'nullable|string',
            'priority'               => 'nullable|in:low,medium,high',
        ]);

        $ticket = SupportTicket::create([
            'user_id'                => $request->user()->id,
            'ticket_number'          => 'TKT-' . strtoupper(uniqid()),
            'subject'                => $request->subject,
            'category'               => $request->category,
            'priority'               => $request->priority ?? 'medium',
            'status'                 => 'open',
            'transaction_reference'  => $request->transaction_reference,
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $request->user()->id,
            'message'   => $request->message,
            'sender'    => 'user',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created. We will respond shortly.',
            'data'    => $ticket->load('messages'),
        ], 201);
    }

    /**
     * Reply to a ticket
     *
     * @urlParam id integer required Ticket ID. Example: 1
     * @bodyParam message string required Reply message.
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $request->validate(['message' => 'required|string|min:5']);

        $ticket = SupportTicket::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        if ($ticket->status === 'closed') {
            return response()->json(['success' => false, 'message' => 'Cannot reply to a closed ticket.'], 422);
        }

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $request->user()->id,
            'message'   => $request->message,
            'sender'    => 'user',
        ]);

        $ticket->update(['status' => 'pending_support', 'updated_at' => now()]);

        return response()->json(['success' => true, 'data' => $message]);
    }

    public function myTickets(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->with(['messages' => fn($q) => $q->latest()->limit(1)])
            ->latest()->paginate(10);

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    public function showTicket(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with('messages')
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $ticket]);
    }
}
