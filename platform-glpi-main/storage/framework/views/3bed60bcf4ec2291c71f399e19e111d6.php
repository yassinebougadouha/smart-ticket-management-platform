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

/* ─── Mode Toggle Buttons (Client / Admin) ──────────────────── */
.c-mode-tabs {
  display: flex;
  gap: 6px;
  padding: 2px 0 4px;
  flex-shrink: 0;
}
.c-mode-tab {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 7px 10px;
  border-radius: 9px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  border: 1.5px solid var(--border-color, #e2e8f0);
  background: var(--bg-body, #f8fafc);
  color: var(--text-muted, #64748b);
  transition: all .18s ease;
  letter-spacing: .2px;
  user-select: none;
}
.c-mode-tab:hover {
  background: var(--border-color, #e2e8f0);
  color: var(--text-heading, #1e293b);
  transform: translateY(-1px);
  box-shadow: 0 2px 6px rgba(0,0,0,.06);
}
.c-mode-tab.active-client {
  background: linear-gradient(135deg, #0ea5e9, #0284c7);
  color: #fff;
  border-color: #0ea5e9;
  box-shadow: 0 3px 10px rgba(14,165,233,.35);
  transform: translateY(-1px);
}
.c-mode-tab.active-admin {
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  color: #fff;
  border-color: #8b5cf6;
  box-shadow: 0 3px 10px rgba(139,92,246,.35);
  transform: translateY(-1px);
}
.c-mode-tab .c-mode-icon {
  font-size: 13px;
}
.c-mode-cnt {
  background: rgba(255,255,255,.22);
  border-radius: 99px;
  padding: 1px 6px;
  font-size: 9px;
  font-weight: 800;
}
.c-mode-tab:not(.active-client):not(.active-admin) .c-mode-cnt {
  background: rgba(0,0,0,.09);
  color: var(--text-muted,#64748b);
}

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
.c-client-item.active-admin-row{
  background:color-mix(in srgb,#8b5cf6 8%,transparent);
  border-left:3px solid #8b5cf6;
}
.c-client-avatar{
  width:36px;height:36px;border-radius:50%;
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

/* ─── WhatsApp mode conversation items ──────────────────────── */
.c-conv-item.wa-style {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
}
.c-conv-item.wa-style.active {
  background:color-mix(in srgb,#8b5cf6 8%,transparent);
  border-left:3px solid #8b5cf6;
}
.wa-conv-av-sm {
  width:34px;height:34px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:12px;font-weight:700;color:#fff;flex-shrink:0;
}
.wa-conv-info-sm { flex:1;min-width:0; }
.wa-conv-name-sm { font-size:12.5px;font-weight:700;color:var(--text-heading,#1e293b);white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.wa-conv-prev-sm { font-size:11px;color:var(--text-muted,#64748b);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px; }

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
/* WhatsApp style messages */
.c-msg.wa-in {
  align-self:flex-start;
  background:#ffffff;
  border-color:#e2e8f0;
  color:#1e293b;
  border-bottom-left-radius:3px;
}
.c-msg.wa-out {
  align-self:flex-end;
  background:linear-gradient(135deg,#d1fae5,#a7f3d0);
  border-color:#6ee7b7;
  color:#064e3b;
  border-bottom-right-radius:3px;
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

/* ─── Mode indicator badge ──────────────────────────────────── */
.c-mode-badge {
  display:inline-flex;align-items:center;gap:4px;
  border-radius:99px;padding:2px 8px;font-size:10px;font-weight:700;
  margin-left:6px;
}
.c-mode-badge.client { background:#dbeafe;color:#1e40af; }
.c-mode-badge.admin  { background:#ede9fe;color:#4c1d95; }
</style>

<div class="chat-root">

  
  <div class="chat-topbar">
    <div class="chat-topbar-left">
      <div class="chat-page-title">📨 Supervision des Conversations</div>
      <div class="chat-page-sub">Vue super-admin — conversations clients (chat et WhatsApp) &amp; admins (WhatsApp)</div>
    </div>
    <span class="chat-badge-readonly">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      Lecture seule
    </span>
  </div>

  
  <div class="chat-grid">

    
    <?php
      $allClients = isset($clients) ? $clients : \App\Models\User::where('role','client')->orderBy('name')->get();
      $allAdmins  = \App\Models\User::where('role','admin')->orderBy('name')->get();
    ?>
    <div class="c-panel" id="panelClients">
      <div class="c-panel-head">

        
        <div class="c-mode-tabs">
          <button class="c-mode-tab active-client" id="btnModeClient" onclick="switchMode('client')">
            <span class="c-mode-icon">👤</span>
            Clients
            <span class="c-mode-cnt" id="cntClients"><?php echo e($allClients->count()); ?></span>
          </button>
          <button class="c-mode-tab" id="btnModeAdmin" onclick="switchMode('admin')">
            <span class="c-mode-icon">🛡️</span>
            Admins
            <span class="c-mode-cnt" id="cntAdmins"><?php echo e($allAdmins->count()); ?></span>
          </button>
        </div>

        <div class="c-panel-label" id="panelTitleLabel">👤 Clients</div>
        <input id="clientSearch" class="c-search" placeholder="Rechercher un client…">
      </div>
      <div class="c-panel-body" id="clientList">
        
        <div id="clientItems">
          <?php $__currentLoopData = $allClients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="c-client-item" data-uid="<?php echo e($client->id); ?>" data-mode="client"
               data-name="<?php echo e($client->name); ?>"
               data-search="<?php echo e(strtolower($client->name.' '.$client->email)); ?>"
               onclick="selectUser(this,'client')">
            <div class="c-client-avatar" style="background: linear-gradient(135deg,#0ea5e9,#0284c7);">
              <?php if($client->avatar): ?><img src="<?php echo e(asset('storage/'.$client->avatar)); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
              <?php else: ?><?php echo e(strtoupper(substr($client->name,0,2))); ?><?php endif; ?>
            </div>
            <div class="c-client-info">
              <div class="c-client-name"><?php echo e($client->name); ?></div>
              <div class="c-client-email"><?php echo e($client->email); ?></div>
            </div>
          </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        
        <div id="adminItems" style="display:none;">
          <?php $__currentLoopData = $allAdmins; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $admin): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="c-client-item" data-uid="<?php echo e($admin->id); ?>" data-mode="admin"
               data-name="<?php echo e($admin->name); ?>"
               data-search="<?php echo e(strtolower($admin->name.' '.$admin->email)); ?>"
               onclick="selectUser(this,'admin')">
            <div class="c-client-avatar" style="background: linear-gradient(135deg,#8b5cf6,#6d28d9);">
              <?php if($admin->avatar): ?><img src="<?php echo e(asset('storage/'.$admin->avatar)); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
              <?php else: ?><?php echo e(strtoupper(substr($admin->name,0,2))); ?><?php endif; ?>
            </div>
            <div class="c-client-info">
              <div class="c-client-name"><?php echo e($admin->name); ?></div>
              <div class="c-client-email"><?php echo e($admin->email); ?></div>
            </div>
          </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
    </div>

    
    <div class="c-panel" id="panelConvs">
      <div class="c-panel-head">
        <div class="c-panel-label" id="convListLabel">
          💬 Conversations
          <span class="c-mode-badge client" id="convModeBadge" style="display:none;"></span>
        </div>
        <input id="convSearch" class="c-search" placeholder="Rechercher une conversation…">
      </div>
      <div class="c-panel-body" id="convList">
        <div class="c-empty">
          <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          Sélectionnez un client ou un admin
        </div>
      </div>
    </div>

    
    <div class="c-panel" id="panelMsgs">
      <div class="c-msg-head">
        <div class="c-msg-head-info">
          <div class="c-msg-head-title" id="msgTitle">Sélectionnez une conversation</div>
          <div class="c-msg-head-meta" id="msgMeta">Aucun fil chargé.</div>
        </div>
        <button class="c-msg-refresh" id="btnRefreshMsgs" disabled>↻ Actualiser</button>
      </div>
      <div class="c-messages" id="msgBox">
        <div class="c-empty">
          <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          Aucun message à afficher.
        </div>
      </div>
      <div class="c-readonly-bar">
        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        Mode supervision — lecture seule
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

let currentMode      = 'client'; // 'client' | 'admin'
let selectedUserId   = null;
let selectedUserName = '';
let clientConvs      = [];      // conversations of selected client/admin
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

function channelClass(ch){ return 'ch-'+(ch||'OTHER').toUpperCase(); }

function statusClass(st){
  const s = String(st||'').toLowerCase().replace(/\s+/g,'_');
  return 'st-'+s;
}

function parseDate(s){
  if(!s) return null;
  if(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(s) && !/[Z+]/.test(s)) s += 'Z';
  const d = new Date(s);
  return isNaN(d.getTime()) ? null : d;
}

function relativeTime(dateStr){
  if(!dateStr) return '';
  const d = parseDate(dateStr);
  if(!d) return '';
  const diff = (Date.now() - d.getTime()) / 1000;
  if(diff < 0)   return "à l'instant";
  if(diff < 60)  return "à l'instant";
  if(diff < 3600) return Math.floor(diff/60) + ' min';
  if(diff < 86400) return 'aujourd\'hui ' + d.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});
  if(diff < 172800) return 'hier ' + d.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});
  return d.toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit',year:'2-digit'});
}

function convTitle(c){ return c.subject||c.title||('Conv. '+String(c.id).slice(0,8)); }

function avatarColor(mode){ return mode==='admin' ? '#8b5cf6' : '#0ea5e9'; }

/* ══════════════════════════════════════════════════════════════
   MODE SWITCHING (Client ↔ Admin)
   ══════════════════════════════════════════════════════════════ */
function switchMode(mode) {
  currentMode    = mode;
  selectedUserId = null;
  selectedConvId = null;
  clientConvs    = [];
  clientSearchQ  = '';

  const btnClient = document.getElementById('btnModeClient');
  const btnAdmin  = document.getElementById('btnModeAdmin');
  const label     = document.getElementById('panelTitleLabel');
  const searchBox = document.getElementById('clientSearch');

  // Update button active states
  btnClient.className = 'c-mode-tab' + (mode==='client' ? ' active-client' : '');
  btnAdmin.className  = 'c-mode-tab' + (mode==='admin'  ? ' active-admin'  : '');

  // Toggle list sections
  document.getElementById('clientItems').style.display = (mode==='client') ? 'block' : 'none';
  document.getElementById('adminItems').style.display  = (mode==='admin')  ? 'block' : 'none';

  // Update labels
  if(mode === 'client') {
    label.innerHTML   = '👤 Clients';
    searchBox.placeholder = 'Rechercher un client…';
  } else {
    label.innerHTML   = '🛡️ Admins';
    searchBox.placeholder = 'Rechercher un admin…';
  }

  // Reset search
  searchBox.value = '';
  filterUsers();

  // Reset panels
  resetConvsPanel();
  resetMessagesPanel();
}

/* ══════════════════════════════════════════════════════════════
   PANEL 1 — USER FILTERING
   ══════════════════════════════════════════════════════════════ */
document.getElementById('clientSearch').addEventListener('input', e=>{
  clientSearchQ = (e.target.value || '').toLowerCase().trim();
  filterUsers();
});

function filterUsers() {
  const container = currentMode === 'client'
    ? document.getElementById('clientItems')
    : document.getElementById('adminItems');

  container.querySelectorAll('.c-client-item').forEach(el => {
    const isSearchMatch = !clientSearchQ || el.getAttribute('data-search').includes(clientSearchQ);
    el.style.display = isSearchMatch ? 'flex' : 'none';
  });
}

function resetConvsPanel() {
  const badge = document.getElementById('convModeBadge');
  badge.style.display = 'none';
  document.getElementById('convList').innerHTML = `
    <div class="c-empty">
      <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
      Sélectionnez un ${currentMode === 'admin' ? 'admin' : 'client'}
    </div>`;
}

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

async function selectUser(el, mode) {
  // Deactivate all items
  document.querySelectorAll('.c-client-item').forEach(item => {
    item.classList.remove('active','active-admin-row');
  });
  el.classList.add(mode === 'admin' ? 'active-admin-row' : 'active');

  selectedUserId   = el.getAttribute('data-uid');
  selectedUserName = el.getAttribute('data-name');
  selectedConvId   = null;
  resetMessagesPanel();

  // Update conv panel badge
  const badge = document.getElementById('convModeBadge');
  badge.className  = `c-mode-badge ${mode}`;
  badge.textContent = mode === 'admin' ? '🛡️ WhatsApp' : '💬 Chat & WA';
  badge.style.display = 'inline-flex';

  await loadUserConversations(selectedUserId, mode);
}

/* ══════════════════════════════════════════════════════════════
   PANEL 2 — CONVERSATIONS
   ══════════════════════════════════════════════════════════════ */
async function loadUserConversations(userId, mode) {
  const box = document.getElementById('convList');
  box.innerHTML = '<div class="c-empty"><div class="c-spinner"></div></div>';

  try {
    let res;
    let convs = [];

    res = await apiFetch(`/conversations?user_id=${encodeURIComponent(userId)}&limit=200`);
    convs = Array.isArray(res?.conversations) ? res.conversations : (Array.isArray(res) ? res : []);
    if (!convs.length) {
      res = await apiFetch(`/conversations?client_id=${encodeURIComponent(userId)}&limit=200`);
      convs = Array.isArray(res?.conversations) ? res.conversations : (Array.isArray(res) ? res : []);
    }

    if (mode === 'admin') {
      // Admin mode: only WhatsApp conversations
      clientConvs = convs.filter(c => {
        const uid = c.user_id || c.client_id || c.userId || c.clientId || null;
        const belongsToUser = !uid || String(uid) === String(userId);
        const channel = String(c.channel || '').toUpperCase();
        return belongsToUser && channel === 'WHATSAPP';
      });
    } else {
      // Client mode: chat AND WhatsApp
      clientConvs = convs.filter(c => {
        const uid = c.user_id || c.client_id || c.userId || c.clientId || null;
        const belongsToUser = !uid || String(uid) === String(userId);
        const channel = String(c.channel || '').toUpperCase();
        return belongsToUser && ['CHAT','WHATSAPP'].includes(channel);
      });
    }

    renderConvs(mode);
  } catch(e) {
    box.innerHTML = `<div class="c-empty">⚠️ ${esc(e.message || 'Erreur chargement conversations')}</div>`;
  }
}

function filteredConvs(){
  const q = convSearchQ.trim().toLowerCase();
  const sorted = [...clientConvs].sort((a,b)=>new Date(b.updated_at||b.created_at||0)-new Date(a.updated_at||a.created_at||0));
  if(!q) return sorted;
  return sorted.filter(c=>`${convTitle(c)} ${c.status||''} ${c.channel||''}`.toLowerCase().includes(q));
}

function renderConvs(mode){
  mode = mode || currentMode;
  const box = document.getElementById('convList');
  const list = filteredConvs();

  if(!list.length){
    const emptyMsg = mode === 'admin'
      ? 'Aucune conversation WhatsApp pour cet admin.'
      : 'Aucune conversation pour ce client.';
    box.innerHTML = `<div class="c-empty">${emptyMsg}</div>`;
    return;
  }

  if(mode === 'admin') {
    // WhatsApp-style compact list for admins
    const color = '#8b5cf6';
    box.innerHTML = list.map(c => {
      const active = c.id===selectedConvId ? ' active' : '';
      const title  = convTitle(c);
      const initials = title.split(/\s+/).slice(0,2).map(w=>w[0]||'').join('').toUpperCase() || 'WA';
      const preview = c.last_message || c.preview || '';
      return `
      <div class="c-conv-item wa-style${active}" data-cid="${esc(c.id)}" onclick="convClick(event)">
        <div class="wa-conv-av-sm" style="background:${color};">${esc(initials)}</div>
        <div class="wa-conv-info-sm">
          <div class="wa-conv-name-sm" title="${esc(title)}">📱 ${esc(title)}</div>
          <div class="wa-conv-prev-sm">${esc(preview || relativeTime(c.updated_at||c.created_at))}</div>
        </div>
      </div>`;
    }).join('');
  } else {
    // Standard list for clients
    box.innerHTML = list.map(c=>{
      const active = c.id===selectedConvId?' active':'';
      const chCls  = channelClass(c.channel);
      const stCls  = statusClass(c.status);
      return `
      <div class="c-conv-item${active}" data-cid="${esc(c.id)}" onclick="convClick(event)">
        <div class="c-conv-title" title="${esc(convTitle(c))}">${esc(convTitle(c))}</div>
        <div class="c-conv-row">
          <span class="c-conv-channel ${chCls}">${esc(c.channel||'?')}</span>
          <span class="c-conv-status ${stCls}">${esc(c.status||'-')}</span>
          <span class="c-conv-date">${esc(relativeTime(c.updated_at||c.created_at))}</span>
        </div>
      </div>`;
    }).join('');
  }
}

document.getElementById('convSearch').addEventListener('input', e=>{
  convSearchQ = e.target.value || '';
  renderConvs();
});

function convClick(e) {
  const row = e.target.closest('[data-cid]');
  if(!row) return;
  selectedConvId = row.getAttribute('data-cid');
  renderConvs();
  const conv = clientConvs.find(c=>c.id===selectedConvId)||null;
  loadMessages(selectedConvId, conv);
}

/* ══════════════════════════════════════════════════════════════
   PANEL 3 — MESSAGES (read-only)
   ══════════════════════════════════════════════════════════════ */
async function loadMessages(convId, convObj){
  const box   = document.getElementById('msgBox');
  const title = document.getElementById('msgTitle');
  const meta  = document.getElementById('msgMeta');
  const btn   = document.getElementById('btnRefreshMsgs');

  const isAdminMode = currentMode === 'admin';
  const channelIcon = isAdminMode ? '📱 WhatsApp' : (convObj?.channel||'-');

  title.textContent = convObj ? convTitle(convObj) : ('Conv. '+String(convId).slice(0,8));
  meta.textContent  = convObj
    ? `ID ${convId.slice(0,8)}… | ${channelIcon} | ${convObj.status||'-'} | ${selectedUserName||''}`
    : 'Chargement…';
  btn.disabled = false;

  box.innerHTML = '<div class="c-empty"><div class="c-spinner"></div></div>';
  try{
    const msgs = await apiFetch(`/conversations/${encodeURIComponent(convId)}/messages`);
    if(!Array.isArray(msgs)||!msgs.length){
      box.innerHTML = '<div class="c-empty">Cette conversation ne contient pas encore de messages.</div>';
      return;
    }
    const sorted = [...msgs].sort((a,b)=>new Date(a.created_at||0)-new Date(b.created_at||0));

    box.innerHTML = sorted.map(m=>{
      const isClientSender = convObj && (m.sender_id == convObj.user_id || m.sender_id == selectedUserId);
      let side, label;

      if(isAdminMode) {
        // Admin WA mode: out = admin sent, in = client sent
        side  = isClientSender ? 'wa-in' : 'wa-out';
        label = isClientSender ? 'Client' : 'Admin (WA)';
      } else {
        side  = isClientSender ? 'user' : 'agent';
        label = isClientSender ? 'Client' : 'Agent / IA';
      }

      const when = m.created_at ? new Date(m.created_at).toLocaleString('fr-FR') : '-';
      return `
      <div class="c-msg ${side}">
        <div class="c-msg-role">${esc(m.is_internal ? 'Note Interne' : label)}</div>
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
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/super-admin/conversations.blade.php ENDPATH**/ ?>