<?php
$pageTitle  = 'Tableau de bord';
$activePage = 'dashboard';
$root       = '';
require_once 'includes/header.php';

$db = getDB();

// ============================================================
// DONNEES DASHBOARD
// ============================================================

// Stats du jour
$today = date('Y-m-d');
$stmtToday = $db->prepare("
    SELECT 
        COUNT(c.id) AS nb_colis,
        COALESCE(SUM(c.poids), 0) AS total_kilos,
        COALESCE(SUM(c.montant), 0) AS total_montant
    FROM colis c
    JOIN arrivages a ON c.arrivage_id = a.id
    WHERE a.date_arrivee = ?
");
$stmtToday->execute([$today]);
$statsToday = $stmtToday->fetch();

// Total dettes impayées
$stmtDettes = $db->query("SELECT COALESCE(SUM(solde), 0) AS total_dettes FROM colis_proprietaires WHERE statut != 'paye'");
$totalDettes = $stmtDettes->fetchColumn();

// Dettes du mois courant
$stmtDetteMois = $db->prepare("
    SELECT COALESCE(SUM(cp.solde), 0) AS dettes_mois
    FROM colis_proprietaires cp
    JOIN colis c ON cp.colis_id = c.id
    JOIN arrivages a ON c.arrivage_id = a.id
    WHERE MONTH(a.date_arrivee) = MONTH(CURDATE()) AND YEAR(a.date_arrivee) = YEAR(CURDATE())
    AND cp.statut != 'paye'
");
$stmtDetteMois->execute();
$dettesMois = $stmtDetteMois->fetchColumn();

// Nb arrivages du mois
$stmtArrivMois = $db->query("SELECT COUNT(*) FROM arrivages WHERE MONTH(date_arrivee) = MONTH(CURDATE()) AND YEAR(date_arrivee) = YEAR(CURDATE())");
$nbArrivagesMois = $stmtArrivMois->fetchColumn();

// Montant encaissé du mois
$stmtEncMois = $db->query("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE MONTH(date_paiement) = MONTH(CURDATE()) AND YEAR(date_paiement) = YEAR(CURDATE())");
$encaisseMois = $stmtEncMois->fetchColumn();

// Données graphique: colis par jour (7 derniers jours)
$stmtGraph = $db->query("
    SELECT DATE(a.date_arrivee) as jour, COUNT(c.id) as nb
    FROM colis c
    JOIN arrivages a ON c.arrivage_id = a.id
    WHERE a.date_arrivee >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(a.date_arrivee)
    ORDER BY jour ASC
");
$graphColis = $stmtGraph->fetchAll();

// Données graphique: revenus par jour (7 derniers jours)
$stmtRevenu = $db->query("
    SELECT DATE(date_paiement) as jour, COALESCE(SUM(montant), 0) as total
    FROM paiements
    WHERE date_paiement >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(date_paiement)
    ORDER BY jour ASC
");
$graphRevenu = $stmtRevenu->fetchAll();

// Répartition statuts
$stmtStatut = $db->query("
    SELECT statut, COUNT(*) as nb, COALESCE(SUM(montant_du), 0) as montant
    FROM colis_proprietaires
    GROUP BY statut
");
$repartition = $stmtStatut->fetchAll();
$repartData = ['paye' => 0, 'partiel' => 0, 'non_paye' => 0];
foreach ($repartition as $r) {
    $repartData[$r['statut']] = (int)$r['nb'];
}

// Derniers arrivages
$stmtArr = $db->query("
    SELECT a.*, t.nom as transitaire_nom
    FROM arrivages a
    JOIN transitaires t ON a.transitaire_id = t.id
    ORDER BY a.date_arrivee DESC LIMIT 5
");
$derniersArrivages = $stmtArr->fetchAll();

// Dettes récentes non payées
$stmtDR = $db->query("
    SELECT cp.*, cl.nom as client_nom, c.code_complet,
           a.date_arrivee
    FROM colis_proprietaires cp
    JOIN clients cl ON cp.client_id = cl.id
    JOIN colis c ON cp.colis_id = c.id
    JOIN arrivages a ON c.arrivage_id = a.id
    WHERE cp.statut != 'paye'
    ORDER BY a.date_arrivee DESC LIMIT 6
");
$dettesRecentes = $stmtDR->fetchAll();

// Préparer données JSON graphiques
$joursColisLabels = array_map(fn($r) => date('d/m', strtotime($r['jour'])), $graphColis);
$nbColisValues    = array_map(fn($r) => (int)$r['nb'], $graphColis);

$joursRevenuLabels = array_map(fn($r) => date('d/m', strtotime($r['jour'])), $graphRevenu);
$revenuValues      = array_map(fn($r) => (float)$r['total'], $graphRevenu);
?>
<div class="page-content">

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= (int)$statsToday['nb_colis'] ?></div>
                <div class="stat-label">Colis aujourd'hui</div>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon"><i class="fas fa-weight-hanging"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format((float)$statsToday['total_kilos'], 1) ?> kg</div>
                <div class="stat-label">Kilos aujourd'hui</div>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format((float)$statsToday['total_montant'], 0, ',', ' ') ?></div>
                <div class="stat-label">Montant aujourd'hui (FCFA)</div>
            </div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format((float)$totalDettes, 0, ',', ' ') ?></div>
                <div class="stat-label">Dettes totales (FCFA)</div>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-truck-loading"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= (int)$nbArrivagesMois ?></div>
                <div class="stat-label">Arrivages ce mois</div>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format((float)$encaisseMois, 0, ',', ' ') ?></div>
                <div class="stat-label">Encaissé ce mois (FCFA)</div>
            </div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="charts-grid">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-chart-bar"></i> Colis / jour (7 derniers jours)</div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartColis"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-chart-line"></i> Revenus / jour (7 derniers jours)</div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartRevenu"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-chart-pie"></i> Statut des paiements</div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartStatut"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- GRID 2 COL -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

        <!-- Derniers arrivages -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-truck-loading"></i> Derniers arrivages</div>
                <a href="pages/arrivages.php" class="btn btn-outline btn-sm">Voir tout</a>
            </div>
            <div class="card-body" style="padding:0">
                <?php if ($derniersArrivages): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transitaire</th>
                                <th>Colis</th>
                                <th>Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($derniersArrivages as $arr): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($arr['date_arrivee'])) ?></td>
                                <td><?= sanitize($arr['transitaire_nom']) ?></td>
                                <td><span class="badge badge-primary"><?= $arr['nb_colis_total'] ?></span></td>
                                <td><?= number_format((float)$arr['cout_total'], 0, ',', ' ') ?> <?= $arr['devise'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-truck-loading"></i>
                    <p>Aucun arrivage enregistré</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dettes récentes -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-exclamation-circle"></i> Dettes en cours</div>
                <a href="pages/dettes.php" class="btn btn-outline btn-sm">Voir tout</a>
            </div>
            <div class="card-body" style="padding:0">
                <?php if ($dettesRecentes): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Colis</th>
                                <th>Solde</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dettesRecentes as $d): ?>
                            <tr>
                                <td><?= sanitize($d['client_nom']) ?></td>
                                <td><span class="colis-code"><?= sanitize($d['code_complet']) ?></span></td>
                                <td style="font-weight:700;color:var(--danger)">
                                    <?= number_format((float)$d['solde'], 0, ',', ' ') ?>
                                </td>
                                <td>
                                    <?php if ($d['statut'] === 'partiel'): ?>
                                        <span class="badge badge-warning">Partiel</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Non payé</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>Aucune dette en cours</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
(function() {
    const colisLabels  = <?= json_encode($joursColisLabels) ?>;
    const colisValues  = <?= json_encode($nbColisValues) ?>;
    const revenuLabels = <?= json_encode($joursRevenuLabels) ?>;
    const revenuValues = <?= json_encode($revenuValues) ?>;

    // Chart Colis
    new Chart(document.getElementById('chartColis'), {
        type: 'bar',
        data: {
            labels: colisLabels,
            datasets: [{
                label: 'Nombre de colis',
                data: colisValues,
                backgroundColor: 'rgba(59,130,246,.7)',
                borderColor: '#1e40af',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    // Chart Revenus
    new Chart(document.getElementById('chartRevenu'), {
        type: 'line',
        data: {
            labels: revenuLabels,
            datasets: [{
                label: 'Revenus (FCFA)',
                data: revenuValues,
                backgroundColor: 'rgba(22,163,74,.1)',
                borderColor: '#16a34a',
                borderWidth: 2,
                tension: .3,
                fill: true,
                pointBackgroundColor: '#16a34a'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    // Chart Statut
    new Chart(document.getElementById('chartStatut'), {
        type: 'doughnut',
        data: {
            labels: ['Payé', 'Partiel', 'Non payé'],
            datasets: [{
                data: [
                    <?= (int)$repartData['paye'] ?>,
                    <?= (int)$repartData['partiel'] ?>,
                    <?= (int)$repartData['non_paye'] ?>
                ],
                backgroundColor: ['#16a34a','#d97706','#dc2626'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
