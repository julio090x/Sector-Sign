'use strict';
const PLAYER_BUILD=window.SS_BOOT_RELEASE||{version:'0.7.0',released_at:'2026-10-24T12:03:36Z'};
let syncFailures=0,renderGeneration=0,isDownloading=false,telemetryBusy=false;
// Local-first player; media and active manifest remain in IndexedDB if the network is lost.
const el=id=>document.getElementById(id);
const prefsKey='sector-sign-player-030'; // Compatible with 0.3: do not silently unlink devices on upgrade.
let device=JSON.parse(localStorage.getItem(prefsKey)||'null');
let activeManifest=null,serverTimezone='America/Lima',scheduleTimer=null,syncTimer=null,renderTimer=null,activeNode=null,objectUrl=null,position=0,paused=false,muted=true,requestRunning=false,lastSync=false,ready=false;
const stage=el('stage'),setup=el('setup');
let idb;let lastPairCodeAt=Date.now();let activeSelection=null,selectionSignature='',currentItem=null,downloadPercent=0,lastTelemetry=0;
function playerDate(value){if(!value)return '—';const d=new Date(value);return isNaN(d)?String(value):new Intl.DateTimeFormat('es-PE',{dateStyle:'medium',timeStyle:'short',timeZone:'America/Lima'}).format(d)}
function setRelease(meta){if(!meta)return;const at=el('software-updated');if(at)at.textContent=playerDate(meta.released_at);const own=el('installed-version');if(own)own.textContent='v'+PLAYER_BUILD.version;const badge=el('player-build');if(badge)badge.textContent='v'+PLAYER_BUILD.version+(meta.version!==PLAYER_BUILD.version?' · actualizar':'');}
function lastContact(){const node=el('last-contact');if(node)node.textContent=playerDate(new Date().toISOString());}
function showStatus(message){const t=el('status-toast');t.textContent=message;t.classList.add('show');clearTimeout(showStatus.timer);showStatus.timer=setTimeout(()=>t.classList.remove('show'),3700);}
function showControls(){el('control-panel').classList.add('show');clearTimeout(showControls.timer);showControls.timer=setTimeout(()=>el('control-panel').classList.remove('show'),5000);}
function dbOpen(){return new Promise((resolve,reject)=>{let req=indexedDB.open('sector-sign-offline-v030',1);req.onupgradeneeded=()=>{const db=req.result;db.createObjectStore('settings');db.createObjectStore('assets');};req.onsuccess=()=>resolve(req.result);req.onerror=()=>reject(req.error);});}
async function dbGet(store,key){let db=idb||await dbOpen();return new Promise((resolve,reject)=>{let tx=db.transaction(store,'readonly'),req=tx.objectStore(store).get(key);req.onsuccess=()=>resolve(req.result);req.onerror=()=>reject(req.error);});}
async function dbPut(store,key,value){let db=idb||await dbOpen();return new Promise((resolve,reject)=>{let tx=db.transaction(store,'readwrite');tx.objectStore(store).put(value,key);tx.oncomplete=()=>resolve();tx.onerror=()=>reject(tx.error);});}
async function dbPrune(manifest,previous){
 let db=idb||await dbOpen();const keep=new Set([...(manifest?.items||[]),...(previous?.items||[])].map(it=>it.id+':'+it.sha256));
 await new Promise((resolve,reject)=>{const tx=db.transaction('assets','readwrite');const store=tx.objectStore('assets');
 const scan=store.openCursor();scan.onsuccess=()=>{const cursor=scan.result;if(!cursor)return;if(!keep.has(cursor.key))cursor.delete();cursor.continue();};
 tx.oncomplete=resolve;tx.onerror=()=>reject(tx.error);});
}
async function dbClear(){let db=idb||await dbOpen();await new Promise((resolve,reject)=>{let tx=db.transaction(['settings','assets'],'readwrite');tx.objectStore('settings').clear();tx.objectStore('assets').clear();tx.oncomplete=resolve;tx.onerror=()=>reject(tx.error);});}
async function request(action,data){
 const opts={method:data===undefined?'GET':'POST',headers:{},cache:'no-store'};
 if(device?.token)opts.headers.Authorization='Bearer '+device.token;
 if(data!==undefined){opts.headers['Content-Type']='application/json';opts.body=JSON.stringify(data);}
 const response=await fetch('/api.php?action='+encodeURIComponent(action),opts);
 const result=await response.json().catch(()=>({error:'Respuesta no válida del servidor'}));
 if(!response.ok||!result.ok){const error=Error(result.error||'Error de comunicación');error.httpStatus=response.status;throw error;}return result;
}
function setDevice(data){device=data;localStorage.setItem(prefsKey,JSON.stringify(device));}
async function registerDevice(){
  if(device?.id && device?.token)return;
  try{const r=await request('player.register',{});setDevice({id:r.device_id,token:r.device_token});el('pair-code').textContent=r.pair_code;lastPairCodeAt=Date.now();el('pair-status').textContent='Este código vence en 15 minutos.';}
  catch(e){el('pair-code').textContent='SIN RED';el('pair-status').textContent='Conecta el dispositivo al servidor y vuelve a intentarlo.';}
}
async function renewPairCode(){
 try{
  if(!device?.token){await registerDevice();return;}
  const r=await request('player.pair_code',{});el('pair-code').textContent=r.pair_code;lastPairCodeAt=Date.now();el('pair-status').textContent='Código vigente durante 15 minutos.';
 }catch(e){el('pair-status').textContent=e.message;}
}
function hideSetup(){setup.style.display='none';ready=true;showControls();}
function showSetup(){setup.style.display='block';ready=false;}
function localNow(date=new Date()){
 try{
  const obj={};const parts=new Intl.DateTimeFormat('en-US',{timeZone:serverTimezone,year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false,weekday:'short'}).formatToParts(date);
  parts.forEach(p=>{if(p.type!=='literal')obj[p.type]=p.value;});
  const weekdays=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];return {date:`${obj.year}-${obj.month}-${obj.day}`,minutes:(Number(obj.hour)%24)*60+Number(obj.minute),day:weekdays.indexOf(obj.weekday)};
 }catch{
  return {date:date.toISOString().slice(0,10),minutes:date.getHours()*60+date.getMinutes(),day:date.getDay()};
 }
}
function isScheduled(it,date=new Date()){
 const sc=it.schedule||{};if(Object.keys(sc).length===0)return true;
 const current=localNow(date);
 const hasDays=Array.isArray(sc.days)&&sc.days.length>0;
 const timeToNum=x=>Number(x.slice(0,2))*60+Number(x.slice(3,5));
 const start=sc.start?timeToNum(sc.start):0,end=sc.end?timeToNum(sc.end):1440;
 let day=current.day;let effectiveDate=current.date;let withinTime=true;
 if(sc.start&&sc.end&&start>end){
   withinTime=current.minutes>=start||current.minutes<end;
   if(current.minutes<end){day=(day+6)%7;const previous=new Date(current.date+'T12:00:00Z');previous.setUTCDate(previous.getUTCDate()-1);effectiveDate=previous.toISOString().slice(0,10);}
 } else withinTime=start===end?true:current.minutes>=start&&current.minutes<end;
 if(sc.from&&effectiveDate<sc.from)return false;if(sc.until&&effectiveDate>sc.until)return false;
 return withinTime&&(!hasDays||sc.days.includes(day));
}
function selection(){return SectorSchedule.resolve(activeManifest);}
function playable(){activeSelection=selection();return activeSelection.items;}
async function playNext(){
 const generation=++renderGeneration;clearTimeout(renderTimer);
 if(paused)return;
 const items=playable();if(!items.length){currentItem=null;activeNode?.pause?.();activeNode?.remove();activeNode=null;if(objectUrl){URL.revokeObjectURL(objectUrl);objectUrl=null;}stage.innerHTML='<div style="display:grid;place-items:center;height:100%;color:#afc6e8;text-align:center"><div>▣<p>No hay anuncios programados para este momento.</p></div></div>';renderTimer=setTimeout(playNext,60000);return;}
 const idx=position%items.length;position=(position+1)%items.length;const it=items[idx];currentItem=it;
 try{
  const asset=await dbGet('assets',it.id+':'+it.sha256);if(generation!==renderGeneration||paused)return;if(!(asset instanceof Blob))throw Error('Archivo local no disponible');
  const url=URL.createObjectURL(asset);const video=it.mime.startsWith('video/');
  const node=document.createElement(video?'video':'img');node.className='slide';node.style.objectFit=it.fit==='cover'?'cover':'contain';
  if(it.transition==='none')node.classList.add('no-fade');
  node.src=url;
  if(video){node.muted=muted;node.autoplay=true;node.loop=true;node.playsInline=true;node.preload='auto';node.addEventListener('error',()=>showStatus('No se puede reproducir este video'));}
  if(!video)node.alt=it.name||'Publicidad';
  const previous=activeNode,previousUrl=objectUrl;activeNode=node;objectUrl=url;if(!previous)stage.innerHTML='';stage.appendChild(node);
  requestAnimationFrame(()=>node.classList.add('visible'));
  if(video)node.play().catch(()=>showStatus('Toca la pantalla para iniciar el video'));
  setTimeout(()=>{if(previous?.parentNode)previous.remove();if(previousUrl)URL.revokeObjectURL(previousUrl)},it.transition==='fade'?550:50);
  renderTimer=setTimeout(playNext,Math.max(2,Number(it.duration)||8)*1000);
 }catch(e){if(generation!==renderGeneration)return;showStatus('Contenido pendiente: '+it.name);renderTimer=setTimeout(playNext,2000);}
}
function startPlayback(manifest){activeManifest=manifest;serverTimezone=manifest.timezone||serverTimezone;selectionSignature=selection().signature;position=0;hideSetup();const n=el('current-publication');if(n)n.textContent='v'+manifest.version+' · '+playerDate(manifest.created_at);playNext();}
async function stageNewManifest(manifest){
 if(!manifest?.items?.length){showStatus('Lista recibida sin anuncios');return;}
 if(activeManifest?.id===manifest.id)return;
 showStatus('Descargando publicación v'+manifest.version);
 let complete=0;downloadPercent=0;for(const it of manifest.items){
  downloadPercent=Math.floor(100*complete++/manifest.items.length);
  const key=it.id+':'+it.sha256;
  let existing=await dbGet('assets',key);if(existing instanceof Blob&&existing.size===Number(it.bytes))continue;
  const r=await fetch('/media.php?id='+encodeURIComponent(it.id),{headers:{Authorization:'Bearer '+device.token},cache:'no-store'});
  if(!r.ok)throw Error('Descarga fallida: '+it.name+' (HTTP '+r.status+')');
  const blob=await r.blob();if(blob.size!==Number(it.bytes))throw Error('Tamaño incorrecto: '+it.name);
  if(crypto.subtle){
   const digest=new Uint8Array(await crypto.subtle.digest('SHA-256',await blob.arrayBuffer()));
   const sha=Array.from(digest,b=>b.toString(16).padStart(2,'0')).join('');
   if(sha!==it.sha256)throw Error('Verificación SHA-256 falló: '+it.name);
  }
  await dbPut('assets',key,blob);
 }
 // Commit active manifest only AFTER every new asset is stored and verified.
  const previous=activeManifest;
  await dbPut('settings','previous_manifest',previous);
 await dbPut('settings','manifest',manifest);
 downloadPercent=100;startPlayback(manifest);showStatus('Campaña v'+manifest.version+' lista, incluso offline');
  // Keep one previous publication for rollback; remove obsolete blobs after a successful commit.
  dbPrune(manifest,previous).catch(()=>{});
 try{await request('player.ack',{publication_id:manifest.id});}catch{}
}
async function captureContent(){
 if(!activeNode)return;
 const canvas=document.createElement('canvas');canvas.width=480;canvas.height=270;const ctx=canvas.getContext('2d');ctx.fillStyle='#081626';ctx.fillRect(0,0,480,270);
 try{ctx.drawImage(activeNode,0,0,480,270);const data=canvas.toDataURL('image/png').split(',')[1];await request('player.capture',{png_base64:data});showStatus('Captura enviada');}
 catch(e){showStatus('Captura no disponible en este dispositivo');}
}
async function processCommands(commands){let acknowledgements=[];for(const cmd of commands){
 try{
  if(cmd.action==='pause'){paused=true;clearTimeout(renderTimer);activeNode?.pause?.();showStatus('Reproducción pausada');}
  if(cmd.action==='resume'){paused=false;activeNode?.play?.().catch(()=>{});playNext();showStatus('Reproducción reanudada');}
  if(cmd.action==='capture')await captureContent();
  if(cmd.action==='reload'){acknowledgements.push(cmd.id);await request('player.ack',{command_ids:acknowledgements});location.reload();return;}
  acknowledgements.push(cmd.id);
 }catch(e){showStatus('Comando falló: '+cmd.action);}
 }
 if(acknowledgements.length)await request('player.ack',{command_ids:acknowledgements});
}
async function sync(){
 if(!device?.token){await registerDevice();return;}
 if(requestRunning)return;requestRunning=true;
 try{
  const data=await request('player.sync');
  const firstOnline=!lastSync;lastSync=true;syncFailures=0;el('connection').textContent='● En línea';setRelease(data.software);lastContact();
  if(!data.paired){
   if(activeManifest){showStatus('Sin vinculación: reproduce el último contenido descargado');}
   if(!ready)showSetup();
   if(el('pair-code').textContent.includes('··')||el('pair-code').textContent==='SIN RED'||Date.now()-lastPairCodeAt>14*60*1000)await renewPairCode();
   return;
  }
  hideSetup();serverTimezone=data.timezone||'America/Lima';if(data.manifest&&!data.manifest.timezone)data.manifest.timezone=serverTimezone;
  if(!data.manifest&&!activeManifest){stage.innerHTML='<div style="height:100%;display:grid;place-items:center;text-align:center;color:#a8bedc;padding:30px">Pantalla vinculada. Publica una lista desde el centro de mando para empezar.</div>';} 
  if(data.manifest && (!activeManifest||activeManifest.id!==data.manifest.id)){
    isDownloading=true;try{await stageNewManifest(data.manifest);}catch(e){showStatus('Descarga pendiente · sigue la campaña anterior');await request('player.ack',{error:e.message}).catch(()=>{});}finally{isDownloading=false;}
  }else if(activeManifest&&!activeNode&&!paused){playNext();}
  if(activeManifest&&Number(data.applied_publication_id)!==Number(activeManifest.id))await request('player.ack',{publication_id:activeManifest.id});
  if(data.commands?.length)await processCommands(data.commands);
  if(Date.now()-lastTelemetry>15000){await reportTelemetry().catch(()=>{});}
  if(firstOnline)showStatus('Conectado con el centro de mando');
 }catch(e){
   if(e.httpStatus===401&&device?.token){showStatus('Pantalla desvinculada. Se borrará la publicidad local.');localStorage.removeItem(prefsKey);await dbClear().catch(()=>{});location.reload();return;}
   lastSync=false;syncFailures=Math.min(5,syncFailures+1);el('connection').textContent='◌ Sin internet · modo offline';if(activeManifest&&!activeNode)playNext();
 }
 finally{requestRunning=false;}
}
function scheduleSync(delay=5000){clearTimeout(syncTimer);syncTimer=setTimeout(async()=>{await sync();scheduleSync(lastSync?5000:Math.min(60000,5000*2**syncFailures));},delay+Math.floor(Math.random()*700));}
async function reportTelemetry(){
 if(!device?.token||telemetryBusy)return;telemetryBusy=true;try{const current=selection();const memory=navigator.storage?.estimate?await navigator.storage.estimate().catch(()=>({})):{};
 await request('player.status',{app_version:(navigator.userAgent.match(/SectorSign\/([^\s]+)/)||[])[1]||'',web_version:PLAYER_BUILD.version,platform:navigator.userAgent.slice(-140),screen:screen.width+' × '+screen.height,state:isDownloading?'downloading':paused?'paused':current.items.length?'playing':'idle',current_item:currentItem?.name||'',schedule:current.fallback?'Respaldo':current.rules.map(x=>x.name).join(' / '),playlists:current.lists.map(x=>x.name),storage_bytes:memory.usage||0,quota_bytes:memory.quota||0,cached_files:activeManifest?.items?.length||0,download_percent:downloadPercent});lastTelemetry=Date.now();}finally{telemetryBusy=false;}
}
async function boot(){
 el('server-host').textContent=location.origin;el('device-id').textContent=device?.id||'Sin registrar';
 try{idb=await dbOpen();activeManifest=await dbGet('settings','manifest')||null;}catch(e){showStatus('Almacenamiento offline no disponible');}
 if(activeManifest){downloadPercent=100;startPlayback(activeManifest);}
 else showSetup();
 setInterval(()=>{if(Date.now()-lastTelemetry>14000)reportTelemetry().catch(()=>{});},15000);
 await sync();
 scheduleSync(lastSync?5000:10000);
 // Refresh schedules at a minute boundary even if no new publication arrives.
 scheduleTimer=setInterval(()=>{if(!activeManifest)return;const current=selection();if(current.signature!==selectionSignature){selectionSignature=current.signature;position=0;if(!paused)playNext();lastTelemetry=0;}},1000);
 if('serviceWorker'in navigator&&window.isSecureContext)navigator.serviceWorker.register('/sw-player.js').catch(()=>{});
}
el('pair-refresh').addEventListener('click',renewPairCode);
el('persist-storage')?.addEventListener('click',async()=>{if(!navigator.storage?.persist){showStatus('No disponible en este navegador');return;}const granted=await navigator.storage.persist().catch(()=>false);showStatus(granted?'El navegador intentará conservar los archivos':'El navegador puede borrar su caché cuando falte espacio');});
el('config')?.addEventListener('click',async()=>{try{const x=await navigator.storage?.estimate?.();if(x)el('storage-report').textContent='Almacenamiento: '+Math.round((x.usage||0)/1048576)+' MB usados / '+Math.round((x.quota||0)/1048576)+' MB disponibles';}catch{}});
el('full-screen').addEventListener('click',()=>{const target=document.documentElement;if(!document.fullscreenElement)target.requestFullscreen?.().catch(()=>{});else document.exitFullscreen?.();});
el('volume').addEventListener('click',()=>{muted=!muted;if(activeNode?.tagName==='VIDEO'){activeNode.muted=muted;if(!muted)activeNode.play().catch(()=>{});}el('volume').textContent=muted?'🔇':'🔊';showControls();});
el('pause').addEventListener('click',()=>{paused=!paused;if(paused){clearTimeout(renderTimer);activeNode?.pause?.();}else{activeNode?.play?.().catch(()=>{});playNext();}el('pause').textContent=paused?'▶':'⏸';showControls();});
const settingOpen=()=>{el('settings-overlay').hidden=false;showControls();};el('pair-settings').addEventListener('click',settingOpen);el('config').addEventListener('click',settingOpen);el('close-settings').addEventListener('click',()=>el('settings-overlay').hidden=true);
el('reset-device').addEventListener('click',async()=>{if(!confirm('Esto desvinculará el navegador. Necesitarás generar otro código. ¿Continuar?'))return;localStorage.removeItem(prefsKey);await dbClear().catch(()=>{});location.reload();});
document.addEventListener('pointermove',showControls);document.addEventListener('keydown',showControls);
window.addEventListener('online',async()=>{clearTimeout(syncTimer);await sync();scheduleSync(lastSync?5000:10000);});setRelease(PLAYER_BUILD);boot();
