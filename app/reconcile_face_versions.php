<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/database.php';

$delete = in_array('--delete', $argv, true);
$graceHours = 24;
foreach ($argv as $argument) {
    if (preg_match('/^--grace-hours=(\d{1,4})$/', $argument, $match) === 1) { $graceHours = min(8760, (int) $match[1]); }
}
$facesRoot = private_storage_path('faces');
if (!is_dir($facesRoot) || is_link($facesRoot)) { fwrite(STDERR, "Direktori faces tiada atau tidak selamat.\n"); exit(1); }
$rootReal = realpath($facesRoot);
if ($rootReal === false) { fwrite(STDERR, "Direktori faces tidak dapat disahkan.\n"); exit(1); }

$references = [];
$result = db()->query("SELECT s.student_id,s.face_dataset_version,COUNT(f.id) image_count FROM student s LEFT JOIN face_image f ON f.student_id=s.student_id AND f.dataset_version=s.face_dataset_version WHERE s.face_dataset_version IS NOT NULL GROUP BY s.student_id,s.face_dataset_version");
while ($row = $result->fetch_assoc()) {
    $key = $row['student_id'] . '/' . $row['face_dataset_version'];
    $references[$key] = (int) $row['image_count'];
    if ((int) $row['image_count'] !== 6) { fwrite(STDOUT, "CURRENT_METADATA_INCOMPLETE {$key} rows={$row['image_count']}\n"); }
}

function safe_remove_version_directory(string $path, string $rootReal): bool
{
    $real = realpath($path);
    if ($real === false || !str_starts_with($real, $rootReal . DIRECTORY_SEPARATOR) || is_link($path) || !is_dir($path)) { return false; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_link($child)) { return false; }
        if (is_dir($child)) {
            if (!safe_remove_version_directory($child, $rootReal)) { return false; }
        } elseif (!is_file($child) || !@unlink($child)) { return false; }
    }
    return @rmdir($path);
}

$orphans = 0; $removed = 0; $unsafe = 0; $cutoff = time() - ($graceHours * 3600);
foreach (scandir($facesRoot) ?: [] as $studentId) {
    if ($studentId === '.' || $studentId === '..') { continue; }
    $studentPath = $facesRoot . DIRECTORY_SEPARATOR . $studentId;
    if (is_link($studentPath) || !is_dir($studentPath) || preg_match('/^[A-Z0-9][A-Z0-9_-]{1,29}$/', $studentId) !== 1) { fwrite(STDOUT, "UNSAFE_OR_UNKNOWN {$studentId}\n"); $unsafe++; continue; }
    foreach (scandir($studentPath) ?: [] as $version) {
        if ($version === '.' || $version === '..') { continue; }
        $path = $studentPath . DIRECTORY_SEPARATOR . $version;
        if (is_link($path) || !is_dir($path) || preg_match('/^[a-z0-9][a-z0-9-]{7,63}$/', $version) !== 1) { fwrite(STDOUT, "UNSAFE_OR_LEGACY {$studentId}/{$version}\n"); $unsafe++; continue; }
        $key = $studentId . '/' . $version;
        if (isset($references[$key])) { fwrite(STDOUT, "CURRENT {$key} rows={$references[$key]}\n"); continue; }
        $orphans++; $ageEligible = ((int) @filemtime($path)) <= $cutoff;
        fwrite(STDOUT, 'UNREFERENCED ' . $key . ($ageEligible ? '' : ' (within grace period)') . "\n");
        if ($delete && $ageEligible) {
            if (safe_remove_version_directory($path, $rootReal)) { fwrite(STDOUT, "REMOVED {$key}\n"); $removed++; }
            else { fwrite(STDOUT, "REFUSED_REMOVE {$key}\n"); $unsafe++; }
        }
    }
}
fwrite(STDOUT, sprintf("Mode=%s current=%d unreferenced=%d removed=%d unsafe=%d grace_hours=%d\n", $delete?'delete':'dry-run', count($references), $orphans, $removed, $unsafe, $graceHours));
exit($unsafe > 0 ? 2 : 0);
