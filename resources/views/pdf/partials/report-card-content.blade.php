@php
    /** @var array<string, mixed> $payload */
    $logoMime = match (true) {
        str_ends_with((string) ($payload['school']['logo']['path'] ?? ''), '.jpg'),
        str_ends_with((string) ($payload['school']['logo']['path'] ?? ''), '.jpeg') => 'image/jpeg',
        default => 'image/png',
    };
    $logoSrc = ($payload['school']['logo']['content_base64'] ?? null) !== null
        ? 'data:'.$logoMime.';base64,'.$payload['school']['logo']['content_base64']
        : null;
    $subjects = $payload['subjects'];
    $attendance = $payload['attendance'];
    $decision = $payload['decision'];
    $decisionLine = $decision === null ? null : match ($decision['value']) {
        'promoted' => 'Naik ke kelas '.((int) $payload['class']['grade_level'] + 1),
        'retained' => 'Tinggal di kelas '.$payload['class']['grade_level'],
        default => 'Lulus',
    };
@endphp
@include('pdf.partials.kop', [
    'logoSrc' => $logoSrc,
    'schoolName' => $payload['school']['name'],
    'schoolAddress' => $payload['school']['address'],
])

<div class="judul">LAPORAN HASIL BELAJAR</div>

<table class="identitas">
    <tr>
        <td style="width: 3cm;">Nama</td>
        <td style="width: 0.3cm;">:</td>
        <td>{{ $payload['student']['name'] }}</td>
        <td style="width: 3cm;">Kelas</td>
        <td style="width: 0.3cm;">:</td>
        <td>{{ $payload['class']['name'] }}</td>
    </tr>
    <tr>
        <td>NIS</td>
        <td>:</td>
        <td>{{ $payload['student']['student_number'] ?? '—' }}</td>
        <td>Fase</td>
        <td>:</td>
        <td>{{ $payload['class']['phase'] ?? '—' }}</td>
    </tr>
    <tr>
        <td>NISN</td>
        <td>:</td>
        <td>—</td>
        <td>Semester</td>
        <td>:</td>
        <td>{{ $payload['semester']['name'] }}</td>
    </tr>
    <tr>
        <td></td>
        <td></td>
        <td></td>
        <td>Tahun Ajaran</td>
        <td>:</td>
        <td>{{ $payload['semester']['academic_year'] }}</td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th style="width: 1cm;">No</th>
            <th>Mata Pelajaran</th>
            <th style="width: 2cm;">Nilai Akhir</th>
            <th style="width: 6cm;">Capaian Kompetensi</th>
        </tr>
    </thead>
    <tbody>
        @php $lastGroup = null; @endphp
        @foreach ($subjects as $index => $subject)
            @if (($subject['group_label']) !== $lastGroup)
                <tr class="group-row">
                    <td colspan="4">{{ $subject['group_label'] }}</td>
                </tr>
                @php $lastGroup = $subject['group_label']; @endphp
            @endif
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>
                    {{ $subject['name'] }}
                    @if (($subject['from_class'] ?? null) !== null)
                        (dari kelas {{ $subject['from_class'] }})
                    @endif
                </td>
                <td style="text-align: center;">{{ $subject['final_score'] ?? '—' }}</td>
                <td>{{ $subject['description'] ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p style="margin: 8pt 0 2pt; font-weight: bold;">Ekstrakurikuler</p>
<table>
    <tr>
        <td>{{ $payload['notes']['extracurricular'] ?? '—' }}</td>
    </tr>
</table>

<p style="margin: 8pt 0 2pt; font-weight: bold;">Ketidakhadiran</p>
<table style="width: 8cm;">
    <tr>
        <td>Sakit</td>
        <td>: {{ $attendance['sick'] }} hari</td>
    </tr>
    <tr>
        <td>Izin</td>
        <td>: {{ $attendance['leave'] }} hari</td>
    </tr>
    <tr>
        <td>Tanpa Keterangan</td>
        <td>: {{ $attendance['absent'] }} hari</td>
    </tr>
</table>

<p style="margin: 8pt 0 2pt; font-weight: bold;">Catatan Wali Kelas</p>
<table>
    <tr>
        <td>{{ $payload['notes']['homeroom'] ?? '—' }}</td>
    </tr>
</table>

@if ($decisionLine !== null)
    <p style="margin: 8pt 0 2pt; font-weight: bold;">Keputusan</p>
    <table>
        <tr>
            <td>{{ $decisionLine }}</td>
        </tr>
    </table>
@endif

<table class="ttd" style="margin-top: 24pt;">
    <tr>
        <td style="width: 33%;">&nbsp;</td>
        <td style="width: 34%;">&nbsp;</td>
        <td style="width: 33%;">&nbsp;</td>
    </tr>
    <tr>
        <td>Orang Tua/Wali</td>
        <td></td>
        <td>{{ $payload['school']['address'] }}, <span style="white-space: nowrap">{{ $payload['published_at'] }}</span></td>
    </tr>
    <tr>
        <td style="height: 2cm;"></td>
        <td>Wali Kelas</td>
        <td>Kepala Sekolah</td>
    </tr>
    <tr>
        <td></td>
        <td>
            <strong>{{ $payload['homeroom_teacher'] ?? '—' }}</strong>
        </td>
        <td>
            <strong>{{ $payload['principal']['name'] ?? '—' }}</strong><br>
            NIP. {{ $payload['principal']['nip'] ?? '—' }}
        </td>
    </tr>
</table>

<div class="footer">Versi {{ $payload['version'] }}, diterbitkan {{ $payload['published_at'] }}</div>
