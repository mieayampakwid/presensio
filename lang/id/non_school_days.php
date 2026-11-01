<?php

return [
    'index' => [
        'title' => 'Hari Libur',
        'description' => 'Hari libur dan penutupan sekolah — sweep dan scanner memperhitungkannya.',
        'add' => 'Tambah tanggal',
        'empty' => 'Tidak ada hari libur tercatat.',
        'columns' => [
            'date' => 'Tanggal',
            'name' => 'Nama',
            'source' => 'Sumber',
        ],
        'delete' => 'Hapus',
        'remove_title' => 'Hapus hari libur',
        'remove_description' => 'Hapus :date — ":name"? Catatan: tanggal yang disinkronkan dan dihapus saat feed hari besar nasional masih mencantumkannya akan diimpor ulang pada sinkronisasi mingguan berikutnya.',
        'remove_confirm' => 'Hapus',
    ],
    'source' => [
        'sync' => 'Sinkron',
        'manual' => 'Manual',
    ],
    'form' => [
        'create_title' => 'Tambah hari libur',
        'create_description' => 'Tetapkan penutupan sekolah. Hanya satu entri per tanggal yang diizinkan.',
        'date' => 'Tanggal',
        'name' => 'Nama',
        'name_placeholder' => 'mis. Hari pelatihan guru',
        'submit_create' => 'Tambah tanggal',
    ],
];
