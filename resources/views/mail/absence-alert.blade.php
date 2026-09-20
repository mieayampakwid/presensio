@component('mail::message')
Yth. {{ $guardianName }},

Anak Anda, **{{ $studentName }}**{{ $className ? " ({$className})" : '' }}, tercatat **TIDAK HADIR** pada {{ $dateText }}.

Silakan hubungi wali kelas, atau masuk ke aplikasi untuk melihat catatan kehadiran.

@component('mail::button', ['url' => config('app.url')])
Buka {{ config('app.name') }}
@endcomponent

Terima kasih,<br>
{{ config('app.name') }}
@endcomponent
