<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Application Received</title>
</head>
<body>
    <h2>Dear {{ $application->applicant_name }},</h2>

    <p>Thank you for submitting a burial service application with <strong>Memorabeth</strong>.</p>

    <p>We have received the details of your application for <strong>{{ $application->deceased_name }}</strong> and will review it shortly.</p>

    <p>You will be notified via this email once your application is <strong>approved</strong> or <strong>denied</strong>.</p>

    <p>Best regards,<br>
    <strong>Memorabeth Team</strong></p>
</body>
</html>
