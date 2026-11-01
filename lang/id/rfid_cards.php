<?php

return [
    'index' => [
        'title' => 'Kartu RFID',
        'description' => 'Kartu pemindaian terdaftar; tetapkan ke siswa untuk absensi.',
        'create' => 'Buat kartu',
        'search_placeholder' => 'Cari berdasarkan kartu atau siswa…',
        'empty' => 'Kartu tidak ditemukan.',
        'columns' => [
            'card_number' => 'Nomor kartu',
            'assigned_to' => 'Ditetapkan ke',
            'status' => 'Status',
        ],
        'revoke' => 'Cabut',
        'delete' => 'Hapus',
        'remove_title' => 'Hapus kartu',
        'remove_description' => 'Apakah Anda yakin ingin menghapus kartu ":number"?',
        'remove_confirm' => 'Hapus',
    ],
    'status' => [
        'assigned' => 'Ditetapkan',
        'spare' => 'Cadangan',
    ],
    'form' => [
        'create_title' => 'Buat kartu',
        'create_description' => 'Daftarkan nomor kartu RFID baru dan tetapkan ke siswa jika perlu.',
        'edit_title' => 'Ubah kartu :number',
        'edit_description' => 'Ubah penugasan atau kembalikan kartu ke status cadangan.',
        'card_number' => 'Nomor kartu',
        'card_number_placeholder' => 'Nomor yang tercetak pada kartu',
        'assigned_student' => 'Siswa yang ditetapkan',
        'spare_option' => 'Cadangan (tidak ditetapkan)',
        'submit_create' => 'Buat kartu',
        'submit_update' => 'Simpan perubahan',
    ],
];
