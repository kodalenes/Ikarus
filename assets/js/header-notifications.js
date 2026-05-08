const NotifApp = (() => {
    const API = '/pages/team-api.php';
    const INTERVAL = 60_000;
    let timer = null;
    let isOpen = false;

    function btn() {
        return document.getElementById('hdr-notif-btn');
    }

    function badge() {
        return document.getElementById('hdr-notif-count');
    }

    function panel() {
        return document.getElementById('hdr-notif-panel');
    }

    function body() {
        return document.getElementById('hdr-notif-body');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function timeAgo(datetime) {
        const diff = Math.floor((Date.now() - new Date(datetime)) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        return `${Math.floor(diff / 86400)}d ago`;
    }

    async function refresh() {
        try {
            const response = await fetch(`${API}?action=get_invites`);
            const json = await response.json();
            if (!json.ok) return;

            const invites = json.data?.invites || [];
            const count = invites.length;
            const bellBadge = badge();
            const bellButton = btn();
            const panelCount = document.getElementById('hdr-notif-panel-count');
            const panelBody = body();

            if (bellBadge) {
                bellBadge.textContent = count;
                bellBadge.classList.toggle('hidden', count === 0);
            }

            if (bellButton) {
                bellButton.classList.toggle('has-notifs', count > 0);
            }

            if (panelCount) {
                panelCount.textContent = count > 0 ? `${count} pending` : '';
            }

            if (!panelBody) return;

            if (count === 0) {
                panelBody.innerHTML = '<div class="hdr-notif-empty">No pending invitations.</div>';
                return;
            }

            panelBody.innerHTML = invites.map((invite) => {
                const initials = invite.team_name.substring(0, 2).toUpperCase();
                const avatar = invite.logo_url
                    ? `<img src="${escapeHtml(invite.logo_url)}" alt="">`
                    : initials;

                return `
                    <div class="hdr-notif-item" id="hdr-inv-${invite.id}">
                        <div class="hdr-notif-item-top">
                            <div class="hdr-notif-ava">${avatar}</div>
                            <div class="hdr-notif-info">
                                <div class="hdr-notif-team">${escapeHtml(invite.team_name)}</div>
                                <div class="hdr-notif-sender">from ${escapeHtml(invite.sender_name)} · ${timeAgo(invite.sent_at)}</div>
                            </div>
                        </div>
                        <div class="hdr-notif-btns">
                            <button class="hdr-notif-accept" onclick="NotifApp.respond(${invite.id}, true)">Accept</button>
                            <button class="hdr-notif-decline" onclick="NotifApp.respond(${invite.id}, false)">Decline</button>
                        </div>
                    </div>`;
            }).join('');
        } catch (error) {
            console.error('NotifApp.refresh:', error);
        }
    }

    async function respond(inviteId, accept) {
        const item = document.getElementById(`hdr-inv-${inviteId}`);
        item?.querySelectorAll('button').forEach((button) => {
            button.disabled = true;
            button.style.opacity = '0.5';
        });

        try {
            const formData = new FormData();
            formData.append('action', 'respond_invite');
            formData.append('invite_id', inviteId);
            formData.append('response', accept ? 'accept' : 'decline');

            const response = await fetch(API, { method: 'POST', body: formData });
            const json = await response.json();
            if (!json.ok) {
                throw new Error(json.message || 'Failed to respond to invitation.');
            }

            if (item) {
                item.style.transition = 'opacity 0.3s ease';
                item.style.opacity = '0';
                setTimeout(() => item.remove(), 320);
            }

            const toast = document.getElementById('tm-toast');
            if (toast) {
                toast.className = 'tm-toast tm-toast--success tm-toast--show';
                toast.textContent = json.message;
                setTimeout(() => toast.classList.remove('tm-toast--show'), 3400);
            }

            setTimeout(() => {
                refresh();
                if (accept) {
                    window.location.reload();
                }
            }, 400);
        } catch (error) {
            item?.querySelectorAll('button').forEach((button) => {
                button.disabled = false;
                button.style.opacity = '1';
            });
            console.error('NotifApp.respond:', error);
        }
    }

    function closePanel() {
        isOpen = false;
        panel()?.classList.remove('is-open');
    }

    function togglePanel() {
        const notifPanel = panel();
        if (!notifPanel) return;

        isOpen = !isOpen;
        notifPanel.classList.toggle('is-open', isOpen);

        if (isOpen) {
            refresh();
        }
    }

    function init() {
        if (!btn()) return;
        refresh();
        clearInterval(timer);
        timer = setInterval(refresh, INTERVAL);

        document.addEventListener('click', (event) => {
            const wrap = document.getElementById('hdr-notif-wrap');
            if (wrap && !wrap.contains(event.target)) {
                closePanel();
            }
        });
    }

    return { init, refresh, respond, togglePanel };
})();

window.NotifApp = NotifApp;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('hdr-notif-btn')) {
        NotifApp.init();
    }
});
