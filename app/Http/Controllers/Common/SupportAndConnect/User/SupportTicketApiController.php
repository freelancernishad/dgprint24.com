<?php

namespace App\Http\Controllers\Common\SupportAndConnect\User;

use Illuminate\Http\Request;

use App\Models\SupportAndConnect\Ticket\SupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SupportTicketApiController extends Controller
{
    // Get all support tickets for the authenticated user
    public function index()
    {
        $tickets = SupportTicket::where('user_id', Auth::id())->orderBy('id', 'desc')->get();
        return response()->json($tickets, 200);
    }

    // Create a new support ticket
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'subject' => 'required|string|max:255',
            // 'message' => 'required|string',
            'priority' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,docx|max:2048',

            // NEW FIELDS
            'job_id' => 'nullable|string|max:255',
            'login_email' => 'nullable|email',
            'company_name' => 'nullable|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_telephone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email',
            'problem_category' => 'nullable|string|max:255',
            'request_reprint' => 'nullable|boolean',
            'problem_description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

    
        // Create the ticket
        $ticket = SupportTicket::create([
            'user_id' => Auth::id(),
            'subject' => "-",
            'message' => "-",
            'priority' => $request->priority,

            // NEW FIELDS
            'job_id' => $request->job_id,
            'login_email' => $request->login_email,
            'company_name' => $request->company_name,
            'contact_name' => $request->contact_name,
            'contact_telephone' => $request->contact_telephone,
            'contact_email' => $request->contact_email,
            'problem_category' => $request->problem_category,
            'request_reprint' => $request->request_reprint,
            'problem_description' => $request->problem_description,
        ]);

        // Handle attachment if present
        if ($request->hasFile('attachment')) {
           $ticket->saveAttachment($request->file('attachment'));
        }

        return response()->json(['message' => 'Ticket created successfully.', 'ticket' => $ticket], 201);
    }

    // Show a specific support ticket
    public function show(SupportTicket $ticket)
    {
        if ($ticket->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        return response()->json($ticket, 200);
    }

    // Update a support ticket
    public function update(Request $request, SupportTicket $ticket)
    {
        if ($ticket->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        $validator = Validator::make($request->all(), [
            // 'subject' => 'nullable|string|max:255',
            // 'message' => 'nullable|string',
            'priority' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,docx|max:2048',

            // NEW FIELDS
            'job_id' => 'nullable|string|max:255',
            'login_email' => 'nullable|email',
            'company_name' => 'nullable|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_telephone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email',
            'problem_category' => 'nullable|string|max:255',
            'request_reprint' => 'nullable|boolean',
            'problem_description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update ticket
        $ticket->update($request->only([
            // 'subject',
            // 'message',
            'priority',
            'job_id',
            'login_email',
            'company_name',
            'contact_name',
            'contact_telephone',
            'contact_email',
            'problem_category',
            'request_reprint',
            'problem_description',
        ]));

        // Handle attachment if present
        if ($request->hasFile('attachment')) {
            $ticket->saveAttachment($request->file('attachment'));
        }

        return response()->json(['message' => 'Ticket updated successfully.', 'ticket' => $ticket], 200);
    }
}
