import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
const base='http://127.0.0.1:18080'; // Deliberately fixed to the isolated test project.
let checks=0;
const check=(value,label)=>{assert.ok(value,label);checks++;console.log('PASS '+label);};
class Client {
  cookie=''; csrf='';
  async request(path,body,headers={}) {
    const response=await fetch(base+path,{method:body?'POST':'GET',headers:{Cookie:this.cookie,...(body?{'Content-Type':'application/json','X-CSRF-Token':this.csrf}:{}),...headers},body:body?JSON.stringify(body):undefined});
    const cookies=response.headers.getSetCookie(); if(cookies.length) this.cookie=cookies.map(x=>x.split(';')[0]).join('; ');
    const text=await response.text(); return {status:response.status,headers:response.headers,text,data:response.headers.get('content-type')?.includes('application/json')?JSON.parse(text):null};
  }
  async page() { const r=await this.request('/');const boot=JSON.parse(r.text.match(/<script id="boot" type="application\/json">(.*?)<\/script>/s)[1]);this.csrf=boot.csrf;return r; }
  async login(username,password='LocalTestPassphrase!2026') {await this.page();const r=await this.request('/api',{action:'login',username,password});assert.equal(r.status,200);await this.page();}
}
const anon=new Client();const page=await anon.page();
check(page.headers.get('content-security-policy')?.includes("default-src 'self'"),'CSP header');
check(page.headers.get('set-cookie')?.includes('HttpOnly'),'HttpOnly session cookie');
check((await anon.request('/api?resource=entries')).status===401,'anonymous report denied');
check((await anon.request('/api',{action:'login',username:'qa_admin',password:'x'},{'X-CSRF-Token':''})).status===419,'missing CSRF denied');
check([403,404].includes((await anon.request('/.env')).status),'environment file inaccessible');
check((await anon.request('/src/bootstrap.php')).status===404,'source file inaccessible');
const admin=new Client(),member=new Client(),customer=new Client();
await admin.login('qa_admin');await member.login('qa_member');await customer.login('qa_customer');
check((await customer.request('/api?resource=admin')).status===403,'customer admin endpoint denied');
check((await member.request('/api?resource=rates')).status===403,'member rate endpoint denied');
const before=(await customer.request('/api?resource=entries')).data;
check(before.entries.every(e=>e.status!=='draft'),'customer receives only published rows');
const payload={action:'entry.save',service_date:new Intl.DateTimeFormat('en-CA',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date()),minutes:25,description:'HTTP '+randomUUID(),category:'Support',billable:true,request_key:randomUUID().replaceAll('-','')};
check((await customer.request('/api',payload)).status===403,'customer write denied');
check((await member.request('/api',payload,{Origin:'https://foreign.invalid'})).status===403,'foreign origin denied');
const [first,retry]=await Promise.all([member.request('/api',payload),member.request('/api',payload)]);
check(first.status===200&&retry.status===200&&first.data.id===retry.data.id,'concurrent duplicate submit produces one row');
const mine=(await member.request('/api?resource=entries')).data;
check(mine.entries.some(e=>e.user_id===1),'team member sees colleague activities');
check(mine.entries.every(e=>!('amount_cents' in e)&&!('rate_cents' in e)),'member JSON omits financial fields');
check((await customer.request('/api?resource=entries')).data.summary.count===before.summary.count,'draft stays invisible to customer');
let result=await admin.request('/api',{action:'entry.transition',transition:'release',items:[{id:first.data.id,version:1}]});
check(result.status===200,'admin releases via HTTP');
const published=(await customer.request('/api?resource=entries')).data.entries.find(e=>e.id===first.data.id);
check(published?.amount_cents===3542,'customer sees cent-exact published amount');
const csv=await customer.request('/export');
check(csv.status===200&&csv.headers.get('content-type').includes('text/csv')&&csv.text.includes(payload.description),'customer CSV contains published work');
const adminData=await admin.request('/api?resource=admin');
check(!adminData.text.includes('password_hash'),'admin endpoint omits password hashes');
const newUser=await admin.request('/api',{action:'user.save',username:'http_'+Date.now(),display_name:'HTTP Test',kind:'internal',role_ids:[1],permissions:[],active:true,admin_password:'LocalTestPassphrase!2026'});
check(newUser.status===200&&newUser.data.initial_password,'admin creates temporary password');
const users=(await admin.request('/api?resource=admin')).data.users;
const account=users.find(u=>u.id===newUser.data.id);const fresh=new Client();await fresh.login(account.username,newUser.data.initial_password);
check((await fresh.request('/api?resource=entries')).status===403,'initial password forces change');
const changed=await fresh.request('/api',{action:'password.change',current_password:newUser.data.initial_password,new_password:'ChangedTestPassphrase!2026',confirm_password:'ChangedTestPassphrase!2026'});
check(changed.status===200,'temporary password can be changed');await fresh.page();
check((await fresh.request('/api?resource=entries')).status===200,'changed password unlocks app');
await admin.request('/api',{action:'user.reset',id:account.id,admin_password:'LocalTestPassphrase!2026'});
check((await fresh.request('/api?resource=entries')).status===401,'password reset revokes existing session');
const audit=await admin.request('/api?resource=audit');
check(!audit.text.includes('password_hash')&&!audit.text.includes(newUser.data.initial_password),'audit response contains no password secrets');
check((await customer.request('/api?resource=templates')).status===403,'customer template catalog denied');
const templates=await member.request('/api?resource=templates');
check(templates.status===200&&templates.data.templates.length>=6,'members can choose shared defaults');
check((await member.request('/api',{action:'template.create',label:'Nicht erlaubt'})).status===403,'template creation permission enforced');
const label='HTTP-Vorlage '+randomUUID();
const createdTemplate=await admin.request('/api',{action:'template.create',label});
check(createdTemplate.status===200,'admin creates template');
const selected=await member.request('/api',{...payload,template_id:createdTemplate.data.id,description:'',request_key:randomUUID().replaceAll('-','')});
check(selected.status===200,'template-only entry accepted by HTTP API');
await admin.request('/api',{action:'template.delete',id:createdTemplate.data.id});
check((await member.request('/api?resource=entries')).data.entries.find(e=>e.id===selected.data.id)?.description===label,'deleted template leaves saved text intact');
check((await member.request('/api?resource=templates')).data.templates.every(t=>t.id!==createdTemplate.data.id),'deleted template removed from dropdown catalog');
console.log(`${checks} HTTP checks passed.`);
