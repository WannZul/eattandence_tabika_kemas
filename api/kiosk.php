<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/errors.php';
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/validation.php';
require_once __DIR__ . '/../app/database.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
if (!request_is_https()) {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $local = app_env() !== 'production' && env_bool('ALLOW_INSECURE_KIOSK_API', false) && in_array($remote, ['127.0.0.1', '::1'], true);
    if (!$local) { json_response(['success' => false, 'message' => 'HTTPS diperlukan untuk API kiosk.'], 400); }
}
$expected = env_value('KIOSK_API_KEY');
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) || $expected === null || strlen($expected) < 24 || !hash_equals($expected, trim($matches[1]))) {
    header('WWW-Authenticate: Bearer realm="eAttendance Kiosk"');
    json_response(['success' => false, 'message' => 'Kunci kiosk tidak sah.'], 401);
}

function face_minimum_samples(): int
{
    $value = filter_var(env_value('FACE_MIN_USABLE_SAMPLES', '4'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 6]]);
    return $value === false ? 4 : (int) $value;
}
function face_fingerprint(array $images): string
{
    return hash('sha256', implode('|', array_map(static fn(array $image): string => (string) $image['id'] . ':' . (string) $image['sha256'], $images)));
}
function current_face_images(mysqli $connection, string $studentId, string $version, bool $lock = false): array
{
    $sql = 'SELECT id,sha256,relative_path,width,height FROM face_image WHERE student_id=? AND dataset_version=? ORDER BY id' . ($lock ? ' FOR UPDATE' : '');
    $stmt = $connection->prepare($sql);
    $stmt->bind_param('ss', $studentId, $version);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
function attendance_payload(array $row): array
{
    return ['date'=>(string)$row['date'],'time'=>$row['time']!==null?(string)$row['time']:null,'status'=>(string)$row['status'],'source'=>(string)$row['source'],'observed_at'=>$row['observed_at']!==null?(string)$row['observed_at']:null,'kiosk_id'=>$row['kiosk_id']!==null?(string)$row['kiosk_id']:null,'recognition_confidence'=>$row['recognition_confidence']!==null?(float)$row['recognition_confidence']:null];
}
function exact_keys(array $data, array $allowed, string $message): void
{
    $keys = array_keys($data); sort($keys); sort($allowed);
    if ($keys !== $allowed) { throw new InvalidArgumentException($message); }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    try {
        $action = (string) ($_GET['action'] ?? 'health');
        if ($action === 'health') {
            json_response(['success'=>true,'service'=>'eAttendance TABIKA KEMAS Kiosk API','version'=>3,'server_time'=>date(DATE_ATOM),'timezone'=>app_timezone(),'minimum_usable_samples'=>face_minimum_samples()]);
        }
        if ($action === 'dataset') {
            $result = db()->query('SELECT s.student_id,s.name,s.class,s.face_dataset_version,s.face_status,s.face_validated_at,s.face_validation_message,s.usable_face_samples,f.id image_id,f.sha256,f.width,f.height FROM student s JOIN face_image f ON f.student_id=s.student_id AND f.dataset_version=s.face_dataset_version ORDER BY s.student_id,f.id');
            $students = [];
            while ($row = $result->fetch_assoc()) {
                $id = (string) $row['student_id'];
                $students[$id] ??= ['student_id'=>$id,'name'=>(string)$row['name'],'class'=>(string)$row['class'],'dataset_version'=>(string)$row['face_dataset_version'],'face_status'=>(string)$row['face_status'],'face_validated_at'=>$row['face_validated_at'],'face_validation_message'=>$row['face_validation_message'],'usable_face_samples'=>(int)$row['usable_face_samples'],'images'=>[]];
                $students[$id]['images'][] = ['id'=>(int)$row['image_id'],'sha256'=>(string)$row['sha256'],'width'=>(int)$row['width'],'height'=>(int)$row['height']];
            }
            foreach ($students as &$student) { $student['dataset_fingerprint'] = face_fingerprint($student['images']); } unset($student);
            json_response(['success'=>true,'generated_at'=>date(DATE_ATOM),'minimum_usable_samples'=>face_minimum_samples(),'students'=>array_values($students)]);
        }
        if ($action === 'image') {
            $id = require_positive_int($_GET['id'] ?? null, 'ID imej');
            $stmt = db()->prepare('SELECT f.relative_path,f.sha256 FROM face_image f JOIN student s ON s.student_id=f.student_id AND s.face_dataset_version=f.dataset_version WHERE f.id=?');
            $stmt->bind_param('i', $id); $stmt->execute(); $image = $stmt->get_result()->fetch_assoc();
            if (!$image) { json_response(['success'=>false,'message'=>'Imej tidak dijumpai.'],404); }
            $root = realpath(private_storage_path('faces'));
            $path = realpath(private_storage_path('faces/' . $image['relative_path']));
            if ($root===false || $path===false || !str_starts_with($path,$root.DIRECTORY_SEPARATOR) || !is_file($path) || is_link($path) || !hash_equals((string)$image['sha256'],hash_file('sha256',$path)?:'')) {
                json_response(['success'=>false,'message'=>'Fail imej tidak tersedia.'],404);
            }
            $etag='"'.$image['sha256'].'"';
            if (($_SERVER['HTTP_IF_NONE_MATCH']??'')===$etag) { http_response_code(304); exit; }
            header('Content-Type: image/jpeg'); header('Content-Length: '.filesize($path)); header('ETag: '.$etag); header('Content-Disposition: attachment; filename="face-'.$id.'.jpg"'); readfile($path); exit;
        }
        json_response(['success'=>false,'message'=>'Tindakan GET tidak sah.'],404);
    } catch (InvalidArgumentException $exception) {
        json_response(['success'=>false,'message'=>$exception->getMessage()],422);
    } catch (Throwable $exception) {
        error_log('Kiosk GET failed: '.$exception->getMessage());
        json_response(['success'=>false,'message'=>'Permintaan kiosk tidak dapat diproses.'],500);
    }
}

if ($method === 'POST') {
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>32768) { json_response(['success'=>false,'message'=>'Payload terlalu besar.'],413); }
    try {
        $data=json_decode((string)file_get_contents('php://input'),true,16,JSON_THROW_ON_ERROR);
        if (!is_array($data)) { throw new InvalidArgumentException('Payload JSON tidak sah.'); }
        $action=(string)($data['action']??'attendance');
        if ($action==='validation') {
            exact_keys($data,['action','student_id','dataset_fingerprint','status','usable_samples','message'],'Medan laporan validasi tidak lengkap atau tidak dibenarkan.');
            $studentId=require_student_id($data['student_id']);
            $fingerprint=strtolower(trim((string)$data['dataset_fingerprint']));
            if (preg_match('/^[a-f0-9]{64}$/',$fingerprint)!==1) { throw new InvalidArgumentException('Cap jari dataset tidak sah.'); }
            $usable=filter_var($data['usable_samples'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>6]]);
            if ($usable===false) { throw new InvalidArgumentException('Bilangan sampel boleh guna tidak sah.'); }
            $expectedStatus=(int)$usable>=face_minimum_samples()?'ready':'invalid';
            if ((string)$data['status']!==$expectedStatus) { throw new InvalidArgumentException('Status validasi tidak sepadan dengan bilangan sampel.'); }
            $message=require_text($data['message'],'Mesej validasi',1,255);
            $connection=db(); $connection->begin_transaction();
            try {
                $stmt=$connection->prepare('SELECT face_dataset_version FROM student WHERE student_id=? FOR UPDATE'); $stmt->bind_param('s',$studentId); $stmt->execute(); $student=$stmt->get_result()->fetch_assoc();
                if (!$student) { throw new InvalidArgumentException('Murid tidak dijumpai.'); }
                $version=(string)($student['face_dataset_version']??'');
                $images=current_face_images($connection,$studentId,$version,true);
                if (count($images)!==6 || !hash_equals(face_fingerprint($images),$fingerprint)) { $connection->rollback(); json_response(['success'=>false,'message'=>'Dataset telah berubah. Segerakkan semula sebelum melapor validasi.'],409); }
                $update=$connection->prepare('UPDATE student SET face_status=?,face_validated_at=NOW(),face_validation_message=?,usable_face_samples=? WHERE student_id=? AND face_dataset_version=?');
                $update->bind_param('ssiss',$expectedStatus,$message,$usable,$studentId,$version); $update->execute(); $connection->commit();
                json_response(['success'=>true,'message'=>'Status validasi wajah dikemas kini.','student_id'=>$studentId,'face_status'=>$expectedStatus,'usable_samples'=>(int)$usable]);
            } catch (Throwable $exception) { try{$connection->rollback();}catch(Throwable){} throw $exception; }
        }
        if ($action!=='attendance') { throw new InvalidArgumentException('Tindakan POST tidak sah.'); }
        exact_keys($data,['action','event_id','student_id','kiosk_id','confidence','observed_at','dataset_fingerprint'],'Medan acara kehadiran tidak lengkap atau tidak dibenarkan.');
        $eventId=strtolower(trim((string)$data['event_id']));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',$eventId)!==1) { throw new InvalidArgumentException('ID acara kiosk tidak sah.'); }
        $studentId=require_student_id($data['student_id']); $kioskId=require_text($data['kiosk_id'],'ID kiosk',2,50);
        $fingerprint=strtolower(trim((string)$data['dataset_fingerprint']));
        if (preg_match('/^[a-f0-9]{64}$/',$fingerprint)!==1) { throw new InvalidArgumentException('Cap jari dataset tidak sah.'); }
        $confidence=filter_var($data['confidence'],FILTER_VALIDATE_FLOAT);
        if ($confidence===false||$confidence<0||$confidence>300) { throw new InvalidArgumentException('Nilai keyakinan tidak sah.'); }
        $observed=DateTimeImmutable::createFromFormat(DATE_ATOM,(string)$data['observed_at']);
        $dateErrors=DateTimeImmutable::getLastErrors();
        if ($observed===false || (is_array($dateErrors)&&($dateErrors['warning_count']>0||$dateErrors['error_count']>0))) { throw new InvalidArgumentException('Masa pemerhatian tidak sah.'); }
        $age=(new DateTimeImmutable('now'))->getTimestamp()-$observed->getTimestamp();
        if ($age < -300 || $age > 86400) { throw new InvalidArgumentException('Masa pemerhatian di luar julat yang dibenarkan.'); }
        $observed=$observed->setTimezone(new DateTimeZone(app_timezone())); $observedAt=$observed->format('Y-m-d H:i:s'); $date=$observed->format('Y-m-d'); $time=$observed->format('H:i:s');

        $connection=db(); $connection->begin_transaction();
        try {
            $find=$connection->prepare('SELECT name,face_status,face_dataset_version FROM student WHERE student_id=? FOR UPDATE'); $find->bind_param('s',$studentId); $find->execute(); $student=$find->get_result()->fetch_assoc();
            if (!$student) { throw new InvalidArgumentException('Murid tidak dijumpai.'); }
            $existing=$connection->prepare('SELECT student_id,kiosk_id,observed_at,confidence,attendance_date,dataset_fingerprint,outcome,rejection_reason FROM kiosk_event WHERE event_id=? FOR UPDATE'); $existing->bind_param('s',$eventId); $existing->execute(); $original=$existing->get_result()->fetch_assoc();
            if ($original) {
                $same=$original['student_id']===$studentId && $original['kiosk_id']===$kioskId && $original['observed_at']===$observedAt && $original['attendance_date']===$date && hash_equals((string)($original['dataset_fingerprint']??''),$fingerprint) && abs((float)$original['confidence']-(float)$confidence)<=0.01;
                if (!$same) { throw new InvalidArgumentException('ID acara telah digunakan untuk payload lain.'); }
                if ($original['outcome']==='rejected') { $connection->commit(); json_response(['success'=>false,'message'=>'Model wajah kiosk sudah lapuk. Segerakkan semula dataset.','event_id'=>$eventId,'duplicate'=>true],409); }
                $effectiveStmt=$connection->prepare('SELECT * FROM attendance WHERE student_id=? AND date=?'); $effectiveStmt->bind_param('ss',$studentId,$date); $effectiveStmt->execute(); $effective=$effectiveStmt->get_result()->fetch_assoc(); $connection->commit();
                json_response(['success'=>true,'message'=>'Acara kiosk ini telah diproses.','duplicate'=>true,'event_id'=>$eventId,'student'=>['student_id'=>$studentId,'name'=>(string)$student['name']],'attendance'=>$effective?attendance_payload($effective):null]);
            }
            $version=(string)($student['face_dataset_version']??'');
            $images=current_face_images($connection,$studentId,$version,true);
            $currentFingerprint=count($images)===6?face_fingerprint($images):'';
            if ($currentFingerprint==='' || !hash_equals($currentFingerprint,$fingerprint)) {
                $outcome='rejected'; $reason='stale_dataset';
                $event=$connection->prepare('INSERT INTO kiosk_event(event_id,student_id,kiosk_id,observed_at,confidence,attendance_date,dataset_fingerprint,outcome,rejection_reason) VALUES(?,?,?,?,?,?,?,?,?)');
                $event->bind_param('ssssdssss',$eventId,$studentId,$kioskId,$observedAt,$confidence,$date,$fingerprint,$outcome,$reason); $event->execute(); $connection->commit();
                json_response(['success'=>false,'message'=>'Model wajah kiosk sudah lapuk. Segerakkan semula dataset.','event_id'=>$eventId],409);
            }
            if ($student['face_status']!=='ready') { throw new UserFacingException('Profil wajah murid belum disahkan sedia oleh kiosk.',409); }
            $outcome='accepted'; $reason=null;
            $event=$connection->prepare('INSERT INTO kiosk_event(event_id,student_id,kiosk_id,observed_at,confidence,attendance_date,dataset_fingerprint,outcome,rejection_reason) VALUES(?,?,?,?,?,?,?,?,?)');
            $event->bind_param('ssssdssss',$eventId,$studentId,$kioskId,$observedAt,$confidence,$date,$fingerprint,$outcome,$reason); $event->execute();
            $attendanceStmt=$connection->prepare('SELECT * FROM attendance WHERE student_id=? AND date=? FOR UPDATE'); $attendanceStmt->bind_param('ss',$studentId,$date); $attendanceStmt->execute(); $attendance=$attendanceStmt->get_result()->fetch_assoc();
            if (!$attendance) {
                $name=(string)$student['name']; $status='Hadir'; $source='kiosk';
                $insert=$connection->prepare('INSERT INTO attendance(student_id,name,date,time,status,source,recorded_by,observed_at,kiosk_id,recognition_confidence) VALUES(?,?,?,?,?,?,NULL,?,?,?)'); $insert->bind_param('ssssssssd',$studentId,$name,$date,$time,$status,$source,$observedAt,$kioskId,$confidence); $insert->execute();
            } elseif ($attendance['status']!=='Hadir') {
                $name=(string)$student['name']; $attendanceId=(int)$attendance['id'];
                $update=$connection->prepare("UPDATE attendance SET name=?,time=?,status='Hadir',source='kiosk',recorded_by=NULL,observed_at=?,kiosk_id=?,recognition_confidence=?,updated_at=CURRENT_TIMESTAMP WHERE id=?"); $update->bind_param('ssssdi',$name,$time,$observedAt,$kioskId,$confidence,$attendanceId); $update->execute();
            }
            $effectiveStmt=$connection->prepare('SELECT * FROM attendance WHERE student_id=? AND date=?'); $effectiveStmt->bind_param('ss',$studentId,$date); $effectiveStmt->execute(); $effective=$effectiveStmt->get_result()->fetch_assoc(); $connection->commit();
            json_response(['success'=>true,'message'=>$attendance&&$attendance['status']==='Hadir'?'Acara disimpan; rekod Hadir sedia ada dikekalkan.':'Kehadiran direkod.','duplicate'=>false,'event_id'=>$eventId,'student'=>['student_id'=>$studentId,'name'=>(string)$student['name']],'attendance'=>attendance_payload($effective)]);
        } catch (Throwable $exception) { try{$connection->rollback();}catch(Throwable){} throw $exception; }
    } catch (UserFacingException $exception) {
        json_response(['success'=>false,'message'=>$exception->getMessage()],$exception->httpStatus());
    } catch (JsonException|InvalidArgumentException $exception) {
        json_response(['success'=>false,'message'=>$exception->getMessage()],422);
    } catch (Throwable $exception) {
        error_log('Kiosk request failed: '.$exception->getMessage());
        json_response(['success'=>false,'message'=>'Permintaan kiosk tidak dapat diproses.'],500);
    }
}
header('Allow: GET, POST'); json_response(['success'=>false,'message'=>'Kaedah tidak dibenarkan.'],405);
