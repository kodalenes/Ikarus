// ============================================================================
// 3. TURNUVA (TOURNAMENTS.PHP) İŞLEMLERİ
// ============================================================================

const statusConfig = {
    draft:        { label: 'Draft',        badge: 'op-badge--draft' },
    upcoming:     { label: 'Upcoming',     badge: 'op-badge--soon'  },
    registration: { label: 'Registration', badge: 'op-badge--open'  },
    live:         { label: 'Live',         badge: 'op-badge--live'  },
    finished:     { label: 'Finished',     badge: 'op-badge--done'  },
};

function openStatusPanel(id, currentStatus) {
    document.getElementById('sp-tournament-id').value = id;
    const opts = document.getElementById('spOptions');
    opts.innerHTML = '';
    
    Object.entries(statusConfig).forEach(([val, cfg]) => {
        const btn = document.createElement('button');
        btn.className   = 'adm-sp-option ' + (val === currentStatus ? 'adm-sp-option--active' : '');
        btn.textContent = cfg.label;
        btn.type        = 'button';
        if (val !== currentStatus) {
            btn.onclick = () => applyStatusChange(id, val);
        }
        opts.appendChild(btn);
    });
    
    document.getElementById('statusPanel').style.display = 'block';
    document.getElementById('statusPanelOverlay').style.display = 'block';
}

function closeStatusPanel() {
    document.getElementById('statusPanel').style.display = 'none';
    document.getElementById('statusPanelOverlay').style.display = 'none';
}

function applyStatusChange(id, newStatus) {
    const params = new URLSearchParams({
        action: 'change_status',
        id: id,
        status: newStatus,
        csrf_token: getCsrfToken()
    });

    fetch('tournaments.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            closeStatusPanel();
            showToast(data.message, 'success');

            const badge = document.querySelector(`.adm-status-badge[data-id="${id}"]`);
            if (badge && statusConfig[newStatus]) {
                badge.className   = 'op-badge ' + statusConfig[newStatus].badge + ' adm-status-badge';
                badge.textContent = statusConfig[newStatus].label;
            }

            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row && newStatus === 'live') {
                const delBtn = row.querySelector('.adm-btn-danger');
                if (delBtn) {
                    const span = document.createElement('span');
                    span.className   = 'op-td-muted';
                    span.style.fontSize = '10px';
                    span.textContent = 'Live';
                    delBtn.parentNode.replaceChild(span, delBtn);
                }
            }
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(() => showToast('Connection error.', 'error'));
}

function adminDeleteTournament(id, name) {
    adminConfirm(
        `Delete tournament <strong>"${name}"</strong>?<br><small style="color:var(--text-muted)">Related matches and team registrations will also be removed.</small>`,
        () => {
            const params = new URLSearchParams({
                action: 'delete',
                id: id,
                csrf_token: getCsrfToken() 
            });

            fetch('tournaments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) {
                        row.style.transition = 'opacity 0.3s, transform 0.3s';
                        row.style.opacity    = '0';
                        row.style.transform  = 'translateX(12px)';
                        setTimeout(() => row.remove(), 320);
                    } else {
                        window.location.reload();
                    }
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message || 'Error occurred.', 'error');
                }
            })
            .catch(() => showToast('Connection error.', 'error'));
        }
    );
}