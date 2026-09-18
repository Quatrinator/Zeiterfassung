<?php
declare(strict_types=1);

function people(array $actor): array
{
    if ($actor['kind']==='customer') return query("SELECT DISTINCT u.id,u.display_name FROM users u JOIN time_entries t ON t.user_id=u.id WHERE t.company_id=? AND t.status IN ('released','billed') AND t.deleted_at IS NULL ORDER BY u.display_name",[$actor['company_id']])->fetchAll();
    return query("SELECT id,display_name,active FROM users WHERE kind='internal' OR id IN (SELECT user_id FROM time_entries) ORDER BY display_name")->fetchAll();
}
function report_filter(array $actor,array $input): array
{
    $from=valid_date($input['from'] ?? substr(today(),0,7).'-01');
    $to=valid_date($input['to'] ?? today());
    if ($to<$from) fail('Das Enddatum muss nach dem Startdatum liegen.');
    $where=['t.company_id=?','t.deleted_at IS NULL','t.service_date BETWEEN ? AND ?'];
    $params=[$actor['company_id'],$from,$to];
    if ($actor['kind']==='customer') $where[]="t.status IN ('released','billed')";
    if (!empty($input['user_id'])) { $where[]='t.user_id=?'; $params[]=integer($input['user_id'],'Person'); }
    if (!empty($input['status'])) {
        if (!in_array($input['status'],['draft','released','billed'],true)) fail('Ungültiger Status.');
        $where[]='t.status=?'; $params[]=$input['status'];
    }
    if (isset($input['billable']) && $input['billable']!=='') { $where[]='t.billable=?'; $params[]=(int)bool_value($input['billable']); }
    if (!empty($input['search'])) { $where[]="t.description LIKE ?"; $params[]='%'.addcslashes(text_value($input['search'],'Suche',100),'%_\\').'%'; }
    return [implode(' AND ',$where),$params,['from'=>$from,'to'=>$to]];
}
function visible_entry(array $actor,array $entry): array
{
    $result=array_intersect_key($entry,array_flip(['id','user_id','kind','original_id','service_date','minutes','description','category','billable','status','version','created_at','updated_at','released_at','billed_at','invoice_reference','display_name']));
    $result['editable']=$actor['kind']==='internal' && $entry['status']==='draft' && ($entry['kind']==='adjustment' ? can($actor,'billing.correct') : ((int)$actor['id']===(int)$entry['user_id'] || can($actor,'entries.manage_team')));
    if (has_finance($actor)) foreach(['rate_cents','amount_cents','target_minutes','target_amount_cents'] as $field) $result[$field]=$entry[$field] ?? null;
    return $result;
}
function report(array $actor,array $input): array
{
    [$where,$params,$dates]=report_filter($actor,$input);
    $page=integer($input['page'] ?? 1,'Seite',1,1000000);
    $perPage=40;
    return transaction(function() use($actor,$where,$params,$dates,$page,$perPage) {
        $summary=row("SELECT COUNT(*) count,COALESCE(SUM(minutes),0) minutes,COALESCE(SUM(IF(billable,minutes,0)),0) billable_minutes,COALESCE(SUM(amount_cents),0) amount_cents,COALESCE(SUM(status='draft'),0) drafts,COALESCE(SUM(amount_cents IS NULL),0) unpriced,COALESCE(SUM(IF(status='draft',amount_cents,0)),0) draft_amount_cents,COALESCE(SUM(IF(status<>'draft',amount_cents,0)),0) published_amount_cents FROM time_entries t WHERE $where",$params);
        foreach($summary as &$value) $value=(int)$value; unset($value);
        $pages=max(1,(int)ceil($summary['count']/$perPage)); $page=min($pages,$page);
        $offset=($page-1)*$perPage;
        $rows=query("SELECT t.*,u.display_name FROM time_entries t JOIN users u ON u.id=t.user_id WHERE $where ORDER BY t.service_date DESC,t.id DESC LIMIT $perPage OFFSET $offset",$params)->fetchAll();
        $byPerson=query("SELECT u.id,u.display_name,COALESCE(SUM(t.minutes),0) minutes,COALESCE(SUM(IF(t.billable,t.minutes,0)),0) billable_minutes,COALESCE(SUM(t.amount_cents),0) amount_cents FROM time_entries t JOIN users u ON u.id=t.user_id WHERE $where GROUP BY u.id,u.display_name ORDER BY minutes DESC",$params)->fetchAll();
        if (!has_finance($actor)) {
            foreach(['amount_cents','unpriced','draft_amount_cents','published_amount_cents'] as $key) unset($summary[$key]);
            foreach($byPerson as &$person) unset($person['amount_cents']); unset($person);
        }
        return ['entries'=>array_map(fn($r)=>visible_entry($actor,$r),$rows),'summary'=>$summary,'by_person'=>$byPerson,'page'=>$page,'pages'=>$pages,'dates'=>$dates];
    });
}
function dashboard(array $actor): array
{
    require_internal($actor);
    $date=today();
    $sum=row("SELECT COALESCE(SUM(IF(service_date=?,minutes,0)),0) today,COALESCE(SUM(minutes),0) month,COALESCE(SUM(status='draft'),0) drafts FROM time_entries WHERE user_id=? AND deleted_at IS NULL AND service_date BETWEEN ? AND ?",[$date,$actor['id'],substr($date,0,7).'-01',$date]);
    $recent=query('SELECT t.*,u.display_name FROM time_entries t JOIN users u ON u.id=t.user_id WHERE t.deleted_at IS NULL AND t.user_id=? ORDER BY t.service_date DESC,t.id DESC LIMIT 6',[$actor['id']])->fetchAll();
    $days=[];
    $start=(new DateTimeImmutable($date))->modify('-6 days')->format('Y-m-d');
    $daily=query("SELECT service_date,SUM(minutes) minutes FROM time_entries WHERE user_id=? AND deleted_at IS NULL AND service_date BETWEEN ? AND ? GROUP BY service_date",[$actor['id'],$start,$date])->fetchAll(PDO::FETCH_KEY_PAIR);
    for($i=0;$i<7;$i++) { $d=(new DateTimeImmutable($start))->modify("+$i days")->format('Y-m-d'); $days[]=['date'=>$d,'minutes'=>(int)($daily[$d] ?? 0)]; }
    return ['summary'=>$sum,'recent'=>array_map(fn($r)=>visible_entry($actor,$r),$recent),'days'=>$days];
}
function csv_text(mixed $value): string
{
    $text=(string)($value ?? '');
    return preg_match('/^[\s\x{FEFF}]*[=+@-]/u',$text) || preg_match('/^[\t\r\n]/',$text) ? "'".$text : $text;
}
function export_csv(array $actor,array $input): never
{
    [$where,$params,$dates]=report_filter($actor,$input);
    // Materialize one consistent result before sending headers.
    $statement=query("SELECT t.*,u.display_name FROM time_entries t JOIN users u ON u.id=t.user_id WHERE $where ORDER BY t.service_date DESC,t.id DESC",$params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="zeitwerk-'.$dates['from'].'_'.$dates['to'].'.csv"');
    $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF");
    $columns=['Kennung','Typ','Originalkennung','Datum','Person','Tätigkeit','Kategorie','Minuten','Status','Veröffentlicht am','Rechnungsreferenz'];
    if (has_finance($actor)) array_push($columns,'Abrechenbar','Stundensatz EUR','Betrag EUR','Währung');
    $columns[]='Bericht erstellt (UTC)';
    fputcsv($out,$columns,';','"',''); $generated=gmdate('Y-m-d H:i:s');
    while($entry=$statement->fetch()) {
        $line=[$entry['id'],$entry['kind']==='time'?'Arbeitszeit':'Korrektur',$entry['original_id'] ?? '',$entry['service_date'],csv_text($entry['display_name']),csv_text($entry['description']),csv_text($entry['category']),$entry['minutes'],['draft'=>'Entwurf','released'=>'Freigegeben','billed'=>'Abgerechnet'][$entry['status']],$entry['released_at'] ?? '',csv_text($entry['invoice_reference'])];
        if (has_finance($actor)) array_push($line,$entry['billable']?'Ja':'Nein',$entry['rate_cents']===null?'':number_format($entry['rate_cents']/100,2,',',''),$entry['amount_cents']===null?'Noch nicht berechnet':number_format($entry['amount_cents']/100,2,',',''),'EUR');
        $line[]=$generated; fputcsv($out,$line,';','"','');
    }
    fclose($out); exit;
}
