<?php

namespace App\Http\Controllers\Common\SupportAndConnect\Admin;

use Illuminate\Http\Request;
use App\Helpers\ExternalTokenVerify;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\SupportAndConnect\Ticket\SupportTicket;

class AdminSupportTicketApiController extends Controller
{
    // Get all support tickets for admin
    public function index()
    {
        $tickets = SupportTicket::latest()->get();
        return response()->json(['data' => $tickets], 200);
    }

    // View a specific support ticket
    public function show($id)
    {
        $ticket = SupportTicket::with(['replies'])->findOrFail($id);
        return response()->json(['data' => $ticket], 200);
    }

    // Reply to a support ticket
    public function reply(Request $request, $id)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'reply' => 'required|string',
            'status' => 'required|string|in:open,closed,pending,replay', // Define allowed statuses
            'reply_id' => 'nullable|exists:replies,id', // Check if the parent reply exists
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,docx|max:2048', // Validate attachment
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find the support ticket by ID
        $ticket = SupportTicket::findOrFail($id);

        // Update ticket status
        $ticket->status = $request->status;

        // Prepare reply data
        $replyData = [
            'reply' => $request->reply,
            'reply_id' => $request->reply_id, // Set the parent reply ID if provided
        ];

        // Check if the logged-in user is an admin
        if (auth()->guard('admin')->check()) {
            $replyData['admin_id'] = auth()->guard('admin')->id();
        } else {

            $token = $request->bearerToken();
            $authUser = ExternalTokenVerify::verifyExternalToken($token);

            // ৫. ইউজার বা সেশন আইডি নির্ধারণ
            $userId = null;
            if ($authUser) {
                $userId = $authUser->id ?? null;
            }

            $replyData['user_id'] = $userId;
        }

        // Create a new reply associated with the support ticket
        $reply = $ticket->replies()->create($replyData);

        // Handle attachment if present
        if ($request->hasFile('attachment')) {
            $reply->saveAttachment($request->file('attachment'));
        }

        // Save the ticket with the updated status
        $ticket->save();

        return response()->json([
            'message' => 'Reply sent successfully and ticket status updated.',
            'reply' => $reply
        ], 200);
    }

    // Update ticket status
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:open,closed,pending,replay', // Define allowed statuses
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,docx|max:2048', // Validate attachment
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $request->status;

        // Handle attachment if present
        if ($request->hasFile('attachment')) {
            $ticket->saveAttachment($request->file('attachment'));
        }

        $ticket->save();

        return response()->json(['message' => 'Ticket status updated successfully.'], 200);
    }
}
