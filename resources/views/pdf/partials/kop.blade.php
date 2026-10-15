{{-- School document header (kop), shared by report cards and fee receipts. --}}
<table class="kop">
    <tr>
        <td style="width: 2.5cm;">
            @if (($logoSrc ?? null) !== null)
                <img src="{{ $logoSrc }}" alt="Logo" style="width: 2cm; height: 2cm;">
            @endif
        </td>
        <td>
            <div class="sekolah">{{ $schoolName }}</div>
            <div class="alamat">{{ $schoolAddress }}</div>
        </td>
        <td style="width: 2.5cm;"></td>
    </tr>
</table>
