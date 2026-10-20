import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
const root = process.cwd();
const out = path.join(root, 'qa-audit/2026-10-18');
fs.mkdirSync(out, {recursive:true});
const skip = new Set(['.git','.tmp','qa-audit','storage','node_modules']);
function walk(dir) { return fs.readdirSync(dir,{withFileTypes:true}).flatMap(e => skip.has(e.name) ? [] : e.isDirectory() ? walk(path.join(dir,e.name)) : [path.join(dir,e.name)]); }
const files = walk(root).filter(f => /\.(php|js|css|html|sql|json)$|\.htaccess$|AGENTS\.md$/.test(f));
const appFiles = files.filter(f => !path.relative(root,f).startsWith('tests'+path.sep));
const rel = f => path.relative(root,f).replaceAll('\\','/');
const hash = f => crypto.createHash('sha256').update(fs.readFileSync(f)).digest('hex');
const esc = v => '"'+String(v??'').replaceAll('"','""')+'"';
function csv(name, rows) { if (!rows.length) return; const keys=Object.keys(rows[0]); fs.writeFileSync(path.join(out,name),[keys.map(esc).join(','),...rows.map(r=>keys.map(k=>esc(r[k])).join(','))].join('\n')); }
const roleMap = {'student':'Student','student-council':'Student Council','organization':'Organization','saso':'SASO','dean':'Dean','registrar':'Registrar','library':'Library','clinic':'Clinic','bookstore':'Bookstore','accounting':'Accounting','comlab':'Computer Lab','facilities':'Facilities'};
const roles = ['STUDENT','ORG_STAFF','DEAN','SASO_STAFF','STUDENT_COUNCIL_STAFF','LIBRARY_STAFF','BOOKSTORE_STAFF','CLINIC_STAFF','FACILITIES_STAFF','COMLAB_STAFF','ACCOUNTING_STAFF','REGISTRAR'];
const inventory=[], controls=[], functions=[], routes=[], actions=[];
for (const file of appFiles) {
    const p=rel(file), source=fs.readFileSync(file,'utf8'), role=roleMap[p.split('/')[0]] || 'Shared';
    const line = n => source.slice(0,n).split('\n').length;
    if (file.endsWith('.php')) {
        const kind = /<!doctype|<html|<main|<body/i.test(source) ? 'UI/template' : /require.*office_dashboard|require.*event_attendance_page/.test(source) ? 'UI wrapper' : /(?:_action|\/action|_process|_submit|\/submit|_image|\/download|_status|\/notifications|\/logout)\.php$/.test(p) ? 'Request handler' : 'Helper/include (direct request safety reviewed separately)';
        routes.push({file:p,role,kind,guard:(source.match(/requireRole\(['"]([^'"]+)/)||[])[1] || (/requireLogin\(/.test(source)?'requireLogin':'Delegated or absent'),status:'NOT TESTED'});
    }
    if (/\.(php|js)$/.test(file)) for (const m of source.matchAll(/\bfunction\s+([A-Za-z_$][\w$]*)\s*\(/g)) functions.push({file:p,line:line(m.index),role,function:m[1],status:'NOT TESTED',evidence:'Source discovery only; execution requires a mapped test.'});
    if (/\.(php|html|js)$/.test(file)) for (const m of source.matchAll(/<(button|a|input|select|textarea|form|dialog|summary|iframe)\b[\s\S]*?>/gi)) {
        const snippet=m[0].replace(/\s+/g,' ').slice(0,380);
        controls.push({file:p,line:line(m.index),role,type:m[1].toLowerCase(),name:(snippet.match(/\bname=["']([^"']+)/)||[])[1]||'',snippet,status:'NOT TESTED',evidence:'Static source occurrence; loops and conditional rendering are not expanded.'});
    }
    if (/\.(php|html)$/.test(file)) {
        for (const m of source.matchAll(/<input\b(?=[^>]*\bname=["']action["'])(?=[^>]*\bvalue=["']([^"']+)["'])[^>]*>/gi)) actions.push({file:p,line:line(m.index),role,action:m[1],status:'NOT TESTED',evidence:'Declared form action; backend execution not implied.'});
        for (const m of source.matchAll(/\bcase\s+["']([^"']+)["']\s*:/g)) actions.push({file:p,line:line(m.index),role,action:m[1],status:'NOT TESTED',evidence:'Switch branch; may be an action or rendering branch.'});
        for (const m of source.matchAll(/\$action\s*===\s*["']([^"']+)["']/g)) actions.push({file:p,line:line(m.index),role,action:m[1],status:'NOT TESTED',evidence:'Backend action branch; execution requires an isolated request and database checks.'});
        for (const m of source.matchAll(/<button\b(?=[^>]*\bname=["']action["'])(?=[^>]*\bvalue=["']([^"']+)["'])[^>]*>/gi)) actions.push({file:p,line:line(m.index),role,action:m[1],status:'NOT TESTED',evidence:'Declared submit-button action.'});
    }
}
let n=1;
for (const [type,rows] of [['ROUTE',routes],['FUNCTION',functions],['CONTROL',controls],['ACTION',actions]]) for (const r of rows) inventory.push({test_id:'INV-'+String(n++).padStart(4,'0'),kind:type,role:r.role,file:r.file,line:r.line||1,feature:r.function||r.action||r.type||r.kind,description:r.snippet||r.function||r.action||r.kind,result:'NOT TESTED',evidence:r.evidence||'Awaiting route probe or runtime test; inventory is not proof of functionality.'});
csv('source-files.csv',appFiles.map(f=>({file:rel(f),bytes:fs.statSync(f).size,sha256:hash(f)})));
csv('routes.csv',routes); csv('functions.csv',functions); csv('controls.csv',controls); csv('actions.csv',actions); csv('coverage-matrix.csv',inventory);
const summary={audit_date:'2026-10-18',timezone:'Asia/Manila',roles,source_files:appFiles.length,php_files:routes.length,ui_source_files:routes.filter(r=>r.kind.startsWith('UI')).length,function_declarations:functions.length,control_occurrences:controls.length,action_occurrences:actions.length,inventory_cases:inventory.length,limitations:'Counts are source occurrences, not unique rendered controls; dynamically generated controls and expanded loop instances require browser inspection.'};
fs.writeFileSync(path.join(out,'inventory-summary.json'),JSON.stringify(summary,null,2));
if(!fs.existsSync(path.join(out,'source-hashes-before.json')))fs.writeFileSync(path.join(out,'source-hashes-before.json'),JSON.stringify(Object.fromEntries(appFiles.map(f=>[rel(f),hash(f)])),null,2));
console.log(JSON.stringify(summary,null,2));
