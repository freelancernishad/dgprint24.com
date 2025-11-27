<?php

namespace App\Models\SupportAndConnect\Ticket;


use App\Models\SupportTicketReply;
use Illuminate\Database\Eloquent\Model;
use App\Services\FileSystem\FileUploadService;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subject',
        'message',
        'status',
        'priority', // Add this line
        'attachment', // Add this line



          // New fields for Trade Job Trouble Ticket
        'job_id',
        'login_email',
        'company_name',
        'contact_name',
        'contact_telephone',
        'contact_email',
        'problem_category',
        'request_reprint',
        'problem_description',
    ];

    protected $hidden = [
        'subject',
        'message',
    ];


    protected $with = [
        'user',
        'replies',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replies()
    {
        return $this->hasMany(SupportTicketReply::class);
    }



    /**
     * Save the attachment for the support ticket.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string File path of the uploaded attachment
     */
    public function saveAttachment($file)
    {
        $filePath = (new FileUploadService())->uploadFileToS3($file, 'attachments/support_tickets'); // Define the S3 directory
        $this->attachment = $filePath;
        $this->save();

        return $filePath;
    }

}
