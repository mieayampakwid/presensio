# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Presensio melayani ekosistem multi-persona terpadu di lingkungan sekolah:

- **Administrator & Tata Usaha (Admin / TU):** Mengelola master data siswa, guru, wali murid, dan pegawai; konfigurasi kartu RFID; kalender akademik dan hari libur; roll-over tahun ajaran; serta audit log mutasi data.
- **Pimpinan Sekolah (Kepala Sekolah / Principal):** Memantau dashboard presensi waktu-nyata (guru, staf, dan siswa), memonitor rekapitulasi kehadiran bulanan, menyetujui kebijakan sekolah, dan menandatangani/mengesahkan rapor digital.
- **Guru & Wali Kelas:** Merancang tujuan pembelajaran (TP) Kurikulum Merdeka, mencatat asesmen formatif dan sumatif, mengelola buku nilai (gradebook), memantau capaian kompetensi, menyusun catatan perkembangan siswa, dan menerbitkan rapor digital semester.
- **Pegawai & Staf Non-Kependidikan (Tendik):** Melakukan presensi masuk/pulang harian, memeriksa jadwal kerja shift, dan mengajukan cuti/izin kerja pegawai.
- **Operator Scanner Gerbang:** Peran stasiun khusus di pos gerbang sekolah untuk mengoperasikan in-app webcam QR scanner secara cepat dan berulang pada jam sibuk kedatangan dan kepulangan.
- **Wali Murid (Orang Tua / Guardian):** Mengajukan surat permohonan izin/sakit anak secara digital beserta dokumen bukti, memantau kehadiran harian siswa, melihat pengumuman resmi dan memberikan konfirmasi membaca, memantau riwayat nilai dan rapor semester, serta memeriksa tagihan SPP dan mengunggah bukti transfer bank.
- **Siswa:** Memeriksa presensi harian pribadi, menampilkan Dynamic QR kehadiran, melihat jadwal pelajaran mingguan kelas, serta memantau tagihan biaya sekolah dan riwayat nilai asesmen.
- **Kasir & Bendahara / Akuntan:** Melayani loket kasir fisik untuk penerimaan pembayaran SPP tunai satu atau beberapa anak bersaudara sekaligus, memverifikasi transfer bank dari wali murid, mengelola titipan/kelebihan transfer (overpayment refund), mencatat ayat jurnal umum manual, dan menganalisis laporan akuntansi (buku kas, buku besar, neraca saldo, rekonsiliasi piutang, umur piutang).

## Product Purpose

Presensio adalah sistem informasi manajemen sekolah tunggal (single-school management system) komprehensif yang mengintegrasikan presensi fisik otomatis (RFID & Dynamic QR), evaluasi akademik Kurikulum Merdeka, tata kelola jadwal pelajaran dan pengumuman sekolah, serta manajemen keuangan biaya sekolah (SPP) dan pembukuan double-entry akuntansi akrual dalam satu platform terpusat. Keberhasilan diukur dari kecepatan presensi gerbang tanpa antrean, akurasi pelaporan akademik, transparansi komunikasi ke wali murid, dan keseimbangan mutlak buku besar keuangan sekolah.

## Positioning

Presensio membedakan diri dari software absensi generik maupun aplikasi nilai terpisah dengan menyatukan mesin operasional fisik dengan akuntansi double-entry formal:

- **Arsitektur Enrollment Murni:** Keanggotaan kelas hanya ditentukan oleh rekam jejak `enrollments` (`classOn(date)`), bukan kolom statis pada data siswa atau baris absensi, menjamin integritas riwayat mutasi antar-tahun ajaran tanpa duplikasi data.
- **Akuntansi Riil Terintegrasi:** Setiap siklus tagihan, pembayaran kasir, verifikasi transfer wali, pengembalian kelebihan, dan pembatalan biaya secara otomatis menerbitkan ayat jurnal buku besar yang seimbang (balanced ledger), menghindarkan sekolah dari ketidaksesuaian saldo subledger.
- **Evaluasi Kurikulum Merdeka Terstruktur:** Penilaian berpusat pada ketercapaian Tujuan Pembelajaran (TP) dengan pembagian asesmen formatif dan sumatif (lingkup materi & akhir semester) yang langsung bermuara pada lembar rapor digital versi final yang diarsipkan secara imutabel.

## Operating Context

- **Pos Gerbang & Meja Piket (Jam Sibuk Pagi):** Perangkat komputer di pos gerbang dengan pembaca kartu RFID USB dan webcam in-app scanner memproses ratusan siswa dan staf dalam rentang waktu singkat (06.30 - 07.30), membutuhkan feedback audio-visual cepat dan latensi minimal.
- **Meja Kerja Guru & Rapat Pleno Nilai:** Guru mengakses buku nilai (gradebook) dan leger nilai pada laptop saat masa penginputan asesmen semester, membutuhkan antarmuka tabel berkepadatan tinggi (high data density), navigasi sel yang mulus, dan kejelasan status batas kelulusan (KKTP).
- **Loket Kasir Keuangan Sekolah:** Kasir melayani antrean langsung wali murid yang melakukan pembayaran SPP secara tunai, membutuhkan pemilihan akun deposit kas cepat, alokasi tagihan lintas-anak bersaudara, dan cetak struk/kuitansi instan.
- **Akses Ponsel Wali Murid:** Orang tua membuka portal web responsif pada ponsel pintar untuk mengajukan izin sakit anak, membaca pengumuman sekolah yang mewajibkan konfirmasi baca, atau memantau status verifikasi slip pembayaran transfer bank.

## Capabilities and Constraints

- **Single Source of Truth:** Semua penentuan kelas, riwayat absensi, dan penerima pengumuman diturunkan dari tabel penempatan `enrollments`.
- **Integritas Penjurnalan Akrual:** Tidak ada manipulasi langsung pada saldo akun; semua perubahan saldo terjadi melalui posting jurnal berpasangan (debit & kredit).
- **Batas Lingkup Operasional Kasir v1:** Pembayaran SPP difokuskan pada kasir tunai fisik sekolah dan verifikasi manual transfer rekening koran bank internal; integrasi payment gateway pihak ketiga (Midtrans/Xendit/QRIS callback) secara sengaja berada di luar cakupan v1.
- **Densitas & Gaya Desain Utilitarian:** Mengutamakan efisiensi visual, minim ornamen berlebihan, keterbacaan data tabel tinggi, penanganan mode gelap yang konsisten, dan transisi antar-layar yang cepat.
- **Lokalisasi:** Antarmuka multibahasa berbasis Laravel translation strings (`lang/{en,id}`) dengan bahasa Indonesia sebagai default operasional.

## Brand Commitments

- **Nama Identitas:** Presensio.
- **Karakter Visual:** Utilitarian, presisi operasional, bersih, terpercaya, dan profesional untuk institusi pendidikan modern.
- **Palet & Nada:** Bersih dengan aksen fungsional kontras tinggi, mendukung mode terang dan gelap dengan hierarki tipografi yang tegas.

## Evidence on Hand

- Dokumen spesifikasi teknis lengkap dari fase fondasi hingga penagihan biaya di `docs/specs/01-auth.md` hingga `docs/specs/23-cashier-default-account.md`.
- Indeks model domain dan status implementasi terpusat pada `docs/specs/README.md`.
- Aturan arsitektur dan konvensi kode pada `.ai/rules/workflow.md`, `.ai/rules/inertia.md`, `.ai/rules/data.md`, `.ai/rules/navigation.md`, dan `.ai/rules/controllers.md`.

## Product Principles

1. **Utilitarian & Presisi di Atas Ornamen:** Setiap elemen visual harus memiliki tujuan operasional. Mengutamakan densitas data, kemudahan pemindaian tabel, dan kejelasan status daripada elemen dekoratif yang memakan ruang.
2. **Kebenaran Tunggal & Imutabilitas Historis:** Tidak ada modifikasi riwayat absensi, log pemindaian gerbang, audit trail, atau ayat jurnal keuangan secara destruktif; rekam jejak masa lalu adalah arsip hukum sekolah yang tidak dapat diubah.
3. **Penyajian Status Transparan & Tanpa Ambiguitas:** Setiap status presensi, status persetujuan izin, status posting jurnal, dan status pembayaran SPP memiliki indikator visual semantik yang tegas dan konsisten di seluruh role persona.
4. **Alur Kerja Door-to-Door yang Utuh:** Setiap entitas dan fitur memiliki navigasi clickable yang lengkap dari login/sidebar hingga aksi spesifik tanpa ketergantungan pada pengetikan URL langsung.

## Accessibility & Inclusion

- Memenuhi standar WCAG 2.1 AA untuk rasio kontras teks dan elemen interaktif di kedua mode tema (light/dark).
- Navigasi keyboard penuh pada form input, tabel dengan tombol aksi, dan dialog konfirmasi.
- Setiap halaman wajib memiliki landmark heading semantik hierarkis (`<h1>`, `<nav>`, `<main>`) yang terbaca akurat oleh screen reader.
