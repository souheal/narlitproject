<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $approved ? 'Organization approved' : 'Organization application update' }} — NarLit</title>
</head>
<body style="margin:0;padding:0;background:#f3f6f5;color:#19342e;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f3f6f5;padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">
                <tr><td style="background:#174f43;color:#ffffff;padding:24px 32px;font-size:26px;font-weight:bold;letter-spacing:0.3px;">NarLit</td></tr>
                <tr><td style="padding:32px;line-height:1.6;font-size:16px;">
                    <p style="margin:0 0 20px;">Hello {{ $organizationName }},</p>
                    @if ($approved)
                        <p style="margin:0 0 16px;">Great news! Your organization's application to NarLit has been approved.</p>
                        <p style="margin:0 0 16px;">Your organization is now verified and can proceed with the next available steps on NarLit.</p>
                        <p style="margin:0 0 24px;">Thank you for joining NarLit. We look forward to supporting your mission.</p>
                    @else
                        <p style="margin:0 0 16px;">Thank you for your interest in joining NarLit.</p>
                        <p style="margin:0 0 16px;">After reviewing your organization's application, we regret to inform you that it has not been approved at this time.</p>
                        <p style="margin:0 0 24px;">We appreciate your time and interest in NarLit.</p>
                    @endif
                    <p style="margin:0;">Best regards,<br>The NarLit Team</p>
                </td></tr>
                <tr><td style="padding:18px 32px;background:#eef4f1;color:#4a625a;font-size:13px;">NarLit</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
