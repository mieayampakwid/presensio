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

### 1. `App\Http\Controllers\Finance\ManualJournalController` (Selesai Direfaktor ✅ — commit `3b3b066`, 2026-11-10)

* **Hasil Refactoring:**
  * Ukuran controller menyusut dari 530 baris menjadi 248 baris (-53%).
  * Seluruh mutasi draf, create-and-post, update, post, reverse, dan delete diekstrak ke `App\Services\Finance\ManualJournalService`.
  * Seluruh pembentukan view data, opsi akun/siswa, dan serialisasi Inertia diekstrak ke `App\Services\Finance\ManualJournalViewService`.
  * Controller kini ramping dan murni menangani HTTP coordination, validation, dan authorization.
* **Verifikasi:** 64 finance tests passing (460 assertions), PHPStan clean, Pint clean, frontend checks clean.

---

### 2. `App\Http\Controllers\Classes\ClassSubjectController` (Selesai Direfaktor ✅ — commit `8aa8398`, 2026-11-10)

* **Hasil Refactoring:**
  * Ukuran controller menyusut drastis dari 295 baris menjadi 102 baris (-65%).
  * Otorisasi akses baca non-admin dipindahkan ke `ClassAccess::canViewClassSubjects()` yang tersentralisasi.
  * Pembentukan payload Inertia dan query join mapel kelas untuk tampilan admin maupun non-admin diekstrak ke `App\Services\Classes\ClassSubjectViewService`.
  * Seluruh mutasi `assignSubject`, `updateSubject`, dan `removeSubject` beserta transaksi DB dan audit logging diekstrak ke `App\Services\Classes\ClassSubjectService`.
* **Verifikasi:** 42 classes tests passing (218 assertions), PHPStan clean, Pint clean, frontend checks clean.

---

### 3. `App\Http\Controllers\Staff\StaffAttendanceController` (Selesai Direfaktor ✅ — commit `c068c40`, 2026-11-10)

* **Hasil Refactoring:**
  * Ukuran controller menyusut dari 282 baris menjadi 74 baris (-74%).
  * Seluruh kalkulasi kehadiran harian staf (*expected, late, absent, no checkout*), filter, serta pengkayaan jadwal jam pertama guru diekstrak ke `App\Services\Staff\StaffAttendanceSummaryService`.
  * Seluruh mutasi manual override beserta perhitungan keterlambatan / pulang cepat, dukungan shift lintas-hari (cross-midnight), dan pencatatan audit diekstrak ke `App\Services\Staff\StaffAttendanceOverrideService`.
  * Controller kini ramping dan murni bertugas mengoordinasikan HTTP request, otorisasi, dan navigasi view.
* **Verifikasi:** 103 staff tests passing (826 assertions), PHPStan clean, Pint clean, frontend checks clean.

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
