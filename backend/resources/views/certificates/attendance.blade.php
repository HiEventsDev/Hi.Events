<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'DejaVu Sans', sans-serif;
            background: white;
        }
        .certificate {
            width: 297mm;
            height: 210mm;
            position: relative;
            padding: 30mm 40mm;
            box-sizing: border-box;
        }
        .border-frame {
            position: absolute;
            top: 15mm;
            left: 15mm;
            right: 15mm;
            bottom: 15mm;
            border: 3px solid #2563eb;
            border-radius: 4px;
        }
        .border-frame-inner {
            position: absolute;
            top: 18mm;
            left: 18mm;
            right: 18mm;
            bottom: 18mm;
            border: 1px solid #93c5fd;
            border-radius: 2px;
        }
        .content {
            position: relative;
            z-index: 1;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .title {
            font-size: 36pt;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 8mm;
            text-transform: uppercase;
            letter-spacing: 4px;
        }
        .subtitle {
            font-size: 14pt;
            color: #6b7280;
            margin-bottom: 12mm;
        }
        .recipient-name {
            font-size: 28pt;
            font-weight: bold;
            color: #111827;
            margin-bottom: 6mm;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 3mm;
            display: inline-block;
        }
        .body-text {
            font-size: 13pt;
            color: #374151;
            line-height: 1.6;
            max-width: 180mm;
            margin-bottom: 12mm;
        }
        .event-title {
            font-weight: bold;
            font-size: 15pt;
            color: #1e40af;
        }
        .event-date {
            font-size: 12pt;
            color: #6b7280;
            margin-top: 2mm;
        }
        .signature-section {
            margin-top: 10mm;
            display: flex;
            justify-content: center;
            align-items: flex-end;
        }
        .signature-block {
            text-align: center;
            min-width: 60mm;
        }
        .signature-line {
            border-top: 1px solid #374151;
            padding-top: 2mm;
            margin-top: 10mm;
        }
        .signatory-name {
            font-size: 12pt;
            font-weight: bold;
            color: #111827;
        }
        .signatory-title {
            font-size: 10pt;
            color: #6b7280;
        }
        .certificate-id {
            position: absolute;
            bottom: 20mm;
            right: 25mm;
            font-size: 8pt;
            color: #9ca3af;
        }
    </style>
</head>
<body>
<div class="certificate">
    <div class="border-frame"></div>
    <div class="border-frame-inner"></div>
    <div class="content">
        <div class="title">{{ $title ?? __('Certificate of Attendance') }}</div>
        <div class="subtitle">{{ __('This is to certify that') }}</div>
        <div class="recipient-name">{{ $attendeeName }}</div>
        <div class="body-text">
            {!! $bodyText !!}
        </div>
        <div class="event-title">{{ $eventTitle }}</div>
        <div class="event-date">{{ $eventDate }}</div>

        @if($signatoryName)
        <div class="signature-section">
            <div class="signature-block">
                <div class="signature-line">
                    <div class="signatory-name">{{ $signatoryName }}</div>
                    @if($signatoryTitle)
                    <div class="signatory-title">{{ $signatoryTitle }}</div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
    <div class="certificate-id">{{ __('Certificate ID:') }} {{ $certificateId }}</div>
</div>
</body>
</html>
