<div id="kjm-request-overlay" class="kjm-request-overlay" hidden role="status" aria-live="polite" aria-busy="false">
  <div class="kjm-request-content">
    <img class="kjm-request-logo" src="{{ asset('logo1.png') }}" alt="KJM Coffee Shop">
    <span class="kjm-request-title">Loading...</span>
    <section class="kjm-request-error" hidden aria-labelledby="kjm-request-error-title">
      <strong id="kjm-request-error-title">Unable to Load Page</strong>
      <p>Something went wrong while loading this page. Would you like to try again?</p>
      <span class="kjm-request-status" hidden></span>
      <div class="kjm-request-actions">
        <button type="button" data-kjm-refresh>Refresh</button>
        <button type="button" data-kjm-back>Go Back</button>
      </div>
    </section>
  </div>
</div>
<style>
.kjm-request-overlay{position:fixed;inset:0;z-index:9999;display:grid;place-items:center;padding:20px;background:rgba(54,35,24,.68);color:#fff8ef}.kjm-request-overlay[hidden]{display:none}.kjm-request-content{display:grid;justify-items:center;gap:10px;width:min(100%,420px);text-align:center}.kjm-request-title{font-size:14px;font-weight:600;letter-spacing:.02em}.kjm-request-logo{display:block;width:64px;height:64px;object-fit:contain;animation:kjm-logo-rotate 1.8s linear infinite}.kjm-request-overlay.has-error .kjm-request-logo{animation:none}.kjm-request-error{width:100%;padding:18px;border:1px solid #ffffff35;border-radius:14px;background:#211812d9;text-align:center}.kjm-request-error[hidden],.kjm-request-status[hidden]{display:none}.kjm-request-error strong{display:block;font-size:18px}.kjm-request-error p{margin:8px 0 0;color:#f0e3d8;font-size:14px;line-height:1.5}.kjm-request-status{display:block;margin-top:7px;color:#e7c9ad;font-size:12px}.kjm-request-actions{display:flex;justify-content:center;gap:10px;margin-top:16px}.kjm-request-actions button{min-height:40px;padding:0 16px;border:1px solid #fff8ef;border-radius:9px;background:#fff8ef;color:#382318;font:inherit;font-weight:700;cursor:pointer}.kjm-request-actions button+button{background:transparent;color:#fff8ef}@keyframes kjm-logo-rotate{to{transform:rotate(360deg)}}@media(max-width:520px){.kjm-request-actions{display:grid;grid-template-columns:1fr}.kjm-request-actions button{width:100%}}@media(prefers-reduced-motion:reduce){.kjm-request-logo{animation:none}}
</style>
<script>
(()=>{
  const overlay=document.getElementById('kjm-request-overlay');
  if(!overlay)return;
  const title=overlay.querySelector('.kjm-request-title');
  const error=overlay.querySelector('.kjm-request-error');
  const status=overlay.querySelector('.kjm-request-status');
  const nativeFetch=window.fetch?.bind(window);
  let retrySafeRequest=null;
  let retryOnReconnect=null;
  const clear=()=>{
    overlay.hidden=true;
    overlay.classList.remove('has-error');
    overlay.setAttribute('aria-busy','false');
    title.hidden=false;
    error.hidden=true;
    status.hidden=true;
    retrySafeRequest=null;
    retryOnReconnect=null;
  };
  const start=()=>{
    overlay.hidden=false;
    overlay.classList.remove('has-error');
    overlay.setAttribute('aria-busy','true');
    title.hidden=false;
    error.hidden=true;
    status.hidden=true;
  };
  const showError=(code,retry,reconnectable=false)=>{
    overlay.hidden=false;
    overlay.classList.add('has-error');
    overlay.setAttribute('aria-busy','false');
    title.hidden=true;
    error.hidden=false;
    status.textContent=code?`HTTP ${code}`:'Connection unavailable';
    status.hidden=false;
    retrySafeRequest=retry||null;
    retryOnReconnect=reconnectable?retrySafeRequest:null;
  };
  window.kjmStartRequestLoading=start;

  // Full-page navigation and normal forms use the browser's native behavior.
  // Only Admin same-origin fetch failures enter this recovery overlay.
  if(document.body.dataset.kjmErrorRecovery==='admin'&&nativeFetch){
    const handledStatuses=new Set([400,402,403,404,405,406,408,419,429,500,502,503,504]);
    const retryableStatuses=new Set([408,500,502,503,504]);
    const retryDelays=[500,1500];
    const delay=milliseconds=>new Promise(resolve=>setTimeout(resolve,milliseconds));
    const requestInfo=(input,init)=>{
      const method=(init?.method||(input instanceof Request?input.method:'GET')).toUpperCase();
      const target=new URL(input instanceof Request?input.url:input,window.location.href);
      return {method,target,safeGet:method==='GET'&&target.origin===window.location.origin};
    };
    const isSuccess=response=>(response.status>=200&&response.status<300)||response.status===304;
    const recoveryRequest=async(input,init,allowRetries=true)=>{
      const info=requestInfo(input,init);
      let response;
      try{
        response=await nativeFetch(input,init);
      }catch(requestError){
        let lastStatus=null;
        if(allowRetries&&info.safeGet){
          start();
          for(const wait of retryDelays){
            await delay(wait);
            try{
              response=await nativeFetch(input,init);
              if(isSuccess(response)){clear();return response;}
              lastStatus=response.status;
              if(!retryableStatuses.has(response.status)){
                showError(response.status,()=>recoveryRequest(input,init,false),false);
                return response;
              }
            }catch(_){/* Continue within this bounded GET retry window. */}
          }
        }
        showError(lastStatus,info.safeGet?()=>recoveryRequest(input,init,false):null,lastStatus===null||retryableStatuses.has(lastStatus));
        throw requestError;
      }

      if(isSuccess(response)){clear();return response;}
      // Leave authentication expiry and field-level validation to their
      // existing Laravel/client handlers.
      if(response.status===422||!handledStatuses.has(response.status))return response;
      if(allowRetries&&info.safeGet&&retryableStatuses.has(response.status)){
        start();
        for(const wait of retryDelays){
          await delay(wait);
          try{
            response=await nativeFetch(input,init);
            if(isSuccess(response)){clear();return response;}
            if(!retryableStatuses.has(response.status))break;
          }catch(_){/* Continue within this bounded GET retry window. */}
        }
      }
      showError(response.status,info.safeGet?()=>recoveryRequest(input,init,false):null,info.safeGet&&retryableStatuses.has(response.status));
      return response;
    };
    window.fetch=(input,init)=>{
      let info;
      try{info=requestInfo(input,init)}catch(_){return nativeFetch(input,init)}
      if(info.target.origin!==window.location.origin)return nativeFetch(input,init);
      const operation=recoveryRequest(input,init);
      if(info.safeGet)retrySafeRequest=()=>recoveryRequest(input,init);
      return operation;
    };
    window.addEventListener('online',()=>{
      if(overlay.classList.contains('has-error')&&retryOnReconnect)retryOnReconnect().catch(()=>{});
    });
    overlay.querySelector('[data-kjm-refresh]')?.addEventListener('click',()=>{
      if(retrySafeRequest){retrySafeRequest().catch(()=>{});return;}
      window.location.reload();
    });
    overlay.querySelector('[data-kjm-back]')?.addEventListener('click',()=>{
      try{
        const previous=new URL(document.referrer);
        if(previous.origin===window.location.origin&&previous.href!==window.location.href){window.location.assign(previous.href);return;}
      }catch(_){}
      window.location.assign(@json(route('admin.dashboard')));
    });
  }
  window.addEventListener('pagehide',clear,{once:true});
})();
</script>
