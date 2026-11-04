@extends('layouts.admin')
@section('title', 'GPS Monitor')
@section('admin-styles')
<style>
.gps-page{--gps-coffee:#4b291a;--gps-muted:#88786d;--gps-line:#eee3d8;--gps-caramel:#a85b2a}.gps-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:5px 0 19px}.gps-heading-main{display:flex;align-items:center;gap:14px}.gps-heading-icon{width:54px;height:54px;border-radius:15px;background:linear-gradient(145deg,#713b1c,#a45a2b);color:#fff;display:grid;place-items:center;box-shadow:0 7px 16px #66351522;flex:none}.gps-heading-icon svg{width:27px;height:27px}.gps-title{margin:0;color:#3f2418;font:700 27px 'Playfair Display',serif}.gps-subtitle{margin:4px 0 0;color:#817165;font-size:12px}.gps-heading-meta{text-align:right}.gps-live-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 13px;border-radius:99px;background:#e9f6e8;border:1px solid #cfe8ca;color:#2b7540;font-size:11px;font-weight:700}.gps-live-chip i,.gps-status-online i{width:8px;height:8px;border-radius:50%;background:#32a553;display:inline-block;box-shadow:0 0 0 3px #32a55320}.gps-last-update{font-size:10px;color:#8e8075;margin-top:7px;display:flex;justify-content:flex-end;align-items:center;gap:6px}.gps-refresh{border:0;background:transparent;color:#71543f;padding:2px;cursor:pointer;display:grid;place-items:center}.gps-refresh svg{width:14px;height:14px}.gps-layout{display:grid;grid-template-columns:minmax(0,1.8fr) minmax(300px,.82fr);gap:14px;align-items:stretch}.gps-panel{background:#fffdfa;border:1px solid #eee7df;border-radius:16px;box-shadow:0 7px 20px #3d23140a;min-width:0}.gps-map-panel{padding:14px}.gps-map-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 1px 12px}.gps-map-heading{font-size:13px;font-weight:700;color:#50301e;display:flex;align-items:center;gap:8px}.gps-map-heading svg{width:18px;height:18px;color:#9a5428}.gps-map-hint{font-size:10px;color:#95877b}.gps-map-wrap{height:560px;position:relative;border-radius:13px;overflow:hidden;background:#e9e5db;border:1px solid #eee7df}.gps-map{height:100%;width:100%;z-index:1}.gps-legend{position:absolute;left:13px;bottom:13px;z-index:500;display:flex;gap:12px;align-items:center;padding:9px 12px;border-radius:99px;background:#fffdf4eF;box-shadow:0 2px 12px #25160d30;font-size:10px;color:#4c4038}.gps-legend-item{display:flex;align-items:center;gap:5px;white-space:nowrap}.gps-legend-dot{width:8px;height:8px;border-radius:50%}.gps-dot-online{background:#2eaa58}.gps-dot-offline{background:#9da1a3}.gps-dot-delivery{background:#f49b22}.gps-map-credit{position:absolute;right:12px;bottom:12px;z-index:500;border-radius:99px;background:#4a2818;color:#fff;padding:8px 11px;font-size:10px;box-shadow:0 2px 10px #25160d30}.gps-rider-panel{padding:17px;display:flex;flex-direction:column;min-height:590px;max-height:590px}.gps-rider-heading{display:flex;align-items:center;gap:10px;padding:0 0 13px;border-bottom:1px solid var(--gps-line);margin-bottom:12px}.gps-rider-heading-icon{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#f2e5d8;color:#70401f}.gps-rider-heading-icon svg{width:19px;height:19px}.gps-rider-heading h2{margin:0;color:#402719;font-size:14px;font-weight:700}.gps-rider-count{margin-left:auto;border-radius:99px;background:#f6eee7;color:#815333;padding:4px 8px;font-size:10px;font-weight:700}.gps-rider-list{flex:1;overflow:auto;padding:1px 3px 2px 1px;display:flex;flex-direction:column;gap:10px;scrollbar-width:thin;scrollbar-color:#cbb5a3 transparent}.gps-rider-card{padding:13px;border:1px solid #eee4da;border-radius:13px;background:linear-gradient(145deg,#fffdfa,#fcf8f3);transition:box-shadow .18s,border-color .18s}.gps-rider-card:hover{border-color:#d8bda4;box-shadow:0 5px 15px #50301a0c}.gps-rider-card-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:11px}.gps-status-online,.gps-status-offline{display:inline-flex;align-items:center;gap:6px;padding:4px 9px;border-radius:99px;font-size:10px;font-weight:700;white-space:nowrap}.gps-status-online{background:#e4f4e8;color:#287742}.gps-status-offline{background:#eeeae6;color:#77716c}.gps-status-offline i{width:8px;height:8px;border-radius:50%;background:#a3a09c;display:inline-block}.gps-updated{font-size:10px;color:#968a80;white-space:nowrap}.gps-rider-ident{display:flex;align-items:center;gap:11px;min-width:0}.gps-avatar{width:52px;height:52px;border-radius:50%;overflow:hidden;flex:none;display:grid;place-items:center;background:#eadbcb;color:#664126;border:2px solid #fff;box-shadow:0 0 0 1px #eadbcb;font-size:16px;font-weight:700}.gps-avatar img{width:100%;height:100%;object-fit:cover}.gps-rider-name{margin:0;color:#3e2b20;font-size:13px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.gps-rider-order{margin:4px 0 0;color:#8c796b;font-size:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.gps-rider-meta{margin:10px 0 11px 63px;display:grid;gap:6px;color:#6e5b4d;font-size:10px}.gps-rider-meta span{display:flex;align-items:center;gap:6px}.gps-rider-meta svg{width:13px;height:13px;color:#79543a;flex:none}.gps-view-location{width:100%;height:36px;border:0;border-radius:9px;background:#67381f;color:#fffaf4;font:600 11px 'DM Sans',sans-serif;display:flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;transition:background .16s}.gps-view-location:hover{background:#844b27}.gps-view-location svg{width:15px;height:15px}.gps-empty{padding:38px 15px;text-align:center;color:#95877d;font-size:12px;line-height:1.6}.gps-empty svg{display:block;width:34px;height:34px;margin:0 auto 10px;color:#bd9b7b}.gps-marker{background:transparent;border:0}.gps-marker-pin{width:38px;height:38px;border:3px solid #fff;border-radius:50% 50% 50% 6px;transform:rotate(-45deg);display:grid;place-items:center;box-shadow:0 4px 12px #25160d55;color:white}.gps-marker-pin svg{width:20px;height:20px;transform:rotate(45deg)}.gps-marker-online{background:#188446}.gps-marker-photo{width:42px;height:42px;border:3px solid #fff;border-radius:50%;overflow:hidden;box-shadow:0 3px 12px #25160d55;background:#188446}.gps-marker-photo img{width:100%;height:100%;object-fit:cover}.gps-customer-marker{width:34px;height:34px;background:#fff;border:3px solid #bc672d;border-radius:50%;box-shadow:0 2px 10px #25160d55;color:#67381f;display:grid;place-items:center;overflow:hidden;font-weight:800}.gps-customer-marker img{width:100%;height:100%;object-fit:cover}.gps-marker-offline{background:#818487}.gps-marker-delivery{background:#bc672d}.leaflet-popup-content-wrapper{border-radius:10px!important}.leaflet-popup-content{font-family:'DM Sans',sans-serif;font-size:12px}.leaflet-control-zoom a{color:#4c2a1a!important}
@media(max-width:1120px){.gps-layout{grid-template-columns:minmax(0,1.5fr) minmax(280px,.9fr)}.gps-map-wrap{height:500px}}
@media(max-width:900px){.gps-layout{grid-template-columns:1fr}.gps-map-wrap{height:min(60vh,500px);min-height:360px}.gps-rider-panel{min-height:360px;max-height:480px}}
@media(max-width:540px){.gps-heading{align-items:flex-start}.gps-heading-main{gap:10px}.gps-heading-icon{width:45px;height:45px}.gps-title{font-size:23px}.gps-heading-meta{max-width:130px}.gps-live-chip{padding:7px 9px;font-size:10px}.gps-last-update{font-size:9px}.gps-map-panel{padding:9px}.gps-map-wrap{height:390px;min-height:330px}.gps-map-hint{display:none}.gps-legend{left:7px;bottom:7px;gap:7px;padding:7px 8px;font-size:9px}.gps-legend-item{gap:4px}.gps-map-credit{right:7px;bottom:7px;padding:7px 9px;font-size:9px}.gps-rider-panel{padding:13px}}
</style>
@endsection
@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<div class="gps-page">
    <header class="gps-heading">
        <div class="gps-heading-main">
            <span class="gps-heading-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
            <div><h1 class="gps-title">GPS Monitor</h1><p class="gps-subtitle">Track your riders in real time</p></div>
        </div>
        <div class="gps-heading-meta">
            <span class="gps-live-chip"><i></i><span id="gps-connection">System Active</span></span>
            <div class="gps-last-update"><span id="last-update">Last update: just now</span><button type="button" class="gps-refresh" onclick="refreshRiders()" title="Refresh rider locations" aria-label="Refresh rider locations"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg></button></div>
        </div>
    </header>
    <div class="gps-layout">
        <section class="gps-panel gps-map-panel" aria-label="Live rider map">
            <div class="gps-map-toolbar"><h2 class="gps-map-heading"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>Live Rider Map</h2><span class="gps-map-hint">Live rider location updates</span></div>
            <div class="gps-map-wrap"><div id="map" class="gps-map"></div>
                <div class="gps-legend"><span class="gps-legend-item"><i class="gps-legend-dot gps-dot-online"></i>Rider</span><span class="gps-legend-item"><i class="gps-legend-dot gps-dot-delivery"></i>Customer</span><span class="gps-legend-item"><i style="width:16px;height:3px;background:#bc672d;border-radius:2px"></i>OSRM route</span></div>
                <span class="gps-map-credit">OpenStreetMap</span>
            </div>
        </section>
        <aside class="gps-panel gps-rider-panel" id="rider-panel">
            <div class="gps-rider-heading"><span class="gps-rider-heading-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="m15 6 3.5 11.5M12 17.5V14l-3-3 4-3 2 3h3"/></svg></span><h2>Rider Details</h2><span class="gps-rider-count" id="rider-count">0 riders</span></div>
            <div id="rider-list" class="gps-rider-list"><div class="gps-empty">Loading rider locations...</div></div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
const SHOP = @json($shop);
const DATA_URL = @json(route('admin.gps.data', [], false));
let map=null,riderMarkers={},customerMarkers={},routeLines={},shopMarker=null,fitted=false,lastUpdateTime=Date.now(),refreshing=false,cachedRiders=[],lastVisibleKey='';
const esc=value=>String(value??'').replace(/[&<>'"]/g,function(character){if(character==='&')return '&amp;';if(character==='<')return '&lt;';if(character==='>')return '&gt;';if(character==='"')return '&quot;';return '&#39;';});
const statusLabel=status=>({assigned:'Assigned',accepted:'Accepted',picked_up:'Picked up',on_the_way:'On the way'}[status]||String(status||'').replaceAll('_',' '));
function riderIcon(r){
 const name=esc(r.name||'Rider'),avatar=r.avatar_url||'';
 const html=avatar?'<span class=\"gps-marker-photo\"><img src=\"'+esc(avatar)+'\" alt=\"'+name+'\" onerror=\"this.remove()\"></span>':'<span class=\"gps-marker-pin gps-marker-delivery\">R</span>';
 return L.divIcon({className:'gps-marker',html:html,iconSize:[42,42],iconAnchor:[21,21],popupAnchor:[0,-20]});
}
function customerIcon(name,avatar){
 const customer=esc(name||'Customer'),image=avatar?'<img src=\"'+esc(avatar)+'\" alt=\"'+customer+'\" onerror=\"this.remove()\">':'C';
 return L.divIcon({className:'gps-marker',html:'<span class=\"gps-customer-marker\" aria-label=\"Customer '+customer+'\">'+image+'</span>',iconSize:[34,34],iconAnchor:[17,17],popupAnchor:[0,-17]});
}
function initMap(){
 map=L.map('map',{zoomControl:true}).setView([SHOP.lat,SHOP.lng],13);
 L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a>'}).addTo(map);
 shopMarker=L.circleMarker([SHOP.lat,SHOP.lng],{radius:9,color:'#fff',weight:3,fillColor:'#6b3c20',fillOpacity:1}).addTo(map).bindPopup('<strong>KJM Coffee Shop</strong>');
}
function renderList(riders){
 const list=document.getElementById('rider-list');
 document.getElementById('rider-count').textContent=riders.length+' '+(riders.length===1?'active delivery':'active deliveries');
 if(!riders.length){list.innerHTML='<div class=\"gps-empty\">No active deliveries with a recent rider GPS location.</div>';return;}
 list.innerHTML=riders.map(function(r){
  const name=esc(r.name||'Rider'),customer=esc(r.customer&&r.customer.name||'Customer'),avatar=r.avatar_url||'',customerAvatar=r.customer&&r.customer.avatar_url||'',address=esc(r.destination&&r.destination.address||'Destination address unavailable'),route=r.route||{},distance=Number(route.distance_meters),duration=Number(route.duration_seconds);
  const routeText=route.status==='available'?(Number.isFinite(distance)?(distance/1000).toFixed(1)+' km':'Route available')+(Number.isFinite(duration)?' · '+Math.max(1,Math.round(duration/60))+' min':''):esc(route.error||'Route unavailable');
  const lastSeen=esc(r.updated||'Just now');
  return '<article class=\"gps-rider-card\"><div class=\"gps-rider-card-head\"><span class=\"gps-status-online\"><i></i>Online · Active delivery</span><span class=\"gps-updated\">'+lastSeen+'</span></div><div class=\"gps-rider-ident\"><span class=\"gps-avatar\">'+(avatar?'<img src=\"'+esc(avatar)+'\" alt=\"'+name+'\" onerror=\"this.remove()\">':esc((r.name||'R').charAt(0).toUpperCase()))+'</span><div style=\"min-width:0\"><h3 class=\"gps-rider-name\">'+name+'</h3><p class=\"gps-rider-order\">Order '+esc(r.order||'')+'</p></div></div><div class=\"gps-rider-meta\"><span>'+esc(statusLabel(r.status))+'</span><span><span class=\"gps-avatar\" style=\"width:28px;height:28px\">'+(customerAvatar?'<img src=\"'+esc(customerAvatar)+'\" alt=\"'+customer+'\" onerror=\"this.remove()\">':esc((r.customer&&r.customer.name||'C').charAt(0).toUpperCase()))+'</span>'+customer+'</span><span>Destination: '+address+'</span><span>OSRM: '+routeText+'</span><span>Last GPS update: '+esc(r.updated_at||lastSeen)+'</span></div><button type=\"button\" class=\"gps-view-location\" onclick=\"zoomToDelivery('+Number(r.id)+','+Number(r.lat)+','+Number(r.lng)+')\">View rider, customer and route</button></article>';
 }).join('');
}
function pointArray(points){return Array.isArray(points)?points.map(p=>[Number(p.lat),Number(p.lng)]).filter(p=>Number.isFinite(p[0])&&Number.isFinite(p[1])):[];}
function renderMarkers(riders){
 const seen=new Set(),bounds=[[SHOP.lat,SHOP.lng]];
 riders.forEach(function(r){
  const id=String(r.id),lat=Number(r.lat),lng=Number(r.lng);
  if(!Number.isFinite(lat)||!Number.isFinite(lng))return;
  seen.add(id);bounds.push([lat,lng]);
  const riderPopup='<strong>'+esc(r.name||'Rider')+'</strong><br>Order '+esc(r.order||'')+' · '+esc(statusLabel(r.status))+'<br>Last GPS: '+esc(r.updated_at||r.updated||'');
  if(riderMarkers[id])riderMarkers[id].setLatLng([lat,lng]).setIcon(riderIcon(r)).setPopupContent(riderPopup);
  else riderMarkers[id]=L.marker([lat,lng],{icon:riderIcon(r)}).addTo(map).bindPopup(riderPopup);
  const destination=r.destination||{},dlat=Number(destination.lat),dlng=Number(destination.lng),hasDestination=Number.isFinite(dlat)&&Number.isFinite(dlng);
  if(hasDestination){
   bounds.push([dlat,dlng]);
   const customer=esc(r.customer&&r.customer.name||'Customer'),customerPopup='<strong>'+customer+'</strong><br>'+esc(destination.address||'Delivery destination');
   if(customerMarkers[id])customerMarkers[id].setLatLng([dlat,dlng]).setPopupContent(customerPopup);
   else customerMarkers[id]=L.marker([dlat,dlng],{icon:customerIcon(customer,r.customer&&r.customer.avatar_url)}).addTo(map).bindPopup(customerPopup);
   const points=pointArray(r.route&&r.route.points);
   if(points.length>=2){if(routeLines[id])routeLines[id].setLatLngs(points);else routeLines[id]=L.polyline(points,{color:'#bc672d',weight:5,opacity:.82}).addTo(map);points.forEach(p=>bounds.push(p));}
   else if(routeLines[id]){map.removeLayer(routeLines[id]);delete routeLines[id];}
  }else if(customerMarkers[id]){map.removeLayer(customerMarkers[id]);delete customerMarkers[id];}
 });
 [riderMarkers,customerMarkers,routeLines].forEach(function(group){Object.keys(group).forEach(function(id){if(!seen.has(id)){map.removeLayer(group[id]);delete group[id];}});});
 if(!fitted&&seen.size){map.fitBounds(L.latLngBounds(bounds),{padding:[28,28],maxZoom:15});fitted=true;}
}
function renderVisibleRiders(force){
 const now=Date.now(),visible=cachedRiders.filter(function(r){
  const stamp=Date.parse(r.updated_at||''),age=now-stamp;
  return Number.isFinite(stamp)&&age<=120000&&age>=-30000;
 });
 const key=visible.map(function(r){return String(r.id)+':'+String(r.updated_at||'');}).join('|');
 if(force||key!==lastVisibleKey){lastVisibleKey=key;renderList(visible);renderMarkers(visible);}
}
function zoomToDelivery(id,lat,lng){
 if(!map||!Number.isFinite(lat)||!Number.isFinite(lng))return;
 const rider=riderMarkers[String(id)],customer=customerMarkers[String(id)],line=routeLines[String(id)],layers=[rider,customer,line].filter(Boolean);
 if(layers.length>1)map.fitBounds(L.featureGroup(layers).getBounds().pad(.18),{maxZoom:16});else map.flyTo([lat,lng],16,{duration:.7});
 rider&&rider.openPopup();
}
async function refreshRiders(){
 if(refreshing)return;refreshing=true;
 const state=document.getElementById('gps-connection');
 try{
  const response=await fetch(DATA_URL,{headers:{'Accept':'application/json'},cache:'no-store'});
  if(!response.ok)throw new Error('GPS request failed ('+response.status+')');
  const json=await response.json();cachedRiders=Array.isArray(json.riders)?json.riders:[];renderVisibleRiders(true);lastUpdateTime=Date.now();state.textContent='System Active';state.parentElement.classList.remove('gps-status-offline');document.getElementById('last-update').textContent='Last update: just now';
 }catch(error){console.error(error);renderVisibleRiders();state.textContent='Connection issue';document.getElementById('last-update').textContent='Could not refresh rider locations';}
 finally{refreshing=false;}
}
setInterval(function(){renderVisibleRiders();if(Date.now()-lastUpdateTime<1500)return;const seconds=Math.floor((Date.now()-lastUpdateTime)/1000);document.getElementById('last-update').textContent='Last update: '+seconds+' seconds ago';},1000);
initMap();refreshRiders();setInterval(refreshRiders,5000);window.addEventListener('live-update',function(event){if(['delivery','rider'].includes(event.detail&&event.detail.kind))refreshRiders();});
</script>
@endpush
