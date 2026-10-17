<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $payment->receipt_number }}</title>
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
        .kop td, .info td, .ttd td {
            border: none;
        }
        .kop td {
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
        .kanan {
            text-align: right;
        }
        .watermark {
            position: fixed;
            top: 35%;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 120pt;
            font-weight: bold;
            color: #d9d9d9;
        }
    </style>
</head>
<body>
@if ($isVoid)
    <div class="watermark">BATAL</div>
@endif
@include('pdf.partials.kop', [
    'logoSrc' => $logoSrc,
    'schoolName' => $schoolName,
    'schoolAddress' => $schoolAddress,
])

<div class="judul">KUITANSI PEMBAYARAN</div>

<table class="info">
    <tr>
        <td style="width: 4cm;">Nomor</td>
        <td>: {{ $payment->receipt_number }}</td>
    </tr>
    <tr>
        <td>Tanggal verifikasi</td>
        <td>: {{ $payment->verified_at?->translatedFormat('d F Y') }}</td>
    </tr>
    <tr>
        <td>Metode pembayaran</td>
        <td>: {{ $payment->payment_method->label() }}</td>
    </tr>
</table>

<br>
<table>
    <thead>
        <tr>
            <th>Siswa</th>
            <th>NIS</th>
            <th>Kelas</th>
            <th>Tagihan</th>
            <th>Jumlah</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr>
                <td>{{ $line['student'] }}</td>
                <td>{{ $line['student_number'] }}</td>
                <td>{{ $line['class'] }}</td>
                <td>{{ $line['title'] }}</td>
                <td class="kanan">Rp {{ number_format((float) $line['amount'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="4"><strong>Total</strong></td>
            <td class="kanan"><strong>Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</strong></td>
        </tr>
    </tbody>
</table>

<p>Terbilang: <em>{{ $words }}</em></p>

<table class="ttd" style="margin-top: 1cm;">
    <tr>
        <td style="width: 60%;"></td>
        <td style="text-align: center;">
            Petugas verifikasi,
            <br><br><br><br>
            {{ $payment->verifiedBy?->employee?->name ?? $payment->verifiedBy?->username }}
        </td>
    </tr>
</table>
</body>
</html>
