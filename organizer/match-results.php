<?php
    require_once __DIR__ . '/guard.php';
    require_once __DIR__ . '/../includes/db.php'; // Veritabanı bağlantısı eklendi

    $orgId = $_SESSION['user_id'];
    $errors = [];
    $success = '';

    // Sonuç kaydetme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['match_id'])) {
        $matchId = (int)$_POST['match_id'];
        $score1 = isset($_POST['score1']) && $_POST['score1'] !== '' ? (int)$_POST['score1'] : null;
        $score2 = isset($_POST['score2']) && $_POST['score2'] !== '' ? (int)$_POST['score2'] : null;

        if ($score1 === null || $score2 === null) {
            $errors[] = 'Both score must be entered.';
        } else if ($score1 < 0 || $score2 < 0) {
            $errors[] = 'Score cant be negative.';
        } else if ($score1 === $score2) {
            $errors[] = 'Cant be tie - there must be a winner.';
        } else {
            try {
                // Macin bu organizatore ait oldugunu dogrulama
                $check = $pdo->prepare("
                    SELECT m.id, m.team1_id, m.team2_id FROM Matches m 
                    JOIN Tournament t ON t.id = m.tournament_id
                    WHERE m.id = ? AND t.organizer_id = ?
                ");
                $check->execute([$matchId, $orgId]);
                $matchData = $check->fetch(PDO::FETCH_ASSOC);
                
                if (!$matchData) {
                    $errors[] = 'You dont have permission on this match.';
                } else {
                    // Kazanan takımı belirleme
                    $winnerId = ($score1 > $score2) ? $matchData['team1_id'] : $matchData['team2_id'];

                    $pdo->prepare("
                        UPDATE Matches 
                        SET score_team1 = ?, score_team2 = ?, winner_id = ?
                        WHERE id = ?
                    ")->execute([$score1, $score2, $winnerId, $matchId]);

                    $success = 'Match result saved.';
                }
            } catch (Exception $e) {
                // Kayıt hatasını yakala
                $errors[] = 'Save error: ' . $e->getMessage();
            }
        }
    }

    // Gelen filtre id'si var mı kontrol et
    $filterTournamentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    // Bekleyen maclari cek
    $grouped = [];
    try {
        $query = "
            SELECT m.id, m.stage, m.date, m.score_team1, m.score_team2, m.winner_id,
                    t1.name AS home_team, t2.name AS away_team,
                    tour.name AS tournament_name, tour.id AS tournament_id
            FROM Matches m
            JOIN Team t1 ON t1.id = m.team1_id
            JOIN Team t2 ON t2.id = m.team2_id
            JOIN Tournament tour ON tour.id = m.tournament_id
            WHERE tour.organizer_id = ? 
                AND tour.deleted_at IS NULL
        ";
        
        $params = [$orgId];

        // Eğer belirli bir turnuva için filtreleme yapılıyorsa
        if ($filterTournamentId) {
            $query .= " AND tour.id = ?";
            $params[] = $filterTournamentId;
        }

        $query .= " ORDER BY m.winner_id IS NOT NULL, tour.name, m.stage, m.date";

        $stmtPending = $pdo->prepare($query);
        $stmtPending->execute($params);
        $matches = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

        foreach ($matches as $m) {
            $grouped[$m['tournament_name']][] = $m;
        }
    } catch (Exception $e) {
        $errors[] = 'Query Error: ' . $e->getMessage();
        $grouped = [];
    }

    $pageTitle = 'Match Results';
    $pageSubtitle = 'Enter pending match results';

    require_once __DIR__ . '/layout-top.php';
?>

<!-- Hata Mesajları -->
<?php if(!empty($errors)): ?>
    <div class="op-alert op-alert--error animate-in" style="font-family:monospace; margin-bottom:16px; --delay:50ms;">
        <?php foreach($errors as $e): ?>
            <div>• <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if($success): ?>
    <div class="op-alert op-alert--success animate-in" style="--delay:50ms;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if(empty($grouped)): ?>
    <div class="op-empty op-card animate-in" style="--delay:100ms;">
        There is no pending match result.All matches are up-to date!
    </div>
<?php else: ?>
    <?php 
    $baseDelay = 100;
    foreach($grouped as $tournamentName => $tMatches): 
    ?>
        <div class="op-card animate-in" style="margin-bottom: 16px; --delay:<?= $baseDelay ?>ms;">
            <div class="op-card-head">
                <span class="op-card-title"><?= htmlspecialchars($tournamentName) ?></span>
                <span class="op-td-muted" style="font-size: 12px;">
                    <?= count(array_filter($tMatches, fn($m) => $m['score_team1'] === null)) ?> waiting
                </span>
            </div>

            <div class="op-match-list">
                <?php foreach($tMatches as $match):
                    $isDone = !empty($match['winner_id']);
                ?>
                    <div class="op-match-row" <?= $isDone ? 'op-match-row--done' : '' ?>>
                        <div class="op-match-stage"><?= htmlspecialchars($match['stage'] ?? 'Match') ?></div>

                        <div class="op-match-teams">
                            <span class="op-match-team"><?= htmlspecialchars($match['home_team']) ?></span>
                            <span class="op-match-vs">vs</span>
                            <span class="op-match-team"><?= htmlspecialchars($match['away_team']) ?></span>
                        </div>

                        <?php if($isDone): ?>
                            <div class="op-match-result">
                                <span class="op-score <?= $match['score_team1'] > $match['score_team2'] ? 'op-score--win' : '' ?>">
                                    <?= $match['score_team2'] ?>
                                </span>
                            </div>
                            <form method="post" style="display: contents;">
                                <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                                <input class="op-score-input" type="number" name="score1"
                                        value="<?= $match['score_team1'] ?>" min="0" max="99">
                                <span class="op-score-sep">-</span>
                                <input class="op-score-input" type="number" name="score2"
                                        value="<?= $match['score_team2'] ?>" min="0" max="99">
                                <button class="op-btn-sm op-btn-sm--accent" type="submit">Update</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: contents;">
                                <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                                <input class="op-score-input" type="number" name="score1"
                                        placeholder="0" min="0" max="99">
                                <span class="op-score-sep">-</span>
                                <input class="op-score-input" type="number" name="score2"
                                        placeholder="0" min="0" max="99">
                                <button class="op-btn-sm op-btn-sm--primary" type="submit">Save</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php 
    $baseDelay += 50;
    endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/layout-bottom.php' ?>