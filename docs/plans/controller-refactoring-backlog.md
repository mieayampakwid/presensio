# Controller Refactoring Backlog

Dokumen ini mencatat daftar controller yang teridentifikasi mengalami *fat controller* / *code smell* untuk direfaktor agar mematuhi aturan arsitektur di `.ai/rules/controllers.md` dan panduan Laravel Best Practices.

---

## Standar Acuan (*Target Architecture*)

Sesuai aturan di `.ai/rules/controllers.md`:
* **HTTP Concerns Only:** Controller hanya bertugas memvalidasi input HTTP, memeriksa otorisasi, memanggil Action/Service/Builder, dan mengembalikan respon (`Inertia::render` atau `RedirectResponse`).
* **No Raw SQL / Complex Queries:** Dilarang menempatkan `selectRaw`, `whereRaw`, join manual multi-tabel, atau agregasi langsung di dalam controller.
* **Separation of Read & Write:**
  * **Read Model / Aggregations:** Delegasikan ke Service/Builder (contoh: `GradebookBuilder`, `CourseViewService`).
  * **Write / Multi-step Mutations:** Delegasikan ke Action/Recorder (contoh: `ScoreRecorder`, `EnrollmentService`).

---

## Fase 1 — Daftar Backlog Awal (Selesai 100% ✅)

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

## Fase 2 — Refactoring Lanjutan

### 8. `App\Http\Controllers\Dashboard\DashboardController` (Selesai Direfaktor ✅ — commit `7d31b43`, 2026-11-10)

* **Hasil Refactoring:**
  * Ukuran controller menyusut drastis dari 245 baris menjadi 36 baris (-85%).
  * Seluruh perakitan metrik multi-role (`announcementSummary`, `is_school_day`, `board`, `pending_excuses`, `pending_leave_requests`, `employee_leave_today`, `children`, `student`, `today_schedule`) beserta ketujuh helper query diekstrak ke `App\Services\Dashboard\DashboardBuilder`.
  * Controller kini sangat ramping, murni menangani otorisasi, redirect role scanner operator, dan delegasi payload view.
* **Verifikasi:** 27 dashboard feature tests passing (362 assertions), PHPStan clean, Pint clean, frontend checks clean.

---

### 9. `App\Http\Controllers\Teacher\ReportCardWorkspaceController` (Selesai Direfaktor ✅ — commit `113eba1`, 2026-11-10)

* **Hasil Refactoring:**
  * Ukuran controller menyusut dari 224 baris menjadi 157 baris (-30%).
  * Helper `firstAccessibleClass()` diekstrak ke `App\Services\ReportCards\ReportCardWorkspaceService`.
  * Resolusi semester (`resolveSemester`) dan pencarian kartu rapor terpublikasi (`findPublishedCard`) didelegasikan ke `ReportCardWorkspaceService`.
  * Perakitan payload view `show()` diekstrak ke `ReportCardWorkspaceService::getWorkspaceViewData()`.
  * Pengecekan ketersediaan bundel PDF (`bundleAvailable`) dipindahkan ke `ReportCardWorkspaceService`.
  * Controller kini ramping dan murni bertugas mengoordinasikan otorisasi `ClassAccess`, memanggil `ReportCardWorkspaceService` & `ReportCardPdf`, serta mengembalikan response HTTP/Inertia/PDF.
* **Verifikasi:** 34 report card & workspace feature tests passing (209 assertions), PHPStan clean, Pint clean, frontend checks clean.

---

### 10. `App\Http\Controllers\Students\StudentController` (221 baris) & `App\Http\Controllers\Teachers\TeacherController` (198 baris)

* **Kondisi Saat Ini:**
  * **`StudentController`** masih memuat query paginasi filter pencarian/status (`active`/`withdrawn`) di `index()`, transaksi `DB::transaction` di `store()`, `update()`, `destroy()`, serta helper `assignClass()`, `formOptions()`, dan `deletionBlockers()`.
  * **`TeacherController`** masih memuat query paginasi `index()`, transaksi pembuatan/pembaruan `Employee` + `Teacher` di `store()`, `update()`, `destroy()`, serta `deletionBlockers()`.
* **Rencana Ekstraksi:**
  1. **Domain Students (`StudentController` → Target ~95 baris):**
     * Buat **`App\Services\Students\StudentViewService`**:
       * `getPaginatedStudents(string $search, string $status): LengthAwarePaginator` (mempertahankan `whereLike` dan filter enrollment tahun aktif).
       * `getFormOptions(): array` (daftar kelas tahun aktif & daftar wali murid).
       * `getEditViewData(Student $student): array` (menggabungkan `formOptions()`, `student`, `current_guardian_ids`, dan `current_guardians`).
     * Buat **`App\Services\Students\StudentService`**:
       * Inject `EnrollmentService`.
       * `createStudent(array $attributes, array $guardianPivotData, ?int $classId): Student` dalam `DB::transaction`.
       * `updateStudent(Student $student, array $attributes, array $guardianPivotData, ?int $classId): Student` dalam `DB::transaction`.
       * `deletionBlockers(Student $student): list<string>` dan `deleteStudent(Student $student): void` (melepas guardian pivot, mereset `RfidCard`, dan menghapus student).
  2. **Domain Teachers (`TeacherController` → Target ~90 baris):**
     * Buat **`App\Services\Teachers\TeacherViewService`**:
       * `getPaginatedTeachers(string $search): LengthAwarePaginator` dan `formatForEdit(Teacher $teacher): array`.
     * Buat **`App\Services\Teachers\TeacherService`**:
       * `createTeacher(array $validated): Teacher`, `updateTeacher(Teacher $teacher, array $validated): Teacher`, `deletionBlockers(Teacher $teacher): list<string>`, dan `deleteTeacher(Teacher $teacher): void`.
* **Verifikasi Test:**
  * `vendor/bin/sail artisan test --compact tests/Feature/Students tests/Feature/Teachers`

---

### 11. `App\Http\Controllers\Timetable\ClassTimetableController` (212 baris) & `App\Http\Controllers\Staff\ShiftRosterController` (201 baris)

* **Kondisi Saat Ini:**
  * **`ClassTimetableController`** memiliki method `edit()` yang merakit opsi mapel/semester dan private method `checkPlacement()` (40 baris) yang memanggil `TimetableValidator::errors()` dan `conflicts()`, melempar `ValidationException`, serta merakit respons 409. Selain itu `copy()` memvalidasi semester secara inline di controller.
  * **`ShiftRosterController`** memiliki method `index()` yang membangun matriks 2 dimensi `(employees × dates)` secara manual menggunakan nested loop `resolveMany()`, serta method `bulk()`, `set()`, `clear()`, `resolveRange()`, dan `datesBetween()` langsung di controller.
* **Rencana Ekstraksi:**
  1. **Domain Timetable (`ClassTimetableController` → Target ~95 baris):**
     * Perluas **`App\Services\Timetable\TimetableView`**:
       * Tambahkan `getEditorViewData(Request $request, SchoolClass $schoolClass): array` untuk merakit seluruh payload `timetable/editor`.
     * Buat **`App\Services\Timetable\TimetableSlotService`** (atau perluas service timetable):
       * Pindahkan pengecekan `validateAndCheckConflicts(SchoolClass $schoolClass, array $attributes, ?TimetableSlot $slot): list<array<string, mixed>>` yang melempar `ValidationException` bila ada *hard error* dan mengembalikan daftar *soft conflicts*.
       * Pindahkan validasi tahun aktif & kepemilikan semester pada operasi `copySemesterSlots(SchoolClass $schoolClass, int $semesterId): array{copied: int, skipped: list<mixed>}`.
  2. **Domain Shift Roster (`ShiftRosterController` → Target ~80 baris):**
     * Buat **`App\Services\Staff\ShiftRosterViewService`**:
       * Pindahkan `getRosterGrid(string $weekInput, string $monthInput): array` beserta helper `resolveRange()` dan `datesBetween()` untuk merakit matriks `rows`, `shifts`, `view`, dan `today`.
     * Buat **`App\Services\Staff\ShiftRosterService`**:
       * Pindahkan mutasi `bulkUpsert(array $employeeIds, list<string> $dates, ?int $shiftId): void`, `setCell(int $employeeId, string $date, ?int $shiftId): void`, dan `clearCell(int $employeeId, string $date): void`.
* **Verifikasi Test:**
  * `vendor/bin/sail artisan test --compact tests/Feature/Timetable tests/Feature/Staff`

---

## Urutan & Prosedur Eksekusi

Setiap item pada Fase 2 dieksekusi secara berurutan dengan prosedur disiplin yang sama:
1. Buat feature branch dari `main` (`refactor/<nama-controller>`).
2. Jalankan test suite spesifik domain sebelum perubahan untuk memastikan *baseline* hijau.
3. Buat/perluas class Service/Builder/Action dengan tipe parameter & PHPDoc array-shape yang presisi.
4. Refactor controller agar hanya mengoordinasikan HTTP request, otorisasi, dan respon.
5. Jalankan verifikasi wajib:
   * `vendor/bin/sail artisan test --compact <path_test>`
   * `vendor/bin/sail bin pint --dirty --format agent`
   * `vendor/bin/sail bin phpstan analyse --memory-limit=1G <changed_files>`
   * `vendor/bin/sail npm run check && vendor/bin/sail npm run types:check`
6. Commit pada feature branch, merge `--ff-only` ke `main`, hapus branch, perbarui status dokumen, dan push ke `origin/main`.
