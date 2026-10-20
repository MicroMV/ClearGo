import fs from 'node:fs';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
const root=process.cwd(), out=path.join(root,'qa-audit/2026-10-18'), php='C:/xamppl/php/php.exe';
const results=[];
const esc=v=>'"'+String(v??'').replaceAll('"','""')+'"';
export function save() { fs.writeFileSync(path.join(out,'executed-tests.json'),JSON.stringify(results,null,2)); const keys=['test_id','role','feature','test','result','milliseconds','evidence']; fs.writeFileSync(path.join(out,'executed-tests.csv'),[keys.join(','),...results.map(r=>keys.map(k=>esc(r[k])).join(','))].join('\n')); }
function add(role,feature,test,result,evidence,milliseconds=0) { const row={test_id:'TC-'+String(results.length+1).padStart(3,'0'),role,feature,test,result,milliseconds,evidence}; results.push(row); save(); return row; }
const safe = new Set(['bulk_clearance_test.php','office_remarks_test.php','student_dashboard_history_test.php','student_import_test.php','student_import_scale_test.php','staff_navigation_test.php','comlab_violations_modal_test.php','facilities_items_modal_test.php','clearance_report_workbook_test.php']);
const before=JSON.parse(fs.readFileSync(path.join(out,'source-hashes-before.json')));
const phpFiles=[...Object.keys(before).filter(f=>f.endsWith('.php')),...fs.readdirSync('tests').filter(f=>f.endsWith('.php')).map(f=>'tests/'+f)];
for (const [name,command,args] of [['PHP syntax',php,['-l']],['JavaScript syntax',process.execPath,['--check']]]) {
    const targets=name.startsWith('PHP')?phpFiles:Object.keys(before).filter(f=>f.endsWith('.js'));
    const checks=targets.map(file=>{const start=Date.now(),r=spawnSync(command,[...args,file],{encoding:'utf8',timeout:20000,windowsHide:true});return {file,status:r.status,ms:Date.now()-start,output:(r.stdout||'')+(r.stderr||''),error:r.error?.message};});
    fs.writeFileSync(path.join(out,name.startsWith('PHP')?'php-lint.json':'js-syntax.json'),JSON.stringify(checks,null,2));
    add('All',name,'Parse '+targets.length+' files without executing them',checks.every(r=>r.status===0)?'PASS':'FAIL',checks.filter(r=>r.status!==0).map(r=>r.file+': '+r.output).join('\n') || 'All files parsed successfully.',checks.reduce((n,r)=>n+r.ms,0));
}
for (const file of fs.readdirSync('tests').filter(f=>f.endsWith('.php'))) {
    if (!safe.has(file)) { add('Various',file,'Existing suite requires live MySQL or may change actual school records','BLOCKED','Not executed against the existing database. An isolated MySQL fixture is required.'); continue; }
    const start=Date.now(); const r=spawnSync(php,['-d','mysqlnd.net_read_timeout=3','tests/'+file],{encoding:'utf8',timeout:90000,windowsHide:true});
    const output=(r.stdout||'')+(r.stderr||'')+(r.error?.message||''); fs.writeFileSync(path.join(out,file+'.log'),output);
    add('Various',file,'Run existing suite with in-memory fixtures, source rendering, or temporary workbook files',r.status===0?'PASS':r.error?.code==='ETIMEDOUT'?'BLOCKED':'FAIL',output.trim(),Date.now()-start);
}
async function request(url,options={}) { const start=Date.now(); try {const r=await fetch('http://localhost/ClearGo'+url,{redirect:'manual',signal:AbortSignal.timeout(12000),...options}); const body=await r.text(); return {status:r.status,location:r.headers.get('location'),type:r.headers.get('content-type'),database:r.headers.get('x-cleargo-service-unavailable'),cookie:r.headers.get('set-cookie'),body,ms:Date.now()-start};}catch(e){return {status:0,ms:Date.now()-start,error:e.message,body:''};} }
const login=await request('/login.php');
add('Public','Login page','Unauthenticated login page renders',login.status===200&&login.body.includes('name="email"')?'PASS':'FAIL','HTTP '+login.status+'; form fields present='+login.body.includes('name="email"'),login.ms);
const health=await request('/auth/connection_status.php',{headers:{Accept:'application/json'}});
add('All','Live database availability','Ask the real server to execute SELECT 1',health.status===200&&health.body.includes('"available":true')?'PASS':'FAIL',JSON.stringify({status:health.status,db:health.database,body:health.body}),health.ms);
const gated=Object.keys(before).filter(f=>/^(student|dean|organization|student-council|saso|facilities|comlab|accounting|library|bookstore|clinic|registrar)\/.+\.php$/.test(f)&&!/(?:functions|dashboard_view|profile_picture_form)\.php$/.test(f));
for (const file of gated) {
    const r=await request('/'+file);
    let passed=[301,302,303,307,308].includes(r.status)&&r.location?.includes('/login.php') || [401,403].includes(r.status);
    let redirectChain;
    if (!passed && file==='student/attendance_checkin.php' && r.status===303 && r.location==='/ClearGo/student/dashboard.php') {
        const next=await request('/student/dashboard.php');
        passed=next.status===303&&next.location==='/ClearGo/login.php';
        redirectChain={status:next.status,location:next.location};
    }
    add(file.split('/')[0],file,'Open protected route without a login cookie',passed?'PASS':r.status===0?'BLOCKED':'FAIL',JSON.stringify({status:r.status,location:r.location,redirectChain,raw_error:/SQLSTATE|Fatal error|Warning:|Stack trace|[A-Z]:\\xamppl/i.test(r.body)}),r.ms);
}
const token=login.body.match(/name="csrf_token" value="([^"]+)/)?.[1], cookie=login.cookie?.split(';')[0];
if(token&&cookie) for(const [name,body] of [
    ['Missing CSRF token',new URLSearchParams({email:'qa.invalid@example.invalid',password:'invalid'})],
    ['Invalid CSRF token',new URLSearchParams({csrf_token:'invalid',email:'qa.invalid@example.invalid',password:'invalid'})],
    ['Empty required fields',new URLSearchParams({csrf_token:token,email:'',password:''})],
    ['Foreign email domain',new URLSearchParams({csrf_token:token,email:'qa.invalid@example.invalid',password:'invalid'})],
    ['Array CSRF input',new URLSearchParams([['csrf_token[]','invalid'],['email','qa.invalid@example.invalid'],['password','invalid']])]
]) {
    const r=await request('/auth/login_process.php',{method:'POST',headers:{Cookie:cookie},body});
    const passed=r.status===303&&r.location?.endsWith('/login.php');
    add('Public','Login validation',name,passed?'PASS':'FAIL',JSON.stringify({status:r.status,location:r.location,raw_error:/SQLSTATE|Fatal error|Warning:|Stack trace|[A-Z]:\\xamppl/i.test(r.body),body:r.status>=500?r.body.slice(0,1400):undefined}),r.ms);
}
for(const url of ['/config/','/database/','/storage/','/storage/private/','/storage/sessions/','/.git/config','/.tmp/','/tests/']) {
    const r=await request(url,{method:'HEAD'});
    add('Public','Sensitive route exposure','HEAD '+url,[403,404].includes(r.status)?'PASS':r.status===0?'BLOCKED':'FAIL','HTTP '+r.status+'; no private file body was requested.',r.ms);
}
const testRoute=await request('/tests/staff_navigation_test.php');
add('Public','Test runner exposure','Invoke the harmless navigation-only regression through HTTP',[403,404].includes(testRoute.status)?'PASS':'FAIL','HTTP '+testRoute.status+'; '+testRoute.body.slice(0,400),testRoute.ms);
const manifest=Object.keys(before).filter(f=>/manifest|webmanifest/.test(f));
add('All','PWA install metadata','Discover a web app manifest and link',manifest.length?'NOT TESTED':'NOT IMPLEMENTED','Manifest files discovered: '+manifest.length+'. Service worker presence alone does not establish installability.');
console.log(JSON.stringify({total:results.length,counts:results.reduce((n,r)=>(n[r.result]=(n[r.result]||0)+1,n),{}),failed:results.filter(r=>r.result==='FAIL').map(r=>({id:r.test_id,feature:r.feature,test:r.test,evidence:r.evidence}))},null,2));
