<?php $__env->startSection('title', 'Chat - Supervision - L2T Support'); ?>

<?php $__env->startSection('content'); ?>
<style>
/* Prevent main dashboard layout from scrolling */
.main-content {
  overflow-y: hidden !important;
}
/* Ensure root container does not cause page scroll */
.chat-root {
  overflow: hidden;
}
/* ─── Root ──────────────────────────────────────────────────── */
.chat-root{
  display:flex;flex-direction:column;
  height:calc(100vh - 140px);
  padding:0;gap:12px;
  box-sizing:border-box;
}

/* ─── Top bar ───────────────────────────────────────────────── */
.chat-topbar{
  display:flex;align-items:center;justify-content:space-between;
  flex-shrink:0;gap:12px;flex-wrap:wrap;
}
.chat-topbar-left{display:flex;flex-direction:column;gap:2px;}
.chat-page-title{font-size:20px;font-weight:800;color:var(--text-heading,#1e293b);letter-spacing:-.3px;}
.chat-page-sub{font-size:12px;color:var(--text-muted,#64748b);}
.chat-badge-readonly{
  display:inline-flex;align-items:center;gap:5px;
  background:#fef3c7;color:#92400e;
  border:1px solid #fde68a;border-radius:99px;
  padding:3px 10px;font-size:11px;font-weight:700;
}

/* ─── 3-col grid ────────────────────────────────────────────── */
.chat-grid{
  flex:1;min-height:0;
  display:grid;
  grid-template-columns:280px 300px 1fr;
  gap:12px;
  height:100%;
}
@media(max-width:1100px){.chat-grid{grid-template-columns:240px 260px 1fr;}}
@media(max-width:860px){.chat-grid{grid-template-columns:1fr;height:auto;}}

/* ─── Panel shell ───────────────────────────────────────────── */
.c-panel{
  display:flex;flex-direction:column;
  border:1px solid var(--border-color,#e2e8f0);
  background:var(--bg-card,#ffffff);
  border-radius:14px;overflow:hidden;
  min-height:0;
}
.c-panel-head{
  padding:10px 12px;
  border-bottom:1px solid var(--border-color,#e2e8f0);
  flex-shrink:0;
  display:flex;flex-direction:column;gap:6px;
}
.c-panel-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted,#64748b);}
.c-panel-body{flex:1;overflow-y:auto;min-height:0;}

/* ─── Search input ──────────────────────────────────────────── */
.c-search{
  width:100%;height:34px;
  border:1px solid var(--border-color,#e2e8f0);
  border-radius:8px;padding:0 10px;
  font-size:12.5px;background:var(--bg-body,#f8fafc);
  color:var(--text-heading,#1e293b);
  outline:none;box-sizing:border-box;
  transition:border-color .2s;
}
.c-search:focus{border-color:var(--color-primary,#2563eb);}

/* ─── Client list ───────────────────────────────────────────── */
.c-client-item{
  display:flex;align-items:center;gap:10px;
  padding:10px 12px;
  border-bottom:1px solid var(--border-color,#e2e8f0);
  cursor:pointer;transition:background .15s;
}
.c-client-item:hover{background:var(--bg-body,#f8fafc);}
.c-client-item.active{
  background:color-mix(in srgb,var(--color-primary,#2563eb) 8%,transparent);
  border-left:3px solid var(--color-primary,#2563eb);
}
.c-client-avatar{
  width:36px;height:36px;border-radius:50%;
  background:linear-gradient(135deg,#6366f1,#8b5cf6);
  display:flex;align-items:center;justify-content:center;
  font-size:13px;font-weight:700;color:#fff;flex-shrink:0;
}
.c-client-info{flex:1;min-width:0;}
.c-client-name{font-size:13px;font-weight:700;color:var(--text-heading,#1e293b);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.c-client-email{font-size:11px;color:var(--text-muted,#64748b);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.c-client-count{
  font-size:10px;font-weight:700;
  background:var(--color-primary,#2563eb);color:#fff;
  border-radius:99px;padding:2px 7px;flex-shrink:0;
  display:none;
}

/* ─── Conversation list ─────────────────────────────────────── */
.c-conv-item{
  padding:10px 12px;
  border-bottom:1px solid var(--border-color,#e2e8f0);
  cursor:pointer;transition:background .15s;
}
.c-conv-item:hover{background:var(--bg-body,#f8fafc);}
.c-conv-item.active{
  background:color-mix(in srgb,var(--color-primary,#2563eb) 8%,transparent);
  border-left:3px solid var(--color-primary,#2563eb);
}
.c-conv-title{font-size:12.5px;font-weight:700;color:var(--text-heading,#1e293b);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.c-conv-row{display:flex;justify-content:space-between;align-items:center;gap:6px;}
.c-conv-channel{
  font-size:10px;font-weight:700;
  border-radius:99px;padding:2px 7px;
}
.ch-CHAT{background:#dbeafe;color:#1e40af;}
.ch-EMAIL{background:#fce7f3;color:#9d174d;}
.ch-WHATSAPP{background:#d1fae5;color:#065f46;}
.ch-VOICE{background:#ede9fe;color:#4c1d95;}
.ch-OTHER,.ch-default{background:#f1f5f9;color:#475569;}
.c-conv-status{
  font-size:10px;border-radius:99px;padding:2px 7px;font-weight:600;
}
.st-open,.st-unresolved{background:#fef3c7;color:#92400e;}
.st-resolved{background:#d1fae5;color:#065f46;}
.st-in_progress{background:#dbeafe;color:#1e40af;}
.st-closed{background:#f1f5f9;color:#475569;}
.c-conv-date{font-size:10px;color:var(--text-muted,#94a3b8);}

/* ─── Messages panel ────────────────────────────────────────── */
.c-msg-head{
  padding:12px 14px;
  border-bottom:1px solid var(--border-color,#e2e8f0);
  display:flex;justify-content:space-between;align-items:flex-start;flex-shrink:0;
}
.c-msg-head-info{display:flex;flex-direction:column;gap:3px;}
.c-msg-head-title{font-size:14px;font-weight:800;color:var(--text-heading,#1e293b);}
.c-msg-head-meta{font-size:11px;color:var(--text-muted,#64748b);}
.c-msg-refresh{
  border:1px solid var(--border-color,#e2e8f0);
  background:transparent;border-radius:8px;
  padding:5px 10px;font-size:11px;font-weight:700;cursor:pointer;
  color:var(--text-heading,#1e293b);flex-shrink:0;
}
.c-msg-refresh:disabled{opacity:.5;cursor:not-allowed;}
.c-messages{
  flex:1;min-height:0;overflow-y:auto;
  padding:14px;
  display:flex;flex-direction:column;gap:9px;
  background:var(--bg-body,#f8fafc);
}
.c-msg{
  max-width:75%;padding:10px 13px;border-radius:14px;
  font-size:13px;line-height:1.65;border:1px solid;
}
.c-msg.user{
  align-self:flex-end;
  background:#ffffff;
  border-color:#e2e8f0;
  color:#1e293b;
  border-bottom-right-radius:3px;
}
.c-msg.agent{
  align-self:flex-start;
  background:color-mix(in srgb,var(--color-primary,#2563eb) 10%,#fff);
  border-color:color-mix(in srgb,var(--color-primary,#2563eb) 25%,transparent);
  color:#0f172a;
  border-bottom-left-radius:3px;
}
.c-msg-role{font-size:10px;font-weight:700;margin-bottom:4px;opacity:.65;text-transform:uppercase;letter-spacing:.4px;}
.c-msg-time{font-size:10px;color:#94a3b8;margin-top:5px;text-align:right;}
.c-msg-text{white-space:pre-wrap;word-break:break-word;}

/* ─── Readonly notice at bottom ────────────────────────────── */
.c-readonly-bar{
  padding:9px 14px;
  border-top:1px solid var(--border-color,#e2e8f0);
  background:var(--bg-body,#f8fafc);
  font-size:11px;color:var(--text-muted,#94a3b8);
  text-align:center;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;gap:6px;
}

/* ─── Empty / loading states ────────────────────────────────── */
.c-empty{
  padding:28px 16px;text-align:center;
  color:var(--text-muted,#94a3b8);font-size:13px;
}
.c-empty svg{display:block;margin:0 auto 10px;opacity:.35;}
.c-spinner{
  display:inline-block;width:20px;height:20px;
  border:2px solid var(--border-color,#e2e8f0);
  border-top-color:var(--color-primary,#2563eb);
  border-radius:50%;animation:spin .7s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg);}}
</style>

<div class="chat-root">

  
  <div class="chat-topbar">
    <div class="chat-topbar-left">
      <div class="chat-page-title">📨 Supervision des Conversations</div>
      <div class="chat-page-sub">Vue admin — conversations clients uniquement (chat)</div>
    </div>
    <span class="chat-badge-readonly">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      Lecture seule
    </span>
  </div>

  
  <div class="chat-grid">

    
    <div class="c-panel" id="panelClients">
      <div class="c-panel-head">
        <div class="c-panel-label">👤 Clients</div>
        <input id="clientSearch" class="c-search" placeholder="Rechercher un client…">
      </div>
      <div class="c-panel-body" id="clientList">
        <div class="c-empty"><div class="c-spinner"></div></div>
      </div>
    </div>

    
    <div class="c-panel" id="panelConvs">
      <div class="c-panel-head">
        <div class="c-panel-label">💬 Conversations</div>
        <input id="convSearch" class="c-search" placeholder="Rechercher une conversation…">
      </div>
      <div class="c-panel-body" id="convList">
        <div class="c-empty">
          <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          Sélectionnez un client
        </div>
      </div>
    </div>

    
    <div class="c-panel" id="panelMsgs">
      <div class="c-msg-head">
        <div class="c-msg-head-info">
          <div class="c-msg-head-title" id="msgTitle">Sélectionnez une conversation</div>
          <div class="c-msg-head-meta" id="msgMeta">Aucun fil chargé.</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <button class="c-msg-refresh" id="btnDownloadCall" style="display:none;" disabled>⬇️ Télécharger l'appel</button>
          <button class="c-msg-refresh" id="btnRefreshMsgs" disabled>↻ Actualiser</button>
        </div>
      </div>
      <div class="c-messages" id="msgBox">
        <div class="c-empty">
          <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          Aucun message à afficher.
        </div>
      </div>
      <div class="c-readonly-bar">
        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        Mode lecture seule — aucune action possible depuis cette vue
      </div>
    </div>

  </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   STATE
══════════════════════════════════════════════════════════════ */
const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const apiUrl = p => window.supportApiUrl ? window.supportApiUrl(p) : '/api/v1/' + String(p||'').replace(/^\//,'');

let allClients       = [];   // raw list from /users?role=CLIENT
let selectedClientId = null;
let clientConvs      = [];   // conversations of selected client
let selectedConvId   = null;
let clientSearchQ    = '';
let convSearchQ      = '';

/* ══════════════════════════════════════════════════════════════
   HELPERS
══════════════════════════════════════════════════════════════ */
function esc(v){ return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

async function apiFetch(path, opts={}){
  const headers = Object.assign({'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':CSRF}, opts.headers||{});
  const res = await fetch(apiUrl(path), Object.assign({},opts,{headers}));
  if(res.status===204) return null;
  const data = await res.json().catch(()=>({}));
  if(!res.ok) throw new Error(data.detail||data.message||('HTTP '+res.status));
  return data;
}

function initials(name){
  if(!name) return '?';
  const parts = name.trim().split(/\s+/);
  return parts.length>=2 ? (parts[0][0]+parts[1][0]).toUpperCase() : name.slice(0,2).toUpperCase();
}

function avatarColor(id){
  const colors = [
    ['#6366f1','#8b5cf6'],['#0ea5e9','#06b6d4'],['#f59e0b','#f97316'],
    ['#10b981','#059669'],['#ec4899','#a855f7'],['#3b82f6','#6366f1'],
  ];
  const h = String(id||'').split('').reduce((a,c)=>a+c.charCodeAt(0),0);
  const [c1,c2] = colors[h%colors.length];
  return `linear-gradient(135deg,${c1},${c2})`;
}

function channelClass(ch){ return 'ch-'+(ch||'OTHER').toUpperCase(); }

function statusClass(st){
  const s = String(st||'').toLowerCase().replace(/\s+/g,'_');
  return 'st-'+s;
}

function parseDate(s){
  if(!s) return null;
  // If the string has no timezone info, treat as UTC
  if(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(s) && !/[Z+]/.test(s)) s += 'Z';
  const d = new Date(s);
  return isNaN(d.getTime()) ? null : d;
}

function relativeTime(dateStr){
  if(!dateStr) return '';
  const d = parseDate(dateStr);
  if(!d) return '';
  const diff = (Date.now() - d.getTime()) / 1000;
  if(diff < 0)   return "à l'instant";  // future / clock skew
  if(diff < 60)  return "à l'instant";
  if(diff < 3600) return Math.floor(diff/60) + ' min';
  if(diff < 86400) return 'aujourd\'hui ' + d.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});
  if(diff < 172800) return 'hier ' + d.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});
  return d.toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit',year:'2-digit'});
}

function convTitle(c){ return c.subject||c.title||('Conv. '+String(c.id).slice(0,8)); }

/* ══════════════════════════════════════════════════════════════
   PANEL 1 — CLIENTS
══════════════════════════════════════════════════════════════ */
async function loadClients(){
  const box = document.getElementById('clientList');
  box.innerHTML = '<div class="c-empty"><div class="c-spinner"></div></div>';
  try{
    const res = await apiFetch('/users?role=CLIENT&limit=200');
    allClients = Array.isArray(res?.users) ? res.users : (Array.isArray(res) ? res : []);
    // Admin supervision should only show real client chat accounts, not WhatsApp-only identities.
    allClients = allClients.filter(u => {
      const email = String(u.email || '').toLowerCase();
      const name = String(u.full_name || u.name || '').toLowerCase();
      const id = String(u.id || '');
      return !email.includes('@whatsapp.local')
        && !email.startsWith('wa_')
        && !name.includes('whatsapp')
        && !name.startsWith('wa_')
        && !id.startsWith('wa_');
    });
    renderClients();
  }catch(e){
    box.innerHTML = `<div class="c-empty">⚠️ ${esc(e.message||'Erreur chargement clients')}</div>`;
  }
}

function filteredClients(){
  const q = clientSearchQ.trim().toLowerCase();
  if(!q) return allClients;
  return allClients.filter(u=>`${u.full_name||''} ${u.email||''}`.toLowerCase().includes(q));
}

function renderClients(){
  const box = document.getElementById('clientList');
  const list = filteredClients();
  if(!list.length){
    box.innerHTML = '<div class="c-empty">Aucun client trouvé.</div>';
    return;
  }
  box.innerHTML = list.map(u=>{
    const active = u.id===selectedClientId?' active':'';
    const displayName = u.full_name || u.email || '(sans nom)';
    return `
    <div class="c-client-item${active}" data-uid="${esc(u.id)}">
      <div class="c-client-avatar" style="background:${avatarColor(u.id)}">${esc(initials(displayName))}</div>
      <div class="c-client-info">
        <div class="c-client-name">${esc(displayName)}</div>
        <div class="c-client-email">${esc(u.email||'-')}</div>
      </div>
    </div>`;
  }).join('');
}

document.getElementById('clientSearch').addEventListener('input',e=>{
  clientSearchQ = e.target.value||'';
  renderClients();
});

document.getElementById('clientList').addEventListener('click',async e=>{
  const row = e.target.closest('[data-uid]');
  if(!row) return;
  selectedClientId = row.getAttribute('data-uid');
  selectedConvId = null;
  renderClients();
  await loadClientConversations(selectedClientId);
});

/* ══════════════════════════════════════════════════════════════
   PANEL 2 — CONVERSATIONS
══════════════════════════════════════════════════════════════ */
async function loadClientConversations(userId){
  const box = document.getElementById('convList');
  box.innerHTML = '<div class="c-empty"><div class="c-spinner"></div></div>';
  resetMessagesPanel();
  try{
    // Try fetching with user_id param; fall back to client_id if empty
    let res = await apiFetch(`/conversations?user_id=${encodeURIComponent(userId)}&limit=200`);
    let convs = Array.isArray(res?.conversations) ? res.conversations : (Array.isArray(res) ? res : []);
    // If nothing returned, try with client_id param
    if(!convs.length){
      res = await apiFetch(`/conversations?client_id=${encodeURIComponent(userId)}&limit=200`);
      convs = Array.isArray(res?.conversations) ? res.conversations : (Array.isArray(res) ? res : []);
    }
    // Client-side guard: keep only chat conversations that belong to this user
    clientConvs = convs.filter(c => {
      const uid = c.user_id || c.client_id || c.userId || c.clientId || null;
      const channel = String(c.channel || '').toUpperCase();
      return (!uid || String(uid) === String(userId)) && channel === 'CHAT';
    });
    renderConvs();
  }catch(e){
    box.innerHTML = `<div class="c-empty">⚠️ ${esc(e.message||'Erreur chargement conversations')}</div>`;
  }
}

function filteredConvs(){
  const q = convSearchQ.trim().toLowerCase();
  const sorted = [...clientConvs].sort((a,b)=>new Date(b.updated_at||b.created_at||0)-new Date(a.updated_at||a.created_at||0));
  if(!q) return sorted;
  return sorted.filter(c=>`${convTitle(c)} ${c.status||''} ${c.channel||''}`.toLowerCase().includes(q));
}

function renderConvs(){
  const box = document.getElementById('convList');
  const list = filteredConvs();
  if(!list.length){
    box.innerHTML = '<div class="c-empty">Aucune conversation pour ce client.</div>';
    return;
  }
  box.innerHTML = list.map(c=>{
    const active = c.id===selectedConvId?' active':'';
    const chCls  = channelClass(c.channel);
    const stCls  = statusClass(c.status);
    return `
    <div class="c-conv-item${active}" data-cid="${esc(c.id)}">
      <div class="c-conv-title" title="${esc(convTitle(c))}">${esc(convTitle(c))}</div>
      <div class="c-conv-row">
        <span class="c-conv-channel ${chCls}">${esc(c.channel||'?')}</span>
        <span class="c-conv-status ${stCls}">${esc(c.status||'-')}</span>
        <span class="c-conv-date">${esc(relativeTime(c.updated_at||c.created_at))}</span>
      </div>
    </div>`;
  }).join('');
}

document.getElementById('convSearch').addEventListener('input',e=>{
  convSearchQ = e.target.value||'';
  renderConvs();
});

document.getElementById('convList').addEventListener('click',async e=>{
  const row = e.target.closest('[data-cid]');
  if(!row) return;
  selectedConvId = row.getAttribute('data-cid');
  renderConvs();
  const conv = clientConvs.find(c=>c.id===selectedConvId)||null;
  await loadMessages(selectedConvId, conv);
});

/* ══════════════════════════════════════════════════════════════
   PANEL 3 — MESSAGES (read-only)
══════════════════════════════════════════════════════════════ */
function resetMessagesPanel(){
  document.getElementById('msgTitle').textContent = 'Sélectionnez une conversation';
  document.getElementById('msgMeta').textContent  = 'Aucun fil chargé.';
  document.getElementById('btnRefreshMsgs').disabled = true;
  document.getElementById('msgBox').innerHTML = `
    <div class="c-empty">
      <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
      Choisissez une conversation.
    </div>`;
}

async function loadMessages(convId, convObj){
  const box   = document.getElementById('msgBox');
  const title = document.getElementById('msgTitle');
  const meta  = document.getElementById('msgMeta');
  const btn   = document.getElementById('btnRefreshMsgs');
  const btnDownload = document.getElementById('btnDownloadCall');

  title.textContent = convObj ? convTitle(convObj) : ('Conv. '+String(convId).slice(0,8));
  meta.textContent  = convObj
    ? `ID ${convId.slice(0,8)}… | ${convObj.channel||'-'} | ${convObj.status||'-'}`
    : 'Chargement…';
  btn.disabled = false;
  // Hide download button by default
  btnDownload.style.display = 'none';
  btnDownload.disabled = true;

  box.innerHTML = '<div class="c-empty"><div class="c-spinner"></div></div>';
  try{
    const msgs = await apiFetch(`/conversations/${encodeURIComponent(convId)}/messages`);
    if(!Array.isArray(msgs)||!msgs.length){
      box.innerHTML = '<div class="c-empty">Cette conversation ne contient pas encore de messages.</div>';
      return;
    }
    const sorted = [...msgs].sort((a,b)=>new Date(a.created_at||0)-new Date(b.created_at||0));
    // Detect first audio attachment (call) to enable download button
    const audioMsg = sorted.find(m => (m.attachment_content_type||'').startsWith('audio/') || (m.attachment_filename||'').match(/\.(mp3|wav|m4a|ogg)$/i));
    if(audioMsg){
      const downloadUrl = apiUrl(`/conversations/${encodeURIComponent(convId)}/messages/${encodeURIComponent(audioMsg.id)}/attachment`);
      btnDownload.style.display = '';
      btnDownload.disabled = false;
      btnDownload.onclick = () => { window.open(downloadUrl, '_blank'); };
    }
    box.innerHTML = sorted.map(m=>{
      const isClientSender = (convObj && m.sender_id == convObj.user_id) || (m.sender_id == selectedClientId);
      const side   = isClientSender ? 'user' : 'agent';
      const label  = isClientSender ? 'Client' : 'Agent / IA';
      const when   = m.created_at ? new Date(m.created_at).toLocaleString('fr-FR') : '-';
      return `
      <div class="c-msg ${side}">
        <div class="c-msg-role">${esc(label)}</div>
        <div class="c-msg-text">${esc(m.content||m.message||'')}</div>
        <div class="c-msg-time">${esc(when)}</div>
      </div>`;
    }).join('');
    box.scrollTop = box.scrollHeight;
  }catch(e){
    box.innerHTML = `<div class="c-empty">⚠️ ${esc(e.message||'Impossible de charger les messages')}</div>`;
  }
}

document.getElementById('btnRefreshMsgs').addEventListener('click',()=>{
  if(selectedConvId){
    const conv = clientConvs.find(c=>c.id===selectedConvId)||null;
    loadMessages(selectedConvId,conv);
  }
});

/* ══════════════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', loadClients);
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/admin/chat.blade.php ENDPATH**/ ?>