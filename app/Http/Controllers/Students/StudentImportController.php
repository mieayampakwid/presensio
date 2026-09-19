<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\PreviewStudentImportRequest;
use App\Http\Requests\Students\RunStudentImportRequest;
use App\Http\Requests\Students\StoreStudentImportRequest;
use App\Models\StudentImportMapping;
use App\Services\StudentImport\ColumnMapper;
use App\Services\StudentImport\CsvRowReader;
use App\Services\StudentImport\ImportOptions;
use App\Services\StudentImport\ImportResult;
use App\Services\StudentImport\RowReader;
use App\Services\StudentImport\StudentImportService;
use App\Services\StudentImport\XlsxRowReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class StudentImportController extends Controller
{
    private const DISK = 'local';

    private const DIRECTORY = 'student-imports';

    /**
     * Show the upload form.
     */
    public function create(): Response
    {
        return Inertia::render('students/import/upload');
    }

    /**
     * Accept the upload, check for a remembered column mapping, and either
     * jump straight to the dry-run or to the mapping screen.
     */
    public function store(StoreStudentImportRequest $request, StudentImportService $service): Response
    {
        $extension = strtolower((string) $request->file('file')?->getClientOriginalExtension());
        $token = bin2hex(random_bytes(20));

        $stored = $request->file('file')->storeAs(self::DIRECTORY, $token.'.'.$extension, self::DISK);

        if ($stored === false) {
            throw new RuntimeException('Could not persist the import upload.');
        }

        $path = $this->tempPath($token, $extension);
        $reader = $this->readerFor($path, $extension);

        $fingerprint = $reader->fingerprint();
        $remembered = StudentImportMapping::query()->where('header_fingerprint', $fingerprint)->first();

        if ($remembered !== null) {
            $mapping = $remembered->mapping['columns'];
            $options = $remembered->mapping['options'];

            $result = $service->preview($reader, $mapping, ImportOptions::fromArray($options));
            $reader->close();

            return $this->renderPreview($token, $extension, $mapping, $options, $result);
        }

        $headers = $reader->headers();
        $sampleRows = [];
        $count = 0;

        foreach ($reader->rows() as $row) {
            if ($count++ >= 5) {
                break;
            }

            $sampleRows[] = $row;
        }

        $reader->close();

        return Inertia::render('students/import/map', [
            'token' => $token,
            'extension' => $extension,
            'headers' => $headers,
            'sampleRows' => $sampleRows,
            'guessedMapping' => (new ColumnMapper)->map($headers),
            'autoCreateClasses' => $request->boolean('auto_create_classes'),
        ]);
    }

    /**
     * Dry-run the current mapping and show the outcome.
     */
    public function preview(PreviewStudentImportRequest $request, StudentImportService $service): Response
    {
        $extension = $this->extensionFor($request->string('token')->toString());
        $reader = $this->readerFor($this->tempPath($request->string('token')->toString(), $extension), $extension);

        $result = $service->preview($reader, $request->columnMapping(), $request->importOptions());
        $reader->close();

        return $this->renderPreview(
            $request->string('token')->toString(),
            $extension,
            $request->columnMapping(),
            $request->importOptions()->toArray(),
            $result,
        );
    }

    /**
     * Commit the import, remember the mapping, and clean up the temp file.
     */
    public function run(RunStudentImportRequest $request, StudentImportService $service): RedirectResponse
    {
        $token = $request->string('token')->toString();
        $extension = $this->extensionFor($token);
        $path = $this->tempPath($token, $extension);
        $reader = $this->readerFor($path, $extension);

        $result = $service->run($reader, $this->fingerprintFor($path, $extension), $request->columnMapping(), $request->importOptions());
        $reader->close();

        Storage::disk(self::DISK)->delete($this->relativePath($token, $extension));

        $message = $result->errors === []
            ? "Imported {$result->validCount} students."
            : 'Imported '.$result->validCount.' students. '.count($result->errors).' rows had errors.';

        Inertia::flash('toast', [
            'type' => $result->errors === [] ? 'success' : 'warning',
            'message' => $message,
        ]);

        return to_route('students.index');
    }

    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, mixed>  $options
     */
    private function renderPreview(string $token, string $extension, array $mapping, array $options, ImportResult $result): Response
    {
        return Inertia::render('students/import/preview', [
            'token' => $token,
            'extension' => $extension,
            'mapping' => $mapping,
            'options' => $options,
            'validCount' => $result->validCount,
            'errors' => $result->errors,
            'rows' => $result->rows,
        ]);
    }

    private function readerFor(string $path, string $extension): RowReader
    {
        if ($extension === 'xlsx') {
            return new XlsxRowReader($path);
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            abort(419, 'This import session has expired — upload the file again.');
        }

        return new CsvRowReader($handle);
    }

    private function fingerprintFor(string $path, string $extension): string
    {
        $reader = $this->readerFor($path, $extension);
        $fingerprint = $reader->fingerprint();
        $reader->close();

        return $fingerprint;
    }

    private function tempPath(string $token, string $extension): string
    {
        $path = Storage::disk(self::DISK)->path($this->relativePath($token, $extension));

        if (! file_exists($path)) {
            abort(419, 'This import session has expired — upload the file again.');
        }

        return $path;
    }

    private function extensionFor(string $token): string
    {
        foreach (['xlsx', 'csv'] as $extension) {
            if (Storage::disk(self::DISK)->exists($this->relativePath($token, $extension))) {
                return $extension;
            }
        }

        abort(419, 'This import session has expired — upload the file again.');
    }

    private function relativePath(string $token, string $extension): string
    {
        return self::DIRECTORY.'/'.$token.'.'.$extension;
    }
}
