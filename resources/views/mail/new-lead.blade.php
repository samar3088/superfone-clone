@extends('mail.layout', [
    'title' => 'New lead: '.$lead->name,
    'eyebrow' => 'New lead',
    'preview' => $lead->name.' — '.$lead->mobile.($lead->campaign ? ' · '.$lead->campaign : ''),
])

@section('content')

    <p class="muted" style="margin:0 0 10px;
       font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
       font-size:11px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase;
       color:#0b5d51;">Assigned to you</p>

    <h1 class="h1 text" style="margin:0 0 6px;
        font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
        font-size:30px; line-height:36px; font-weight:700; letter-spacing:-0.6px;
        color:#151e1b;">{{ $lead->name }}</h1>

    <p class="muted" style="margin:0 0 28px;
       font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
       font-size:14px; line-height:22px; color:#7c8983;">
        Came in {{ $lead->created_at->diffForHumans() }} @if($lead->source) via {{ $lead->source }} @endif
    </p>

    {{-- Detail rows. A table, because this genuinely is tabular data. --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
           class="panel" style="background-color:#f4f7f5; border:1px solid #dbe5e0;
                                border-radius:10px; margin:0 0 30px;">
        <tr>
            <td style="padding:8px 24px 18px;">

                @foreach ($rows as $label => $value)
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td class="divider" style="padding:14px 0 0;
                                @if(! $loop->first) border-top:1px solid #e2eae6; @endif">
                                <p class="muted" style="margin:0 0 3px;
                                   font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                                   font-size:11px; font-weight:600; letter-spacing:1px;
                                   text-transform:uppercase; color:#7c8983;">{{ $label }}</p>
                                <p class="text" style="margin:0 0 14px;
                                   font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                                   font-size:16px; line-height:24px; color:#151e1b;">{{ $value }}</p>
                            </td>
                        </tr>
                    </table>
                @endforeach

            </td>
        </tr>
    </table>

    {{-- Two actions side by side, stacking on narrow screens. --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="stack" align="center" bgcolor="#0b5d51" style="border-radius:8px;">
                <!--[if mso]>
                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml"
                             xmlns:w="urn:schemas-microsoft-com:office:word"
                             href="{{ $url }}" style="height:48px;v-text-anchor:middle;width:180px;"
                             arcsize="17%" stroke="f" fillcolor="#0b5d51">
                    <w:anchorlock/>
                    <center style="color:#ffffff;font-family:sans-serif;font-size:15px;font-weight:600;">
                        Open lead
                    </center>
                </v:roundrect>
                <![endif]-->
                <!--[if !mso]><!-->
                <a href="{{ $url }}"
                   style="display:inline-block; padding:15px 34px; border-radius:8px;
                          background-color:#0b5d51; color:#ffffff;
                          font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                          font-size:15px; font-weight:600;">Open lead</a>
                <!--<![endif]-->
            </td>
            <td class="stack" width="12" style="font-size:0; line-height:0;">&nbsp;</td>
            <td class="stack" align="center" style="border-radius:8px; border:1px solid #cfdad5;">
                <a href="tel:{{ $lead->mobile }}"
                   style="display:inline-block; padding:14px 30px; border-radius:8px;
                          color:#0b5d51;
                          font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
                          font-size:15px; font-weight:600;">Call {{ $lead->mobile }}</a>
            </td>
        </tr>
    </table>

@endsection

@section('footnote')
    You can turn these off under Settings.
@endsection
