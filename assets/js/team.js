function togglePanel(id) {
    ['editPanel','invitePanel'].forEach(p => {
        if (p !== id) {
            const el = document.getElementById(p);
            if (el) el.style.display = 'none';
        }
    });
    const target = document.getElementById(id);
    if (target) {
        target.style.display = target.style.display === 'none' ? 'block' : 'none';
        if (target.style.display === 'block') {
            target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
}

// --- ÖZEL ONAY KUTUSU (CUSTOM CONFIRM) SİSTEMİ ---
let currentConfirmAction = null;
let currentConfirmBtn = null;

function showCustomConfirm(title, message, btnText, actionCallback, sourceBtn = null) {
    document.getElementById('customConfirmTitle').innerText = title;
    document.getElementById('customConfirmMessage').innerText = message;
    
    const confirmBtn = document.getElementById('customConfirmBtn');
    confirmBtn.innerText = btnText;
    
    // İşlemi hafızaya al
    currentConfirmAction = actionCallback;
    currentConfirmBtn = sourceBtn;
    
    // Kutuyu göster
    const overlay = document.getElementById('customConfirmOverlay');
    overlay.style.display = 'flex';
    setTimeout(() => overlay.classList.add('show'), 10); // Animasyon için ufak gecikme
}

function closeCustomConfirm() {
    const overlay = document.getElementById('customConfirmOverlay');
    overlay.classList.remove('show');
    setTimeout(() => overlay.style.display = 'none', 300);
}

// Modal içindeki onay butonuna tıklanınca
document.getElementById('customConfirmBtn').addEventListener('click', function() {
    if (currentConfirmAction) {
        currentConfirmAction(currentConfirmBtn, this);
    }
});

// --- TAKIMDAN AYRILMA (LEAVE TEAM) İŞLEMİ ---
function confirmLeave(btn) {
    showCustomConfirm(
        'Leave Team', 
        'Are you sure you want to leave this team? This action cannot be undone.', 
        'Leave', 
        function(originalBtn, modalBtn) {
            
            // Modal içindeki butonu "Leaving..." yapıp dondur
            modalBtn.innerText = 'Leaving...';
            modalBtn.disabled = true;
            modalBtn.style.opacity = '0.7';
            modalBtn.style.cursor = 'not-allowed';
            
            // 1 saniyelik şık bekleme süresinden sonra formu gönder
            setTimeout(() => {
                document.getElementById('leaveForm').submit();
            }, 1000);
        },
        btn
    );
}

function previewAvatar(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview || !input.files || !input.files[0]) return;

    const file = input.files[0];
    if (!file.type.startsWith('image/')) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
    };
    reader.readAsDataURL(file);
}

// Auto-hide success alerts
document.addEventListener('DOMContentLoaded', () => {
    
    // Tag input auto-uppercase
    document.querySelectorAll('input[name="tag"]').forEach(inp => {
        inp.addEventListener('input', () => {
            inp.value = inp.value.toUpperCase();
        });
    });

    // --- "Save Changes" Fake Delay Animasyonu ---
    const editForm = document.getElementById('editTeamForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault(); 
            const submitBtn = editForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerText = 'Saving...';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.style.cursor = 'not-allowed';
            }
            setTimeout(() => { editForm.submit(); }, 1500);
        });
    }

    // --- "Invite Member" Fake Delay Animasyonu ---
    const inviteForm = document.getElementById('inviteTeamForm');
    if (inviteForm) {
        inviteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = inviteForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerText = 'Inviting...'; // Davet ediliyor...
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.style.cursor = 'not-allowed';
            }
            setTimeout(() => { inviteForm.submit(); }, 500); // 0.5 saniye gecikme
        });
    }
});

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    // Önce varsa eski sınıfları temizle
    toast.className = 'tm-toast';
    
    // Mesajı ve tipi ayarla
    toast.innerText = message;
    toast.classList.add('tm-toast--' + type);

    // Görünür yap (Küçük bir delay tarayıcının render etmesini sağlar)
    setTimeout(() => {
        toast.classList.add('tm-toast--show');
    }, 50);

    // 3 saniye sonra kapat
    setTimeout(() => {
        toast.classList.remove('tm-toast--show');
    }, 3000);
}
function copyInviteCode() {
    const codeEl = document.getElementById('inviteCodeText');
    if (!codeEl) return;
    
    const code = codeEl.innerText.trim();
    
    navigator.clipboard.writeText(code).then(() => {
        showToast('Code copied: ' + code, 'success');
    }).catch(err => {
        console.error('Copy error:', err);
        alert('Code: ' + code); 
    });
}
const editForm = document.getElementById('editTeamForm');
if (editForm) {
    editForm.addEventListener('submit', function(e) {
        e.preventDefault(); // Formun hemen gitmesini engelle

        // Gönder butonunu bul
        const submitBtn = editForm.querySelector('button[type="submit"]');

        // Butonu pasifleştir ve yazısını değiştir
        submitBtn.innerText = 'Saving...'; // İstersen 'Kaydediliyor...' yap
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        submitBtn.style.cursor = 'not-allowed';

        // 1.5 saniye (1500ms) bekleyip formu manuel olarak gönder
        setTimeout(() => {
            editForm.submit();
        }, 1500);
    });
}