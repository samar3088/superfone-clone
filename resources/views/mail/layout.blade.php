{{--
    Shared shell for every email the app sends.

    Deliberately old-fashioned markup: tables, presentational attributes and
    inline styles. Mail clients strip <style> blocks, ignore flexbox and grid,
    and Outlook renders through Word — so the layout has to survive without
    any of it. The <style> block only carries progressive extras (dark mode,
    small-screen tweaks) that are safe to lose.
--}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $title ?? config('company.name') }}</title>

<style>
    /* Clients that honour <style> get these; everything else falls back to the
       inline styles below, which already look right on their own. */
    @media only screen and (max-width: 620px) {
        .wrap { width: 100% !important; }
        .pad { padding-left: 24px !important; padding-right: 24px !important; }
        .h1 { font-size: 26px !important; line-height: 32px !important; }
        .stack { display: block !important; width: 100% !important; }
    }

    @media (prefers-color-scheme: dark) {
        .bg      { background-color: #0f1a17 !important; }
        .card    { background-color: #16211d !important; border-color: #24332e !important; }
        .text    { color: #e8ece9 !important; }
        .muted   { color: #9baba4 !important; }
        .divider { border-color: #24332e !important; }
        .panel   { background-color: #12201c !important; border-color: #24332e !important; }
    }

    a { text-decoration: none; }
</style>

<body class="bg" style="margin:0; padding:0; width:100%; background-color:#fbfaf7;">

<!-- Preview text: shown in the inbox list, hidden in the message itself. -->
<div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
    {{ $preview ?? '' }}
    {{-- Padding stops the client pulling body copy into the preview line. --}}
    &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847;
    &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847;
</div>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       class="bg" style="background-color:#fbfaf7;">
    <tr>
        <td align="center" style="padding:32px 12px;">

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"
                   class="wrap" style="width:600px; max-width:600px;">

                {{-- Masthead: solid teal band, wordmark only. --}}
                <tr>
                    <td style="background-color:#0b5d51; border-radius:14px 14px 0 0; padding:26px 40px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                                           font-size:19px; font-weight:700; letter-spacing:-0.2px;
                                           color:#ffffff;">
                                    {{ config('company.name') }}
                                </td>
                                <td align="right"
                                    style="font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                                           font-size:10px; font-weight:600; letter-spacing:1.4px;
                                           text-transform:uppercase; color:#7fc2b6;">
                                    {{ $eyebrow ?? 'Notification' }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td class="card pad"
                        style="background-color:#ffffff; border:1px solid #e7e3da; border-top:none;
                               border-radius:0 0 14px 14px; padding:40px;">
                        @yield('content')
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td class="pad" style="padding:24px 40px 8px;">
                        <p class="muted" style="margin:0 0 6px;
                           font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                           font-size:12px; line-height:19px; color:#7c8983;">
                            Sent by {{ config('company.legal_name') }}.
                            @hasSection('footnote') @yield('footnote') @endif
                        </p>
                        <p class="muted" style="margin:0;
                           font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                           font-size:12px; line-height:19px; color:#a0aaa5;">
                            This is an automated message — replies to it are not monitored.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
