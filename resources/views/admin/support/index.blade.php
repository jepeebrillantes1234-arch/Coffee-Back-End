@extends('layouts.admin')
@section('title', 'Customer Support')
@section('content')
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div><h1 class="text-2xl font-semibold">Customer Support</h1><p class="mt-1 text-sm text-espresso/60">Reply to customer questions, review attachments, and handle refund requests.</p></div>
    <div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-green-50 px-3 py-2 text-xs font-semibold text-green-700">Inbox updates automatically</span><button id="enableSupportAlerts" type="button" class="rounded-full border border-espresso/15 bg-white px-3 py-2 text-xs font-semibold text-espresso">Enable desktop alerts</button></div>
</div>
<div id="supportInbox">@include('admin.support._inbox', ['conversations' => $conversations])</div>
@endsection
@push('scripts')
<script>
(()=>{const box=document.getElementById('supportInbox');if(!box)return;setInterval(async()=>{if(document.hidden)return;try{const response=await fetch(@json(route('admin.support.poll', request()->query())),{headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});if(response.ok){const data=await response.json();box.innerHTML=data.html}}catch(_){/* keep the inbox visible when temporarily offline */}},5000)})();
</script>
@endpush