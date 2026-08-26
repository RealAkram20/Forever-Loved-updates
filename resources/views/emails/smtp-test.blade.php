{{--
    The test email itself.

    It carries the settings it was sent with, because the useful question is not "did an
    email arrive" but "which configuration produced it" — an admin testing a change wants to
    know they are looking at the new one and not a message queued before they saved.

    Plain and small on purpose. This is a diagnostic, not a piece of brand: dressing it in
    the full template would mean a rendering bug in that template could fail the test and be
    read as a broken mail server.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $appName }} — SMTP test</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f2f7;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f2f7;">
        <tr>
            <td align="center" style="padding:32px 16px;background-color:#f4f2f7;">
                <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:520px;background-color:#ffffff;border-radius:12px;">
                    <tr>
                        <td style="padding:28px 28px 0;background-color:#ffffff;">
                            <h1 style="margin:0;font-family:Georgia,'Times New Roman',serif;font-size:20px;font-weight:normal;color:#29153a;">Your SMTP settings work</h1>
                            <p style="margin:12px 0 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#5b4b63;">
                                This message was sent from {{ $appName }} to confirm that mail can leave the server. If you are reading it, notifications will reach people too.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px 28px;background-color:#ffffff;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;color:#6d5b74;background-color:#faf8fb;border-radius:8px;">
                                <tr><td style="padding:14px 16px 4px;background-color:#faf8fb;"><strong style="color:#29153a;">Host</strong> &nbsp; {{ $host }}:{{ $port }}</td></tr>
                                <tr><td style="padding:4px 16px;background-color:#faf8fb;"><strong style="color:#29153a;">Encryption</strong> &nbsp; {{ $encryption }}</td></tr>
                                <tr><td style="padding:4px 16px;background-color:#faf8fb;"><strong style="color:#29153a;">Sent to</strong> &nbsp; {{ $to }}</td></tr>
                                <tr><td style="padding:4px 16px 14px;background-color:#faf8fb;"><strong style="color:#29153a;">Sent at</strong> &nbsp; {{ $sentAt }}</td></tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
