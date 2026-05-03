document.addEventListener('DOMContentLoaded', () => {
    
    // ── STATE ──
    let state = { 
        tab: 'players', 
        game: 'all', 
        searchPlayers: '', 
        searchTeams: '' 
    };

    let isInitialLoad = true; // Sayfa ilk açıldığında animasyon yapmasını engeller

    // ── DOM ELEMENTS ──
    const tabPlayersDiv = document.getElementById('tab-players');
    const tabTeamsDiv = document.getElementById('tab-teams');
    const playerTbody = document.getElementById('player-tbody');
    const teamTbody = document.getElementById('team-tbody');
    const playerCountEl = document.getElementById('player-count');
    const teamCountEl = document.getElementById('team-count');
    const podiumEl = document.getElementById('podium');
    const tableCardEl = document.querySelector('.table-card'); // Kutunun kendisi

    // ── HELPERS ──
    function gameMatch(r, gameFilter) {
        if (gameFilter === 'all') return true;
        const g = (r.game || '').toLowerCase();
        const f = (gameFilter || '').toLowerCase();
        return g.includes(f) || f.includes(g);
    }

    function getWrClass(wr) { return wr >= 70 ? 'wr-high' : wr >= 50 ? 'wr-mid' : 'wr-low'; }
    function getRankColor(i) { return i === 0 ? 'rank-gold' : i === 1 ? 'rank-silver' : i === 2 ? 'rank-bronze' : 'rank-num'; }
    function getAvatarClass(i) { return i === 0 ? 'gold' : i === 1 ? 'silver' : i === 2 ? 'bronze' : 'default'; }
    function getGameTagClass(g) {
        const gl = (g || '').toLowerCase();
        if (gl.includes('cs'))       return 'cs2';
        if (gl.includes('valorant')) return 'val';
        if (gl.includes('fc'))       return 'fc';
        if (gl.includes('league'))   return 'lol';
        return '';
    }

    // ── KUTU BOYUTUNU YUMUŞATAN ANA FONKSİYON ──
    function smoothHeightTransition(element, action) {
        if (!element || isInitialLoad) {
            action();
            return;
        }

        // 1. Eski yüksekliği kaydet
        const startHeight = element.offsetHeight;

        // 2. Yüksekliği serbest bırak ve işlemi yap
        element.style.height = 'auto';
        element.style.transition = 'none';
        action();

        // 3. İşlem sonrası yeni yüksekliği ölç
        const endHeight = element.offsetHeight;

        // 4. Boyut değişmediyse animasyona gerek yok
        if (startHeight === endHeight) return;

        // 5. Animasyon için başlangıç değerine geri dön
        element.style.height = startHeight + 'px';
        element.style.overflow = 'hidden'; // Taşmaları gizle

        // Tarayıcıyı yenilemeye (reflow) zorla
        void element.offsetWidth;

        // 6. Geçişi başlat ve yeni yüksekliğe git
        element.style.transition = 'height 0.35s ease-in-out';
        element.style.height = endHeight + 'px';

        // 7. Animasyon bitince temizlik yap (Responsive yapıyı bozmamak için)
        element.ontransitionend = (e) => {
            if (e.propertyName === 'height' && e.target === element) {
                element.style.height = 'auto';
                element.style.overflow = '';
                element.style.transition = '';
                element.ontransitionend = null;
            }
        };
    }

    // Tablo satırlarına fade efekti atar
    function triggerFade(element) {
        element.classList.remove('animate-fade');
        void element.offsetWidth;
        element.classList.add('animate-fade');
    }

    // ── HTML ÜRETİCİLER (RENDERERS) ──
    function updatePodiumHTML(currentData) {
        if (!podiumEl) return;
        
        const order = [currentData[1], currentData[0], currentData[2]];
        const classes = ['second', 'first', 'third'];
        const ranks = [2, 1, 3];
        
        podiumEl.innerHTML = order.map((r, i) => {
            if (!r) return `<div class="podium-slot ${classes[i]}"><div class="pod-base"></div></div>`;
            return `
                <div class="podium-slot ${classes[i]}">
                    <div class="pod-avatar">${r.init}</div>
                    <div class="pod-name">${r.name}</div>
                    <div class="pod-pts">${r.points.toLocaleString('en-US')} pts</div>
                    <div class="pod-base"><span class="rank-label">#${ranks[i]}</span></div>
                </div>
            `;
        }).join('');
        triggerFade(podiumEl);
    }

    function renderTableHTML(tbody, data, countEl, typeLabel) {
        if (!tbody) return;
        
        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">No records found matching this filter.</div></td></tr>`;
        } else {
            tbody.innerHTML = data.map((r, i) => {
                const meLabel = r.me ? '<span class="me-badge">YOU</span>' : '';
                const gc = getGameTagClass(r.game);
                return `
                    <tr class="${r.me ? 'me' : ''}">
                        <td><span class="${getRankColor(i)}">${i+1}</span></td>
                        <td>
                            <div class="player-cell">
                                <div class="p-av ${getAvatarClass(i)}">${r.init}</div>
                                <div>
                                    <div class="p-nm">${r.name}${meLabel}</div>
                                    <div class="p-tag">${r.tag}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="game-tag ${gc}">${r.game}</span></td>
                        <td class="wins-cell">${r.wins}</td>
                        <td class="matches-cell">${r.matches}</td>
                        <td>
                            <div class="wr-cell">
                                <div class="wr-bar"><div class="wr-fill ${getWrClass(r.wr)}" style="width:${r.wr}%"></div></div>
                                <span class="wr-text">${r.wr}%</span>
                            </div>
                        </td>
                        <td class="pts-cell">${r.points.toLocaleString('en-US')}</td>
                    </tr>
                `;
            }).join('');
        }
        
        if (countEl) countEl.textContent = `${data.length} ${typeLabel}`;
        triggerFade(tbody);
    }

    // ── ANA TETİKLEYİCİ (RENDER) ──
    function render() {
        
        // 1. KÜRSÜ (PODIUM) GÜNCELLEMESİ VE BOYUT ANİMASYONU
        smoothHeightTransition(podiumEl, () => {
            const currentData = state.tab === 'players' ? playersData : teamsData;
            const filtered = currentData.filter(r => gameMatch(r, state.game));
            filtered.sort((a, b) => b.points - a.points);
            updatePodiumHTML(filtered);
        });

        // 2. ANA KUTU (TABLE CARD) GÜNCELLEMESİ VE BOYUT ANİMASYONU
        smoothHeightTransition(tableCardEl, () => {
            
            // Oyuncuları filtrele ve çiz
            const filteredPlayers = playersData.filter(p => 
                gameMatch(p, state.game) && 
                (!state.searchPlayers || p.name.toLowerCase().includes(state.searchPlayers) || p.tag.toLowerCase().includes(state.searchPlayers))
            );
            filteredPlayers.sort((a, b) => b.points - a.points);
            renderTableHTML(playerTbody, filteredPlayers, playerCountEl, 'players');

            // Takımları filtrele ve çiz
            const filteredTeams = teamsData.filter(t => 
                gameMatch(t, state.game) && 
                (!state.searchTeams || t.name.toLowerCase().includes(state.searchTeams) || t.tag.toLowerCase().includes(state.searchTeams))
            );
            filteredTeams.sort((a, b) => b.points - a.points);
            renderTableHTML(teamTbody, filteredTeams, teamCountEl, 'teams');

            // Aktif Tab'a göre göster/gizle
            if (state.tab === 'players') {
                tabPlayersDiv.style.display = '';
                tabTeamsDiv.style.display = 'none';
            } else {
                tabPlayersDiv.style.display = 'none';
                tabTeamsDiv.style.display = '';
            }
        });

        isInitialLoad = false;
    }

    // ── EVENT LISTENERS (GLOBAL) ──
    window.switchTab = function(tabName, btnElement) {
        state.tab = tabName;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btnElement.classList.add('active');
        render(); 
    };

    window.filterGame = function(gameName, btnElement) {
        state.game = gameName;
        document.querySelectorAll('.gf-btn').forEach(b => b.classList.remove('active'));
        btnElement.classList.add('active');
        render();
    };

    window.filterSearch = function(val, tabName) {
        if (tabName === 'players') {
            state.searchPlayers = val.toLowerCase();
        } else {
            state.searchTeams = val.toLowerCase();
        }
        render();
    };

    // ── INITIAL CALL ──
    render();
});