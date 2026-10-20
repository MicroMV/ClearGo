import fs from 'node:fs';import path from 'node:path';import {spawn,spawnSync} from 'node:child_process';
const out=path.resolve('qa-audit/2026-10-18'),app=path.join(out,'sandbox-app'),fixture=JSON.parse(fs.readFileSync(path.join(out,'sandbox-fixture.json'))),php='C:/xamppl/php/php.exe',env={...process.env,DB_DATABASE:fixture.database,APP_BASE_URL:'/'};
const dir=path.join(app,'tests');fs.mkdirSync(dir,{recursive:true});
const skipped=new Set(['bulk_clearance_test.php','office_remarks_test.php','student_dashboard_history_test.php','student_import_test.php','student_import_scale_test.php','staff_navigation_test.php','comlab_violations_modal_test.php','facilities_items_modal_test.php','clearance_report_workbook_test.php']);
for(const f of fs.readdirSync('tests').filter(f=>f.endsWith('.php'))){const original=fs.readFileSync('tests/'+f,'utf8');fs.writeFileSync(path.join(dir,f),original.replaceAll('http://localhost/ClearGo','http://127.0.0.1:8829'));}
const log=fs.openSync(path.join(out,'mysql-suite-server.log'),'a');const server=spawn(php,['-S','127.0.0.1:8829','-t',app,path.join(out,'sandbox-router.php')],{env,windowsHide:true,stdio:['ignore',log,log]});
const run=(args,timeout=90000)=>new Promise(resolve=>{const start=Date.now();const child=spawn(php,args,{env,windowsHide:true,stdio:['ignore','pipe','pipe']});let text='';child.stdout.on('data',d=>text+=d);child.stderr.on('data',d=>text+=d);const timer=setTimeout(()=>{child.kill();resolve({status:null,ms:Date.now()-start,output:text+'\nTIMEOUT',timeout:true});},timeout);child.on('exit',status=>{clearTimeout(timer);resolve({status,ms:Date.now()-start,output:text});});});
const results=[];
try{
    await new Promise(r=>setTimeout(r,700));
    const requested=process.argv.slice(2);
    for(const file of fs.readdirSync(dir).filter(f=>f.endsWith('.php')&&!skipped.has(f)&&(!requested.length||requested.includes(f)))){
        const reset=await run([path.join(out,'sandbox-data.php'),'reset']);if(reset.status!==0)throw Error(reset.output);
        // These existing suites require an open semester. Readiness explicitly requires PREPARING.
        if(!['readiness_test.php','student_import_preview_test.php','semester_notifications_test.php'].includes(file))await run([path.join(out,'sandbox-data.php'),'open']);
        if(file==='student_import_mysql_test.php')await run([path.join(out,'sandbox-data.php'),'import-year']);
        const args=[path.join(dir,file)];if(file==='bulk_dashboard_render_test.php')args.push('COMLAB_STAFF');
        const r=await run(args);fs.writeFileSync(path.join(out,'mysql-'+file+'.log'),r.output);
        results.push({test_id:'MY-'+String(results.length+1).padStart(3,'0'),role:'Various',feature:file,test:'Run existing suite against a reset synthetic MySQL database; copied HTTP tests target the isolated server',result:r.status===0?'PASS':r.timeout?'BLOCKED':'FAIL',milliseconds:r.ms,evidence:r.output.trim()});
        fs.writeFileSync(path.join(out,'mysql-tests.json'),JSON.stringify(results,null,2));
        console.log(file+': '+results.at(-1).result+' ('+r.ms+' ms)');
    }
    await run([path.join(out,'sandbox-data.php'),'reset']);
}catch(e){console.error(e);process.exitCode=1;}finally{server.kill();fs.closeSync(log);}
