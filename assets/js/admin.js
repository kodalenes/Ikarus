// Admin Paneli JS Dosyasi

//Show Toast
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `adm-toast adm-toast--${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position:fixed; bottom:24px; right:24px; z-index:9999;
        padding:12px 18px; border-radius:8px; font-size:13px;
        background:${type === 'success' ? 'rgba(72,159,181,0.15)' : 'rgba(220,38,38,0.15)'};
        border:1px solid ${type === 'success' ? 'rgba(72,159,181,0.3)' : 'rgba(220,38,38,0.3)'};
        color:${type === 'success' ? 'var(--accent-soft)' : '#f87171'};
        animation: slideUp 0.3s ease;
    `;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'opacity 0.4s';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

/** Kullanici tehlikeli bir aksiyon aldiginda ekstra onay icin modal gosterme
 * 
 * @param {*} message 
 * @param {*} onConfirm 
 */
function adminConfirm(message, onConfirm) {
    const overlay = document.createElement('div');
    overlay.className = 'adm-confirm-overlay';
    overlay.innerHTML = `
        <div class="adm-confirm-box">
            <div class="adm-confirm-title">⚠️ Are you sure?</div>
            <div class="adm-confirm-sub">${message}</div>
            <div class="adm-confirm-actions">
                <button class="op-btn op-btn--ghost" id="adm-cancel">Cancel</button>
                <button class="adm-btn-danger" id="adm-ok" style="padding:8px 20px;font-size:13px">Confirm</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    document.getElementById('adm-ok').addEventListener('click' , () => {
        overlay.remove();
        onConfirm();
    });
    document.getElementById('adm-cancel').addEventListener('click' , () => overlay.remove());
    overlay.addEventListener('click' , e => { if(e.target === overlay) overlay.remove()});
}

function changeUserRole(userId, newRole, username) {
    adminConfirm(
        `Change <strong>${username}</strong>'s role to <strong>${newRole}</strong>`,
        () => {
            fetch('users.php', {
                method: 'POST',
                headers: {'Content=Type': 'application/x-www-form-urlencoded'},
                body: `action=change_role&user_id=${userId}&role=${newRole}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'succes') {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload() , 800);
                } else {
                    showToast(data.message || 'Error occured.' , 'error');
                }
            })
            .catch(() => showToast('Connection error.' , 'error'));
        }
    );
}

// ─── Turnuva silme ──────────────────────────────────────────────────────────
function adminDeleteTournament(id, name) {
    adminConfirm(
        `Delete tournament <strong>"${name}"</strong>? This action is irreversible.`,
        () => {
            fetch('tournaments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const row = document.querySelector(`[data-id="${id}"]`);
                    if (row) {
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    } else {
                        window.location.reload();
                    }
                } else {
                    showToast(data.message || 'Error occurred.', 'error');
                }
            })
            .catch(() => showToast('Connection error.', 'error'));
        }
    );
}

// ─── Oyun silme ─────────────────────────────────────────────────────────────
function deleteGame(id, name) {
    adminConfirm(
        `Delete game <strong>"${name}"</strong>? Tournaments linked to this game may break.`,
        () => {
            fetch('games.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    showToast(data.message || 'Error occurred.', 'error');
                }
            })
            .catch(() => showToast('Connection error.', 'error'));
        }
    );
}

// ─── Success alert otomatik kapat ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.op-alert--success').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 3500);
    });
});
 