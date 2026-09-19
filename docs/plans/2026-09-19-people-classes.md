# Spec 02 — People & Classes: Implementation Plan

## Context

Spec 01 (auth + admin user CRUD) shipped 2026-09-19 from `docs/plans/2026-09-18-auth.md`; 58 tests green. Spec 02 (`specs/02-people-classes.md`) is next in the build order (MOC: 01→02→03…): master data for teachers, guardians, students, classes + enrollment, RFID cards, guardian contact self-service, the user↔profile linkage deferred from spec 01, and the column-mapping bulk student importer.

User decisions locked during planning:
- **openspout** composer package approved → importer reads CSV **and** XLSX (spec: "reads any CSV/XLSX").
- **One plan** covering all of spec 02 (mirrors spec 01's fully-executed single plan doc).

**Execution convention:** at implementation start, copy this plan to `docs/plans/2026-09-19-people-classes.md` (repo convention, cf. commit `b9eb356`), then execute tasks in order — each is independently testable. Dev DB is PostgreSQL via Sail (`./vendor/bin/sail artisan migrate:fresh`); tests always run on sqlite `:memory:` (phpunit.xml pins) — plain `php artisan test` works without Docker.

**Every task that adds a controller ends with `php artisan wayfinder:generate --with-form`** — without `--with-form`, all `.form()` consumers crash (spec 01 lesson, MOC).

## Decisions baked into this plan

1. Model for `classes` table = **`SchoolClass`** (reserved word); route param `{school_class}`.
2. `guardians.phone_number` gets a **DB unique index** — it is the guardian identity (import dedupe, sibling coalescing); manual CRUD enforces `Rule::unique` so manual and import never diverge.
3. `teacher_number` (NIP/NUPTK, nullable, non-unique) is master data only — never synced/compared to `users.username`.
4. **No DB-level FK constraints** — `foreignId(...)->nullable()->index()` only (matches existing schema); integrity via app-level guards + transactional cleanup.
5. Homeroom 1:1 via `config/school.php` → `'allow_multiple_homerooms' => env(..., false)`; DB allows duplicate `classes.teacher_id`; validation enforces. Tests flip with `config([...])`.
6. **Real deletes** for master data (users keep deactivate-only). Blocked deletes flash an **error** toast + `back()`. Blocker checks written as an extensible list — spec 03 appends its attendance-history check.
7. Profile delete blocked while user-linked; guardian delete detaches pivot; student delete detaches pivot **and returns RFID cards to spare pool** (`student_id = null`).
8. Importer: **synchronous**, service cluster under `app/Services/StudentImport/`, 2-phase with persisted temp file (`storage/app/student-imports/{token}.csv`), no queues, no session bloat. `student_import_mappings` table (beyond spec, flagged) keyed by `header_fingerprint` = sha256 of normalized headers; stores mapping + options JSON.
9. Import per-row policy: `full_name` required; `dob` must parse (day-first: `dd/mm/yyyy`, `dd-mm-yyyy`, ISO, Excel serials 20000–60000); `student_number` unique in-file + DB when present; `class` column must be mapped, values resolve or auto-create per option; guardian rows need **name + phone** (phone = dedupe key), phone-only links existing guardian. Partial import: valid rows commit, bad rows reported.
10. Identifiers never numeric-cast (leading zeros, 16-digit NIK survive as strings). CSV parser sniffs delimiter (`,` `;` `\t`), strips BOM, converts Windows-1252→UTF-8.
11. User↔profile link: `user_id` lives on profiles (nullable unique); the users create/edit form gets a "Linked profile" select of unlinked profiles matching the selected role; **changing role clears the old link** unless a new profile is chosen (linker detaches-then-attaches in a transaction). Role `parent` maps to `guardians`.
12. Guardian self-service: section on `settings/profile`, own PUT route guarded `role:parent`; edits only `phone_number`/`address`/`work`.
13. Auth = route group `role:admin` + `FormRequest::authorize()` (defense in depth); no Gates/Policies. Every bound route gets `->missing(fn () => to_route('<index>'))`.
14. Frontend mirrors `users/*` verbatim: shared per-entity form component (Inertia `<Form {...action.form()}>`, uncontrolled inputs, `InputError`, **native select/checkbox only**), hand-rolled index table, prev/next pagination, server-flash toasts, Dialog-confirm deletes (passkey-item pattern), `PageName.layout = { breadcrumbs }` on every new page.
15. QA gates per task: `vendor/bin/pint --dirty --format agent`, `composer types:check` (larastan), narrow `php artisan test --compact <path>`, `npm run types:check`, `npm run build` at the end.

## Tasks

### Task 1 — Schema, models, factories, school config
Create `config/school.php` (Decision 5). Migrations in dependency order `2026_09_19_00000N_*`:
`teachers` (user_id nullable+unique, name, teacher_number nullable, phone_number nullable) → `guardians` (user_id nullable+unique, name, phone_number **unique**, work nullable, address text nullable) → `classes` (name, teacher_id nullable index) → `students` (user_id nullable+unique, full_name, nickname nullable, dob date, student_number nullable unique, class_id nullable index) → `guardian_student` (composite PK, both FKs indexed) → `rfid_cards` (rfid_number unique, student_id nullable index) → `student_import_mappings` (header_fingerprint unique, mapping json).

Models `app/Models/{Teacher,Guardian,Student,SchoolClass,RfidCard,StudentImportMapping}.php` mirroring `User.php` (`#[Fillable]`, `@property` docblocks, `casts()`; `Student::schoolClass()` belongsTo, `Student::guardians()` belongsToMany, `SchoolClass::students()`/`teacher()`, `User` gains `teacher()/guardian()/student()` hasOne in Task 6). Factories `database/factories/*Factory.php` in `UserFactory` style — `GuardianFactory` phone `fake()->unique()->e164PhoneNumber()`, `StudentFactory::unnumbered()`, `RfidCardFactory::assigned($student)/spare()`, `SchoolClassFactory::withTeacher()`. Unit tests `tests/Unit/Models/*Test.php` (defaults, relations, casts).

**Verify:** `sail artisan migrate:fresh` + `php artisan test --compact tests/Unit/Models` + pint + `composer types:check`.

### Task 2 — Classes CRUD + homeroom validation + deletion constraint
`app/Http/Controllers/Classes/SchoolClassController.php`, `app/Http/Requests/Classes/*`, `routes/classes.php` (+ require in `routes/web.php`), `resources/js/pages/classes/{index,create,edit,class-form}.tsx`, `tests/Feature/Classes/ClassManagementTest.php`. First full vertical — all later CRUDs mirror it byte-for-byte (routes shape from `routes/users.php` + `destroy`).

- Index: search over `name` + `orWhereHas('teacher', name)`; standard pagination; `create()/edit()` pass `Teacher::orderBy('name')->get(['id','name'])`.
- Rules: `name` required max:255; `teacher_id` nullable exists. **Homeroom 1:1 in `after()`**: skip when `config('school.allow_multiple_homerooms')` or teacher_id empty; else reject when `SchoolClass::where('teacher_id', $id)->when($target, whereKeyNot)->exists()` → error on `teacher_id`.
- `destroy()` via `deletionBlockers(): list<string>` (students enrolled → block; commented slot for spec 03 attendance) → error flash + `back()`, else delete + success flash.
- Tests: index/search, 403 DataProvider, create ±teacher, homeroom rejection + config-flip pass, self-ignore on update, destroy blocked/allowed, duplicate class name allowed.

### Task 3 — Teachers CRUD
Same skeleton under `app/Http/Controllers/Teachers/` + `routes/teachers.php` + `resources/js/pages/teachers/*` + `tests/Feature/Teachers/TeacherManagementTest.php`. Fields: name (required), teacher_number (nullable, free-form), phone_number (nullable). `destroy()` blockers: homerooms a class; linked user account. Index searches name/teacher_number/phone.

### Task 4 — Guardians CRUD
Same skeleton. Fields: name (required), phone_number (required + `Rule::unique` ignore-self, max:32), work (nullable), address (nullable). `edit()` shows linked students read-only. `destroy()` blocker: linked user; else transactional pivot detach + delete. Index searches name/phone_number.

### Task 5 — Students CRUD (class assignment + guardian linking)
Same skeleton. Rules: `full_name` required, `nickname` nullable, `dob` required date `before:today`, `student_number` nullable unique ignore-self, `class_id` nullable exists, `guardian_ids` array of existing guardian ids. `store()/update()`: create/update from `safe()->except(['guardian_ids'])` then `$student->guardians()->sync($request->validated('guardian_ids', []))` — enrollment replacement is inherent (plain `class_id` update; attendance history untouched, spec §3). Form: native select for class, `<input type="date">`, **checkbox group** `guardian_ids[]` (native inputs only). `edit()` passes classes, guardians (id/name/phone), current `guardian_ids` pluck. `destroy()` blockers: linked user; else detach pivot + `RfidCard::where('student_id',...)->update(['student_id'=>null])` + delete.

### Task 6 — User↔profile linkage on users forms (spec 01 req 4)
Modify `UserController` (create/store/edit/update), both user FormRequests, `User` model (+3 hasOne), `resources/js/pages/users/*`; create `app/Services/UserProfileLinker.php`; test `tests/Feature/Users/UserProfileLinkingTest.php`.

- `UserProfileLinker::sync(User, role, ?profileId)`: transaction — null out any profile row pointing at this user across teachers/guardians/students, then attach selected profile for the role map `teacher→Teacher, parent→Guardian, student→Student`. Sole writer of link state.
- Requests gain `profile_id => ['nullable','integer']` + `after()` check: profile exists in the role-matching table AND (`user_id IS NULL` OR already this user's on update); role admin + profile → error.
- Controller `profileOptions()`: per role, unlinked `{id,label}` lists + currently-linked profile injected; `store()/update()` wrap in transaction with linker.
- `user-form.tsx`: "Linked profile" native select whose options derive from **controlled role state** (useState + onChange — only interactive element in the form).
- Tests: link on store; already-linked profile → error; admin+profile → error; role change clears old link; role change + new profile in one request; re-submit same profile stays linked (ignore-self); wrong-table profile → error.

### Task 7 — RFID card management
Standard CRUD under `app/Http/Controllers/RfidCards/` + `routes/rfid-cards.php` + pages + `tests/Feature/RfidCards/RfidCardManagementTest.php`. Rules: `rfid_number` required unique ignore-self; `student_id` nullable exists. Extra dotted sub-action `PUT rfid-cards/{rfid_card}/revoke` → `student_id = null` + "Card revoked." toast. Index searches `rfid_number` + student name (`whereHas`); Badge Assigned/Spare. Row actions: Edit, Revoke (when assigned), Delete (Dialog confirm).

### Task 8 — Guardian contact self-service (spec §9 / spec 01 req 6)
Create `app/Http/Controllers/Settings/GuardianContactController.php` (update-only) + `UpdateGuardianContactRequest`; route `PUT settings/contact` → `guardian-contact.update` guarded `role:parent` in `routes/settings.php`. `ProfileController::edit()` passes `guardian` prop (`$request->user()?->guardian`). `settings/profile.tsx` renders a Contact section only when `guardian` is non-null: phone/work/address inputs, no name/relations. Request `authorize()`: role Parent **and** guardian linked; rules mirror Task 4 (unique ignore-self). Tests: parent edits own fields; uniqueness vs other guardians; posted `name` ignored; other roles 403; parent without linked guardian 403.

### Task 9 — Bulk import engine (`app/Services/StudentImport/`)
`RowReader` (interface: `headers(): array`, `rows(): Generator`, `fingerprint(): string`, `close(): void`), `CsvRowReader` (delimiter sniff, BOM strip, Windows-1252→UTF-8, all cells trimmed strings — never numeric-cast), `XlsxRowReader` (openspout, first sheet, stringified cells), `ColumnDictionary` (seeded Indonesian alias map: `full_name`, `nickname`, `dob`, `student_number`, `class`, `guardian_{name,phone,work,address}` — e.g. `dob`: `['tgl lahir','tanggal lahir','tgl lhr','tanggal kelahiran','birth date']`), `ColumnMapper` (pass 1 exact/prefix on normalized headers, pass 2 fuzzy `similar_text ≥80%` / Levenshtein ≤2, one column consumed once), `DateNormalizer` (Excel serials 20000–60000 via 1899-12-30 epoch; then `!d/m/Y`, `d/m/Y`, `d-m-Y`, `Y-m-d`; year ≥1900, not future), `RowValidator` (per-row policy from Decision 9, in-run seen-sets + class map + staged guardians in `ImportContext`), `StudentImportService` (`preview()` dry-run / `run()` commit-in-transaction: student create, guardian `firstOrCreate(['phone_number'])` + `syncWithoutDetaching`, auto-created classes inside the tx), `ImportResult` (valid rows, per-row errors with row numbers).

Mapping persistence on successful `run()`: `StudentImportMapping::updateOrCreate(['header_fingerprint' => ...], ['mapping' => ['columns' => ..., 'options' => ...]])`.

Tests: unit `DateNormalizerTest` / `ColumnMapperTest` / `CsvRowReaderTest` (semicolon, BOM, cp1252, `"007"`, 16-digit NIK, fingerprint stability, exact `"TGL LHR "` → dob) + feature `StudentImportServiceTest` (dry-run writes nothing; partial import; in-file + in-DB NIS dupes; class missing/auto-create; sibling guardian dedupe → one guardian two links; phone-only links existing; mapping persisted).

### Task 10 — Import HTTP layer + UI
`app/Http/Controllers/Students/StudentImportController.php` + three FormRequests; routes in `routes/students.php`: `students.import.create/store/preview/run`. Pages `resources/js/pages/students/import/{upload,map,preview}.tsx` + "Import students" button on `students/index.tsx`; all with breadcrumbs.

Flow: `create()` renders upload (file input + auto-create-classes checkbox). `store()` accepts **`.csv`, `.txt`, `.xlsx`** (openspout per user decision; legacy `.xls` rejected with "save as .xlsx or CSV"); builds the right `RowReader` by extension; fingerprint hit in `student_import_mappings` → render dry-run directly with stored mapping (skip mapping screen); miss → `map` page (headers, guessed mapping as per-field native selects with "— ignored —", first 5 sample rows, options). `preview()` validates `mapping.full_name` + `mapping.class` are mapped, runs dry-run, renders summary + first 25 valid rows + per-row errors + hidden token/mapping/options. `run()` re-runs with commit (cheap at this scale, avoids stale state), persists mapping, deletes temp file, flashes `Imported X students. Y rows had errors.` (success/warning), redirects to `students.index`.

Temp file: `storage/app/student-imports/{token}.csv|utf8.csv` (normalized copy so re-validation is byte-identical); token = random 40-char, hidden field carried through map/preview.

Tests (`StudentImportHttpTest`, `UploadedFile::fake()->createWithContent()`): fresh headers → map page; identical re-upload → dry-run directly; unmapped `full_name` → `mapping.full_name` error; per-row errors surfaced; partial commit + temp file deleted; non-admin 403; `.xls` rejected; `.xlsx` happy path (fixture written in-test via openspout writer).

### Task 11 — Navigation, breadcrumbs, final sweep
Sidebar: extend the `role === 'admin'` conditional spread — Teachers, Guardians, Students, Classes, RFID Cards (Wayfinder route fn imports; leave `/users` literal as-is). Breadcrumb sweep: every new page declares `.layout`. Final gates:

```bash
./vendor/bin/sail artisan migrate:fresh --no-interaction
vendor/bin/pint --dirty --format agent
composer types:check
php artisan test              # full suite
php artisan wayfinder:generate --with-form
npm run types:check && npm run check && npm run build
```

Manual smoke (sail app at http://localhost, admin): teacher → class with that teacher → guardian → student with class + 2 guardians; second class for same teacher rejected; class delete blocked; upload `;`-delimited BOM CSV with leading-zero NIS + `dd/mm/yyyy` → fix a bad row from the error list → import; two kids one guardian; parent user edits contact on Profile.

## Spec coverage checklist

| Spec 02 requirement | Task |
| --- | --- |
| §Schema teachers/guardians/students/classes/pivot/rfid_cards | 1 |
| `student_import_mappings` (beyond spec) | 1, 9, 10 |
| §4 Homeroom via `allow_multiple_homerooms` | 2 |
| §1 CRUD screens (Teachers/Guardians/Students/Classes) | 3 / 4 / 5 / 2 |
| §2 Assign class + link guardians on student form | 5 |
| §3 Enrollment replaces class; history intact | 5 |
| §5 Class deletion constraints (+ spec 03 hook) | 2 |
| §6 RFID register/assign/revoke | 7 |
| §7 Search + pagination everywhere | 2–5, 7 |
| §8 Import: alias dictionary, fuzzy map, override, remembered mapping, dry-run, partial commit, auto-create classes, defensive parsing, guardian dedupe | 9, 10 |
| §8 XLSX input | 9 (`XlsxRowReader`), 10 |
| §9 Guardian contact self-service | 8 |
| Spec 01 req 4: user↔profile linking + role-change clearing | 6 |
| Spec 01 req 7: admin-only access | route groups + `authorize()` (2–8, 10) |
| Sidebar nav | 11 |

## Risks
- Excel serial false positives in `dob` columns → mitigated by the 20000–60000 numeric-only guard.
- Day-first hard assumption (documented; no locale detection).
- Wayfinder arg keys for `{school_class}`/`{rfid_card}` — confirm camelCase in generated modules before wiring pages.
- Profile dropdown payload on users forms — fine at single-school scale; swap to a search endpoint only if it hurts.
