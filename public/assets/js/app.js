'use strict';
const boot = JSON.parse(document.getElementById('boot').textContent);
const user = boot.user;
const main = document.getElementById('main');
const modal = document.getElementById('modal');
const state = { route: '', filters: {}, entries: [], people: [], selected: new Set(), page: 1, admin: null, rates: [], sequence: 0 };
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = value => value === null || value === undefined ? 'Satz fehlt' : new Intl.NumberFormat('de-DE',{style:'currency',currency:'EUR'}).format(Number(value)/100);
const euroInput = value => (Number(value)/100).toFixed(2).replace('.',',');
const duration = value => { const n=Number(value); return `${n<0?'−':''}${Math.floor(Math.abs(n)/60)} Std. ${String(Math.abs(n)%60).padStart(2,'0')} Min.`; };
const dateLabel = value => new Date(`${value}T12:00:00`).toLocaleDateString('de-DE',{day:'2-digit',month:'short',year:'numeric'});
const stamp = value => value ? new Date(value.replace(' ','T')+'Z').toLocaleString('de-DE',{timeZone:'Europe/Berlin'}) : '–';
const can = permission => user?.kind === 'internal' && user.permissions.includes(permission);
const finance = () => user?.kind === 'customer' || can('finance.view');
const randomKey = () => Array.from(crypto.getRandomValues(new Uint8Array(24)),b=>b.toString(16).padStart(2,'0')).join('');
const iconPaths = {
  clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  list:'<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
  team:'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3a4 4 0 0 1 0 8"/><circle cx="9" cy="7" r="4"/>',
  bill:'<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z M9 7h6m-6 4h6m-6 4h3"/>',
  arrow:'<path d="M7 17 17 7M7 7h10v10"/>',
  plus:'<path d="M12 5v14M5 12h14"/>',
  check:'<path d="m5 12 4 4L19 6"/>',
  download:'<path d="M12 3v12m-5-5 5 5 5-5M5 16v5h14v-5"/>',
  shield:'<path d="m12 3 8 4v5c0 5-8 9-8 9s-8-4-8-9V7l8-4Z M8 12l3 3 5-6"/>',
  rates:'<path d="M18 6a7 7 0 1 0 0 12M4 10h11M4 14h9"/>',
  calendar:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
  history:'<path d="M3 11a9 9 0 1 1 2 7M3 4v7h7M12 7v5l3 2"/>',
};
const icon = name => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${iconPaths[name]||iconPaths.clock}</svg>`;
const statusName = {draft:'Entwurf',released:'Freigegeben',billed:'Abgerechnet'};
const badge = status => `<span class="badge badge-${esc(status)}">${esc(statusName[status])}</span>`;
let toastTimer;
function toast(message,error=false) { const el=document.getElementById('toast'); el.textContent=message; el.hidden=false; el.classList.toggle('error',error); clearTimeout(toastTimer); toastTimer=setTimeout(()=>el.hidden=true,6500); }
async function api(resource,params={}) {
  const response=await fetch('/api?'+new URLSearchParams({resource,...params}),{headers:{Accept:'application/json'}});
  let data; try { data=await response.json(); } catch { throw new Error('Die Antwort konnte nicht gelesen werden. Bitte erneut versuchen.'); }
  if (!response.ok) { if(response.status===401) location.reload(); throw new Error(data.error||'Anfrage fehlgeschlagen.'); }
  return data;
}
async function post(action,payload) {
  const response=await fetch('/api',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':boot.csrf},body:JSON.stringify({action,...payload})});
  let data; try { data=await response.json(); } catch { throw new Error('Keine eindeutige Speicherbestätigung erhalten. Deine Eingaben bleiben erhalten; bitte erneut versuchen.'); }
  if(!response.ok) { if(response.status===401 && action!=='login') toast('Bitte erneut anmelden.',true); throw new Error(data.error||'Speichern fehlgeschlagen.'); }
  return data;
}
function heading(title,subtitle,button='') { return `<div class="page-heading"><div><span class="eyebrow">${esc(boot.company_name)}</span><h1>${esc(title)}</h1><p>${esc(subtitle)}</p></div>${button}</div>`; }
function stat(label,value,note,ico='clock',highlight=false) { return `<div class="stat ${highlight?'highlight':''}"><span class="stat-label">${esc(label)}</span><span class="stat-icon">${icon(ico)}</span><div class="stat-number">${esc(value)}</div><div class="stat-note">${esc(note)}</div></div>`; }
function empty(title,text) { return `<div class="empty-state">${icon('clock')}<h3>${esc(title)}</h3><p>${esc(text)}</p></div>`; }
function modalOpen(title,body) { document.getElementById('modal-content').innerHTML=`<div class="dialog-head"><h2 id="modal-title">${esc(title)}</h2><button type="button" class="icon-button" data-command="close" aria-label="Dialog schließen">×</button></div>${body}`; if(!modal.open) modal.showModal(); }
const errorBox = '<div class="form-error" role="alert" hidden></div>';
const hidden = (name,value) => `<input type="hidden" name="${name}" value="${esc(value)}">`;
function actions(label='Speichern',danger=false) { return `${errorBox}<div class="dialog-actions"><button type="button" class="btn btn-secondary" data-command="close">Abbrechen</button><button type="submit" class="btn ${danger?'btn-danger':'btn-primary'}">${esc(label)}</button></div>`; }
function initNavigation() {
  const links = user.kind==='customer' ? [['customer','Leistungsübersicht','bill']] : [['capture','Zeit erfassen','plus'],['mine','Meine Zeiten','clock'],['team','Teamübersicht','team']];
  if(can('finance.view')) links.push(['billing','Abrechnung','bill']);
  if(can('rates.manage')) links.push(['rates','Stundensätze','rates']);
  if(user.is_admin) links.push(['users','Benutzer','team'],['roles','Rollen & Rechte','shield']);
  if(can('audit.view')) links.push(['audit','Änderungshistorie','history']);
  document.getElementById('navigation').innerHTML='<div class="nav-heading">ÜBERSICHT</div>'+links.map(([route,title,ico])=>`<a class="nav-link" href="#${route}" data-route="${route}">${icon(ico)}${title}</a>`).join('');
}
function defaultFilters(route) { return {from:boot.today.slice(0,7)+'-01',to:boot.today,user_id:route==='mine'?String(user.id):'',status:'',billable:'',search:''}; }
async function render() {
  if(!user) return;
  if(user.must_change_password) { renderPassword(true); return; }
  const route=(location.hash.slice(1)|| (user.kind==='customer'?'customer':'capture')).split('?')[0];
  const allowed=[...document.querySelectorAll('[data-route]')].map(a=>a.dataset.route);
  if(!allowed.includes(route)) { location.hash=user.kind==='customer'?'customer':'capture'; return; }
  if(state.route!==route) { state.filters=defaultFilters(route); state.page=1; state.selected.clear(); }
  state.route=route; const sequence=++state.sequence;
  document.querySelectorAll('[data-route]').forEach(a=>a.classList.toggle('active',a.dataset.route===route));
  document.getElementById('breadcrumb').textContent=document.querySelector(`[data-route="${route}"]`).textContent;
  document.getElementById('sidebar').classList.remove('open');
  document.querySelector('[data-command="menu"]').setAttribute('aria-expanded','false');
  main.innerHTML='<div class="loading-state">Wird geladen …</div>';
  try {
    if(route==='capture') { const data=await api('dashboard'); if(sequence===state.sequence) renderCapture(data); }
    else if(['mine','team','billing','customer'].includes(route)) {
      const [data,p]=await Promise.all([api('entries',{...state.filters,page:state.page}),api('people')]);
      if(sequence===state.sequence) { state.people=p.people; renderReport(data); }
    } else if(['users','roles'].includes(route)) { const data=await api('admin'); if(sequence===state.sequence) { state.admin=data; renderAdmin(route,data); } }
    else if(route==='rates') { const data=await api('rates'); if(sequence===state.sequence) { state.rates=data.rates; state.people=data.people; renderRates(data); } }
    else if(route==='audit') { const data=await api('audit',{page:state.page}); if(sequence===state.sequence) renderAudit(data); }
  } catch(error) { if(sequence===state.sequence) main.innerHTML=`${heading('Das hat nicht geklappt','Deine gespeicherten Daten bleiben erhalten.')}<div class="panel panel-body"><p class="mb-4">${esc(error.message)}</p><button class="btn btn-primary" data-command="refresh">Erneut versuchen</button></div>`; }
}
function entryForm(entry=null) {
  const id=entry?.id;
  return `<form data-action="entry.save" class="form-stack">${hidden('request_key',randomKey())}${id?hidden('id',id)+hidden('version',entry.version):''}
    <div class="field-row"><label>Leistungsdatum<input type="date" name="service_date" required min="2000-01-01" max="${boot.today}" value="${esc(entry?.service_date||boot.today)}"></label>
    <div><label>Dauer <span class="sr-only">in Minuten</span><input type="number" name="minutes" min="1" max="1440" step="1" inputmode="numeric" required placeholder="Minuten" value="${entry?entry.minutes:''}"></label><div class="quick-buttons">${[15,30,60,120].map(n=>`<button type="button" class="quick-button" data-minutes="${n}">${n<60?n+' Min.':n/60+' Std.'}</button>`).join('')}</div></div></div>
    <label>Was hast du gemacht?<textarea name="description" required maxlength="500" placeholder="Zum Beispiel: VPN-Zugang eingerichtet …">${esc(entry?.description||'')}</textarea></label>
    <div class="field-row"><label>Kategorie <span class="helper">Optional</span><select name="category">${['','Support','Wartung','Einrichtung','Entwicklung','Beratung','Sonstiges'].map(x=>`<option value="${x}" ${entry?.category===x?'selected':''}>${x||'Ohne Kategorie'}</option>`).join('')}</select></label><div class="flex flex-col justify-end gap-3 pb-3"><label class="check-label"><input type="checkbox" name="billable" ${!entry || entry.billable?'checked':''}> Abrechenbare Tätigkeit</label><span class="helper">Deine Zeit ist im gesamten Team sichtbar.</span></div></div>
    ${id && (user.is_admin||Number(entry.user_id)!==Number(user.id))?'<label>Änderungsgrund<input name="reason" required maxlength="500" placeholder="Warum wird der Eintrag geändert?"></label>':''}
    ${errorBox}<div class="form-bottom"><span class="helper">${id?'Änderungen werden protokolliert.':'Datum und Person sind bereits zugeordnet.'}</span><button class="btn btn-primary" type="submit">${icon('check')}${id?'Änderung speichern':'Zeit speichern'}</button></div></form>`;
}
function renderCapture(data) {
  state.entries=data.recent;
  const first=user.display_name.split(' ')[0];
  main.innerHTML=heading(`Guten Tag, ${first}.`,'Ein kurzer Eintrag. Und deine Arbeit ist festgehalten.')+
    `<div class="stats">${stat('Heute erfasst',duration(data.summary.today),'Dein Aufwand am heutigen Tag','clock',true)}${stat('Dieser Monat',duration(data.summary.month),'Deine erfasste Arbeitszeit','calendar')}${stat('Offene Entwürfe',data.summary.drafts,'Noch nicht für den Kunden freigegeben','list')}${stat('Gemeinsam im Blick','Dein Team','Alle Tätigkeiten in der Teamübersicht','team')}</div>
    <div class="capture-layout"><section class="panel"><div class="panel-head"><h2>Neue Arbeitszeit</h2><span class="badge badge-released">Einfach erfasst</span></div><div class="panel-body">${entryForm()}</div></section>
    <aside class="panel"><div class="panel-head"><h2>Deine letzten 7 Tage</h2>${icon('calendar')}</div><div class="panel-body"><div class="text-3xl font-semibold tracking-tight">${duration(data.days.reduce((s,d)=>s+d.minutes,0))}</div><p class="helper mt-2">Erfasste Arbeitszeit</p><div class="weekly-bars">${data.days.map(d=>`<div class="weekly-day"><small>${d.minutes?Math.round(d.minutes/6)/10+' h':'–'}</small><meter min="0" max="${Math.max(480,...data.days.map(d=>d.minutes))}" value="${Math.max(0,d.minutes)}" aria-label="${dateLabel(d.date)}: ${duration(d.minutes)}"></meter><span>${new Date(d.date+'T12:00').toLocaleDateString('de-DE',{weekday:'short'})}</span></div>`).join('')}</div><div class="hint-card"><strong>Die kleinen Aufgaben zählen auch.</strong><p class="mt-2">Trage deine Zeit am besten direkt nach der Tätigkeit ein. Auch fünf Minuten sind schnell festgehalten.</p></div></div></aside></div>
    <section class="panel mt-6"><div class="panel-head"><h2>Deine letzten Einträge</h2><a href="#mine" class="link-button">Alle ansehen →</a></div>${entryList(data.recent,false)}</section>`;
}
function entryList(entries,selectable) {
  if(!entries.length) return empty('Hier ist noch Platz für gute Arbeit.','Für diesen Zeitraum gibt es noch keine Einträge. Passe den Filter an oder erfasse deine erste Tätigkeit.');
  return `<div class="entries-list">${entries.map(entry=>`<article class="entry-row">${selectable?`<input type="checkbox" data-select="${entry.id}" aria-label="Eintrag ${entry.id} auswählen" ${state.selected.has(entry.id)?'checked':''}>`:''}<span class="avatar hidden sm:flex">${esc(entry.display_name.charAt(0).toUpperCase())}</span><div class="entry-body"><div class="entry-title">${esc(entry.description)}</div><div class="entry-meta"><span>${esc(entry.display_name)}</span><span>${esc(dateLabel(entry.service_date))}</span>${entry.category?`<span>${esc(entry.category)}</span>`:''}${!entry.billable?'<span>Nicht abrechenbar</span>':''}${badge(entry.status)}${entry.kind==='adjustment'?`<span class="badge badge-adjustment">Korrektur zu #${entry.original_id}</span>`:''}</div>
    ${entry.released_at&&user.kind==='customer'?`<div class="helper mt-1">Veröffentlicht: ${esc(stamp(entry.released_at))}</div>`:''}
    ${entry.invoice_reference?`<div class="helper mt-1">Rechnung: ${esc(entry.invoice_reference)}</div>`:''}
    <div class="entry-actions">${entry.editable && entry.kind==='time'?`<button class="link-button" data-command="edit-entry" data-id="${entry.id}">Bearbeiten</button>`:''}${entry.editable?`<button class="link-button" data-command="delete-entry" data-id="${entry.id}">Löschen</button>`:''}${entry.kind==='time' && Number(entry.user_id)===Number(user.id) && user.kind==='internal'?`<button class="link-button" data-command="copy-entry" data-id="${entry.id}">Übernehmen</button>`:''}${can('billing.correct')&&entry.kind==='time'&&entry.status==='billed'?`<button class="link-button" data-command="adjust-entry" data-id="${entry.id}">Korrigieren</button>`:''}</div></div><div class="entry-right"><span class="entry-time">${esc(duration(entry.minutes))}</span>${finance()?`<span class="entry-amount">${esc(money(entry.amount_cents))}</span>`:''}${finance()&&entry.rate_cents!==null?`<span class="helper">${esc(money(entry.rate_cents))}/h</span>`:''}<span class="text-[9px] muted">#${entry.id}</span></div></article>`).join('')}</div>`;
}
function renderReport(data) {
  state.entries=data.entries; state.page=data.page; state.selected.clear();
  const titles={mine:['Meine Zeiten','Deine Arbeit, chronologisch und nachvollziehbar.'],team:['Teamübersicht','Seht gemeinsam, was ihr geleistet habt.'],billing:['Abrechnung','Leistungen prüfen, freigeben und abrechnen.'],customer:['Leistungsübersicht','Alle für dich freigegebenen IT-Leistungen auf einen Blick.']};
  const [title,subtitle]=titles[state.route];
  const select=state.route==='billing'&&(can('entries.release')||can('billing.finalize')||can('rates.manage'));
  const f=state.filters;
  main.innerHTML=heading(title,subtitle,`<a class="btn btn-secondary" data-export href="/export?${esc(new URLSearchParams(f))}">${icon('download')} CSV exportieren</a>`)+
    `<div class="stats">${stat('Gesamter Aufwand',duration(data.summary.minutes),'Im gewählten Zeitraum','clock',true)}${stat('Abrechenbare Zeit',duration(data.summary.billable_minutes),`${duration(data.summary.minutes-data.summary.billable_minutes)} nicht abrechenbar`,'check')}${finance()?stat('Gesamtbetrag',money(data.summary.amount_cents),user.kind==='customer'?'Netto · veröffentlichte Leistungen':`${money(data.summary.draft_amount_cents)} davon vorläufig`,'rates'):stat('Einträge',data.summary.count,'Erfasste Tätigkeiten und Korrekturen','list')}${user.kind==='customer'?stat('Positionen',data.summary.count,'Veröffentlichte Leistungen und Korrekturen','list'):stat('Entwürfe',data.summary.drafts,finance()&&data.summary.unpriced?`${data.summary.unpriced} davon ohne Betrag`:'Intern sichtbar, noch nicht veröffentlicht','list')}</div>
    <form id="filters" class="filters"><label>Von<input type="date" name="from" value="${f.from}" required></label><label>Bis<input type="date" name="to" value="${f.to}" required></label>${state.route==='mine'?hidden('user_id',user.id):`<label>Person<select name="user_id"><option value="">Alle Personen</option>${state.people.map(p=>`<option value="${p.id}" ${String(p.id)===f.user_id?'selected':''}>${esc(p.display_name)}</option>`).join('')}</select></label>`}<label>Status<select name="status"><option value="">Alle Status</option>${Object.entries(statusName).filter(([s])=>user.kind!=='customer'||s!=='draft').map(([s,n])=>`<option value="${s}" ${s===f.status?'selected':''}>${n}</option>`).join('')}</select></label><label>Abrechenbar<select name="billable"><option value="">Alle</option><option value="1" ${f.billable==='1'?'selected':''}>Ja</option><option value="0" ${f.billable==='0'?'selected':''}>Nein</option></select></label><label>Tätigkeit suchen<input name="search" value="${esc(f.search)}" maxlength="100" placeholder="Suchbegriff …"></label><button class="btn btn-secondary" type="submit">Anwenden</button></form>
    <section class="panel"><div class="panel-head"><h2>Leistungen <span class="muted text-sm font-normal">· ${data.summary.count}</span></h2><span class="helper">${dateLabel(f.from)} – ${dateLabel(f.to)}</span></div>${select?`<div class="bulk-toolbar"><label class="check-label mr-2"><input type="checkbox" id="select-all"> Seite auswählen</label>${can('entries.release')?'<button class="btn btn-small btn-primary" data-transition="release">Freigeben</button><button class="btn btn-small btn-secondary" data-transition="recall">Zurückziehen</button>':''}${can('billing.finalize')?'<button class="btn btn-small btn-secondary" data-transition="bill">Abgerechnet</button>':''}${can('rates.manage')?'<button class="btn btn-small btn-secondary" data-transition="reprice">Neu berechnen</button>':''}<span id="selected-count" class="ml-auto muted">0 ausgewählt</span></div>`:''}${entryList(data.entries,select)}${pagination(data.page,data.pages)}</section>
    ${data.by_person.length?`<section class="panel mt-6"><div class="panel-head"><h2>Aufwand nach Person</h2><span class="helper">Gleicher Filter wie oben</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Person</th><th>Arbeitszeit</th><th>Abrechenbar</th><th>Nicht abrechenbar</th>${finance()?'<th>Betrag netto</th>':''}</tr></thead><tbody>${data.by_person.map(p=>`<tr><td>${esc(p.display_name)}</td><td>${esc(duration(p.minutes))}</td><td>${esc(duration(p.billable_minutes))}</td><td>${esc(duration(p.minutes-p.billable_minutes))}</td>${finance()?`<td>${esc(money(p.amount_cents))}</td>`:''}</tr>`).join('')}</tbody></table></div></section>`:''}`;
  state.selected.clear();
}
function pagination(page,pages) { return `<div class="pagination"><span>Seite ${page} von ${pages}</span><div class="flex gap-2"><button class="btn btn-small btn-secondary" data-page="${page-1}" ${page<=1?'disabled':''}>← Zurück</button><button class="btn btn-small btn-secondary" data-page="${page+1}" ${page>=pages?'disabled':''}>Weiter →</button></div></div>`; }
function renderPassword(required=false) {
  const form=`<form data-action="password.change" class="form-stack"><p class="helper">${required?'Bitte ersetze dein Initialpasswort, bevor du deinen Arbeitsbereich öffnest.':'Andere aktive Sitzungen werden durch die Änderung abgemeldet.'}</p><label>Aktuelles Passwort<input type="password" name="current_password" required maxlength="256" autocomplete="current-password"></label><label>Neues Passwort<input type="password" name="new_password" required minlength="12" maxlength="256" autocomplete="new-password"></label><label>Neues Passwort wiederholen<input type="password" name="confirm_password" required minlength="12" maxlength="256" autocomplete="new-password"></label><p class="helper">Mindestens 12 Zeichen. Eine lange Passphrase ist leicht zu merken.</p>${errorBox}<button class="btn btn-primary" type="submit">Passwort ändern</button></form>`;
  if(required) main.innerHTML=heading('Ein eigenes Passwort für dich.','Dein Initialzugang ist nur für den ersten Schritt gedacht.')+`<div class="panel panel-body max-w-xl">${form}</div>`;
  else modalOpen('Passwort ändern',form);
}

function renderAdmin(route,data) {
  if(route==='users') {
    main.innerHTML=heading('Benutzer verwalten','Lokale Zugänge anlegen und passende Rechte vergeben.',`<button class="btn btn-primary" data-command="new-user">${icon('plus')} Benutzer anlegen</button>`)+
      `<section class="panel"><div class="panel-head"><h2>Alle Zugänge</h2><span class="helper">${data.users.length} Benutzer</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Person</th><th>Rollen</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>${data.users.map(u=>`<tr><td><strong>${esc(u.display_name)}</strong><div class="helper mt-1">${esc(u.username)} · ${u.kind==='customer'?'Kunde':'Intern'}</div></td><td>${u.role_ids.map(id=>esc(data.roles.find(r=>r.id===id)?.name||'')).join(', ')}<div class="helper mt-1">${u.permissions.length} Zusatzrechte</div></td><td><span class="badge ${u.active?'badge-released':'badge-draft'}">${u.active?'Aktiv':'Deaktiviert'}</span>${u.must_change_password?'<div class="helper mt-1">Passwortwechsel erforderlich</div>':''}</td><td><div class="flex flex-wrap gap-3"><button class="link-button" data-command="edit-user" data-id="${u.id}">Bearbeiten</button><button class="link-button" data-command="reset-user" data-id="${u.id}">Passwort zurücksetzen</button></div></td></tr>`).join('')}</tbody></table></div></section>`;
  } else {
    main.innerHTML=heading('Rollen & Rechte','Klare Zuständigkeiten. Teamzeiten sind für alle internen Konten sichtbar.',`<button class="btn btn-primary" data-command="new-role">${icon('plus')} Rolle anlegen</button>`)+
      `<div class="role-grid">${data.roles.map(role=>`<section class="role-card"><div class="flex items-center justify-between"><h3>${esc(role.name)}</h3>${icon('shield')}</div><p class="helper mb-4">${role.is_admin?'Vollständige Administration. Geschützte Systemrolle.':role.system_key==='customer'?'Nur freigegebene Leistungen der eigenen Firma.':role.permissions.length?role.permissions.map(p=>esc(data.permissions[p])).join(' · '):'Eigene Zeiten erfassen und die Teamübersicht lesen.'}</p>${!['admin','customer'].includes(role.system_key)?`<button class="btn btn-small btn-secondary" data-command="edit-role" data-id="${role.id}">Rolle bearbeiten</button>`:'<span class="badge badge-billed">Systemrolle</span>'}</section>`).join('')}</div><p class="helper mt-5">Rechte aus Rollen und Zusatzrechten werden kombiniert. Finanzbezogene Verwaltungsrechte schließen die Betragsansicht ein.</p>`;
  }
}
function permissionChecks(selected=[]) { return `<fieldset class="permission-list"><legend class="helper px-1">Zusätzliche Berechtigungen</legend>${Object.entries(state.admin.permissions).map(([key,label])=>`<label class="check-label"><input type="checkbox" name="permissions" value="${key}" ${selected.includes(key)?'checked':''}>${esc(label)}</label>`).join('')}</fieldset><p class="helper">Abrechnung, Stundensatzverwaltung und Historie vergeben automatisch auch die Betragsansicht.</p>`; }
function adminPassword() { return '<label>Dein Administrator-Passwort<input name="admin_password" type="password" required maxlength="256" autocomplete="current-password"></label>'; }
function userForm(u=null) {
  const kind=u?.kind||'internal';
  const selected=u?.role_ids||[1];
  modalOpen(u?'Benutzer bearbeiten':'Benutzer anlegen',`<form data-action="user.save" class="form-stack">${u?hidden('id',u.id)+hidden('auth_version',u.auth_version):''}<div class="field-row"><label>Anzeigename<input name="display_name" required maxlength="100" value="${esc(u?.display_name||'')}"></label><label>Benutzername<input name="username" required pattern="[a-z0-9][a-z0-9._-]{2,63}" maxlength="64" value="${esc(u?.username||'')}" autocomplete="off"></label></div><label>Kontotyp<select name="kind" id="user-kind"><option value="internal" ${kind==='internal'?'selected':''}>Internes Teammitglied</option><option value="customer" ${kind==='customer'?'selected':''}>Kunde (nur lesen)</option></select></label><fieldset class="permission-list" id="role-options"><legend class="helper px-1">Rollen</legend>${state.admin.roles.map(r=>`<label class="check-label" data-role-kind="${r.system_key==='customer'?'customer':'internal'}" ${((r.system_key==='customer')!==(kind==='customer'))?'hidden':''}><input name="role_ids" type="checkbox" value="${r.id}" ${selected.includes(r.id)?'checked':''}>${esc(r.name)}</label>`).join('')}</fieldset><div id="user-permissions" ${kind==='customer'?'hidden':''}>${permissionChecks(u?.permissions||[])}</div>${u?`<details class="helper"><summary>Aktuell wirksame Rechte anzeigen</summary><p class="mt-2">${u.effective_permissions.map(p=>esc(state.admin.permissions[p])).join(' · ')||'Keine zusätzlichen Fachrechte'}</p></details>`:''}<label class="check-label"><input type="checkbox" name="active" ${!u||u.active?'checked':''}> Zugang aktiv</label>${adminPassword()}${actions(u?'Änderungen speichern':'Benutzer anlegen')}</form>`);
}
function roleForm(role=null) { modalOpen(role?'Rolle bearbeiten':'Rolle anlegen',`<form data-action="role.save" class="form-stack">${role?hidden('id',role.id)+hidden('version',role.version):''}<label>Rollenname<input name="name" value="${esc(role?.name||'')}" required maxlength="80"></label>${permissionChecks(role?.permissions||[])}${adminPassword()}${actions()}</form>`); }
function renderRates(data) {
  main.innerHTML=heading('Stundensätze','Gültig nach Leistungsdatum. Bestehende Einträge behalten ihren gespeicherten Satz.',`<button class="btn btn-primary" data-command="new-rate">${icon('plus')} Stundensatz anlegen</button>`)+
    `<section class="panel"><div class="panel-head"><h2>Gültigkeitszeiträume</h2><span class="helper">Persönlicher Satz vor Firmenstandard</span></div>${data.rates.length?`<div class="table-wrap"><table class="data-table"><thead><tr><th>Zuordnung</th><th>Gültig ab</th><th>Gültig bis (exklusiv)</th><th>Satz netto</th><th></th></tr></thead><tbody>${data.rates.map(r=>`<tr><td>${esc(r.display_name||'Firmenstandard')}</td><td>${dateLabel(r.valid_from)}</td><td>${r.valid_until?dateLabel(r.valid_until):'Unbegrenzt'}</td><td class="font-semibold">${money(r.cents_per_hour)} / h</td><td><button class="link-button" data-command="edit-rate" data-id="${r.id}">Bearbeiten</button></td></tr>`).join('')}</tbody></table></div>`:empty('Noch kein Stundensatz hinterlegt.','Lege einen Firmenstandard oder einen persönlichen Satz an. Zeiten können schon vorher als Entwurf erfasst werden.')}</section><div class="hint-card">Für einen neuen Satz zuerst das Enddatum des bisherigen Zeitraums auf das neue Startdatum setzen. Bereits gespeicherte Beträge bleiben erhalten. Entwürfe lassen sich in der Abrechnung gezielt neu berechnen.</div>`;
}
function rateForm(rate=null) { modalOpen(rate?'Stundensatz bearbeiten':'Stundensatz anlegen',`<form data-action="rate.save" class="form-stack">${rate?hidden('id',rate.id)+hidden('version',rate.version):''}<label>Zuordnung<select name="user_id"><option value="">Firmenstandard</option>${state.people.map(p=>`<option value="${p.id}" ${p.id===rate?.user_id?'selected':''}>${esc(p.display_name)}</option>`).join('')}</select></label><label>Stundensatz netto in EUR<input name="rate" inputmode="decimal" required placeholder="80,00" value="${rate?euroInput(rate.cents_per_hour):''}"></label><div class="field-row"><label>Gültig ab<input type="date" name="valid_from" required value="${rate?.valid_from||boot.today}"></label><label>Gültig bis (exklusiv)<input type="date" name="valid_until" value="${rate?.valid_until||''}"></label></div><p class="helper">Das Enddatum selbst gehört zum folgenden Zeitraum. Leer bedeutet unbegrenzt.</p>${rate?'<label>Änderungsgrund<input name="reason" required maxlength="500"></label>':''}${actions()}</form>`); }
function renderAudit(data) {
  const names={'entry.created':'Arbeitszeit erfasst','entry.updated':'Arbeitszeit geändert','entry.deleted':'Eintrag gelöscht','entry.release':'Leistung freigegeben','entry.recall':'Freigabe zurückgezogen','entry.bill':'Als abgerechnet markiert','entry.reprice':'Stundensatz neu zugeordnet','user.created':'Benutzer angelegt','user.updated':'Benutzerrechte geändert','user.password_reset':'Passwort zurückgesetzt','password.changed':'Passwort geändert','rate.saved':'Stundensatz gespeichert','role.saved':'Rolle gespeichert','adjustment.created':'Korrektur angelegt','admin.bootstrap':'Erster Admin angelegt'};
  main.innerHTML=heading('Änderungshistorie','Wer hat wann was verändert? Die interne Historie schafft Klarheit.')+`<section class="panel">${data.events.length?data.events.map(event=>`<article class="audit-item"><div class="flex flex-wrap items-center justify-between gap-2"><strong>${esc(names[event.action]||event.action)}</strong><span class="muted">${stamp(event.created_at)}</span></div><p class="helper mt-2">${esc(event.actor_name||'System')} · ${esc(event.entity)} #${event.entity_id||'–'}</p>${event.reason?`<p class="mt-2">${esc(event.reason)}</p>`:''}<details class="mt-3"><summary class="link-button cursor-pointer">Änderungsdetails</summary><pre>${esc(JSON.stringify({vorher:event.before_json?JSON.parse(event.before_json):null,nachher:event.after_json?JSON.parse(event.after_json):null},null,2))}</pre></details></article>`).join(''):empty('Noch keine Änderungen.','Sobald etwas gespeichert wird, erscheint hier die Historie.')}${pagination(data.page,data.pages)}</section>`;
}
function payloadFrom(form) {
  const data=Object.fromEntries(new FormData(form));
  for(const key of ['billable','active']) if(form.elements[key]) data[key]=form.elements[key].checked;
  if(form.dataset.action==='user.save') {
    data.role_ids=[...form.querySelectorAll('[name=role_ids]:checked')].map(el=>el.value);
    data.permissions=[...form.querySelectorAll('[name=permissions]:checked')].map(el=>el.value);
  }
  if(form.dataset.action==='role.save') data.permissions=[...form.querySelectorAll('[name=permissions]:checked')].map(el=>el.value);
  if(form.dataset.action==='entry.transition') { data.items=JSON.parse(data.items); }
  return data;
}
document.addEventListener('submit',async event=>{
  const form=event.target;
  if(form.id==='filters') { event.preventDefault(); state.filters=Object.fromEntries(new FormData(form)); state.page=1; state.selected.clear(); await render(); return; }
  if(!form.dataset.action) return;
  event.preventDefault(); if(form.dataset.busy==='1') return;
  const action=form.dataset.action, data=payloadFrom(form), button=form.querySelector('[type=submit]'), error=form.querySelector('.form-error');
  if(error) error.hidden=true;
  form.dataset.busy='1'; button.disabled=true; const old=button.innerHTML; button.textContent='Wird gespeichert …';
  try {
    const result=await post(action,data);
    if(['login','password.change'].includes(action)) { location.href='/'; return; }
    if(result.initial_password) {
      modalOpen('Zugang bereit',`<p class="text-sm">Dieses Initialpasswort wird nur jetzt angezeigt und ist 24 Stunden gültig. Beim ersten Login ist ein eigenes Passwort erforderlich.</p><div class="secret-box">${esc(result.initial_password)}</div><p class="helper mt-3">Bitte sicher an die betreffende Person weitergeben.</p><div class="dialog-actions"><button class="btn btn-primary" data-command="${result.self_changed?'reload':'close'}">Verstanden</button></div>`);
      if(!result.self_changed) await render();
    } else if(result.self_changed) { location.reload(); }
    else { if(modal.open) modal.close(); await render(); toast(result.warning || (action==='entry.save'?'Arbeitszeit gespeichert.':'Änderungen gespeichert.')); }
  } catch(err) { if(error) { error.textContent=err.message; error.hidden=false; error.scrollIntoView({block:'nearest'}); } else toast(err.message,true); }
  finally { form.dataset.busy='0'; button.disabled=false; button.innerHTML=old; }
});
document.addEventListener('change',event=>{
  const el=event.target;
  if(el.id==='user-kind') {
    const customer=el.value==='customer';
    document.querySelectorAll('[data-role-kind]').forEach(label=>{label.hidden=label.dataset.roleKind!==el.value; label.querySelector('input').checked=customer?label.dataset.roleKind==='customer':label.querySelector('input').value==='1';});
    document.getElementById('user-permissions').hidden=customer;
    if(customer) document.querySelectorAll('[name=permissions]').forEach(input=>input.checked=false);
  }
  if(el.dataset.select) { const id=Number(el.dataset.select); el.checked?state.selected.add(id):state.selected.delete(id); }
  if(el.id==='select-all') document.querySelectorAll('[data-select]').forEach(input=>{input.checked=el.checked; el.checked?state.selected.add(Number(input.dataset.select)):state.selected.delete(Number(input.dataset.select));});
  const label=document.getElementById('selected-count'); if(label) label.textContent=state.selected.size+' ausgewählt';
});
document.addEventListener('click',async event=>{
  const el=event.target.closest('[data-command],[data-minutes],[data-page],[data-transition]'); if(!el) return;
  try {
    if(el.dataset.minutes) { const form=el.closest('form'); form.elements.minutes.value=el.dataset.minutes; form.querySelectorAll('[data-minutes]').forEach(b=>b.classList.toggle('selected',b===el)); form.elements.description.focus(); return; }
    if(el.dataset.page) { state.page=Number(el.dataset.page); state.selected.clear(); await render(); return; }
    if(el.dataset.transition) { showTransition(el.dataset.transition); return; }
    const command=el.dataset.command, id=Number(el.dataset.id), entry=state.entries.find(e=>e.id===id);
    if(command==='close') modal.close();
    else if(command==='reload') location.reload();
    else if(command==='refresh') await render();
    else if(command==='menu') { const open=document.getElementById('sidebar').classList.toggle('open'); el.setAttribute('aria-expanded',String(open)); }
    else if(command==='logout') { await post('logout',{}); location.href='/'; }
    else if(command==='profile') renderPassword(false);
    else if(command==='edit-entry'&&entry) modalOpen('Arbeitszeit bearbeiten',entryForm(entry));
    else if(command==='copy-entry'&&entry) { const copy={...entry,id:null,service_date:boot.today}; modalOpen('Tätigkeit übernehmen',entryForm(copy)); }
    else if(command==='delete-entry'&&entry) modalOpen('Eintrag löschen?',`<form data-action="entry.delete" class="form-stack">${hidden('id',entry.id)}${hidden('version',entry.version)}<p class="text-sm">${esc(entry.description)} · ${duration(entry.minutes)}</p><p class="helper">Der Eintrag wird aus den Übersichten entfernt. Die Änderung bleibt in der internen Historie nachvollziehbar.</p>${user.is_admin||entry.user_id!==user.id||entry.kind==='adjustment'?'<label>Grund<input name="reason" required maxlength="500"></label>':''}${actions('Eintrag löschen',true)}</form>`);
    else if(command==='adjust-entry'&&entry) {
      const base=await api('correction-base',{id});
      modalOpen('Abgerechnete Leistung korrigieren',`<form data-action="adjustment.create" class="form-stack">${hidden('original_id',entry.id)}${hidden('request_key',randomKey())}<p class="helper">Original #${entry.id} bleibt erhalten. Gib die gewünschten Gesamtergebnisse einschließlich bisheriger Korrekturen ein. Die Differenz wird als eigener Entwurf gespeichert.</p><div class="field-row"><label>Korrigierte Gesamtdauer (Minuten)<input name="target_minutes" type="number" min="0" max="1440" required value="${base.minutes}"></label><label>Korrigierter Gesamtbetrag (EUR)<input name="target_amount" inputmode="decimal" required value="${euroInput(base.amount_cents)}"></label></div><label>Erläuterung für den Kunden<textarea name="description" required maxlength="500"></textarea></label><label>Interner Änderungsgrund<input name="reason" required maxlength="500"></label>${actions('Korrekturentwurf speichern')}</form>`);
    }
    else if(command==='new-user') userForm();
    else if(command==='edit-user') userForm(state.admin.users.find(u=>u.id===id));
    else if(command==='reset-user') { const u=state.admin.users.find(u=>u.id===id); modalOpen('Passwort zurücksetzen',`<form data-action="user.reset" class="form-stack">${hidden('id',id)}<p class="text-sm">Für ${esc(u.display_name)} wird ein neuer Initialzugang erzeugt. Alle bisherigen Sitzungen werden ungültig.</p>${adminPassword()}${actions('Passwort zurücksetzen')}</form>`); }
    else if(command==='new-role') roleForm();
    else if(command==='edit-role') roleForm(state.admin.roles.find(r=>r.id===id));
    else if(command==='new-rate') rateForm();
    else if(command==='edit-rate') rateForm(state.rates.find(r=>r.id===id));
  } catch(error) { toast(error.message,true); }
});
function showTransition(transition) {
  const selected=state.entries.filter(e=>state.selected.has(e.id));
  if(!selected.length) { toast('Bitte zuerst mindestens einen Eintrag auswählen.',true); return; }
  const names={release:'Leistungen freigeben',recall:'Freigabe zurückziehen',bill:'Als abgerechnet markieren',reprice:'Entwürfe neu berechnen'};
  const explanations={release:'Die ausgewählten Positionen werden anschließend im Kundenbereich sichtbar.',recall:'Die Positionen werden wieder zu Entwürfen und verschwinden aus dem aktuellen Kundenbericht.',bill:'Die Positionen werden gesperrt. Spätere Änderungen erfolgen als separate Korrektur. Dies ist kein Zahlungsstatus.',reprice:'Der zum Leistungsdatum gültige Stundensatz wird für die ausgewählten Entwürfe neu zugeordnet.'};
  const missing=selected.some(e=>e.amount_cents===null);
  modalOpen(names[transition],`<form data-action="entry.transition" class="form-stack">${hidden('transition',transition)}${hidden('items',JSON.stringify(selected.map(e=>({id:e.id,version:e.version}))))}<p class="text-sm">${explanations[transition]}</p><div class="hint-card mt-0"><strong>${selected.length} Positionen · ${duration(selected.reduce((n,e)=>n+Number(e.minutes),0))}</strong><p class="mt-2">Abrechenbar: ${duration(selected.filter(e=>e.billable).reduce((n,e)=>n+Number(e.minutes),0))}</p><p class="mt-2">${money(selected.reduce((n,e)=>n+Number(e.amount_cents||0),0))} netto${missing?' · Unvollständig: Stundensätze fehlen':''}</p><p class="mt-1">${dateLabel(selected.map(e=>e.service_date).sort()[0])} – ${dateLabel(selected.map(e=>e.service_date).sort().at(-1))}</p></div>${['recall','reprice'].includes(transition)?'<label>Änderungsgrund<input name="reason" required maxlength="500"></label>':''}${transition==='bill'?'<label>Rechnungsreferenz (optional)<input name="invoice_reference" maxlength="100" placeholder="Zum Beispiel RE-2026-001"></label>':''}${actions(names[transition])}</form>`);
}
document.addEventListener('click',async event=>{
  const link=event.target.closest('[data-export]'); if(!link) return;
  event.preventDefault(); if(link.dataset.busy==='1') return;
  const old=link.innerHTML; link.dataset.busy='1'; link.setAttribute('aria-busy','true'); link.textContent='Export wird erstellt …';
  try {
    const response=await fetch(link.href);
    if(!response.ok) { let error; try { error=await response.json(); } catch {} throw new Error(error?.error||'Export fehlgeschlagen.'); }
    const url=URL.createObjectURL(await response.blob()); const download=document.createElement('a');
    download.href=url; download.download=(response.headers.get('Content-Disposition')||'').match(/filename="([^"]+)"/)?.[1]||'zeitwerk.csv';
    download.click(); setTimeout(()=>URL.revokeObjectURL(url),60000); toast('CSV-Export ist bereit.');
  } catch(error) { toast(error.message,true); }
  finally { link.innerHTML=old; link.dataset.busy='0'; link.removeAttribute('aria-busy'); }
});
window.addEventListener('hashchange',render);
if(user) { initNavigation(); render(); }
