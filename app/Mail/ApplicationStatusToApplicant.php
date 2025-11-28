<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusToApplicant extends Mailable
{
    use Queueable, SerializesModels;

    /** @var \App\Models\Application */
    public $application;

    /**
     * Extra data for the template and subject line.
     * Example:
     * [
     *   'action' => 'approved'|'denied'|'updated',   // optional; defaults from $application->status
     *   'plot' => \App\Models\Plot|null,             // optional
     *   'reservation' => \App\Models\Reservation|null// optional
     * ]
     * @var array
     */
    public $context;

    /**
     * Backward-compatible constructor: second param is optional.
     */
    public function __construct(Application $application, array $context = [])
    {
        $this->application = $application;
        $this->context = $context;
    }

    /**
     * Build the message (Laravel 9/10/11 compatible).
     */
    public function build()
    {
        $action = $this->context['action'] ?? $this->inferActionFromStatus($this->application->status);

        // Nice readable subject
        $subject = match ($action) {
            'approved' => 'MemoraBeth: Your Application Has Been Approved',
            'denied'   => 'MemoraBeth: Update on Your Application',
            default    => 'MemoraBeth: Application Update - ' . ucfirst((string) $this->application->status),
        };

        // Optional: respect mail.from config (safe default)
        $fromAddress = config('mail.from.address');
        $fromName    = config('mail.from.name', 'MemoraBeth');

        return $this->subject($subject)
            ->from($fromAddress, $fromName)
            ->view('emails.applicant.status')
            ->with([
                'application' => $this->application,
                'context'     => $this->context, // includes 'action', 'plot', 'reservation' if provided
            ]);
    }

    /**
     * Map application.status to a friendly action label.
     */
    protected function inferActionFromStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'approved' => 'approved',
            'denied'   => 'denied',
            default    => 'updated',
        };
    }
}
