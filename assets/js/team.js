/**
 * team.js — Ikarus Team Page
 * All operations via fetch API. No page reloads for mutations.
 * Part 1: core CRUD + animations + region selector + upload preview.
 */

'use strict';

const TeamApp = (() => {

  /* ── Config ──────────────────────────────────────────────────────── */
  const API = 'team-api.php';
  const D   = window.TEAM_INIT || {};

  const REGIONS = [
    { group: 'Europe',
      items: ['Turkey — Türkiye','Western Europe','Eastern Europe',
              'Northern Europe','Southern Europe','CIS / Russia'] },
    { group: 'Americas',
      items: ['North America','Latin America','South America','Caribbean'] },
    { group: 'Asia Pacific',
      items: ['East Asia','Southeast Asia','South Asia','Oceania'] },
    { group: 'Middle East & Africa',
      items: ['Middle East','North Africa','Sub-Saharan Africa'] },
    { group: 'Other',
      items: ['Worldwide / Global'] },
  ];

  /* ── API layer ───────────────────────────────────────────────────── */
  async function apiFetch(action, fields = {}, file = null) {
    const fd = new FormData();
    fd.append('action', action);
    for (const [k, v] of Object.entries(fields)) fd.append(k, v);
    if (file) fd.append('avatar', file);

    try {
      const res  = await fetch(API, { method: 'POST', body: fd });
      const json = await res.json();
      return json;
    } catch (e) {
      return { ok: false, message: 'Network error. Please try again.' };
    }
  }

  /* ── Toast ───────────────────────────────────────────────────────── */
  let _toastTimer;
  function toast(msg, type = 'success') {
    const el = document.getElementById('tm-toast');
    if (!el) return;
    clearTimeout(_toastTimer);
    el.className = `tm-toast tm-toast--${type} tm-toast--show`;
    el.textContent = msg;
    _toastTimer = setTimeout(() => el.classList.remove('tm-toast--show'), 3400);
  }

  /* ── Panels (smooth CSS grid animation) ─────────────────────────── */
  function openPanel(id) {
    // Close siblings first for a clean UX
    document.querySelectorAll('.tm-collapsible.is-open').forEach(p => {
      if (p.id !== id) p.classList.remove('is-open');
    });
    document.getElementById(id)?.classList.add('is-open');
  }

  function closePanel(id) {
    document.getElementById(id)?.classList.remove('is-open');
  }

  function togglePanel(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (el.classList.contains('is-open')) {
      el.classList.remove('is-open');
    } else {
      openPanel(id);
    }
  }

  /* ── Custom Confirm ──────────────────────────────────────────────── */
  function confirm(title, message, okText = 'Confirm') {
    return new Promise(resolve => {
      const overlay = document.getElementById('tm-confirm-overlay');
      if (!overlay) { resolve(window.confirm(message)); return; }

      document.getElementById('tm-confirm-title').textContent   = title;
      document.getElementById('tm-confirm-message').textContent = message;
      const okBtn = document.getElementById('tm-confirm-ok');
      okBtn.textContent = okText;

      overlay.style.display = 'flex';
      requestAnimationFrame(() => overlay.classList.add('is-open'));

      function close(val) {
        overlay.classList.remove('is-open');
        setTimeout(() => { overlay.style.display = 'none'; }, 280);
        cleanup();
        resolve(val);
      }

      function cleanup() {
        okBtn.onclick          = null;
        document.getElementById('tm-confirm-cancel').onclick = null;
        overlay.onclick        = null;
      }

      okBtn.onclick = () => close(true);
      document.getElementById('tm-confirm-cancel').onclick = () => close(false);
      overlay.addEventListener('click', e => { if (e.target === overlay) close(false); }, { once: true });
    });
  }

  /* ── Region Selector ─────────────────────────────────────────────── */
  function initRegionSelector(wrapperId, hiddenId) {
    const wrap    = document.getElementById(wrapperId);
    if (!wrap) return;

    const btn     = wrap.querySelector('.tm-region-btn');
    const display = wrap.querySelector('.tm-region-display');
    const dropdown = wrap.querySelector('.tm-region-dropdown');
    const searchIn = wrap.querySelector('.tm-region-search');
    const list    = wrap.querySelector('.tm-region-list');
    const hidden  = document.getElementById(hiddenId);
    let current   = hidden?.value || '';

    function renderList(q = '') {
      const lq = q.toLowerCase();
      list.innerHTML = REGIONS.map(group => {
        const items = group.items.filter(i => !lq || i.toLowerCase().includes(lq));
        if (!items.length) return '';
        return `
          <div class="tm-region-group">
            <div class="tm-region-group-label">${group.group}</div>
            ${items.map(item => `
              <button type="button"
                      class="tm-region-option${item === current ? ' is-selected' : ''}"
                      data-val="${item}">
                ${item}
              </button>`).join('')}
          </div>`;
      }).join('');

      // Attach click handlers
      list.querySelectorAll('.tm-region-option').forEach(opt => {
        opt.addEventListener('click', () => selectVal(opt.dataset.val));
      });
    }

    function selectVal(val) {
      current = val;
      display.textContent = val;
      if (hidden) hidden.value = val;
      dropdown.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
      // Update button selected state visually
      wrap.querySelectorAll('.tm-region-option').forEach(o => {
        o.classList.toggle('is-selected', o.dataset.val === val);
      });
    }

    btn.addEventListener('click', e => {
      e.stopPropagation();
      const opening = !dropdown.classList.contains('is-open');
      // Close all other open region dropdowns
      document.querySelectorAll('.tm-region-dropdown.is-open').forEach(d => d.classList.remove('is-open'));
      if (opening) {
        dropdown.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        searchIn.value = '';
        renderList();
        setTimeout(() => searchIn.focus(), 30);
      }
    });

    searchIn.addEventListener('input', () => renderList(searchIn.value));

    document.addEventListener('click', e => {
      if (!wrap.contains(e.target)) {
        dropdown.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });

    renderList();
  }

  /* ── Avatar Preview ──────────────────────────────────────────────── */
  function previewAvatar(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview || !input.files?.[0]) return;
    const file = input.files[0];
    if (!file.type.startsWith('image/')) { toast('Please select a valid image.', 'error'); input.value = ''; return; }
    if (file.size > 1_048_576) { toast('Image must be under 1 MB.', 'error'); input.value = ''; return; }
    const reader = new FileReader();
    reader.onload = e => {
      preview.innerHTML = `<img src="${e.target.result}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`;
    };
    reader.readAsDataURL(file);
  }

  /* ── Form: Create Team ───────────────────────────────────────────── */
  async function _submitCreate(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const btn  = form.querySelector('[type=submit]');
    btn.disabled = true;
    btn.textContent = 'Creating...';

    const fd = new FormData(form);
    fd.append('action', 'create_team');
    fd.set('region', document.getElementById('create-region-hidden')?.value || '');
    const fileIn = form.querySelector('input[type=file]');
    if (fileIn?.files[0]) fd.set('avatar', fileIn.files[0]);

    const data = await fetch(API, { method: 'POST', body: fd }).then(r => r.json()).catch(() => ({ ok: false, message: 'Network error.' }));

    if (data.ok) {
      toast('Team created!', 'success');
      setTimeout(() => location.reload(), 700);
    } else {
      toast(data.message, 'error');
      btn.disabled = false;
      btn.textContent = 'Create Team';
    }
  }

  /* ── Form: Update Team ───────────────────────────────────────────── */
  async function _submitUpdate(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const btn  = form.querySelector('[type=submit]');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const fd = new FormData(form);
    fd.append('action', 'update_team');
    fd.set('region', document.getElementById('edit-region-hidden')?.value || '');
    const fileIn = form.querySelector('input[type=file]');
    if (fileIn?.files[0]) fd.set('avatar', fileIn.files[0]);

    const data = await fetch(API, { method: 'POST', body: fd }).then(r => r.json()).catch(() => ({ ok: false, message: 'Network error.' }));

    if (data.ok) {
      toast('Team updated!', 'success');
      closePanel('editPanel');
      setTimeout(() => location.reload(), 500);
    } else {
      toast(data.message, 'error');
      btn.disabled = false;
      btn.textContent = 'Save Changes';
    }
  }

  /* ── Remove Avatar ───────────────────────────────────────────────── */
  async function removeAvatar() {
    const ok = await confirm('Remove Logo', 'Remove the current team logo?', 'Remove');
    if (!ok) return;
    const data = await apiFetch('remove_avatar');
    if (data.ok) { toast('Logo removed.', 'success'); setTimeout(() => location.reload(), 500); }
    else toast(data.message, 'error');
  }

  /* ── Kick Member ─────────────────────────────────────────────────── */
  async function kick(memberId, username) {
    const ok = await confirm('Remove Member', `Remove ${username} from the team?`, 'Remove');
    if (!ok) return;
    const data = await apiFetch('kick_member', { kick_id: memberId });
    if (data.ok) {
      document.querySelector(`.tm-member-row[data-member-id="${memberId}"]`)?.remove();
      _adjustMemberCount(-1);
      toast(data.message, 'success');
    } else {
      toast(data.message, 'error');
    }
  }

  function _adjustMemberCount(delta) {
    const el = document.getElementById('tm-member-count');
    if (!el) return;
    const m = el.textContent.match(/(\d+)/);
    if (m) el.textContent = `${Math.max(0, +m[1] + delta)}/6`;
    const el2 = document.getElementById('tm-member-count-stat');
    if (el2 && m) el2.textContent = Math.max(0, +m[1] + delta);
  }

  /* ── Leave Team ──────────────────────────────────────────────────── */
  async function leaveTeam() {
    const ok = await confirm(
      'Leave Team',
      'Are you sure? If you are the last member the team will be dissolved.',
      'Leave'
    );
    if (!ok) return;
    const data = await apiFetch('leave_team');
    if (data.ok) { toast(data.message, 'success'); setTimeout(() => location.reload(), 900); }
    else toast(data.message, 'error');
  }

  /* ── Copy Code ───────────────────────────────────────────────────── */
  async function copyCode() {
    const code = document.getElementById('tm-invite-code')?.textContent?.trim();
    if (!code) return;
    try {
      await navigator.clipboard.writeText(code);
      toast(`Copied: ${code}`);
    } catch {
      toast(`Code: ${code}`, 'error');
    }
  }

  /* ── Invite form (Part 2 placeholder) ──────────────────────────── */
  function _bindInviteForm() {
    document.getElementById('invite-form')?.addEventListener('submit', async e => {
      e.preventDefault();
      const inp = document.getElementById('invite-username-input');
      const fb  = document.getElementById('invite-feedback');
      const username = inp?.value.trim();
      if (!username) return;
      // Part 2 will replace this with full invitation system
      fb.textContent = 'Invite system coming in Part 2…';
      fb.style.color = 'var(--text-muted)';
    });
  }

  /* ── Init ────────────────────────────────────────────────────────── */
  function _init() {
    // Forms
    document.getElementById('create-team-form')?.addEventListener('submit', _submitCreate);
    document.getElementById('edit-team-form')?.addEventListener('submit',   _submitUpdate);

    // Region selectors
    initRegionSelector('create-region-sel', 'create-region-hidden');
    initRegionSelector('edit-region-sel',   'edit-region-hidden');

    // TAG auto-uppercase
    document.querySelectorAll('input[name="tag"]').forEach(inp => {
      inp.addEventListener('input', () => { inp.value = inp.value.toUpperCase(); });
    });

    // Invite form placeholder
    _bindInviteForm();

    // Escape closes panels and dropdowns
    document.addEventListener('keydown', e => {
      if (e.key !== 'Escape') return;
      document.querySelectorAll('.tm-collapsible.is-open').forEach(p => p.classList.remove('is-open'));
      document.querySelectorAll('.tm-region-dropdown.is-open').forEach(d => d.classList.remove('is-open'));
    });
  }

  document.addEventListener('DOMContentLoaded', _init);

  /* ── Public API ──────────────────────────────────────────────────── */
  return { togglePanel, openPanel, closePanel, kick, leaveTeam, removeAvatar, copyCode, previewAvatar };

})();