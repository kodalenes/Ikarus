// ADMIN PANELİ GENEL JS DOSYASI (admin.js)

// ─── 1. YARDIMCI FONKSİYONLAR (UTILS) ───────────────────────────────────────

// CSRF Token'ı sayfadan güvenli bir şekilde çeker
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]') || document.querySelector('meta[name="csrf_token"]');
    if (meta) return meta.getAttribute('content');
    
    // Fallback: Form içindeki gizli input alanını kontrol et
    const input = document.querySelector('input[name="csrf_token"]') || document.querySelector('input[name="csrf-token"]');
    return input ? input.value : '';
}

// XSS Koruması için HTML etiketlerini temizler
function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Bildirim (Toast) Gösterme
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

// Tehlikeli işlemler için özel onay kutusu
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

    document.getElementById('adm-ok').addEventListener('click', () => {
        overlay.remove();
        onConfirm();
    });
    document.getElementById('adm-cancel').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove() });
}

// Sayfa yüklendiğinde çalışacak genel olaylar
document.addEventListener('DOMContentLoaded', () => {
    // Başarılı işlem bildirimlerini otomatik gizle
    document.querySelectorAll('.op-alert--success').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 3500);
    });

    // Games.php sayfasındaki "+ Add Game" butonunu yakala ve forma bağla
    const actionBtn = document.querySelector('.op-topbar-actions a');
    if (actionBtn && window.location.pathname.includes('games.php')) {
        actionBtn.addEventListener('click', e => { 
            e.preventDefault(); 
            toggleAddForm(); 
        });
    }
});