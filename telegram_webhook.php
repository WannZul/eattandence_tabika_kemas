<?php
declare(strict_types=1);
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/validation.php';
require_once __DIR__ . '/app/database.php';

header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){header('Allow: POST');json_response(['ok'=>false],405);}
if(!request_is_https()&&!(app_env()!=='production'&&env_bool('ALLOW_INSECURE_WEBHOOK',false))){json_response(['ok'=>false],400);}
$expected=env_value('TELEGRAM_WEBHOOK_SECRET');$provided=$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']??'';
if($expected===null||preg_match('/^[A-Za-z0-9_-]{16,256}$/',$expected)!==1||!is_string($provided)||!hash_equals($expected,$provided)){json_response(['ok'=>false],401);}
if((int)($_SERVER['CONTENT_LENGTH']??0)>1_000_000){json_response(['ok'=>false],413);}
try{
    $update=json_decode((string)file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);$updateId=filter_var($update['update_id']??null,FILTER_VALIDATE_INT);
    if($updateId===false){json_response(['ok'=>true]);}
    $connection=db();$connection->begin_transaction();
    try{
        $log=$connection->prepare('INSERT IGNORE INTO telegram_update_log(update_id) VALUES(?)');$log->bind_param('i',$updateId);$log->execute();
        if($log->affected_rows===0){$connection->rollback();json_response(['ok'=>true]);}
        $incoming=$update['message']??null;$text=is_array($incoming)?trim((string)($incoming['text']??'')):'';$chatId=is_array($incoming)?(string)($incoming['chat']['id']??''):'';$chatType=is_array($incoming)?(string)($incoming['chat']['type']??''):'';$reply=null;
        if($chatId!==''&&$chatType!=='private'&&str_starts_with($text,'/start')){$reply='Pautan eAttendance hanya boleh digunakan dalam perbualan peribadi dengan bot.';}
        elseif($chatId!==''&&preg_match('/^\/start(?:@[A-Za-z0-9_]+)?\s+([A-Za-z0-9_-]{20,64})$/',$text,$matches)===1){$hash=hash('sha256',$matches[1]);$studentStmt=$connection->prepare('SELECT student_id,name FROM student WHERE telegram_link_token_hash=? AND telegram_link_expires_at>=NOW() LIMIT 1 FOR UPDATE');$studentStmt->bind_param('s',$hash);$studentStmt->execute();$student=$studentStmt->get_result()->fetch_assoc();if($student){$save=$connection->prepare('UPDATE student SET telegram_chat_id=?,telegram_linked_at=NOW(),telegram_link_token_hash=NULL,telegram_link_expires_at=NULL WHERE student_id=? AND telegram_link_token_hash=?');$save->bind_param('sss',$chatId,$student['student_id'],$hash);$save->execute();$reply='Pautan berjaya. Notifikasi eAttendance untuk '.$student['name'].' telah diaktifkan.';}else{$reply='Pautan tidak sah, telah digunakan atau telah tamat. Minta pautan baharu daripada pentadbir.';}}
        elseif($chatId!==''&&str_starts_with($text,'/start')){$reply='Pautan tidak lengkap. Gunakan pautan yang diberikan oleh pentadbir.';}
        if($reply!==null){$source='webhook';$dedupe='update:'.$updateId;$outbox=$connection->prepare("INSERT INTO telegram_outbox(source,dedupe_key,update_id,notification_log_id,telegram_chat_id,message_text,status,next_attempt_at) VALUES(?,?,?,NULL,?,?,'pending',NOW())");$outbox->bind_param('ssiss',$source,$dedupe,$updateId,$chatId,$reply);$outbox->execute();}
        $connection->commit();
    }catch(Throwable $exception){try{$connection->rollback();}catch(Throwable){}throw $exception;}
    json_response(['ok'=>true]);
}catch(Throwable $exception){error_log('Telegram webhook processing failed: '.$exception->getMessage());json_response(['ok'=>false],500);}
