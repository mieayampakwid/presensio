<?php

return [
    'staff' => [
        'title' => 'Jam Kerja Staf (Fallback)',
        'note' => 'Jam ini berlaku untuk staf yang tidak memiliki penugasan shift. Staf dengan shift mengikuti jam shift masing-masing.',
        'start' => 'Jam mulai kerja staf',
        'end' => 'Jam selesai kerja staf',
        'sweep' => 'Jam sapu absent otomatis',
    ],
    'section' => [
        'title' => 'Shift Kerja',
        'create' => 'Tambah Shift',
        'empty' => 'Belum ada shift kerja. Staf tanpa shift mengikuti jam kerja staf (fallback).',
        'columns' => [
            'name' => 'Nama',
            'times' => 'Jam',
            'sweep_offset' => 'Sapu absent',
        ],
        'crosses_midnight' => 'Lewat tengah malam',
        'sweep_offset' => 'Sapu absent :offset menit setelah mulai',
        'actions' => 'Aksi',
        'remove' => 'Hapus',
        'remove_description' => 'Shift :name akan dihapus. Shift yang masih dipakai tidak dapat dihapus.',
        'remove_confirm' => 'Hapus',
        'cancel' => 'Batal',
        'max_note' => 'Maksimal 10 shift.',
    ],
    'form' => [
        'create_title' => 'Tambah Shift',
        'create_description' => 'Tentukan templat jam kerja bernama untuk staf.',
        'edit_title' => 'Ubah Shift',
        'edit_description' => 'Perbarui jam shift. Perubahan hanya memengaruhi rekaman yang dibuat setelahnya.',
        'name' => 'Nama',
        'name_placeholder' => 'mis. Shift Pagi',
        'start_time' => 'Jam mulai',
        'end_time' => 'Jam selesai',
        'sweep_offset_minutes' => 'Sapu absent (menit setelah mulai)',
        'sweep_offset_hint' => '30–480 menit setelah jam mulai; sapu absent berjalan pada jam ini.',
        'submit_create' => 'Simpan',
        'submit_update' => 'Simpan Perubahan',
    ],
    'errors' => [
        'in_use' => 'Shift ini masih dipakai sebagai shift default pegawai, baris roster, atau rekaman absensi dan tidak dapat dihapus.',
        'max_shifts' => 'Maksimal 10 shift kerja.',
        'sweep_past_midnight' => 'Jam mulai + sapu absent melewati tengah malam; sapu absent harus berjalan pada tanggal mulai shift.',
    ],
    'toast' => [
        'created' => 'Shift kerja berhasil disimpan.',
        'updated' => 'Shift kerja berhasil diperbarui.',
        'deleted' => 'Shift kerja berhasil dihapus.',
    ],
];
