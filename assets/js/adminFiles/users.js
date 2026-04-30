// ============================================================================
// 2. KULLANICI (USERS.PHP) İŞLEMLERİ
// ============================================================================

function changeUserRole(userId, newRole, username, selectEl) {
    adminConfirm(
        `Change <strong>${username}</strong>'s role to <strong>${newRole}</strong>?`,
        () => {
            const params = new URLSearchParams({
                action: 'change_role',
                user_id: userId,
                role: newRole,
                csrf_token: getCsrfToken() 
            });

            fetch('users.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    selectEl.dataset.prev = newRole;
 
                    const badgeMap = {
                        admin:     ['adm-badge--admin',  'Admin'],
                        organizer: ['op-badge--open',    'Organizer'],
                        player:    ['op-badge--done',    'Player'],
                    };
                    const badge = selectEl.closest('tr').querySelector('.op-badge');
                    if (badge && badgeMap[newRole]) {
                        badge.className  = 'op-badge ' + badgeMap[newRole][0];
                        badge.textContent = badgeMap[newRole][1];
                    }
                } else {
                    showToast(data.message, 'error');
                    selectEl.value = selectEl.dataset.prev;
                }
            })
            .catch(() => {
                showToast('Connection error.', 'error');
                selectEl.value = selectEl.dataset.prev;
            });
        },
        () => { selectEl.value = selectEl.dataset.prev; }
    );
}

function deleteUser(userId, username) {
    adminConfirm(
        `Permanently delete <strong>${username}</strong>? This cannot be undone.`,
        () => {
            const params = new URLSearchParams({
                action: 'delete_user',
                user_id: userId,
                csrf_token: getCsrfToken()
            });

            fetch('users.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const row = document.querySelector(`tr[data-id="${userId}"]`);
                    if (row) {
                        row.style.transition = 'opacity 0.3s, transform 0.3s';
                        row.style.opacity    = '0';
                        row.style.transform  = 'translateX(12px)';
                        setTimeout(() => row.remove(), 320);
                    }
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(() => showToast('Connection error.', 'error'));
        }
    );
}