<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Burial Application</title>
</head>
<body>
    <h2>New Burial Application Submitted</h2>
    <p>A new application has been submitted to Memorabeth:</p>

    <ul>
        <li><strong>Applicant Name:</strong> {{ $application->applicant_name }}</li>
        <li><strong>Email:</strong> {{ $application->applicant_email }}</li>
        <li><strong>Phone:</strong> {{ $application->applicant_phone }}</li>
        <li><strong>Deceased:</strong> {{ $application->deceased_name }} ({{ $application->deceased_age }} yrs)</li>
        <li><strong>Date of Death:</strong> {{ $application->deceased_dod }}</li>
        <li><strong>Service Type:</strong> {{ $application->service_type }}</li>
        <li><strong>Remarks:</strong> {{ $application->remarks }}</li>
    </ul>

    <p>Please review this application in the admin panel.</p>
</body>
</html>
