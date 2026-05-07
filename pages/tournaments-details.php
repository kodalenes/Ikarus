<?php
require_once '../includes/session.php';
$extraCss = ['tournaments' , 'tournament-details'];

/* ─── Secure ID retrieval ────────────────────────────────────── */
$t_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

/* ─── Tournament info ────────────────────────────────────────── */
try {
    $stmt = $pdo->prepare("
        SELECT t.*, g.name AS game_name, p.username AS organizer_name
        FROM Tournament t
        LEFT JOIN Game g  ON g.id = t.game_id AND g.deleted_at IS NULL
        LEFT JOIN Player p ON p.id = t.organizer_id AND p.deleted_at IS NULL
        WHERE t.id = ? AND t.deleted_at IS NULL
    ");
    $stmt->execute([$t_id]);
    $tournament = $stmt->fetch();
} catch (Exception $e) {
    $tournament = false;
    die('Database error: ' . $e->getMessage());
}

/* ─── 404 Redirection ────────────────────────────────────────── */
if (!$tournament) {
    header('Location: tournaments.php');
    exit;
}

$customTitle =  htmlspecialchars($tournament['name']);


/* ─── Registered teams ───────────────────────────────────────── */
try {
    $stmtTeams = $pdo->prepare("
        SELECT t.id, t.name, t.tag, t.rank_point, tt.registered_at
        FROM tournament_teams tt
        JOIN Team t ON t.id = tt.team_id AND t.deleted_at IS NULL
        WHERE tt.tournament_id = ?
        ORDER BY t.rank_point DESC
    ");
    $stmtTeams->execute([$t_id]);
    $teams = $stmtTeams->fetchAll();
} catch (Exception $e) {
    $teams = [];
}

/* ─── Matches ────────────────────────────────────────────────── */
try {
    // TBD (Henüz belli olmayan) takımlar için LEFT JOIN kullanıyoruz
    $stmtMatches = $pdo->prepare("
        SELECT m.*,
                t1.name AS home_name,
                t2.name AS away_name
        FROM Matches m
        LEFT JOIN Team t1 ON t1.id = m.team1_id AND t1.deleted_at IS NULL
        LEFT JOIN Team t2 ON t2.id = m.team2_id AND t2.deleted_at IS NULL
        WHERE m.tournament_id = ? AND m.deleted_at IS NULL
        ORDER BY m.round_number ASC, m.id ASC
    ");
    $stmtMatches->execute([$t_id]);
    $matches = $stmtMatches->fetchAll();
} catch (Exception $e) {
    $matches = [];
}

/* ─── Tournament rules ───────────────────────────────────────── */
try {
    $stmtRules = $pdo->prepare("
        SELECT rule_text 
        FROM Tournament_Rule
        WHERE tournament_id = ?
        ORDER BY sort_order
    ");
    $stmtRules->execute([$t_id]);
    $rules = $stmtRules->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $rules = [];
}

/* ─── User team check ────────────────────────────────────────── */
$myTeamId          = null;
$myTeamInTournament = false;
if (isLoggedIn()) {
    try {
        $s = $pdo->prepare("SELECT team_id FROM Player WHERE id = ? AND deleted_at IS NULL");
        $s->execute([$_SESSION['user_id']]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        $myTeamId = $row ? $row['team_id'] : null;

        if ($myTeamId) {
            $s2 = $pdo->prepare("SELECT 1 FROM tournament_teams WHERE team_id = ? AND tournament_id = ?");
            $s2->execute([$myTeamId, $t_id]);
            $myTeamInTournament = (bool)$s2->fetchColumn();
        }
    } catch (Exception $e) {}
}

$registeredCount = count($teams);
$isFull   = $tournament['max_teams'] > 0 && $registeredCount >= $tournament['max_teams'];
$canJoin  = isLoggedIn()
    && !empty($myTeamId)
    && !$myTeamInTournament
    && !$isFull
    && $tournament['status'] === 'registration';

/* ─── Generate bracket data ──────────────────────────────────── */
$bracketTeams   = [];
$bracketResults = [];
$stageLabels    = [];

if (!empty($matches)) {
    // 1. Maçları round_number'a göre grupla
    $byRound = [];
    foreach ($matches as $m) {
        $byRound[$m['round_number']][] = $m;
    }
    ksort($byRound);

    $firstRoundNumber = (int)array_key_first($byRound);

    // 2. İlk turdaki takım çiftlerini al
    foreach ($byRound[$firstRoundNumber] as $m) {
        $bracketTeams[] = [
            $m['home_name'] ?? null,
            $m['away_name'] ?? null
        ];
    }

    // 3. Toplam tur sayısını hesapla (2'nin üssü mantığıyla)
    // Örn: 8 takım → 3 tur, 4 takım → 2 tur
    $firstRoundMatchCount = count($bracketTeams);
    $totalRounds = (int)ceil(log($firstRoundMatchCount * 2, 2));

    // 4. Tüm turlar için boş results oluştur (oynanmamış = [null, null])
    // Bu sayede jquery-bracket tüm turları çizer
    for ($r = 0; $r < $totalRounds; $r++) {
        $matchCountInRound = (int)($firstRoundMatchCount / pow(2, $r));
        $matchCountInRound = max(1, $matchCountInRound); // En az 1 maç (Final)
        $bracketResults[]  = array_fill(0, $matchCountInRound, [null, null]);
    }

    // 5. DB'deki gerçek skorları doldur
    foreach ($byRound as $roundNum => $roundMatches) {
        // 0-tabanlı index: round_number 1'den başlıyorsa -1 yap
        $roundIdx = (int)$roundNum - $firstRoundNumber;

        foreach ($roundMatches as $matchIdx => $m) {
            $s1 = $m['score_team1'];
            $s2 = $m['score_team2'];

            $hasScore = ($s1 !== null && $s2 !== null)
                     && !($s1 == 0 && $s2 == 0 && empty($m['winner_id']));

            if ($hasScore && isset($bracketResults[$roundIdx][$matchIdx])) {
                $bracketResults[$roundIdx][$matchIdx] = [(int)$s1, (int)$s2];
            }
        }
    }

    // 6. Stage isimlerini doğru şekilde üret ($byRound kullanarak)
    $stageSuffixes = [
        'Final', 'Semi Final', 'Quarter Final',
        'Round of 16', 'Round of 32', 'Round of 64'
    ];

    // Tur sayısına göre geriye doğru etiketle
    $roundNumbers = array_keys($byRound);
    $maxRound     = max($roundNumbers);

    foreach ($roundNumbers as $rNum) {
        $distanceToFinal  = $maxRound - $rNum;           // Final'e kaç tur kaldı
        $stageLabels[]    = $stageSuffixes[$distanceToFinal] ?? "Round {$rNum}";
    }

    // Bracket tam dolmamış turların etiketleri
    for ($r = count($stageLabels); $r < $totalRounds; $r++) {
        $distanceToFinal = $totalRounds - 1 - $r;
        $stageLabels[]   = $stageSuffixes[$distanceToFinal] ?? "Round " . ($r + 1);
    }
}

// 7. JSON encode
$bracketData = json_encode([
    'teams'   => $bracketTeams,
    'results' => [$bracketResults]  // Tek dizi: single elimination
]);

/* ─── Status badge mapping ───────────────────────────────────── */
$statusMap = [
    'live'         => ['label' => 'LIVE',     'class' => 's-live'],
    'registration' => ['label' => 'OPEN',     'class' => 's-open'],
    'upcoming'     => ['label' => 'UPCOMING', 'class' => 's-soon'],
    'completed'    => ['label' => 'ENDED',    'class' => 's-full'],
];
$statusInfo = $statusMap[$tournament['status']] ?? ['label' => strtoupper($tournament['status']), 'class' => 's-full'];

/* ─── Game icon mapping ──────────────────────────────────────── */
$gameLower  = strtolower($tournament['game_name'] ?? '');
$gameIcon   = 'T';
$gameClass  = 'icon-all';
if (str_contains($gameLower, 'val'))           { $gameIcon = 'V';  $gameClass = 'icon-val'; }
elseif (str_contains($gameLower, 'cs'))        { $gameIcon = 'CS'; $gameClass = 'icon-cs'; }
elseif (str_contains($gameLower, 'league'))    { $gameIcon = 'LoL';$gameClass = 'icon-lol'; }
elseif (str_contains($gameLower, 'fc') || str_contains($gameLower, 'fifa')) { $gameIcon = 'FC'; $gameClass = 'icon-fc'; }

/* ─── Prize calculation ──────────────────────────────────────── */
$prizePool = (float)($tournament['prize_pool'] ?? 0);
$prize1st  = (float)($tournament['prize_1st']  ?? $prizePool * 0.6);
$prize2nd  = (float)($tournament['prize_2nd']  ?? $prizePool * 0.3);
$prize3rd  = (float)($tournament['prize_3rd']  ?? $prizePool * 0.1);

/* ─── Time left ──────────────────────────────────────────────── */
$daysLeft = '';
if (!empty($tournament['end_date'])) {
    $diff = (new DateTime($tournament['end_date']))->diff(new DateTime());
    $daysLeft = $diff->invert ? $diff->days . ' days left' : 'Ended';
}

/* ─── Handle join action ─────────────────────────────────────── */
$joinMsg   = '';
$joinError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_tournament']) && $canJoin) {
    try {
        $ins = $pdo->prepare("INSERT INTO tournament_teams (team_id, tournament_id) VALUES (?, ?)");
        $ins->execute([$myTeamId, $t_id]);
        $joinMsg = 'Successfully joined the tournament!';
        $myTeamInTournament = true;
        $canJoin = false;
        $registeredCount++;
        // Add team to the list
        $s = $pdo->prepare("SELECT id, name, tag, rank_point FROM Team WHERE id = ?");
        $s->execute([$myTeamId]);
        $teams[] = $s->fetch();
    } catch (Exception $e) {
        $joinError = 'An error occurred while joining.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_tournament']) && $myTeamInTournament && $tournament['status'] === 'registration') {
    try {
        $del = $pdo->prepare("DELETE FROM tournament_teams WHERE team_id = ? AND tournament_id = ?");
        $del->execute([$myTeamId, $t_id]);
        $joinMsg = 'Successfully left the tournament.';
        $myTeamInTournament = false;
        $canJoin = true;
        $registeredCount--;
        $teams = array_filter($teams, fn($team) => $team['id'] != $myTeamId);
    } catch (Exception $e) {
        $joinError = 'An error occurred while leaving.';
    }
}

// Check if current user is the organizer of this tournament
$isOrganizerOwner = false;
if (isLoggedIn() && isset($tournament['organizer_id'])) {
    $isOrganizerOwner = ($_SESSION['user_id'] == $tournament['organizer_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once  '../includes/head.php'?>
    <!-- jquery-bracket library -->
    <link rel="stylesheet" href="https://unpkg.com/jquery-bracket@0.11.1/dist/jquery.bracket.min.css">
</head>
<body>
    <?php require_once '../includes/header.php' ?>

    <main>
        <div class="page tournaments-wrapper">
            <div class="content">

                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <a href="tournaments.php">Tournaments</a>
                    <span class="sep">›</span>
                    <span class="current"><?= htmlspecialchars($tournament['name']) ?></span>
                </div>

                <!-- Alert Messages -->
                <?php if ($joinMsg): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($joinMsg) ?></div>
                <?php endif; ?>
                <?php if ($joinError): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($joinError) ?></div>
                <?php endif; ?>

                <!-- ─── Hero Band ──────────────────────────────── -->
                <div class="hero-band">
                    <div class="t-icon <?= $gameClass ?>">
                        <?= htmlspecialchars($gameIcon) ?>
                    </div>

                    <div class="t-hero-info">
                        <div class="t-title"><?= htmlspecialchars($tournament['name']) ?></div>
                        <div class="t-badges">
                            <span class="badge <?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
                            <span class="badge s-soon"><?= htmlspecialchars($tournament['game_name'] ?? '—') ?></span>
                        </div>
                        <div class="t-organizer">
                            Organizer: <span><?= htmlspecialchars($tournament['organizer_name'] ?? '—') ?></span>
                            &nbsp;·&nbsp;
                            Start: <span><?= date('d M Y', strtotime($tournament['start_date'])) ?></span>
                        </div>
                    </div>

                    <div class="hero-right">
                        <?php if ($prizePool > 0): ?>
                        <div class="prize-big">
                            <div class="prize-label-sm">PRIZE POOL</div>
                            <div class="prize-amount">₺<?= number_format($prizePool, 0, ',', '.') ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($isOrganizerOwner): ?>
                            <!-- Organizer actions -->
                            <a href="../organizer/tournament-manage.php?id=<?= $t_id ?>" class="join-btn" style="text-decoration:none; display:inline-block; text-align:center; background:var(--highlight); margin-bottom:8px; width: 100%;">Manage</a>
                            <a href="../organizer/tournament-create.php?id=<?= $t_id ?>" class="join-btn" style="text-decoration:none; display:inline-block; text-align:center; background:transparent; color:var(--text-muted); border:1px solid var(--border); width: 100%;">Edit</a>
                        <?php elseif ($myTeamInTournament): ?>
                            <?php if ($tournament['status'] === 'registration'): ?>
                                <form class="join-form" method="POST" onsubmit="return confirm('Are you sure you want to leave this tournament?');">
                                    <input type="hidden" name="leave_tournament" value="1">
                                    <button class="join-btn" type="submit" style="background:#f87171; border:1px solid rgba(248,113,113,0.3);">Leave</button>
                                </form>
                            <?php else: ?>
                                <button class="join-btn" disabled>✓ Joined</button>
                            <?php endif; ?>
                        <?php elseif ($canJoin): ?>
                            <form class="join-form" method="POST">
                                <input type="hidden" name="join_tournament" value="1">
                                <button class="join-btn" type="submit">Join</button>
                            </form>
                        <?php elseif ($isFull): ?>
                            <button class="join-btn" disabled>Tournament Full</button>
                        <?php elseif (!isLoggedIn()): ?>
                            <button class="join-btn" onclick="openModal('login')">Login</button>
                        <?php elseif ($tournament['status'] !== 'registration'): ?>
                            <button class="join-btn" disabled>Registration Closed</button>
                        <?php else: ?>
                            <button class="join-btn" disabled title="You must be in a team to join.">Team Required</button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ─── Stats Row ─────────────────────────────── -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-label">Teams</div>
                        <div class="stat-val"><?= $registeredCount ?> / <?= $tournament['max_teams'] ?: '∞' ?></div>
                        <div class="stat-sub"><?= $isFull ? 'Registration closed' : 'Registration open' ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Format</div>
                        <div class="stat-val" style="font-size:14px">Single Elimination</div>
                        <div class="stat-sub">Best of 1</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Status</div>
                        <div class="stat-val" style="font-size:14px"><?= $statusInfo['label'] ?></div>
                        <div class="stat-sub"><?= $registeredCount ?> teams registered</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">End Date</div>
                        <div class="stat-val" style="font-size:14px"><?= date('d M', strtotime($tournament['end_date'])) ?></div>
                        <div class="stat-sub"><?= $daysLeft ?></div>
                    </div>
                    <?php if ($prize1st > 0): ?>
                    <div class="stat-card">
                        <div class="stat-label">Champion Prize</div>
                        <div class="stat-val" style="font-size:15px;color:var(--highlight)">₺<?= number_format($prize1st, 0, ',', '.') ?></div>
                        <div class="stat-sub">2nd ₺<?= number_format($prize2nd, 0, ',', '.') ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ─── Tabs ──────────────────────────────────── -->
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchDetailTab('bracket',this)">Bracket</button>
                    <button class="tab-btn" onclick="switchDetailTab('participants',this)">
                        Participants
                        <span style="font-size:10px;color:var(--text-faint);margin-left:4px">(<?= $registeredCount ?>)</span>
                    </button>
                    <button class="tab-btn" onclick="switchDetailTab('rules',this)">Rules</button>
                </div>

                <!-- ─── Tab: Bracket ──────────────────────────── -->
                <div id="tab-bracket" class="tab-pane active">
                    <?php if (empty($matches)): ?>
                        <div class="bracket-empty">
                            <div class="bracket-empty-icon">🏆</div>
                            <div>Bracket has not been created yet.</div>
                            <div style="color:var(--text-faint);font-size:11px;margin-top:6px">
                                It will appear here when the matches start.
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bracket-scroll">
                            <div id="bracket-container"></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ─── Tab: Participants ─────────────────────── -->
                <div id="tab-participants" class="tab-pane">
                    <?php if (empty($teams)): ?>
                        <div class="empty-state">No registered teams yet.</div>
                    <?php else: ?>
                        <div class="participants-grid">
                            <?php foreach ($teams as $team):
                                $isMyTeam = $myTeamId && $team['id'] == $myTeamId;
                                $initials = strtoupper(substr($team['name'], 0, 2));
                                $colors = [
                                    'rgba(72,159,181,.12)'  => '#489fb5',
                                    'rgba(255,70,84,.1)'    => '#ff8088',
                                    'rgba(200,155,60,.1)'   => '#c89b3c',
                                    'rgba(99,153,34,.1)'    => '#97c459',
                                ];
                                $colorPairs = array_keys($colors);
                                $colorIdx   = crc32($team['name']) % count($colorPairs);
                                $bg    = $colorPairs[abs($colorIdx)];
                                $color = $colors[$bg];
                            ?>
                                <div class="p-card <?= $isMyTeam ? 'my-team-card' : '' ?>">
                                    <div class="p-avatar" style="background:<?= $bg ?>;color:<?= $color ?>">
                                        <?= htmlspecialchars($initials) ?>
                                    </div>
                                    <div>
                                        <div class="p-name"><?= htmlspecialchars($team['name']) ?></div>
                                        <div class="p-tag">
                                            <?= $team['tag'] ? '#' . htmlspecialchars($team['tag']) : '' ?>
                                            <?= $isMyTeam ? ' · <span style="color:var(--highlight)">Your team</span>' : '' ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ─── Tab: Rules ────────────────────────────── -->
                <div id="tab-rules" class="tab-pane">
                    <div class="rules-box">
                        <h3>Tournament Rules</h3>

                        <!-- Static rules -->
                        <div class="rule-item">
                            <div class="rule-key">Format</div>
                            <div class="rule-val">Single Elimination — losing team is eliminated</div>
                        </div>
                        <div class="rule-item">
                            <div class="rule-key">Match format</div>
                            <div class="rule-val">Bo3 (Best of 3), Final Bo5</div>
                        </div>
                        <div class="rule-item">
                            <div class="rule-key">Team size</div>
                            <div class="rule-val">5 active players + 1 substitute (optional)</div>
                        </div>
                        <?php if (!empty($tournament['checkin_minutes'])): ?>
                        <div class="rule-item">
                            <div class="rule-key">Check-in time</div>
                            <div class="rule-val">Mandatory <?= (int)$tournament['checkin_minutes'] ?> minutes before the match</div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($tournament['noshow_minutes'])): ?>
                        <div class="rule-item">
                            <div class="rule-key">No-show rule</div>
                            <div class="rule-val">Wait <?= (int)$tournament['noshow_minutes'] ?> minutes, absent team gets eliminated</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($prize1st > 0): ?>
                        <div class="rule-item">
                            <div class="rule-key">Prize distribution</div>
                            <div class="rule-val">
                                1st ₺<?= number_format($prize1st, 0, ',', '.') ?>
                                &nbsp;·&nbsp; 2nd ₺<?= number_format($prize2nd, 0, ',', '.') ?>
                                <?= $prize3rd > 0 ? ' &nbsp;·&nbsp; 3rd–4th ₺' . number_format($prize3rd, 0, ',', '.') : '' ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="rule-item">
                            <div class="rule-key">Appeal process</div>
                            <div class="rule-val">Report to the organizer within 30 minutes after the match</div>
                        </div>

                        <!-- Rules from DB -->
                        <?php foreach ($rules as $i => $rule): ?>
                        <div class="rule-item">
                            <div class="rule-key">Rule <?= $i + 1 ?></div>
                            <div class="rule-val"><?= htmlspecialchars($rule) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div><!-- /.content -->
        </div><!-- /.tournaments-wrapper -->
    </main>

    <?php require_once '../includes/footer.php' ?>

    <!-- jQuery + jquery-bracket -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://unpkg.com/jquery-bracket@0.11.1/dist/jquery.bracket.min.js"></script>

    <!-- Pass PHP data to JavaScript globally -->
    <script>
        <?php if (!empty($bracketTeams)): ?>
            window.bracketData = <?= $bracketData ?>;
            window.stageLabels = <?= json_encode($stageLabels) ?>;
        <?php else: ?>
            window.bracketData = null;
            window.stageLabels = [];
        <?php endif; ?>
    </script>

    <!-- Tournament Details Specific JS -->
    <script src="../assets/js/tournaments-details.js"></script>
</body>
</html>
</body>
</html>