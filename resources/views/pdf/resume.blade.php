{{--
  The resume, rendered to PDF by dompdf (App\Support\ResumePdf).

  The app's design (lib/app/core/utils/resume_pdf.dart), section for section
  and size for size — this is the copy the website shows and recruiters
  download, so it must read like the one the candidate sees in the app.
  Change both together.

  $branded off is the paid copy: no INTHES anywhere — watermark, header logo,
  footer wordmark. The render date stays (the footer, drawn in ResumePdf).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $name }} — Resume</title>
<style>
  @font-face { font-family: 'Jakarta'; font-weight: 400; src: url('{{ $fonts['regular'] }}') format('truetype'); }
  @font-face { font-family: 'Jakarta'; font-weight: 600; src: url('{{ $fonts['semiBold'] }}') format('truetype'); }
  @font-face { font-family: 'Jakarta'; font-weight: 700; src: url('{{ $fonts['bold'] }}') format('truetype'); }

  @page { margin: 38pt 38pt 56pt 38pt; }
  * { box-sizing: border-box; }
  body { font-family: 'Jakarta', sans-serif; font-size: 10pt; color: #1E1E1E; margin: 0; }

  .watermark { position: fixed; top: 210pt; left: 112pt; width: 290pt; height: 290pt; opacity: 0.08; }

  .header { border-bottom: 1pt solid #EDEDEF; padding-bottom: 14pt; margin-bottom: 18pt; }
  .header td { vertical-align: top; }
  .photo { width: 64pt; height: 64pt; border-radius: 32pt; }
  .name { font-weight: 700; font-size: 22pt; line-height: 1.15; }
  .headline { font-weight: 600; font-size: 10.5pt; color: #EB0401; margin-top: 4pt; }
  .muted-line { font-size: 9.5pt; color: #6B6B70; margin-top: 3pt; }

  .section { margin-bottom: 16pt; }
  /* Short sections (chips, label rows) move to the next page whole, not with
     their heading left behind on this one. */
  .section.keep { page-break-inside: avoid; }
  .section-title { font-weight: 700; font-size: 9.5pt; letter-spacing: 1.2pt; margin-bottom: 8pt; }
  .section-title .bar { display: inline-block; width: 3pt; height: 11pt; background: #EB0401; margin-right: 6pt; vertical-align: -1pt; }

  p.paragraph { margin: 0; font-size: 10pt; line-height: 1.45; }

  .entry { margin-bottom: 10pt; page-break-inside: avoid; }
  .entry-head { width: 100%; border-collapse: collapse; }
  .entry-title { font-weight: 600; font-size: 11pt; }
  .entry-meta { font-size: 9pt; color: #6B6B70; text-align: right; white-space: nowrap; padding-left: 8pt; }
  .entry-sub { font-size: 10pt; color: #EB0401; margin-top: 2pt; }
  .entry-detail { font-size: 9.5pt; line-height: 1.4; margin-top: 3pt; }
  .bullet { margin-top: 3pt; font-size: 9.5pt; line-height: 1.4; }
  .bullet td { vertical-align: top; }
  .bullet .dot { width: 10pt; color: #EB0401; }

  .chip { display: inline-block; border: 1pt solid #EDEDEF; border-radius: 4pt; padding: 3pt 8pt; margin: 0 6pt 6pt 0; font-size: 9pt; }

  .lines { border-collapse: collapse; }
  .lines td { vertical-align: top; padding-bottom: 4pt; font-size: 9.5pt; }
  .lines .label { width: 112pt; font-weight: 600; color: #6B6B70; }
</style>
</head>
<body>
@if ($branded && $logo)
  <img class="watermark" src="{{ $logo }}" alt="">
@endif

<table class="header" width="100%" cellspacing="0" cellpadding="0">
  <tr>
    @if ($photo)
      <td style="width: 78pt;"><img class="photo" src="{{ $photo }}" alt=""></td>
    @endif
    <td>
      <div class="name">{{ $name }}</div>
      @if ($headline !== '')<div class="headline">{{ $headline }}</div>@endif
      @if ($meta !== '')<div class="muted-line" style="margin-top: 5pt;">{{ $meta }}</div>@endif
      @if ($contact !== '')<div class="muted-line">{{ $contact }}</div>@endif
    </td>
    @if ($branded && $logo)
      <td style="width: 60pt; text-align: right;"><img src="{{ $logo }}" alt="INTHES" style="height: 34pt;"></td>
    @endif
  </tr>
</table>

@foreach ($sections as $section)
  <div class="section{{ empty($section['entries']) ? ' keep' : '' }}">
    <div class="section-title"><span class="bar"></span>{{ mb_strtoupper($section['title']) }}</div>

    @foreach ($section['paragraphs'] ?? [] as $text)
      <p class="paragraph">{{ $text }}</p>
    @endforeach

    @foreach ($section['entries'] ?? [] as $entry)
      <div class="entry">
        <table class="entry-head" cellspacing="0" cellpadding="0">
          <tr>
            <td class="entry-title">{{ $entry['title'] }}</td>
            @if ($entry['meta'] !== '')<td class="entry-meta">{{ $entry['meta'] }}</td>@endif
          </tr>
        </table>
        @if ($entry['subtitle'] !== '')<div class="entry-sub">{{ $entry['subtitle'] }}</div>@endif
        @if ($entry['detail'] !== '')<div class="entry-detail">{!! nl2br(e($entry['detail'])) !!}</div>@endif
        @foreach ($entry['bullets'] as $bullet)
          <table class="bullet" cellspacing="0" cellpadding="0"><tr><td class="dot">•</td><td>{{ $bullet }}</td></tr></table>
        @endforeach
      </div>
    @endforeach

    @if (! empty($section['chips']))
      <div>@foreach ($section['chips'] as $chip)<span class="chip">{{ $chip }}</span>@endforeach</div>
    @endif

    @if (! empty($section['lines']))
      <table class="lines" cellspacing="0" cellpadding="0">
        @foreach ($section['lines'] as [$label, $value])
          <tr><td class="label">{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
      </table>
    @endif
  </div>
@endforeach

@if ($sections === [])
  <p class="paragraph" style="color: #6B6B70;">This profile has no details on it yet.</p>
@endif
</body>
</html>
