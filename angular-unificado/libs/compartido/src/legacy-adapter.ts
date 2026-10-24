/** Transitional adapter: the current production business UI is still app.js/player.js.
 * Angular owns the shell here; move one feature at a time to components/services
 * without duplicating server endpoints. NEVER instantiate both roles on one page. */
export function loadExistingUi(jsPath:string):Promise<void>{
 return new Promise((resolve,reject)=>{
   const element=document.createElement('script');
   element.src=jsPath;element.defer=true;
   element.onload=()=>resolve();
   element.onerror=()=>reject(new Error('No se pudo cargar '+jsPath));
   document.body.appendChild(element);
 });
}
