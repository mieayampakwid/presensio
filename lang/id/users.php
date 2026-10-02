<?php

return [
    'index' => [
        'title' => 'Pengguna',
        'description' => 'Akun yang dibuat untuk semua peran.',
        'create' => 'Buat pengguna',
        'search_placeholder' => 'Cari berdasarkan nama pengguna atau email…',
        'empty' => 'Pengguna tidak ditemukan.',
        'columns' => [
            'username' => 'Nama pengguna',
            'email' => 'Email',
            'roles' => 'Peran',
            'status' => 'Status',
        ],
    ],
    'status' => [
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
    ],
    'create' => [
        'title' => 'Buat pengguna',
        'description' => 'Buat akun baru dan hubungkan, jika perlu, dengan profil guru, wali murid, atau siswa.',
    ],
    'edit' => [
        'head' => 'Ubah :username',
        'title' => 'Ubah pengguna',
        'description' => 'Kredensial dan peran akun. Nonaktifkan, jangan dihapus.',
        'submit' => 'Simpan perubahan',
    ],
    'reset_password' => [
        'title' => 'Atur ulang kata sandi',
        'label' => 'Kata sandi baru',
        'placeholder' => 'Kata sandi baru',
        'submit' => 'Atur ulang kata sandi',
    ],
    'form' => [
        'username' => 'Nama pengguna',
        'username_placeholder' => 'NIS / NIP / NIK',
        'email' => 'Email (opsional)',
        'roles' => 'Peran',
        'linked_profile' => 'Profil :role terhubung',
        'not_linked' => 'Tidak terhubung',
        'active' => 'Aktif',
        'password' => 'Kata sandi',
        'password_placeholder' => 'Kata sandi',
        'create_submit' => 'Buat pengguna',
    ],
    'toast' => [
        'created' => 'Pengguna dibuat.',
        'updated' => 'Pengguna diperbarui.',
        'password_updated' => 'Kata sandi diperbarui.',
    ],
];
