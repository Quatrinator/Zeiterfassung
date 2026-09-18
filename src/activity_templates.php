<?php
declare(strict_types=1);

function activity_templates(array $actor): array
{
    require_internal($actor);
    return query('SELECT id,label FROM activity_templates WHERE company_id=? ORDER BY label,id',[$actor['company_id']])->fetchAll();
}
function create_activity_template(array $actor,array $input): array
{
    return mutate($actor,function($actor) use($input) {
        require_permission($actor,'templates.manage');
        $label=text_value($input['label'] ?? null,'Tätigkeitsvorlage',150);
        if(row('SELECT id FROM activity_templates WHERE company_id=? AND label=?',[$actor['company_id'],$label])) fail('Diese Tätigkeitsvorlage gibt es bereits.');
        query('INSERT INTO activity_templates(company_id,label) VALUES(?,?)',[$actor['company_id'],$label]);
        $id=(int)db()->lastInsertId();
        audit((int)$actor['id'],'template.created','activity_template',$id,null,['label'=>$label]);
        return ['ok'=>true,'id'=>$id];
    });
}
function delete_activity_template(array $actor,array $input): array
{
    return mutate($actor,function($actor) use($input) {
        require_permission($actor,'templates.manage');
        $id=integer($input['id'] ?? null,'Tätigkeitsvorlage');
        $template=row('SELECT id,label FROM activity_templates WHERE id=? AND company_id=?',[$id,$actor['company_id']]);
        if(!$template) fail('Die Tätigkeitsvorlage wurde bereits gelöscht.',404);
        query('DELETE FROM activity_templates WHERE id=?',[$id]);
        audit((int)$actor['id'],'template.deleted','activity_template',$id,$template,null);
        return ['ok'=>true];
    });
}
function entry_description(array $actor,array $input): string
{
    $description=text_value($input['description'] ?? '', 'Beschreibung',500,false);
    $templateId=$input['template_id'] ?? '';
    if($templateId!=='' && $templateId!==null) {
        $template=row('SELECT label FROM activity_templates WHERE id=? AND company_id=?',[integer($templateId,'Tätigkeitsvorlage'),$actor['company_id']]);
        if(!$template) fail('Diese Tätigkeitsvorlage ist nicht mehr verfügbar. Bitte eine andere wählen oder die Tätigkeit selbst beschreiben.',409);
        $description=$template['label'].($description!==''?' – '.$description:'');
    }
    if($description==='') fail('Bitte eine Tätigkeitsvorlage auswählen oder beschreiben, was du gemacht hast.');
    return text_value($description,'Tätigkeit einschließlich Vorlage',500);
}
