<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
require_auth(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(['success' => false, 'message' => 'Kaedah permintaan tidak dibenarkan.'], 405);
}
require_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 6_000_000) {
    json_response(['success' => false, 'message' => 'Saiz permintaan melebihi had 6 MB.'], 413);
}

try {
    $data = json_decode((string) file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Data JSON tidak sah.');
    }
    $operation = $data['operation'] ?? null;
    if (!is_string($operation) || !in_array($operation, ['create', 'replace'], true)) {
        throw new InvalidArgumentException('Operasi mesti create atau replace.');
    }
    $studentId = require_student_id($data['student_id'] ?? '');
    $name = require_text($data['student_name'] ?? '', 'Nama murid', 2, 100);
    $class = require_text($data['student_class'] ?? '', 'Kelas', 1, 50);
    $images = $data['images'] ?? null;
    if (!is_array($images) || count($images) !== 6) {
        throw new InvalidArgumentException('Enam gambar wajah diperlukan.');
    }
    if ($operation === 'replace' && ($data['replace_confirmed'] ?? null) !== true) {
        throw new InvalidArgumentException('Pengesahan penggantian enam imej diperlukan.');
    }

    $root = private_storage_path();
    $tmpRoot = private_storage_path('tmp');
    $facesRoot = private_storage_path('faces');
    foreach ([$root, $tmpRoot, $facesRoot] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Private storage creation failed.');
        }
        if (is_link($directory)) {
            throw new RuntimeException('Unsafe private storage symlink.');
        }
    }

    $tempDir = $tmpRoot . DIRECTORY_SEPARATOR . 'register_' . bin2hex(random_bytes(12));
    if (!mkdir($tempDir, 0750)) {
        throw new RuntimeException('Temporary face directory creation failed.');
    }
    try {
        $metadata = stage_face_images($images, $tempDir);
        $version = bin2hex(random_bytes(16));
        if ($operation === 'create') {
            commit_new_student_dataset(db(), $studentId, $name, $class, $version, $metadata, $tempDir, $facesRoot);
            json_response(['success' => true, 'operation' => 'create', 'message' => 'Murid dan enam gambar berjaya disimpan. Profil menunggu validasi kiosk.', 'face_status' => 'pending', 'dataset_version' => $version], 201);
        }
        commit_replacement_dataset(db(), $studentId, $name, $class, $version, $metadata, $tempDir, $facesRoot, ($data['ready_replace_confirmed'] ?? null) === true);
        json_response(['success' => true, 'operation' => 'replace', 'message' => 'Versi imej baharu telah diaktifkan. Profil menunggu validasi kiosk.', 'face_status' => 'pending', 'dataset_version' => $version]);
    } catch (Throwable $exception) {
        if (is_dir($tempDir) && !is_link($tempDir)) {
            remove_face_tree($tempDir);
        }
        throw $exception;
    }
} catch (mysqli_sql_exception $exception) {
    error_log('Student face database error: ' . $exception->getMessage());
    $duplicate = $exception->getCode() === 1062;
    json_response(['success' => false, 'message' => $duplicate ? 'ID murid sudah wujud. Gunakan ID lain atau pilih Ambil semula imej.' : 'Data murid tidak dapat disimpan.'], $duplicate ? 409 : 500);
} catch (UserFacingException $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], $exception->httpStatus());
} catch (JsonException|InvalidArgumentException $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log('Student face operation error: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Operasi imej tidak dapat diselesaikan. Sila cuba lagi atau hubungi pentadbir.'], 500);
}

/** @param array<mixed> $images
 *  @return array<int,array{filename:string,hash:string,width:int,height:int}>
 */
function stage_face_images(array $images, string $tempDir): array
{
    $metadata = [];
    foreach ($images as $index => $image) {
        if (!is_string($image) || preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=\r\n]+)$#', $image, $match) !== 1) {
            throw new InvalidArgumentException('Format gambar ' . ($index + 1) . ' mesti JPEG.');
        }
        $bytes = base64_decode($match[1], true);
        if ($bytes === false || strlen($bytes) < 5_000 || strlen($bytes) > 650_000) {
            throw new InvalidArgumentException('Saiz gambar ' . ($index + 1) . ' tidak sah.');
        }
        $details = @getimagesizefromstring($bytes);
        if (!$details || ($details['mime'] ?? '') !== 'image/jpeg') {
            throw new InvalidArgumentException('Gambar ' . ($index + 1) . ' bukan imej JPEG sebenar.');
        }
        [$width, $height] = [(int) $details[0], (int) $details[1]];
        if ($width < 480 || $height < 360 || $width > 1920 || $height > 1440) {
            throw new InvalidArgumentException('Dimensi gambar ' . ($index + 1) . ' di luar julat 480×360 hingga 1920×1440.');
        }
        $filename = sprintf('%02d.jpg', $index + 1);
        $path = $tempDir . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('Incomplete face image write.');
        }
        @chmod($path, 0640);
        $metadata[] = ['filename' => $filename, 'hash' => hash('sha256', $bytes), 'width' => $width, 'height' => $height];
    }
    if (count(array_unique(array_column($metadata, 'hash'))) !== 6) {
        throw new InvalidArgumentException('Setiap gambar mesti berbeza. Ambil enam sudut wajah yang berlainan.');
    }
    return $metadata;
}

function prepare_version_directory(string $facesRoot, string $studentId, string $version, string $tempDir): string
{
    $studentDir = $facesRoot . DIRECTORY_SEPARATOR . $studentId;
    if (!is_dir($studentDir) && !mkdir($studentDir, 0750) && !is_dir($studentDir)) {
        throw new RuntimeException('Student face directory creation failed.');
    }
    if (is_link($studentDir)) {
        throw new RuntimeException('Unsafe student face directory symlink.');
    }
    $versionDir = $studentDir . DIRECTORY_SEPARATOR . $version;
    if (path_exists($versionDir) || !@rename($tempDir, $versionDir)) {
        throw new RuntimeException('Immutable face version finalization failed.');
    }
    return $versionDir;
}

/** @param array<int,array{filename:string,hash:string,width:int,height:int}> $metadata */
function commit_new_student_dataset(mysqli $connection, string $studentId, string $name, string $class, string $version, array $metadata, string $tempDir, string $facesRoot): void
{
    $versionDir = prepare_version_directory($facesRoot, $studentId, $version, $tempDir);
    $committed = false;
    try {
        $connection->begin_transaction();
        $stmt = $connection->prepare('INSERT INTO student(student_id,name,class,face_dataset_version) VALUES(?,?,?,?)');
        $stmt->bind_param('ssss', $studentId, $name, $class, $version);
        $stmt->execute();
        insert_face_image_metadata($connection, $studentId, $version, $metadata);
        $connection->commit();
        $committed = true;
    } catch (Throwable $exception) {
        try { $connection->rollback(); } catch (Throwable) { }
        throw $exception;
    } finally {
        if (!$committed && !remove_face_tree($versionDir)) {
            error_log('Uncommitted new face version cleanup failed: ' . $versionDir);
        }
    }
}

/** @param array<int,array{filename:string,hash:string,width:int,height:int}> $metadata */
function commit_replacement_dataset(mysqli $connection, string $studentId, string $name, string $class, string $version, array $metadata, string $tempDir, string $facesRoot, bool $readyConfirmed): void
{
    $versionDir = null;
    $oldVersion = null;
    $committed = false;
    $connection->begin_transaction();
    try {
        $stmt = $connection->prepare('SELECT name,class,face_status,face_dataset_version FROM student WHERE student_id=? FOR UPDATE');
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        if (!$student) {
            throw new UserFacingException('Murid yang dipilih tidak lagi wujud. Muat semula halaman.', 409);
        }
        if ((string) $student['name'] !== $name || (string) $student['class'] !== $class) {
            throw new UserFacingException('Identiti murid telah berubah. Muat semula halaman sebelum mengganti imej.', 409);
        }
        if ($student['face_status'] === 'ready' && !$readyConfirmed) {
            throw new InvalidArgumentException('Pengesahan khusus diperlukan untuk menggantikan profil yang sedang Sedia.');
        }
        $oldVersion = (string) ($student['face_dataset_version'] ?? '');
        if ($oldVersion === '') {
            throw new UserFacingException('Profil wajah semasa belum mempunyai versi dataset yang sah.', 409);
        }
        verify_current_face_set($connection, $studentId, $oldVersion, $facesRoot);
        $versionDir = prepare_version_directory($facesRoot, $studentId, $version, $tempDir);
        insert_face_image_metadata($connection, $studentId, $version, $metadata);
        $update = $connection->prepare("UPDATE student SET face_dataset_version=?,face_status='pending',face_validated_at=NULL,face_validation_message=NULL,usable_face_samples=0 WHERE student_id=?");
        $update->bind_param('ss', $version, $studentId);
        $update->execute();
        $connection->commit();
        $committed = true;
    } catch (Throwable $exception) {
        try { $connection->rollback(); } catch (Throwable $rollbackException) { error_log('Face replacement rollback failed: ' . $rollbackException->getMessage()); }
        throw $exception;
    } finally {
        if (!$committed && is_string($versionDir) && !remove_face_tree($versionDir)) {
            error_log('Uncommitted replacement version cleanup failed: ' . $versionDir);
        }
    }

    $oldDir = $facesRoot . DIRECTORY_SEPARATOR . $studentId . DIRECTORY_SEPARATOR . $oldVersion;
    if (!remove_face_tree($oldDir)) {
        error_log('Inactive face version cleanup failed after commit: ' . $oldDir);
    }
}

function verify_current_face_set(mysqli $connection, string $studentId, string $version, string $facesRoot): void
{
    if (preg_match('/^[a-z0-9][a-z0-9-]{7,63}$/', $version) !== 1) {
        throw new RuntimeException('Invalid current face dataset version.');
    }
    $directory = $facesRoot . DIRECTORY_SEPARATOR . $studentId . DIRECTORY_SEPARATOR . $version;
    if (!is_dir($directory) || is_link($directory)) {
        throw new UserFacingException('Set imej wajah semasa tidak lengkap. Jalankan reconciliation sebelum mengganti.', 409);
    }
    $stmt = $connection->prepare('SELECT relative_path,sha256,width,height FROM face_image WHERE student_id=? AND dataset_version=? ORDER BY relative_path FOR UPDATE');
    $stmt->bind_param('ss', $studentId, $version);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if (count($rows) !== 6) {
        throw new UserFacingException('Metadata set imej wajah semasa tidak lengkap.', 409);
    }
    foreach ($rows as $index => $row) {
        $filename = sprintf('%02d.jpg', $index + 1);
        $expected = $studentId . '/' . $version . '/' . $filename;
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        if ((string) $row['relative_path'] !== $expected || !is_file($path) || is_link($path) || !hash_equals((string) $row['sha256'], hash_file('sha256', $path) ?: '')) {
            throw new UserFacingException('Fail atau hash set imej wajah semasa tidak sepadan dengan metadata.', 409);
        }
        $details = @getimagesize($path);
        if (!$details || ($details['mime'] ?? '') !== 'image/jpeg' || (int) $details[0] !== (int) $row['width'] || (int) $details[1] !== (int) $row['height']) {
            throw new UserFacingException('Dimensi set imej wajah semasa tidak sepadan dengan metadata.', 409);
        }
    }
}

/** @param array<int,array{filename:string,hash:string,width:int,height:int}> $metadata */
function insert_face_image_metadata(mysqli $connection, string $studentId, string $version, array $metadata): void
{
    $stmt = $connection->prepare('INSERT INTO face_image(student_id,dataset_version,relative_path,sha256,width,height) VALUES(?,?,?,?,?,?)');
    foreach ($metadata as $meta) {
        $relative = $studentId . '/' . $version . '/' . $meta['filename'];
        $hash = $meta['hash'];
        $width = $meta['width'];
        $height = $meta['height'];
        $stmt->bind_param('ssssii', $studentId, $version, $relative, $hash, $width, $height);
        $stmt->execute();
    }
}

function path_exists(string $path): bool { return file_exists($path) || is_link($path); }
function remove_face_tree(string $directory): bool
{
    if (!file_exists($directory) && !is_link($directory)) { return true; }
    if (is_link($directory) || !is_dir($directory)) { return false; }
    $success = true;
    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $path = $directory . DIRECTORY_SEPARATOR . $entry;
        if (is_link($path)) { $success = false; continue; }
        $success = is_dir($path) ? remove_face_tree($path) && $success : @unlink($path) && $success;
    }
    return $success && @rmdir($directory);
}
