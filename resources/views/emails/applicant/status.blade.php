{{-- $application and $context are available --}}
<p>Hi {{ $application->applicant_name }},</p>

@if(($context['action'] ?? '') === 'approved')
  <p>Your application has been <strong>approved</strong>.</p>
  @if(!empty($context['plot']))
    <p>Assigned Plot: <strong>{{ $context['plot']->lot_number }}</strong></p>
  @endif
  @if(!empty($context['reservation']))
    <p>Reservation: <strong>{{ ucfirst($context['reservation']->status) }}</strong>
      @if($context['reservation']->start_date)
        from {{ $context['reservation']->start_date->toFormattedDateString() }}
      @endif
      @if($context['reservation']->expires_at)
        until {{ $context['reservation']->expires_at->toFormattedDateString() }}
      @endif
    </p>
  @endif
@elseif(($context['action'] ?? '') === 'denied')
  <p>Your application status is <strong>Denied</strong>.</p>
@else
  <p>Your application status is <strong>{{ ucfirst($application->status) }}</strong>.</p>
@endif

@if($application->admin_notes)
  <p><strong>Notes:</strong> {{ $application->admin_notes }}</p>
@endif

<p>Thank you,<br>MemoraBeth</p>
