# Controller Refactoring Backlog

Dokumen ini mencatat daftar controller yang teridentifikasi mengalami *fat controller* / *code smell* untuk direfaktor di waktu mendatang agar mematuhi aturan arsitektur di `.ai/rules/controllers.md` dan panduan Laravel Best Practices.

---

## Standar Acuan (*Target Architecture*)

Sesuai aturan di `.ai/rules/controllers.md`:
* **HTTP Concerns Only:** Controller hanya bertugas memvalidasi input HTTP, memeriksa otorisasi, memanggil Action/Service/Builder, dan mengembalikan respon (`Inertia::render` atau `RedirectResponse`).
* **No Raw SQL / Complex Queries:** Dilarang menempatkan `selectRaw`, `whereRaw`, join manual multi-tabel, atau agregasi langsung di dalam controller.
* **Separation of Read & Write:**
  * **Read Model / Aggregations:** Delegasikan ke Service/Builder (contoh: `GradebookBuilder`, `CourseViewService`).
  * **Write / Multi-step Mutations:** Delegasikan ke Action/Recorder (contoh: `ScoreRecorder`, `EnrollmentService`).

---

## Daftar Backlog Refactoring

### 1. `App\Http\Controllers\Finance\ManualJournalController` (Prioritas Tinggi — 508 baris)

* **Masalah Saat Ini:**
  * Method `store()` mengelola cabang draf vs posting, upload file, iterasi baris debet/kredit, dan transaksi DB langsung di controller.
  * Method `reverse()` mengelola pembuatan jurnal pembalik, validasi status, dan audit log langsung di controller.
  * Method `index()`, `create()`, `show()`, dan `edit()` memuat puluhan baris transformasi array/DTO manual untuk Inertia.
* **Rencana Solusi:**
  1. Ekstrak mutasi simpan/posting ke `App\Services\Finance\ManualJournalService` atau `SaveManualJournalAction`.
  2. Ekstrak logika pembalikan jurnal ke `App\Services\Finance\ReverseJournalAction`.
  3. Ekstrak pembentukan view payload jurnal ke `App\Services\Finance\ManualJournalViewService`.
* **Verifikasi:** `php artisan test --compact tests/Feature/Finance`

---

### 2. `App\Http\Controllers\Classes\ClassSubjectController` (Prioritas Tinggi — 294 baris)

* **Masalah Saat Ini:**
  * Method `index()` (120 baris) memiliki percabangan besar Admin vs Non-Admin, join SQL manual 3 kolom, dan merender 2 view berbeda.
  * Terdapat method *private* 35 baris (`canViewClassSubjects`) yang melakukan pengecekan relasi enrollment siswa dan wali murid.
  * Mutasi `store()`, `update()`, dan `destroy()` membungkus `DB::transaction` dan `auditLogger` langsung di controller.
* **Rencana Solusi:**
  1. Pindahkan otorisasi akses baca ke Policy atau helper `ClassAccess::canViewClassSubjects()`.
  2. Ekstrak query pembacaan daftar mata pelajaran kelas ke `App\Services\Classes\ClassSubjectViewService`.
  3. Pisahkan view non-admin (hanya lihat mapel kelas) jika memungkinkan menjadi endpoint/controller terpisah (misalnya `ClassSubjectsViewController`), atau delegasikan seleksi view ke service.
* **Verifikasi:** `php artisan test --compact tests/Feature/Classes`

---

### 3. `App\Http\Controllers\Staff\StaffAttendanceController` (Prioritas Sedang — 230 baris)

* **Masalah Saat Ini:**
  * Method `index()` (120 baris) melakukan iterasi seluruh karyawan aktif (`foreach`), menghitung ringkasan kehadiran (*expected, late, absent, no checkout*), dan memvalidasi kalender kerja langsung di controller.
* **Rencana Solusi:**
  1. Ekstrak kalkulasi rekap kehadiran harian staf ke `App\Services\Staff\StaffAttendanceSummaryService`.
  2. Controller hanya memanggil `$this->summaryService->getDailyOverview($date, $filter)`.
* **Verifikasi:** `php artisan test --compact tests/Feature/Staff`

---

### 4. `App\Http\Controllers\Employees\EmployeeController` (Prioritas Sedang — 287 baris)

* **Masalah Saat Ini:**
  * Method `store()` dan `update()` menjalankan transaksi multi-tabel untuk menyimpan data `Employee`, membuat/mengubah baris `Teacher`, menyinkronkan peran akun `User` (`UserRole::Teacher` vs `UserRole::Staff`), serta mencatat audit.
* **Rencana Solusi:**
  1. Ekstrak logika simpan dan sinkronisasi pegawai-guru ke `App\Services\Employees\SaveEmployeeAction`.
  2. Ekstrak opsi form (`employmentTypeOptions`, `availableUsers`) ke helper/service form options.
* **Verifikasi:** `php artisan test --compact tests/Feature/Employees`

---

### 5. `App\Http\Controllers\Attendance\AttendanceController` (Prioritas Sedang — 206 baris)

* **Masalah Saat Ini:**
  * Method `index()` memuat query roster siswa per tanggal yang panjang dengan relasi nested `enrollments` dan `attendances`.
  * Method `updateRecord()` dan `bulkStore()` menangani transaksi database dan pembentukan record kehadiran langsung di controller.
* **Rencana Solusi:**
  1. Ekstrak query roster presensi harian ke `App\Services\Attendance\AttendanceRosterBuilder`.
  2. Ekstrak mutasi kehadiran ke `App\Services\Attendance\AttendanceRecorder`.
* **Verifikasi:** `php artisan test --compact tests/Feature/Attendance`

---

### 6. `App\Http\Controllers\Teacher\LearningObjectiveController` (Prioritas Ringan — 216 baris)

* **Masalah Saat Ini:**
  * Method `copyPreviousYear()` memproses iterasi penyalinan TP dari tahun sebelumnya, pengecekan duplikasi, dan persistensi database langsung di controller.
* **Rencana Solusi:**
  1. Ekstrak operasi salin TP ke `App\Services\Grading\CopyLearningObjectivesAction`.
* **Verifikasi:** `php artisan test --compact tests/Feature/Teacher/LearningObjectiveTest.php`

---

### 7. `App\Http\Controllers\Teacher\AssessmentController` (Prioritas Ringan — 208 baris)

* **Masalah Saat Ini:**
  * Method `destroy()` memeriksa keberadaan nilai siswa, mencadangkan snapshot nilai lama, menghapus relasi pivot, dan mencatat audit log langsung di controller.
* **Rencana Solusi:**
  1. Ekstrak operasi hapus asesmen ber-nilai ke `App\Services\Grading\DeleteAssessmentAction`.
* **Verifikasi:** `php artisan test --compact tests/Feature/Teacher/AssessmentTest.php`

---

## Petunjuk Eksekusi saat Mengerjakan Refactoring

1. **Jalankan test terkait terlebih dahulu:** Pastikan seluruh test yang mencakup controller tersebut lulus sebelum menyentuh kode.
2. **Ekstrak secara bertahap:** Buat Service/Action class baru, pindahkan logika dari controller, lalu suntikkan (*dependency injection*) ke controller.
3. **Pastikan zero regressions:** Jalankan `php artisan test --compact <path_to_test>`, lalu `vendor/bin/pint --dirty --format agent`, dan `vendor/bin/phpstan analyse`.
