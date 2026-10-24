"use strict";
const assert=require('node:assert/strict');const S=require('../public/assets/scheduler.js');let count=0;
const test=(name,f)=>{f();console.log('PASS '+name);count++};
const time=x=>new Date(x);
const monday='2026-10-26T'; // Lima UTC-5
for(const [title,sc,at,want] of [
 ['antes del inicio',{start:'08:00',end:'12:00'},monday+'12:59:59Z',false],
 ['inicio inclusivo',{start:'08:00',end:'12:00'},monday+'13:00:00Z',true],
 ['final exclusivo',{start:'08:00',end:'12:00'},monday+'17:00:00Z',false],
 ['lunes seleccionado',{days:[1]},monday+'15:00:00Z',true],
 ['domingo excluido',{days:[0]},monday+'15:00:00Z',false],
 ['sin días son todos',{days:[]},monday+'15:00:00Z',true],
 ['igual hora es día completo',{start:'00:00',end:'00:00'},monday+'16:00:00Z',true],
 ['regla desactivada',{enabled:false},monday+'16:00:00Z',false],
 ['franja nocturna viernes',{days:[5],start:'22:00',end:'02:00'},'2026-10-24T06:59:59Z',true],
 ['final nocturno sábado',{days:[5],start:'22:00',end:'02:00'},'2026-10-24T07:00:00Z',false],
 ['fecha efectiva viernes',{from:'2026-10-23',until:'2026-10-23',start:'22:00',end:'02:00'},'2026-10-24T06:00:00Z',true],
 ['fecha futura',{from:'2026-10-27'},monday+'16:00:00Z',false],
 ['fecha vencida',{until:'2026-10-25'},monday+'16:00:00Z',false],
 ['límite mes nocturno',{from:'2026-10-31',until:'2026-10-31',days:[6],start:'23:00',end:'01:00'},'2026-11-01T05:30:00Z',true],
 ['límite año nocturno',{from:'2026-12-31',until:'2026-12-31',start:'23:00',end:'01:00'},'2027-01-01T05:30:00Z',true],
])test(title,()=>assert.equal(S.isActive(sc,time(at),'America/Lima'),want));
test('DST hora repetida NY primera',()=>assert(S.isActive({start:'01:00',end:'02:00'},time('2026-11-01T05:30Z'),'America/New_York')));
test('DST hora repetida NY segunda',()=>assert(S.isActive({start:'01:00',end:'02:00'},time('2026-11-01T06:30Z'),'America/New_York')));
test('Madrid día correcto',()=>assert.equal(S.clock(time('2026-10-24T00:00Z'),'Europe/Madrid').day,6));
const item=(id,schedule={})=>({id,sha256:id,duration:8,schedule});
const base={id:44,type:'program',timezone:'America/Lima',program:{rules:[],fallback_id:3},playlists:[{id:1,name:'Mañana',items:[item('A'),item('B')]},{id:2,name:'Ofertas',items:[item('C')]},{id:3,name:'Respaldo',items:[item('R')]}]};
const make=()=>JSON.parse(JSON.stringify(base));
const rule=(id,ids,priority=0,schedule={})=>({id,name:id,playlist_ids:ids,priority,schedule,enabled:true});const at=time(monday+'15:00Z');
test('sin manifest vacío',()=>assert.equal(S.resolve(null).items.length,0));
test('legacy conserva orden',()=>assert.deepEqual(S.resolve({items:[item('A'),item('B')]}).items.map(x=>x.id),['A','B']));
test('tres listas en una franja',()=>{let m=make();m.program.rules=[rule('r1',[2,1,3])];assert.deepEqual(S.resolve(m,at).items.map(x=>x.id),['C','A','B','R'])});
test('respaldo sin franjas',()=>{let r=S.resolve(make(),at);assert(r.fallback);assert.deepEqual(r.items.map(x=>x.id),['R'])});
test('sin respaldo espera',()=>{let m=make();m.program.fallback_id=0;assert.equal(S.resolve(m,at).items.length,0)});
test('prioridad mayor sustituye',()=>{let m=make();m.program.rules=[rule('r1',[1],0),rule('r2',[2],50)];assert.deepEqual(S.resolve(m,at).items.map(x=>x.id),['C'])});
test('empate concatena',()=>{let m=make();m.program.rules=[rule('r1',[1],10),rule('r2',[2],10)];assert.deepEqual(S.resolve(m,at).items.map(x=>x.id),['A','B','C'])});
test('evita lista duplicada por solape',()=>{let m=make();m.program.rules=[rule('r1',[1],10),rule('r2',[1,2],10)];assert.deepEqual(S.resolve(m,at).items.map(x=>x.id),['A','B','C'])});
test('regla apagada no gana prioridad',()=>{let m=make();m.program.rules=[{...rule('off',[2],99),enabled:false},rule('on',[1])];assert.deepEqual(S.resolve(m,at).items.map(x=>x.id),['A','B'])});
test('horario propio del anuncio respetado',()=>{let m=make();m.playlists[0].items[0].schedule={start:'18:00',end:'20:00'};m.program.rules=[rule('r1',[1])];assert.deepEqual(S.resolve(m,at).items.map(x=>x.id),['B'])});
test('franja sin anuncios usa respaldo',()=>{let m=make();m.playlists[0].items=[];m.program.rules=[rule('r1',[1])];assert(S.resolve(m,at).fallback)});
test('cambio de franja no depende de internet',()=>{let m=make();m.program.rules=[rule('am',[1],0,{start:'08:00',end:'12:00'}),rule('pm',[2],0,{start:'12:00',end:'20:00'})];assert.notEqual(S.resolve(m,time(monday+'16:59Z')).signature,S.resolve(m,time(monday+'17:00Z')).signature)});
test('misma franja no reinicia en cada segundo',()=>{let m=make();m.program.rules=[rule('am',[1])];assert.equal(S.resolve(m,time(monday+'16:59:01Z')).signature,S.resolve(m,time(monday+'16:59:02Z')).signature)});
test('datos originales no mutados',()=>{let m=make();m.program.rules=[rule('am',[1])];let before=JSON.stringify(m);S.resolve(m,at);assert.equal(before,JSON.stringify(m))});
console.log(`${count} scheduler checks passed`);
