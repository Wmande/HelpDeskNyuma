<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Ticket Created</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f8f9fa; padding: 20px;">
    <div style="max-width:600px; margin:auto; background:#fff; padding:20px; border-radius:8px;">
        <h2 style="color:#2a7ade;">Hello {{ $ticket->name ?? 'User' }},</h2>
        <p>Your support ticket has been created successfully. Here are the details:</p>

        <table style="width:100%; border-collapse:collapse; margin-top:15px;">
            <tr><td><strong>Ticket ID:</strong></td><td>#{{ $ticket->id ?? 'N/A' }}</td></tr>
            <tr><td><strong>Department:</strong></td><td>{{ $ticket->department ?? 'N/A' }}</td></tr>
            <tr><td><strong>Room Number:</strong></td><td>{{ $ticket->room_number ?? 'N/A' }}</td></tr>
            <tr><td><strong>Description:</strong></td><td>{{ $ticket->description ?? 'N/A' }}</td></tr>
            <tr><td><strong>Status:</strong></td><td>{{ ucfirst($ticket->status ?? 'open') }}</td></tr>
        </table>

        <p style="margin-top:20px;">We’ll keep you updated on the progress. You can check your ticket in the staff dashboard.</p>

        <p style="margin-top:20px;">Best Regards,<br><strong>SDEP Tech Support Team</strong></p>
    </div>
</body>
</html>
