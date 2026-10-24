/** Shared settings and simple API helper for the two Angular frontends. */
export type Mode = 'centro' | 'receptor';
export interface ScreenStatus { id: string; name: string; last_seen_at: string|null; applied_version: number|null }
export interface ApiEnvelope<T> { ok: boolean; error?: string; [name: string]: unknown; data?: T }
export interface ProductPublic { external_id: string; name: string; presentation: string; price: number; currency: 'PEN'; image_url?: string }
export function apiUrl(action: string): string {return '/api.php?action='+encodeURIComponent(action);}
export async function apiRequest<T extends object>(action: string, payload?: unknown, csrf=''):Promise<ApiEnvelope<T>> {
  const opts:RequestInit={method:payload===undefined?'GET':'POST',credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json'}};
  if(payload!==undefined){opts.body=JSON.stringify(payload);opts.headers={'Content-Type':'application/json','X-CSRF-Token':csrf};}
  const response=await fetch(apiUrl(action),opts);
  const result=(await response.json()) as ApiEnvelope<T>;
  if(!response.ok||!result.ok)throw new Error(result.error||'Error de comunicación');
  return result;
}
export function isPublicHttpsOrLan(address:string):boolean{
  try {
    const u=new URL(address);
    if(u.username||u.password||u.hash||!(u.pathname==='/'||u.pathname===''))return false;
    if(u.protocol==='https:')return true;
    const host=u.hostname;
    if(u.protocol!=='http:')return false;
    const v4=/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/.exec(host);
    if(host==='localhost')return true;
    if(!v4)return false;
    const a=v4.slice(1).map(Number);if(a.some(x=>x>255))return false;
    return a[0]===10||(a[0]===192&&a[1]===168)||(a[0]===172&&a[1]>=16&&a[1]<=31)||a[0]===127;
  }catch{return false;}
}
