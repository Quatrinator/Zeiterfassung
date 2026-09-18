<?php
declare(strict_types=1);

function entry_record(int $id): array
{
    return row('SELECT * FROM time_entries WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$id]) ?? fail('Eintrag nicht gefunden.',404);
}
function editable(array $actor,array $entry): void
{
    require_internal($actor);
    if ((int)$entry['company_id'] !== (int)$actor['company_id']) fail('Kein Zugriff auf diesen Eintrag.',403);
    if ($entry['status']!=='draft') fail('Nur Entwürfe können bearbeitet werden.',409);
    if ($entry['kind']==='adjustment') require_permission($actor,'billing.correct');
    elseif ((int)$entry['user_id'] !== (int)$actor['id']) require_permission($actor,'entries.manage_team');
}
function select_rate(int $userId,string $date): ?int
{
    $r=row('SELECT cents_per_hour FROM hourly_rates WHERE company_id=1 AND (user_id=? OR user_id IS NULL) AND valid_from<=? AND (valid_until IS NULL OR valid_until>?) ORDER BY (user_id IS NOT NULL) DESC LIMIT 1',[$userId,$date,$date]);
    return $r ? (int)$r['cents_per_hour'] : null;
}
function idempotent(array $actor,array $input,callable $fn): array
{
    $key=$input['request_key'] ?? '';
    if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_-]{16,64}$/D',$key)) fail('Speicherschlüssel fehlt. Bitte die Seite neu laden.');
    $payload=$input; unset($payload['request_key']); ksort($payload);
    $fingerprint=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
    $old=row('SELECT * FROM submission_keys WHERE user_id=? AND request_key=?',[$actor['id'],$key]);
    if ($old) {
        if (!hash_equals($old['fingerprint'],$fingerprint)) fail('Diese Speicheranfrage wurde bereits mit anderem Inhalt verarbeitet. Bitte neu laden.',409);
        return json_decode($old['result_json'],true,512,JSON_THROW_ON_ERROR);
    }
    $result=$fn();
    query('INSERT INTO submission_keys(user_id,request_key,fingerprint,result_json) VALUES(?,?,?,?)',[$actor['id'],$key,$fingerprint,json_encode($result,JSON_THROW_ON_ERROR)]);
    return $result;
}
function save_entry(array $actor,array $input): array
{
    return mutate($actor,function($actor) use($input) {
        require_internal($actor);
        return idempotent($actor,$input,function() use($actor,$input) {
            $id=isset($input['id']) ? integer($input['id'],'Eintrag') : null;
            $old=$id ? entry_record($id) : null;
            if ($old) {
                editable($actor,$old); check_version($old,$input['version'] ?? null);
                if ($old['kind']!=='time') fail('Eine Korrektur bitte löschen und neu anlegen.');
            }
            $date=valid_date($input['service_date'] ?? null);
            if ($date>today()) fail('Gearbeitete Zeit kann nicht in der Zukunft liegen.');
            $minutes=integer($input['minutes'] ?? null,'Dauer',1,1440);
            $description=entry_description($actor,$input);
            $category=text_value($input['category'] ?? '', 'Kategorie',40,false);
            if (!in_array($category,['','Support','Wartung','Einrichtung','Entwicklung','Beratung','Sonstiges'],true)) fail('Ungültige Kategorie.');
            $billable=bool_value($input['billable'] ?? true);
            $userId=$old ? (int)$old['user_id'] : (int)$actor['id'];
            $reason=text_value($input['reason'] ?? '', 'Änderungsgrund',500,false);
            if ($old && ($actor['is_admin'] || $userId!==(int)$actor['id']) && $reason==='') fail('Bitte einen Änderungsgrund angeben.');
            $rate=$old && $old['service_date']===$date && (bool)$old['billable']===$billable ? $old['rate_cents'] : select_rate($userId,$date);
            $value=$billable ? ($rate===null ? null : amount($minutes,(int)$rate)) : 0;
            if ($id) query('UPDATE time_entries SET service_date=?,minutes=?,description=?,category=?,billable=?,rate_cents=?,amount_cents=?,version=version+1,updated_by=? WHERE id=?',[$date,$minutes,$description,$category,(int)$billable,$rate,$value,$actor['id'],$id]);
            else {
                query('INSERT INTO time_entries(user_id,service_date,minutes,description,category,billable,rate_cents,amount_cents,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?,?)',[$userId,$date,$minutes,$description,$category,(int)$billable,$rate,$value,$actor['id'],$actor['id']]);
                $id=(int)db()->lastInsertId();
            }
            audit((int)$actor['id'],$old?'entry.updated':'entry.created','entry',$id,$old,entry_record($id),$reason);
            $total=(int)query("SELECT COALESCE(SUM(minutes),0) FROM time_entries WHERE user_id=? AND service_date=? AND deleted_at IS NULL AND (kind='time' OR status<>'draft')",[$userId,$date])->fetchColumn();
            return ['ok'=>true,'id'=>$id,'warning'=>$total>720 ? 'Für diesen Tag sind mehr als 12 Stunden erfasst. Bitte die Tagessumme prüfen.' : null];
        });
    });
}
function delete_entry(array $actor,array $input): array
{
    return mutate($actor,function($actor) use($input) {
        $entry=entry_record(integer($input['id'] ?? null,'Eintrag'));
        editable($actor,$entry); check_version($entry,$input['version'] ?? null);
        $reason=text_value($input['reason'] ?? '', 'Änderungsgrund',500,false);
        if (($actor['is_admin'] || (int)$entry['user_id']!==(int)$actor['id'] || $entry['kind']==='adjustment') && $reason==='') fail('Bitte einen Grund angeben.');
        query('UPDATE time_entries SET deleted_at=UTC_TIMESTAMP(),version=version+1,updated_by=? WHERE id=?',[$actor['id'],$entry['id']]);
        audit((int)$actor['id'],'entry.deleted','entry',(int)$entry['id'],$entry,null,$reason);
        return ['ok'=>true];
    });
}
function correction_base(int $id): array
{
    $original=entry_record($id);
    $sum=row("SELECT COALESCE(SUM(minutes),0) minutes,COALESCE(SUM(amount_cents),0) amount FROM time_entries WHERE original_id=? AND status IN ('released','billed') AND deleted_at IS NULL",[$id]);
    return ['minutes'=>(int)$original['minutes']+(int)$sum['minutes'],'amount_cents'=>(int)$original['amount_cents']+(int)$sum['amount']];
}
function create_adjustment(array $actor,array $input): array
{
    return mutate($actor,function($actor) use($input) {
        require_permission($actor,'billing.correct'); require_permission($actor,'finance.view');
        return idempotent($actor,$input,function() use($actor,$input) {
            $original=entry_record(integer($input['original_id'] ?? null,'Originaleintrag'));
            if ($original['kind']!=='time' || $original['status']!=='billed') fail('Nur abgerechnete Arbeitsleistungen können so korrigiert werden.');
            if (row("SELECT id FROM time_entries WHERE original_id=? AND status='draft' AND deleted_at IS NULL",[$original['id']])) fail('Für diese Leistung besteht bereits ein Korrekturentwurf.',409);
            $base=correction_base((int)$original['id']);
            $minutes=integer($input['target_minutes'] ?? null,'Korrigierte Gesamtdauer',0,1440);
            $value=cents($input['target_amount'] ?? '');
            if (!$original['billable'] && $value!==0) fail('Nicht abrechenbare Leistungen müssen bei null Euro bleiben.');
            $description=text_value($input['description'] ?? null,'Kundenlesbare Erläuterung',500);
            $reason=text_value($input['reason'] ?? null,'Interner Änderungsgrund',500);
            if ($minutes===$base['minutes'] && $value===$base['amount_cents']) fail('Die Korrektur verändert weder Zeit noch Betrag.');
            query("INSERT INTO time_entries(user_id,kind,original_id,service_date,minutes,description,billable,amount_cents,target_minutes,target_amount_cents,created_by,updated_by) VALUES(?,'adjustment',?,?,?,?,?,?,?,?,?,?)",[$original['user_id'],$original['id'],$original['service_date'],$minutes-$base['minutes'],$description,$original['billable'],$value-$base['amount_cents'],$minutes,$value,$actor['id'],$actor['id']]);
            $id=(int)db()->lastInsertId();
            audit((int)$actor['id'],'adjustment.created','entry',$id,null,entry_record($id),$reason);
            return ['ok'=>true,'id'=>$id];
        });
    });
}
function transition_entries(array $actor,array $input): array
{
    return mutate($actor,function($actor) use($input) {
        $action=$input['transition'] ?? '';
        $permission=match($action) {'release','recall'=>'entries.release','bill'=>'billing.finalize','reprice'=>'rates.manage',default=>fail('Ungültige Aktion.')};
        require_permission($actor,$permission); require_permission($actor,'finance.view');
        $items=$input['items'] ?? null;
        if (!is_array($items) || count($items)<1 || count($items)>200) fail('Bitte 1 bis 200 Einträge auswählen.');
        $ids=[];
        $reason=text_value($input['reason'] ?? '', 'Änderungsgrund',500,false);
        if (in_array($action,['recall','reprice'],true) && !$reason) fail('Bitte einen Grund angeben.');
        $invoice=text_value($input['invoice_reference'] ?? '', 'Rechnungsreferenz',100,false);
        foreach($items as $item) {
            if (!is_array($item)) fail('Ungültige Auswahl.');
            $id=integer($item['id'] ?? null,'Eintrag');
            if (isset($ids[$id])) fail('Eintrag doppelt ausgewählt.');
            $ids[$id]=true;
            $entry=entry_record($id); check_version($entry,$item['version'] ?? null);
            if ((int)$entry['company_id']!==(int)$actor['company_id']) fail('Kein Zugriff.',403);
            if ($action==='release') {
                if ($entry['status']!=='draft') fail("Eintrag #$id ist kein Entwurf.",409);
                if ($entry['amount_cents']===null) fail("Eintrag #$id hat noch keinen Stundensatz. Bitte zuerst neu berechnen.");
                if ($entry['kind']==='adjustment') {
                    require_permission($actor,'billing.correct');
                    $base=correction_base((int)$entry['original_id']);
                    if ($base['minutes']+(int)$entry['minutes']!==(int)$entry['target_minutes'] || $base['amount_cents']+(int)$entry['amount_cents']!==(int)$entry['target_amount_cents']) fail('Die Korrekturgrundlage hat sich geändert. Korrekturentwurf bitte neu anlegen.',409);
                }
                query("UPDATE time_entries SET status='released',released_at=UTC_TIMESTAMP(),version=version+1,updated_by=? WHERE id=?",[$actor['id'],$id]);
            } elseif ($action==='bill') {
                if ($entry['status']!=='released') fail("Eintrag #$id ist noch nicht freigegeben.",409);
                query("UPDATE time_entries SET status='billed',billed_at=UTC_TIMESTAMP(),invoice_reference=?,version=version+1,updated_by=? WHERE id=?",[$invoice,$actor['id'],$id]);
            } elseif ($action==='recall') {
                if ($entry['status']!=='released') fail('Nur noch nicht abgerechnete Freigaben können zurückgezogen werden.',409);
                if ($entry['kind']==='adjustment') {
                    require_permission($actor,'billing.correct');
                    if (row("SELECT id FROM time_entries WHERE original_id=? AND id<>? AND deleted_at IS NULL AND (status='draft' OR (status IN ('released','billed') AND id>?))",[$entry['original_id'],$id,$id])) fail('Eine weitere Korrektur baut auf dieser Position auf. Zuerst diese prüfen.',409);
                }
                query("UPDATE time_entries SET status='draft',released_at=NULL,version=version+1,updated_by=? WHERE id=?",[$actor['id'],$id]);
            } else {
                if ($entry['status']!=='draft' || $entry['kind']!=='time') fail('Nur Arbeitszeit-Entwürfe können neu bewertet werden.',409);
                $rate=select_rate((int)$entry['user_id'],$entry['service_date']);
                $value=$entry['billable'] ? ($rate===null ? null : amount((int)$entry['minutes'],$rate)) : 0;
                query('UPDATE time_entries SET rate_cents=?,amount_cents=?,version=version+1,updated_by=? WHERE id=?',[$rate,$value,$actor['id'],$id]);
            }
            audit((int)$actor['id'],'entry.'.$action,'entry',$id,$entry,entry_record($id),$reason);
        }
        return ['ok'=>true,'count'=>count($ids)];
    });
}
