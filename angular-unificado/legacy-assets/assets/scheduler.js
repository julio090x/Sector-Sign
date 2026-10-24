/* Sector Sign: deterministic local scheduling, independent of network and UI. */
(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.SectorSchedule=api;})(typeof globalThis!=='undefined'?globalThis:this,function(){
 'use strict';
 function clock(date,zone){const out={};new Intl.DateTimeFormat('en-US',{timeZone:zone||'America/Lima',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23',weekday:'short'}).formatToParts(date).forEach(x=>out[x.type]=x.value);return {date:`${out.year}-${out.month}-${out.day}`,minute:(Number(out.hour)%24)*60+Number(out.minute),day:['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].indexOf(out.weekday)};}
 function isActive(s={},date=new Date(),zone='America/Lima'){
  if(s.enabled===false)return false;const now=clock(date,zone);let day=now.day,ymd=now.date;
  const toMinute=x=>Number(x.slice(0,2))*60+Number(x.slice(3,5));
  const a=s.start?toMinute(s.start):0,b=s.end?toMinute(s.end):1440;
  let within=a===b||now.minute>=a&&now.minute<b;
  if(a>b){within=now.minute>=a||now.minute<b;if(now.minute<b){day=(day+6)%7;const prev=new Date(ymd+'T12:00:00Z');prev.setUTCDate(prev.getUTCDate()-1);ymd=prev.toISOString().slice(0,10);}}
  return within&&(!s.from||ymd>=s.from)&&(!s.until||ymd<=s.until)&&(!s.days?.length||s.days.includes(day));
 }
 function resolve(manifest,date=new Date()){
  if(!manifest)return {items:[],lists:[],rules:[],signature:'empty',fallback:false};
  const zone=manifest.timezone||'America/Lima';let lists=[],rules=[],items=[],fallback=false;
  if(manifest.type!=='program')items=(manifest.items||[]).filter(x=>isActive(x.schedule||{},date,zone));
  else {
   const candidates=(manifest.program.rules||[]).filter(x=>x.enabled!==false&&isActive(x.schedule||{},date,zone));
   const priority=Math.max(...candidates.map(x=>Number(x.priority)||0),-Infinity);
   rules=candidates.filter(x=>(Number(x.priority)||0)===priority); // ties concatenate in saved rule/list order
   const ids=[...new Set(rules.flatMap(x=>x.playlist_ids.map(Number)))];
   lists=ids.map(id=>(manifest.playlists||[]).find(x=>Number(x.id)===id)).filter(Boolean);
   items=lists.flatMap(p=>p.items.filter(x=>isActive(x.schedule||{},date,zone)).map(x=>({...x,playlist_id:p.id,playlist_name:p.name})));
   if(!items.length&&manifest.program.fallback_id){const p=(manifest.playlists||[]).find(x=>Number(x.id)===Number(manifest.program.fallback_id));if(p){lists=[p];rules=[];fallback=true;items=p.items.filter(x=>isActive(x.schedule||{},date,zone)).map(x=>({...x,playlist_id:p.id,playlist_name:p.name}));}}
  }
  return {items,lists:lists.map(x=>({id:x.id,name:x.name})),rules:rules.map(x=>({id:x.id,name:x.name})),fallback,signature:JSON.stringify([manifest.id,rules.map(x=>x.id),fallback,items.map(x=>[x.playlist_id||0,x.id,x.sha256,x.duration])])};
 }
 return {clock,isActive,resolve};
});
