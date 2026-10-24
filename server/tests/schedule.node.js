'use strict';
const fs=require('fs'),vm=require('vm');
const file=fs.readFileSync(__dirname+'/../public/assets/player.js','utf8');
const a=file.indexOf('function localNow('),b=file.indexOf('function playable(');
if(a<0||b<a)throw Error('No se encontró motor de horarios');
const ctx={Intl,Date,console};vm.createContext(ctx);
vm.runInContext("let serverTimezone='America/Lima';\n"+file.slice(a,b)+"\nthis.checkSchedule=isScheduled;",ctx);
const start={from:'2026-10-23',until:'2026-10-23',start:'23:00',end:'03:00',days:[5]};
const checks=[
 ['Viernes 23:30 antes de medianoche', '2026-10-24T04:30:00Z',true,start],
 ['Sábado 01:30 pertenece al viernes', '2026-10-24T06:30:00Z',true,start],
 ['Sábado 03:00 fuera de franja','2026-10-24T08:00:00Z',false,start],
 ['Domingo 01:30 fuera de fecha','2026-10-25T06:30:00Z',false,start],
 ['Franja 08:00-10:00','2026-10-24T14:00:00Z',true,{start:'08:00',end:'10:00',days:[6]}],
 ['24 horas con horas iguales','2026-10-24T14:00:00Z',true,{start:'09:00',end:'09:00'}]
];
for(const [name,date,want,schedule] of checks){const got=ctx.checkSchedule({schedule},new Date(date));if(got!==want)throw Error(name+' esperado '+want+' recibió '+got);console.log('PASS: '+name);}
