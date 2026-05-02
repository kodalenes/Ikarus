/* ═══════════════════════════════════════════════════════════════════
   ORGANIZER — PLAYERS PAGE SPECIFIC
   ═══════════════════════════════════════════════════════════════════ */
const openPlayerRows = new Set(); 

function toggleDetail(playerId) {
    const detailRow = document.getElementById('detail-' + playerId);
    const arrow     = document.getElementById('arr-'    + playerId);
    if (!detailRow) return;

    if (openPlayerRows.has(playerId)) {
        detailRow.style.display = 'none';
        arrow.textContent       = '▸';
        openPlayerRows.delete(playerId);
    } else {
        detailRow.style.display = 'table-row';
        arrow.textContent       = '▾';
        openPlayerRows.add(playerId);
    }
}

function submitWarning(playerId, tournamentId) {
    const typeEl  = document.getElementById('warn-type-'  + playerId);
    const noteEl  = document.getElementById('warn-note-'  + playerId);
    const btn     = document.getElementById('warn-btn-'   + playerId);
    const feedEl  = document.getElementById('warn-feed-'  + playerId);

    const warnType = typeEl ? typeEl.value  : '';
    const note     = noteEl ? noteEl.value.trim() : '';

    if (!tournamentId || tournamentId === '0') {
        showFeedback(feedEl, 'Please select a tournament.', 'error');
        return;
    }

    if (!warnType) {
        showFeedback(feedEl, 'Please select a warning type.', 'error');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Saving...';

    const params = new URLSearchParams({
        action       : 'add_warning',
        player_id    : playerId,
        tournament_id: tournamentId,
        warn_type    : warnType,
        note         : note,
    });

    fetch('players.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body   : params.toString(),
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            showFeedback(feedEl, '✓ Warning saved.', 'success');

            const histEl = document.getElementById('warn-history-' + playerId);
            if (histEl) {
                const noWarn = histEl.querySelector('.plr-no-warn');
                if (noWarn) noWarn.remove();

                const item = document.createElement('div');
                item.className = 'plr-warn-item';
                item.innerHTML = `
                    <div class="plr-warn-item-type plr-warn-item-type--${warnType}">
                        ${warnType === 'noshow' ? '🚫 No-Show' : '⚠️ Warning'}
                    </div>
                    ${note ? `<div class="plr-warn-item-note">${escHtml(note)}</div>` : ''}
                    <div class="plr-warn-item-date">Just now</div>
                `;
                histEl.prepend(item);
            }

            updateRowBadge(playerId, warnType);
            if (typeEl) typeEl.value = '';
            if (noteEl) noteEl.value = '';
            showPlayerToast('Warning recorded.', 'success');

        } else {
            showFeedback(feedEl, data.message || 'Error occurred.', 'error');
        }
    })
    .catch(() => showFeedback(feedEl, 'Connection error.', 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Save Warning';
    });
}

function updateRowBadge(playerId, warnType) {
    const badgeContainer = document.getElementById('flag-badge-' + playerId);
    if (!badgeContainer) return;

    const isNoshow = warnType === 'noshow';
    badgeContainer.innerHTML = `
        <span class="plr-flag-badge plr-flag-badge--${isNoshow ? 'noshow' : 'warn'}">
            ${isNoshow ? '🚫 No-Show' : '⚠️ Warned'}
        </span>
    `;

    const mainRow = document.querySelector(`tr.plr-main-row[data-player="${playerId}"]`);
    if (mainRow) {
        mainRow.classList.remove('plr-main-row--flagged', 'plr-main-row--warned');
        mainRow.classList.add(isNoshow ? 'plr-main-row--flagged' : 'plr-main-row--warned');
    }
}

function removeWarning(warningId, playerId) {
    if (!confirm('Remove this warning?')) return;

    const params = new URLSearchParams({
        action    : 'remove_warning',
        warning_id: warningId,
        player_id : playerId,
    });

    fetch('players.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body   : params.toString(),
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            const el = document.getElementById('warn-item-' + warningId);
            if (el) {
                el.style.transition = 'opacity 0.3s';
                el.style.opacity    = '0';
                setTimeout(() => el.remove(), 300);
            }
            showPlayerToast('Warning removed.', 'success');
        } else {
            showPlayerToast(data.message || 'Error.', 'error');
        }
    })
    .catch(() => showPlayerToast('Connection error.', 'error'));
}

function showFeedback(el, message, type) {
    if (!el) return;
    el.textContent  = message;
    el.style.color  = type === 'success' ? 'var(--accent-soft)' : '#f87171';
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; el.textContent = ''; }, 3500);
}

function showPlayerToast(message, type = 'success') {
    const t = document.createElement('div');
    t.className   = `plr-toast plr-toast--${type}`;
    t.textContent = message;
    document.body.appendChild(t);
    setTimeout(() => {
        t.style.transition = 'opacity 0.4s';
        t.style.opacity    = '0';
        setTimeout(() => t.remove(), 400);
    }, 3000);
}

function escHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function wrClass(rate) {
    if (rate >= 60) return 'plr-wr-fill--high';
    if (rate >= 40) return 'plr-wr-fill--mid';
    return 'plr-wr-fill--low';
}

document.addEventListener('DOMContentLoaded', () => {
    // Win Rate Barları
    document.querySelectorAll('.plr-wr-fill[data-rate]').forEach(bar => {
        const rate = parseInt(bar.dataset.rate, 10);
        bar.classList.add(wrClass(rate));
        setTimeout(() => { bar.style.width = rate + '%'; }, 80);
    });

    // Enter ile arama
    const searchInput = document.getElementById('plr-search');
    if (searchInput) {
        searchInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') e.target.closest('form').submit();
        });
    }
});

// HTML tarafında onClick tetikleyicilerini eşleme
window.toggleDetail = toggleDetail;
window.submitWarning = submitWarning;
window.removeWarning = removeWarning;