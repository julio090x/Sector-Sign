#!/usr/bin/env python3
"""Run only against disposable CI fixtures created by ci-setup.php."""
import urllib.request,urllib.error,urllib.parse,http.cookiejar,json,os
if os.environ.get('SECTOR_SIGN_QA')!='1':raise SystemExit('Solo QA explícita')
URL='http://127.0.0.1:8787';count=0
class Client:
 def __init__(self):self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf='';self.token=''
 def call(self,action,data=None,want=200,csrf=True):
  h={'Content-Type':'application/json'}
  if self.token:h['Authorization']='Bearer '+self.token
  if csrf and self.csrf:h['X-CSRF-Token']=self.csrf
  req=urllib.request.Request(URL+'/api.php?action='+urllib.parse.quote(action),data=json.dumps(data).encode() if data is not None else None,headers=h)
  try:r=self.http.open(req);code=r.status;raw=r.read()
  except urllib.error.HTTPError as e:code=e.code;raw=e.read()
  assert code==want,(action,code,want,raw)
  return json.loads(raw)
 def login(self,email):
  self.csrf=self.call('auth.me')['csrf'];r=self.call('auth.login',{'email':email,'password':'Only-QA-password-2026'});self.csrf=r['csrf']
def ok(label):
 global count;count+=1;print('PASS '+label)
a=Client();a.login('admin1@example.invalid');ok('inicio de sesión con sesión MySQL')
b=Client();b.login('admin2@example.invalid');viewer=Client();viewer.login('viewer1@example.invalid')
player=Client();r=player.call('player.register',{});player.token=r['device_token'];device=r['device_id']
a.call('screens.pair',{'code':r['pair_code'],'name':'Pantalla QA'});ok('vinculación por código temporal')
p={'name':'Programa QA','timezone':'America/Lima','fallback_id':3,'rules':[{'name':'Tres listas','playlist_ids':[3,1,2],'priority':0,'enabled':True,'schedule':{'days':[1,2,3,4,5],'start':'08:00','end':'18:00'}}]}
a.call('programs.save',p,want=419,csrf=False);ok('CSRF requerido')
viewer.call('programs.save',p,want=403);ok('consulta no puede editar')
b.call('programs.save',p,want=422);ok('listas de otra organización rechazadas')
r=a.call('programs.save',p);pid=r['id'];rev=r['revision'];assert rev==1;ok('guardar programa de tres listas')
p.update(id=pid,revision=rev);r=a.call('programs.save',p);assert r['revision']==2
a.call('programs.save',p,want=409);ok('conflicto de revisión detectado')
a.call('programs.publish',{'id':pid,'revision':1,'screens':[device]},want=422);ok('no publica borrador desactualizado')
r=a.call('programs.publish',{'id':pid,'revision':2,'screens':[device]});pub=r['publication_id'];ok('publicación transaccional')
sync=player.call('player.sync');m=sync['manifest'];assert m['type']=='program' and len(m['playlists'])==3 and m['program']['rules'][0]['playlist_ids']==[3,1,2];assert len(m['items'])==1;ok('snapshot incluye tres listas; archivo compartido descargado una vez')
a.call('playlist.save',{'id':1,'revision':0,'items':[]});assert player.call('player.sync')['manifest']==m;ok('editar lista no muta publicación anterior')
player.call('player.status',{'web_version':'0.7.0','app_version':'0.7.0','state':'playing','schedule':'Tres listas','playlists':['3','1','2'],'screen':'1920x1080','secret':'MUST_NOT_STORE'});row=a.call('screens.list')['items'][0];status=json.loads(row['telemetry_json']);assert status['web_version']=='0.7.0' and 'secret' not in status;ok('telemetría autenticada con campos permitidos')
assert b.call('screens.list')['items']==[];ok('dispositivos aislados por organización')
player.call('player.ack',{'publication_id':pub});assert a.call('screens.list')['items'][0]['applied_publication_id']==pub;ok('confirmación de campaña recibida')
a.call('screens.command',{'id':device,'command':'pause'});cmd=player.call('player.sync')['commands'][0];player.call('player.ack',{'command_ids':[cmd['id']]});assert player.call('player.sync')['commands']==[];ok('orden remota confirmada')
a.call('screens.unpair',{'id':device});player.call('player.sync',want=401);ok('revocación invalida reproductor')
print(str(count)+' HTTP/MySQL checks passed')
