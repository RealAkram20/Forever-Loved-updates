{{--
    The first email a new account receives: what this place is, and the one thing to do next.

    Written as tables with inline styles because that is what mail clients render. The rules
    that shaped it, so a later edit does not undo them:

    - No <style> block and no web fonts. Gmail strips <head> styles on some clients, and a
      font that fails to load takes the whole hierarchy with it — so the type is Georgia,
      which every client has, and which reads closer to the site's Playfair than any
      sans-serif fallback would.
    - Every cell carries its own background. A cell without one goes transparent under the
      dark-mode inversion some clients apply, and light text lands on light ground.
    - The button is a padded anchor with an Outlook VML twin. Word-engine Outlook ignores
      padding on <a> and would otherwise render a link, not a button.
    - It has to read with images off: nothing here is an image except the optional logo, and
      the brand name sits beside it as text.

    @param string      $appName    the recipient's own brand — the reseller's when they have one
    @param string      $brand      brand hex, already sanitised
    @param string      $brandInk   readable text colour on top of $brand
    @param string      $name       the recipient's first name, or '' when we have no name
    @param string      $ctaUrl     absolute, on the recipient's own site
    @param string      $homeUrl    absolute, their dashboard
    @param string|null $supportEmail
--}}
<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    {{-- Light only. Asking for both and then not restyling for dark is how you get
         grey-on-grey in the clients that invert. --}}
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $appName }}</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
</head>
<body style="margin:0;padding:0;width:100%;background-color:#f4f2f7;">

    {{-- The line under the sender in an inbox list. Without it the client pulls the first
         words of the body, which here would be the logo's alt text. --}}
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f4f2f7;">
        Your account is ready — here is how to create your first memorial.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f2f7;">
        <tr>
            <td align="center" style="padding:32px 16px;background-color:#f4f2f7;">

                <!--[if mso]><table role="presentation" width="600" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
                <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(41,21,58,0.10);">

                    {{-- A hairline of brand at the very top: enough to colour the mail without
                         a saturated banner, which on a memorial product reads as marketing. --}}
                    <tr>
                        <td style="height:4px;line-height:4px;font-size:4px;background-color:{{ $brand }};">&nbsp;</td>
                    </tr>

                    <tr>
                        <td style="padding:28px 28px 0;background-color:#ffffff;">
                            <span style="font-family:Georgia,'Times New Roman',serif;font-size:17px;font-weight:bold;color:#29153a;letter-spacing:0.01em;">{{ $appName }}</span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:26px 28px 0;background-color:#ffffff;">
                            <h1 style="margin:0;font-family:Georgia,'Times New Roman',serif;font-size:26px;line-height:1.25;font-weight:normal;color:#29153a;">
                                @if ($name !== '')Welcome, {{ $name }}@else Welcome @endif
                            </h1>
                            <p style="margin:14px 0 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.65;color:#5b4b63;">
                                Your account is ready. {{ $appName }} is a place to keep someone's
                                story — their photographs, the things people remember, the small
                                details that would otherwise be scattered across phones and albums.
                            </p>
                            <p style="margin:14px 0 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.65;color:#5b4b63;">
                                Everything starts with a memorial page. Here is what that looks like.
                            </p>
                        </td>
                    </tr>

                    {{-- The steps. A table per step rather than an <ol>: list markers are one of
                         the least consistent things across mail clients, and the number is part
                         of the design here rather than punctuation. --}}
                    <tr>
                        <td style="padding:26px 28px 0;background-color:#ffffff;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                @foreach ($steps as $i => $step)
                                    <tr>
                                        <td width="34" valign="top" style="width:34px;padding:0 14px 0 0;background-color:#ffffff;">
                                            {{-- A tinted disc, not the solid brand: three saturated
                                                 dots down the side would out-shout the button. --}}
                                            <table role="presentation" width="34" cellpadding="0" cellspacing="0" border="0" style="width:34px;">
                                                <tr>
                                                    <td align="center" valign="middle" height="34" style="width:34px;height:34px;background-color:{{ $brandTint }};border-radius:17px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;font-weight:bold;color:{{ $brand }};">
                                                        {{ $i + 1 }}
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td valign="top" style="padding:0 0 20px;background-color:#ffffff;">
                                            <p style="margin:0;padding-top:6px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;font-weight:bold;color:#29153a;line-height:1.4;">{{ $step['title'] }}</p>
                                            <p style="margin:5px 0 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#6d5b74;">{{ $step['body'] }}</p>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:6px 28px 0;background-color:#ffffff;">
                            <!--[if mso]>
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $ctaUrl }}" style="height:48px;v-text-anchor:middle;width:240px;" arcsize="21%" stroke="f" fillcolor="{{ $brand }}">
                                <w:anchorlock/>
                                <center style="color:{{ $brandInk }};font-family:sans-serif;font-size:15px;font-weight:bold;">Create a memorial</center>
                            </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-- -->
                            <a href="{{ $ctaUrl }}"
                               style="display:inline-block;background-color:{{ $brand }};color:{{ $brandInk }};font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;font-weight:bold;line-height:20px;text-decoration:none;padding:14px 30px;border-radius:10px;mso-padding-alt:0;">
                                Create a memorial
                            </a>
                            <!--<![endif]-->
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 28px 0;background-color:#ffffff;">
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;line-height:1.6;color:#8a7b91;">
                                Not ready yet? Your <a href="{{ $homeUrl }}" style="color:{{ $brand }};text-decoration:underline;">dashboard</a> is always here, and nothing is published until you say so.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:26px 28px 0;background-color:#ffffff;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr><td style="height:1px;line-height:1px;font-size:1px;background-color:#ece7f0;">&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 30px;background-color:#ffffff;">
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;line-height:1.65;color:#8a7b91;">
                                @if ($supportEmail)
                                    If you get stuck, reply to this email or write to
                                    <a href="mailto:{{ $supportEmail }}" style="color:{{ $brand }};text-decoration:underline;">{{ $supportEmail }}</a> — a person reads it.
                                @else
                                    If you get stuck, reply to this email — a person reads it.
                                @endif
                            </p>
                        </td>
                    </tr>

                </table>

                <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
                    <tr>
                        <td align="center" style="padding:18px 24px 0;">
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#9b8fa2;">
                                You received this because an account was created for {{ $email }} on {{ $appName }}.
                            </p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->

            </td>
        </tr>
    </table>

</body>
</html>
