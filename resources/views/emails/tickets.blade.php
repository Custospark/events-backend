<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td style="background:#1d4ed8;padding:26px 30px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                                <tr>
                                    <td width="46" height="46" style="width:46px;height:46px;background:#ffffff;border-radius:12px;text-align:center;vertical-align:middle;padding:5px;">
                                        <img
                                            src="{{ $message->embed(public_path('brand/custospark-events-logo.png')) }}"
                                            alt="Custospark Events"
                                            width="36"
                                            height="36"
                                            style="display:block;width:36px;height:36px;"
                                        >
                                    </td>
                                    <td style="padding-left:12px;vertical-align:middle;">
                                        <div style="font-size:11px;font-weight:700;letter-spacing:1.5px;color:#bfdbfe;text-transform:uppercase;">Custospark Events</div>
                                        <div style="margin-top:3px;font-size:12px;color:#dbeafe;">Official e-ticket</div>
                                    </td>
                                </tr>
                            </table>
                            <div style="font-size:24px;line-height:1.25;font-weight:700;color:#ffffff;">{{ $title }}</div>
                            <div style="margin-top:8px;font-size:14px;line-height:1.6;color:#dbeafe;">
                                Hi {{ $buyerName }}, your payment was successful. Your tickets are confirmed below.
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:26px 30px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;">
                                <tr>
                                    <td style="padding:20px;">
                                        <div style="font-size:11px;font-weight:700;letter-spacing:1px;color:#64748b;text-transform:uppercase;">Event</div>
                                        <div style="margin-top:6px;font-size:19px;line-height:1.35;font-weight:700;color:#0f172a;">{{ $eventTitle }}</div>
                                        <div style="margin-top:12px;font-size:14px;line-height:1.7;color:#475569;">
                                            <strong style="color:#0f172a;">When:</strong> {{ $eventDate }}<br>
                                            <strong style="color:#0f172a;">Where:</strong> {{ $eventVenue }}<br>
                                            <strong style="color:#0f172a;">Order:</strong> {{ $orderReference }}<br>
                                            <strong style="color:#0f172a;">Total paid:</strong> {{ $total }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 30px 0;">
                            <div style="font-size:16px;font-weight:700;color:#0f172a;">Your tickets</div>
                            <div style="margin-top:4px;font-size:13px;line-height:1.6;color:#64748b;">
                                Present the QR code on each ticket at the entrance. Each ticket admits one person.
                            </div>
                        </td>
                    </tr>

                    @foreach($tickets as $ticket)
                        <tr>
                            <td style="padding:16px 30px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dbeafe;border-radius:12px;background:#ffffff;">
                                    <tr>
                                        <td style="padding:18px 20px;vertical-align:top;">
                                            <div style="font-size:11px;font-weight:700;letter-spacing:1px;color:#2563eb;text-transform:uppercase;">{{ $ticket['type'] }}</div>
                                            <div style="margin-top:6px;font-size:17px;line-height:1.4;font-weight:700;color:#0f172a;">{{ $ticket['holder'] }}</div>
                                            <div style="margin-top:12px;font-size:11px;font-weight:700;letter-spacing:1px;color:#64748b;text-transform:uppercase;">Ticket code</div>
                                            <div style="margin-top:3px;font-family:Consolas,Monaco,monospace;font-size:14px;font-weight:700;color:#0f172a;word-break:break-all;">{{ $ticket['code'] }}</div>
                                            <div style="margin-top:12px;">
                                                <span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#dcfce7;color:#166534;font-size:10px;font-weight:700;letter-spacing:0.8px;">{{ $ticket['status'] }}</span>
                                            </div>
                                        </td>
                                        <td width="164" style="padding:18px 20px 18px 0;vertical-align:middle;text-align:center;">
                                            <img
                                                src="{{ $message->embedData($ticket['qrPng'], 'ticket-'.$ticket['code'].'.png', 'image/png') }}"
                                                alt="QR code for ticket {{ $ticket['code'] }}"
                                                width="140"
                                                height="140"
                                                style="display:block;width:140px;height:140px;margin:0 auto;border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:6px;"
                                            >
                                            <div style="margin-top:8px;font-size:10px;line-height:1.4;color:#94a3b8;">Scan at entrance</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endforeach

                    <tr>
                        <td style="padding:24px 30px 8px;text-align:center;">
                            <a href="{{ $orderUrl }}" style="display:inline-block;padding:14px 28px;border-radius:9px;background:#1d4ed8;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">View and download your tickets</a>
                            <div style="margin-top:10px;font-size:12px;line-height:1.6;color:#64748b;">
                                Use this link anytime to view your tickets or share them with the people attending.
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 30px 26px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eff6ff;border-left:4px solid #2563eb;border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 18px;font-size:13px;line-height:1.7;color:#1e3a8a;">
                                        <strong>Keep this email safe.</strong> It is your ticket and your backup if you close the browser. You can also retrieve your tickets using order <strong>{{ $orderReference }}</strong> and the buyer email.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#0f172a;padding:22px 30px;text-align:center;font-size:12px;line-height:1.8;color:#cbd5e1;">
                            <div style="color:#ffffff;font-weight:700;">{{ $tenantName }}</div>
                            <div>Tickets issued by {{ $tenantName }} through Custospark Events.</div>
                            <div>Need help? Contact us at <a href="mailto:{{ $supportEmail }}" style="color:#93c5fd;text-decoration:underline;">{{ $supportEmail }}</a></div>
                            <div style="margin-top:8px;font-size:11px;color:#94a3b8;">&copy; {{ date('Y') }} Custospark Company Ltd. All rights reserved.</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
