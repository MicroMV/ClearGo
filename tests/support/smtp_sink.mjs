// Loopback-only SMTP receiver for synthetic tests. It never forwards messages.
import net from 'node:net';import fs from 'node:fs';
const [portFile,mailFile]=process.argv.slice(2);if(!portFile||!mailFile)throw Error('Test output paths required.');
const messages=[];
const server=net.createServer(socket=>{let buffer='',dataMode=false,lines=[],recipient='',authStep=0,authUser='';
socket.setEncoding('utf8');socket.write('220 localhost ClearGo test sink\r\n');
socket.on('data',chunk=>{buffer+=chunk;let newline;while((newline=buffer.indexOf('\n'))!==-1){const line=buffer.slice(0,newline).replace(/\r$/,'');buffer=buffer.slice(newline+1);
if(dataMode){if(line==='.') {dataMode=false;messages.push({recipient,body:lines.join('\n')});fs.writeFileSync(mailFile,JSON.stringify(messages));lines=[];socket.write('250 Accepted for local test\r\n');}else lines.push(line.startsWith('..')?line.slice(1):line);continue;}
if(authStep===1){authUser=Buffer.from(line,'base64').toString();authStep=2;socket.write('334 UGFzc3dvcmQ6\r\n');continue;}
if(authStep===2){authStep=0;socket.write(authUser==='synthetic-smtp'&&Buffer.from(line,'base64').toString()==='synthetic-smtp-password'?'235 Authentication successful\r\n':'535 Test credentials only\r\n');continue;}
if(/^EHLO|^HELO/i.test(line))socket.write('250-localhost\r\n250-AUTH LOGIN\r\n250 SIZE 1048576\r\n');
else if(line==='AUTH LOGIN'){authStep=1;socket.write('334 VXNlcm5hbWU6\r\n');}
else if(/^MAIL FROM:/i.test(line))socket.write('250 Sender OK\r\n');
else if(/^RCPT TO:/i.test(line)){recipient=line.slice(8).trim();socket.write('250 Recipient OK\r\n');}
else if(line==='DATA'){dataMode=true;socket.write('354 End with a dot\r\n');}
else if(line==='QUIT'){socket.end('221 Bye\r\n');}
else socket.write('250 OK\r\n');}});socket.on('error',()=>{});
});
server.listen(0,'127.0.0.1',()=>{fs.writeFileSync(portFile,String(server.address().port));fs.writeFileSync(mailFile,'[]');});
