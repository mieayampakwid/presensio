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

### 4. `App\Http\Controllers\Employees\EmployeeController` (Selesai Direfaktor ✅ — commit `9539479`, 2026-11-10)

* **Hasil Refactoring:**
  * Ukuran controller menyusut dari 308 baris menjadi 107 baris (-65%).
  * Seluruh mutasi `createEmployee`, `updateEmployee`, sinkronisasi profil `Teacher` & peran `User` (`Teacher` vs `Staff`), deteksi `deletionBlockers`, serta penghapusan pegawai diekstrak ke `App\Services\Employees\EmployeeService`.
  * Seluruh pembentukan payload index (`getPaginatedEmployees`), opsi form (`getFormOptions`: tipe pegawai, shift, hari operasional, user tersedia), dan format edit data diekstrak ke `App\Services\Employees\EmployeeViewService`.
  * Controller kini ramping dan murni mengoordinasikan HTTP request & redirect.
* **Verifikasi:** 19 employee tests & 14 teacher tests passing, PHPStan clean, Pint clean, frontend checks clean.

---

### 5. `App\Http\Controllers\Attendance\AttendanceController` (Selesai Direfaktor ✅ — commit `b08359e`, 2026-11-10)

* **Hasil Refactoring:**
  * Ukuran controller menyusut dari 206 baris menjadi 88 baris (-57%).
  * Seluruh query roster siswa per tanggal, filter kelas, dan format waktu kehadiran diekstrak ke `App\Services\Attendance\AttendanceRosterBuilder`.
  * Seluruh mutasi rekonsiliasi manual `updateRecord` dan pengisian celah presensi `bulkPresent` beserta savepoint nested transaction penanganan tabrakan konkurensi RFID scanner diekstrak ke `App\Services\Attendance\AttendanceRecorder`.
  * Controller kini ramping dan murni mengoordinasikan otorisasi, validasi form request, pemanggilan service, dan flash toast.
* **Verifikasi:** 70 attendance tests passing (502 assertions), PHPStan clean, Pint clean, frontend checks clean.

---

### 6. `App\Http\Controllers\Teacher\LearningObjectiveController` (Selesai Direfaktor ✅ — commit `e4041b6`, 2026-11-10)

* **Hasil Refactoring:**
  * Ukuran controller menyusut dari 216 baris menjadi 102 baris (-53%).
  * Seluruh query pembentukan payload index (tahun ajaran, filter mapel/tingkat kelas admin vs guru, pengecekan ketersediaan tahun lalu) diekstrak ke `App\Services\Grading\LearningObjectiveViewService`.
  * Seluruh operasi penyalinan TP dari tahun sebelumnya, pengecekan duplikasi kode, dan transaksi pembuatan record diekstrak ke `App\Services\Grading\CopyLearningObjectivesAction`.
  * Otorisasi dan validasi penyalinan TP dipindahkan ke FormRequest `App\Http\Requests\Teacher\CopyLearningObjectivesRequest`.
  * Controller kini ramping dan murni mengoordinasikan HTTP request, otorisasi, dan delegasi service.
* **Verifikasi:** 10 learning objective tests & 82 teacher domain tests passing (456 assertions), PHPStan clean, Pint clean, frontend checks clean.

---

### 7. `App\Http\Controllers\Teacher\AssessmentController` (Selesai Direfaktor ✅ — commit `343ed49`, 2026-11-10)

* **Hasil Refactoring:**
  * Ukuran controller menyusut dari 208 baris menjadi 82 baris (-61%).
  * Seluruh query perakitan payload asesmen kursus (eager loading, resolusi semester, query asesmen & hitungan nilai, query TP lingkup kelas) diekstrak ke `App\Services\Grading\AssessmentViewService`.
  * Seluruh mutasi `create` dan `update` asesmen, sync TP, serta pencatatan audit log diekstrak ke `App\Services\Grading\AssessmentService`.
  * Seluruh operasi `destroy`, validasi semester terbuka, pengecekan konfirmasi nilai terisi, pencadangan snapshot nilai ke audit log, dan penghapusan asesmen diekstrak ke `App\Services\Grading\DeleteAssessmentAction`.
  * Controller kini ramping dan murni mengoordinasikan HTTP request, otorisasi, dan delegasi service.
* **Verifikasi:** 9 assessment tests & 82 teacher domain tests passing (456 assertions), PHPStan clean, Pint clean, frontend checks clean.

---

## Petunjuk Eksekusi saat Mengerjakan Refactoring

1. **Jalankan test terkait terlebih dahulu:** Pastikan seluruh test yang mencakup controller tersebut lulus sebelum menyentuh kode.
2. **Ekstrak secara bertahap:** Buat Service/Action class baru, pindahkan logika dari controller, lalu suntikkan (*dependency injection*) ke controller.
3. **Pastikan zero regressions:** Jalankan `php artisan test --compact <path_to_test>`, lalu `vendor/bin/pint --dirty --format agent`, dan `vendor/bin/phpstan analyse`.
