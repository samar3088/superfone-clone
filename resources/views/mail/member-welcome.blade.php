@extends('mail.layout', [
    'title' => 'Your '.config('company.name').' account',
    'eyebrow' => 'Account created',
    'preview' => 'Your account is ready — here is how to sign in.',
])

@section('content')

    <p class="muted" style="margin:0 0 10px;
       font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
       font-size:11px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase;
       color:#0b5d51;">Welcome aboard</p>

    <h1 class="h1 text" style="margin:0 0 18px;
        font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
        font-size:30px; line-height:36px; font-weight:700; letter-spacing:-0.6px;
        color:#151e1b;">Hello {{ $firstName }}, your account is ready</h1>

    <p class="text" style="margin:0 0 26px;
       font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
       font-size:16px; line-height:26px; color:#3d4a45;">
        {{ $invitedBy }} has set up an account for you on
        {{ config('company.name') }}. You can sign in two ways — with a one-time
        code sent to your mobile, or with the temporary password below.
    </p>

    {{-- Credentials panel --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
           class="panel" style="background-color:#f4f7f5; border:1px solid #dbe5e0;
                                border-radius:10px; margin:0 0 12px;">
        <tr>
            <td style="padding:22px 24px;">

                <p class="muted" style="margin:0 0 4px;
                   font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                   font-size:11px; font-weight:600; letter-spacing:1px;
                   text-transform:uppercase; color:#7c8983;">Mobile number</p>
                <p class="text" style="margin:0 0 18px;
                   font-family:Consolas,'SF Mono',Menlo,monospace;
                   font-size:19px; font-weight:700; letter-spacing:1px; color:#151e1b;">
                    {{ $mobile }}
                </p>

                <p class="muted" style="margin:0 0 4px;
                   font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                   font-size:11px; font-weight:600; letter-spacing:1px;
                   text-transform:uppercase; color:#7c8983;">Temporary password</p>
                <p class="text" style="margin:0;
                   font-family:Consolas,'SF Mono',Menlo,monospace;
                   font-size:19px; font-weight:700; letter-spacing:1px; color:#151e1b;">
                    {{ $password }}
                </p>

            </td>
        </tr>
    </table>

    {{-- The one thing they must not miss. --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
           style="background-color:#fdf6ec; border-left:3px solid #c9962c;
                  border-radius:0 8px 8px 0; margin:0 0 30px;">
        <tr>
            <td style="padding:14px 18px;
                       font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                       font-size:14px; line-height:22px; color:#6b5420;">
                This password works once. You will be asked to choose your own the
                first time you use it — after that, this email is worthless to anyone
                who finds it.
            </td>
        </tr>
    </table>

    {{-- Bulletproof button: the VML branch is what makes Outlook render it. --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" bgcolor="#0b5d51" style="border-radius:8px;">
                <!--[if mso]>
                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml"
                             xmlns:w="urn:schemas-microsoft-com:office:word"
                             href="{{ $url }}" style="height:48px;v-text-anchor:middle;width:210px;"
                             arcsize="17%" stroke="f" fillcolor="#0b5d51">
                    <w:anchorlock/>
                    <center style="color:#ffffff;font-family:sans-serif;font-size:15px;font-weight:600;">
                        Sign in
                    </center>
                </v:roundrect>
                <![endif]-->
                <!--[if !mso]><!-->
                <a href="{{ $url }}"
                   style="display:inline-block; padding:15px 38px; border-radius:8px;
                          background-color:#0b5d51; color:#ffffff;
                          font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                          font-size:15px; font-weight:600; letter-spacing:-0.1px;">
                    Sign in
                </a>
                <!--<![endif]-->
            </td>
        </tr>
    </table>

    <p class="muted" style="margin:26px 0 0;
       font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
       font-size:13px; line-height:21px; color:#7c8983;">
        If the button does not work, open this link:<br>
        <a href="{{ $url }}" style="color:#0b5d51; text-decoration:underline;
           word-break:break-all;">{{ $url }}</a>
    </p>

@endsection

@section('footnote')
    You are receiving this because an account was created for you.
@endsection
