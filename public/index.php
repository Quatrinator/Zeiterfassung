<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';

$path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH);
try {
    secure_headers();
    if ($path==='/health') {
        query("SELECT version FROM schema_versions WHERE version='001_initial'");
        json_response(['status'=>'ok']);
    }
    validate_host(); session_boot();
    if ($path==='/api' && $_SERVER['REQUEST_METHOD']==='POST') {
        csrf_check(); $input=json_input();
        $action=$input['action'] ?? '';
        if ($action==='login') json_response(login($input));
        $actor=current_user();
        if ($action==='logout') { $_SESSION=[]; session_destroy(); json_response(['ok'=>true]); }
        if ($action==='password.change') json_response(change_password($actor,$input));
        if ($actor['must_change_password']) fail('Bitte zuerst dein Passwort ändern.',403);
        $result=match($action) {
            'entry.save'=>save_entry($actor,$input),
            'entry.delete'=>delete_entry($actor,$input),
            'entry.transition'=>transition_entries($actor,$input),
            'adjustment.create'=>create_adjustment($actor,$input),
            'user.save'=>save_user($actor,$input),
            'user.reset'=>reset_password($actor,$input),
            'role.save'=>save_role($actor,$input),
            'rate.save'=>save_rate($actor,$input),
            default=>fail('Unbekannte Aktion.',404),
        };
        json_response($result);
    }
    if (($path==='/api' || $path==='/export') && $_SERVER['REQUEST_METHOD']==='GET') {
        $actor=current_user();
        if ($actor['must_change_password']) fail('Bitte zuerst dein Passwort ändern.',403);
        session_write_close();
        if ($path==='/export') export_csv($actor,$_GET);
        $resource=$_GET['resource'] ?? 'entries';
        if ($resource==='entries') json_response(report($actor,$_GET));
        if ($resource==='dashboard') json_response(dashboard($actor));
        if ($resource==='people') json_response(['people'=>people($actor)]);
        if ($resource==='admin') json_response(admin_data($actor));
        if ($resource==='rates') {
            require_permission($actor,'rates.manage');
            json_response(['rates'=>query('SELECT r.*,u.display_name FROM hourly_rates r LEFT JOIN users u ON u.id=r.user_id ORDER BY r.valid_from DESC,r.id DESC')->fetchAll(),'people'=>people($actor)]);
        }
        if ($resource==='audit') {
            require_permission($actor,'audit.view');
            $page=integer($_GET['page'] ?? 1,'Seite',1,100000); $offset=($page-1)*30;
            json_response(['events'=>query("SELECT a.*,u.display_name actor_name FROM audit_events a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 30 OFFSET $offset")->fetchAll(),'page'=>$page,'pages'=>max(1,(int)ceil((int)query('SELECT COUNT(*) FROM audit_events')->fetchColumn()/30))]);
        }
        if ($resource==='correction-base') {
            require_permission($actor,'billing.correct');
            $id=integer($_GET['id'] ?? null,'Eintrag');
            json_response(transaction(fn()=>correction_base($id)));
        }
        fail('Unbekannte Ansicht.',404);
    }
    if ($path!=='/' || $_SERVER['REQUEST_METHOD']!=='GET') fail('Seite nicht gefunden.',404);
    $actor=current_user(false);
    $boot=['user'=>$actor?public_user($actor):null,'csrf'=>$_SESSION['csrf'],'today'=>today(),'app_name'=>config()['app_name'],'company_name'=>config()['company_name']];
    require ROOT.'/templates/shell.php';
} catch (AppError $e) {
    if ($path==='/api' || $path==='/export') json_response(['error'=>$e->getMessage()],$e->status);
    http_response_code($e->status); echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>Hinweis</title><p>'.e($e->getMessage()).'</p></html>';
} catch (Throwable $e) {
    $reference=bin2hex(random_bytes(4));
    error_log('zeitwerk error '.$reference.' '.get_class($e).' '.$e->getMessage().' '.$e->getFile().':'.$e->getLine());
    if ($path==='/api' || $path==='/health' || $path==='/export') json_response(['error'=>'Die Anfrage konnte nicht verarbeitet werden. Referenz: '.$reference],500);
    http_response_code(503); echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>Wartung</title><p>Die Anwendung ist momentan nicht verfügbar. Referenz: '.e($reference).'</p></html>';
}
