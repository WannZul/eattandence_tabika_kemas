<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function telegram_env_int(string $key,int $default,int $minimum,int $maximum): int { $value=filter_var(env_value($key,(string)$default),FILTER_VALIDATE_INT,['options'=>['min_range'=>$minimum,'max_range'=>$maximum]]); return $value===false?$default:(int)$value; }
function telegram_batch_size(): int { return telegram_env_int('TELEGRAM_ENQUEUE_BATCH_SIZE',20,1,100); }
function telegram_outbox_batch_size(): int { return telegram_env_int('TELEGRAM_OUTBOX_BATCH_SIZE',5,1,20); }
function telegram_send_message(string $chatId,string $message): array
{
    $token=env_value('TELEGRAM_BOT_TOKEN');
    if($token===null){return ['ok'=>false,'error'=>'Token bot belum dikonfigurasi.'];}
    if(!function_exists('curl_init')){return ['ok'=>false,'error'=>'Sambungan cURL PHP tidak tersedia.'];}
    $handle=curl_init('https://api.telegram.org/bot'.rawurlencode($token).'/sendMessage');
    curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['chat_id'=>$chatId,'text'=>$message]),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $body=curl_exec($handle); $curlError=curl_error($handle); $http=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE); curl_close($handle);
    if($body===false){return ['ok'=>false,'error'=>'Telegram tidak dapat dihubungi: '.substr($curlError,0,120)];}
    $decoded=json_decode((string)$body,true);
    if($http!==200||!is_array($decoded)||($decoded['ok']??false)!==true){return ['ok'=>false,'error'=>'Telegram menolak permintaan.','http_status'=>$http];}
    return ['ok'=>true,'message_id'=>(string)($decoded['result']['message_id']??'')];
}

function telegram_process_outbox(?int $limit=null): array
{
    $limit=max(1,min($limit??telegram_outbox_batch_size(),20)); $maximum=telegram_env_int('TELEGRAM_OUTBOX_MAX_ATTEMPTS',6,1,20);
    $processed=0;$sent=0;$failed=0;$cancelled=0;
    while($processed<$limit){
        $connection=db(); $connection->begin_transaction();
        try{
            $stmt=$connection->prepare("SELECT id,source,notification_log_id,telegram_chat_id,message_text,attempt_count FROM telegram_outbox WHERE attempt_count<? AND next_attempt_at<=NOW() AND (status IN ('pending','failed') OR (status='processing' AND locked_at<DATE_SUB(NOW(),INTERVAL 5 MINUTE))) ORDER BY next_attempt_at,id LIMIT 1 FOR UPDATE");
            $stmt->bind_param('i',$maximum);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
            if(!$row){$connection->commit();break;}
            $id=(int)$row['id'];
            if($row['source']==='absence'){
                $notificationId=(int)$row['notification_log_id'];
                $valid=$connection->prepare("SELECT n.id FROM notification_log n JOIN attendance a ON a.student_id=n.student_id AND a.date=n.attendance_date JOIN student s ON s.student_id=n.student_id WHERE n.id=? AND n.notification_type='absence' AND a.status='Tidak Hadir' AND s.telegram_chat_id=n.telegram_chat_id FOR UPDATE");
                $valid->bind_param('i',$notificationId);$valid->execute();
                if(!$valid->get_result()->fetch_assoc()){
                    $cancel=$connection->prepare("UPDATE telegram_outbox SET status='cancelled',locked_at=NULL,last_error='Attendance or chat link no longer eligible' WHERE id=?");$cancel->bind_param('i',$id);$cancel->execute();
                    $cancelLog=$connection->prepare("UPDATE notification_log SET status='cancelled',error_message='Attendance or chat link no longer eligible' WHERE id=? AND status<>'sent'");$cancelLog->bind_param('i',$notificationId);$cancelLog->execute();
                    $connection->commit();$processed++;$cancelled++;continue;
                }
            }
            $claim=$connection->prepare("UPDATE telegram_outbox SET status='processing',attempt_count=attempt_count+1,locked_at=NOW(),last_error=NULL WHERE id=?");$claim->bind_param('i',$id);$claim->execute();
            if($row['notification_log_id']!==null){$nid=(int)$row['notification_log_id'];$log=$connection->prepare("UPDATE notification_log SET status='pending',attempt_count=attempt_count+1,attempted_at=NOW(),error_message=NULL WHERE id=? AND status<>'sent'");$log->bind_param('i',$nid);$log->execute();}
            $connection->commit();
        }catch(Throwable $exception){try{$connection->rollback();}catch(Throwable){}throw $exception;}
        $processed++;$attempt=(int)$row['attempt_count']+1;$result=telegram_send_message((string)$row['telegram_chat_id'],(string)$row['message_text']);
        $finish=db();$finish->begin_transaction();
        try{
            $lock=$finish->prepare("SELECT status,notification_log_id FROM telegram_outbox WHERE id=? FOR UPDATE");$lock->bind_param('i',$id);$lock->execute();$current=$lock->get_result()->fetch_assoc();
            if(!$current||$current['status']!=='processing'){$finish->commit();continue;}
            $notificationId=$current['notification_log_id']!==null?(int)$current['notification_log_id']:null;
            if($result['ok']){
                $messageId=(string)$result['message_id'];$update=$finish->prepare("UPDATE telegram_outbox SET status='sent',telegram_message_id=?,sent_at=NOW(),locked_at=NULL,last_error=NULL WHERE id=?");$update->bind_param('si',$messageId,$id);$update->execute();
                if($notificationId!==null){$log=$finish->prepare("UPDATE notification_log SET status='sent',telegram_message_id=?,sent_at=NOW(),error_message=NULL WHERE id=?");$log->bind_param('si',$messageId,$notificationId);$log->execute();}$sent++;
            }else{
                $error=substr((string)($result['error']??'Ralat Telegram'),0,255);$delay=min(3600,30*(2**min(7,max(0,$attempt-1))));$next=date('Y-m-d H:i:s',time()+$delay);
                $update=$finish->prepare("UPDATE telegram_outbox SET status='failed',next_attempt_at=?,locked_at=NULL,last_error=? WHERE id=?");$update->bind_param('ssi',$next,$error,$id);$update->execute();
                if($notificationId!==null){$log=$finish->prepare("UPDATE notification_log SET status='failed',error_message=? WHERE id=?");$log->bind_param('si',$error,$notificationId);$log->execute();}$failed++;
            }
            $finish->commit();
        }catch(Throwable $exception){try{$finish->rollback();}catch(Throwable){}throw $exception;}
    }
    return ['processed'=>$processed,'sent'=>$sent,'failed'=>$failed,'cancelled'=>$cancelled];
}
