<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 10mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 8pt;
        }
        .label-grid {
            display: table;
            width: 100%;
        }
        .label-row {
            display: table-row;
        }
        .label {
            display: table-cell;
            width: {{ 100 / $columns }}%;
            height: {{ $labelHeight }}mm;
            padding: 2mm;
            text-align: center;
            vertical-align: middle;
            border: 0.5pt dashed #ccc;
            overflow: hidden;
        }
        .label img {
            width: {{ min($labelWidth, $labelHeight) - 8 }}mm;
            height: {{ min($labelWidth, $labelHeight) - 8 }}mm;
        }
        .label .name {
            font-size: 6pt;
            font-weight: bold;
            margin-top: 1mm;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            max-width: {{ $labelWidth - 4 }}mm;
        }
        .label .ticket-id {
            font-size: 5pt;
            color: #666;
            margin-top: 0.5mm;
        }
    </style>
</head>
<body>
    <div class="label-grid">
        @foreach($attendees->chunk($columns) as $row)
            <div class="label-row">
                @foreach($row as $attendee)
                    <div class="label">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($attendee->getPublicId()) }}"
                             alt="QR Code">
                        <div class="name">{{ $attendee->getFirstName() }} {{ $attendee->getLastName() }}</div>
                        <div class="ticket-id">{{ $attendee->getShortId() }}</div>
                    </div>
                @endforeach
                @for($i = $row->count(); $i < $columns; $i++)
                    <div class="label"></div>
                @endfor
            </div>
        @endforeach
    </div>
</body>
</html>
