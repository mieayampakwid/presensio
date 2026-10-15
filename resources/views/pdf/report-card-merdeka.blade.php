<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rapor {{ $payload['student']['name'] }}</title>
    <style>
        @page {
            margin: 2cm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #111;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 0.5pt solid #444;
            padding: 3pt 5pt;
            vertical-align: top;
            text-align: left;
        }
        thead th {
            background-color: #eee;
            text-align: center;
        }
        tr {
            page-break-inside: avoid;
        }
        .kop td {
            border: none;
            text-align: center;
            vertical-align: middle;
        }
        .kop .sekolah {
            font-size: 14pt;
            font-weight: bold;
        }
        .kop .alamat {
            font-size: 9pt;
        }
        .judul {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            margin: 10pt 0;
        }
        .identitas td {
            border: none;
            padding: 1pt 3pt;
        }
        .group-row td {
            background-color: #f3f3f3;
            font-weight: bold;
        }
        .ttd td {
            border: none;
            text-align: left;
            vertical-align: top;
        }
        .footer {
            margin-top: 10pt;
            font-size: 8pt;
            text-align: center;
            color: #444;
        }
    </style>
</head>
<body>

@include('pdf.partials.report-card-content', ['payload' => $payload])

</body>
</html>
