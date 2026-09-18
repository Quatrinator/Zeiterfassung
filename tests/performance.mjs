import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
const base='http://localhost:18080';
const clients=[];
for(let i=1;i<=10;i++) {
  const c={cookie:'',csrf:''};
  c.request=async(path,payload)=>{
    const start=performance.now();
    const r=await fetch(base+path,{method:payload?'POST':'GET',headers:{Cookie:c.cookie,...(payload?{'Content-Type':'application/json','X-CSRF-Token':c.csrf}:{})},body:payload?JSON.stringify(payload):undefined});
    const cookies=r.headers.getSetCookie();if(cookies.length)c.cookie=cookies.map(x=>x.split(';')[0]).join('; ');
    const text=await r.text();assert.equal(r.status,200,text);
    return {ms:performance.now()-start,text};
  };
  const page=await c.request('/');c.csrf=JSON.parse(page.text.match(/<script id="boot" type="application\/json">(.*?)<\/script>/s)[1]).csrf;
  await c.request('/api',{action:'login',username:'load_'+i,password:'LocalTestPassphrase!2026'});
  c.csrf=JSON.parse((await c.request('/')).text.match(/<script id="boot" type="application\/json">(.*?)<\/script>/s)[1]).csrf;
  clients.push(c);
}
const readings=[],writes=[];
for(let round=0;round<3;round++) {
  const read=await Promise.all(clients.map(c=>c.request('/api?resource=entries&from=2026-01-01&to=2026-01-31')));
  for(const r of read) {assert.ok(JSON.parse(r.text).summary.count>=50000);readings.push(r.ms);}
  const write=await Promise.all(clients.map(c=>c.request('/api',{action:'entry.save',service_date:'2026-01-15',minutes:5,description:'Paralleler Speichertest',billable:true,request_key:randomUUID().replaceAll('-','')})));
  writes.push(...write.map(r=>r.ms));
}
const summarize=values=>({requests:values.length,min_ms:Math.round(Math.min(...values)),average_ms:Math.round(values.reduce((a,b)=>a+b,0)/values.length),max_ms:Math.round(Math.max(...values))});
console.log(JSON.stringify({concurrent_users:10,seeded_entries:50000,report:summarize(readings),save:summarize(writes)},null,2));
assert.ok(Math.max(...readings,...writes)<2000,'Local 2 second target exceeded');
