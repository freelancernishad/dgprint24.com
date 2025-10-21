<?php

namespace App\Http\Controllers\Common\Twilio;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Services\Twilio\TwilioService;

class TwilioController extends Controller
{
    protected $twilio;

    public function __construct(TwilioService $twilio)
    {
        $this->twilio = $twilio;
    }

    /**
     * Send SMS globally
     */
    public function send(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $sent = $this->twilio->sendSMS($request->phone, $request->message);

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'SMS sent successfully!' : 'Failed to send SMS'
        ]);
    }
}
