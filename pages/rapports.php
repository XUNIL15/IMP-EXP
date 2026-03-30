<?php
$pageTitle  = 'Rapports & Export';
$activePage = 'rapports';
$root       = '../';
require_once '../includes/header.php';

$db = getDB();

// FILTRES
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin   = $_GET['date_fin'] ?? date('Y-m-d');
$filterClient = (int)($_GET['client_id'] ?? 0);
$filterType   = $_GET['type'] ?? '';

// RESUME PERIODE
$stmtResume = $db->prepare("
    SELECT
        COUNT(DISTINCT a.id) as nb_arrivages,
        COUNT(c.id) as nb_colis,
        COALESCE(SUM(c.poids), 0) as total_kilos,
        COALESCE(SUM(c.montant), 0) as total_montant
    FROM colis c
    JOIN arrivages a ON c.arrivage_id = a.id
    WHERE a.date_arrivee BETWEEN ? AND ?
");
$stmtResume->execute([$dateDebut, $dateFin]);
$resume = $stmtResume->fetch();

$stmtEnc = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE date_paiement BETWEEN ? AND ?");
$stmtEnc->execute([$dateDebut, $dateFin]);
$totalEnc = (float)$stmtEnc->fetchColumn();

$stmtDette = $db->prepare("
    SELECT COALESCE(SUM(cp.solde), 0)
    FROM colis_proprietaires cp
    JOIN colis c ON cp.colis_id = c.id
    JOIN arrivages a ON c.arrivage_id = a.id
    WHERE a.date_arrivee BETWEEN ? AND ? AND cp.statut != 'paye'
");
$stmtDette->execute([$dateDebut, $dateFin]);
$totalDette = (float)$stmtDette->fetchColumn();

// DONNÉES DÉTAILLÉES
$where  = 'a.date_arrivee BETWEEN ? AND ?';
$params = [$dateDebut, $dateFin];
if ($filterClient) { $where .= ' AND cp.client_id=?'; $params[] = $filterClient; }
if ($filterType)   { $where .= ' AND c.type=?'; $params[] = $filterType; }

$stmtData = $db->prepare("
    SELECT cp.*, cl.nom as client_nom, cl.telephone, c.code_complet, c.type as type_colis,
           c.poids as poids_colis, a.date_arrivee, t.nom as transitaire_nom
    FROM colis_proprietaires cp
    JOIN clients cl ON cp.client_id = cl.id
    JOIN colis c ON cp.colis_id = c.id
    JOIN arrivages a ON c.arrivage_id = a.id
    JOIN transitaires t ON a.transitaire_id = t.id
    WHERE $where
    ORDER BY a.date_arrivee DESC, cl.nom
");
$stmtData->execute($params);
$rapportData = $stmtData->fetchAll();

$clients = $db->query("SELECT id, nom FROM clients ORDER BY nom")->fetchAll();

// DONNEES GRAPHIQUE MENSUEL
$stmtMensuel = $db->prepare("
    SELECT DATE_FORMAT(a.date_arrivee, '%Y-%m') as mois,
           COUNT(c.id) as nb_colis,
           COALESCE(SUM(c.montant), 0) as total
    FROM colis c
    JOIN arrivages a ON c.arrivage_id = a.id
    WHERE a.date_arrivee >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(a.date_arrivee, '%Y-%m')
    ORDER BY mois ASC
");
$stmtMensuel->execute();
$mensuel = $stmtMensuel->fetchAll();

$moisLabels  = array_map(fn($r) => $r['mois'], $mensuel);
$moisColis   = array_map(fn($r) => (int)$r['nb_colis'], $mensuel);
$moisMontant = array_map(fn($r) => (float)$r['total'], $mensuel);
?>
<div class="page-content">

    <!-- FILTRES PERIODE -->
    <div class="card">
        <div class="card-body">
            <form method="get" class="filters-bar">
                <label style="font-weight:600"><i class="fas fa-calendar-alt"></i> Période :</label>
                <input type="date" name="date_debut" value="<?= $dateDebut ?>" class="form-control" style="width:155px">
                <span style="color:var(--gray-500)">au</span>
                <input type="date" name="date_fin" value="<?= $dateFin ?>" class="form-control" style="width:155px">
                <select name="client_id" class="form-control" style="width:180px">
                    <option value="">Tous les clients</option>
                    <?php foreach ($clients as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= $filterClient == $cl['id'] ? 'selected' : '' ?>>
                            <?= sanitize($cl['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="type" class="form-control">
                    <option value="">Tous types</option>
                    <option value="individuel" <?= $filterType === 'individuel' ? 'selected' : '' ?>>Individuel</option>
                    <option value="mixte" <?= $filterType === 'mixte' ? 'selected' : '' ?>>Mixte</option>
                </select>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Générer</button>
                <a href="rapports.php" class="btn btn-outline"><i class="fas fa-times"></i></a>
            </form>
        </div>
    </div>

    <!-- KPI PERIODE -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-truck-loading"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= (int)$resume['nb_arrivages'] ?></div>
                <div class="stat-label">Arrivages</div>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= (int)$resume['nb_colis'] ?></div>
                <div class="stat-label">Colis</div>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-weight-hanging"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format((float)$resume['total_kilos'], 1) ?> kg</div>
                <div class="stat-label">Kilos</div>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format((float)$resume['total_montant'], 0, ',', ' ') ?></div>
                <div class="stat-label">Montant total (FCFA)</div>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalEnc, 0, ',', ' ') ?></div>
                <div class="stat-label">Encaissé (FCFA)</div>
            </div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalDette, 0, ',', ' ') ?></div>
                <div class="stat-label">Dettes (FCFA)</div>
            </div>
        </div>
    </div>

    <!-- GRAPHIQUE MENSUEL -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-bar"></i> Evolution mensuelle (6 derniers mois)</div>
        </div>
        <div class="card-body">
            <div class="chart-container" style="height:280px">
                <canvas id="chartMensuel"></canvas>
            </div>
        </div>
    </div>

    <!-- TABLEAU DETAILLE -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-table"></i> Rapport détaillé</div>
            <div style="display:flex;gap:8px">
                <button class="btn btn-outline btn-sm" onclick="exportTableToCSV('tableRapport','rapport_periode')">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button class="btn btn-danger btn-sm" onclick="exportRapportPDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-responsive">
                <table id="tableRapport">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Téléphone</th>
                            <th>Colis</th>
                            <th>Type</th>
                            <th>Transitaire</th>
                            <th>Poids (kg)</th>
                            <th>Montant dû</th>
                            <th>Payé</th>
                            <th>Solde</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rapportData): ?>
                            <?php foreach ($rapportData as $r): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($r['date_arrivee'])) ?></td>
                                <td><?= sanitize($r['client_nom']) ?></td>
                                <td><?= sanitize($r['telephone'] ?: '-') ?></td>
                                <td><span class="colis-code"><?= sanitize($r['code_complet']) ?></span></td>
                                <td><?= ucfirst($r['type_colis']) ?></td>
                                <td><?= sanitize($r['transitaire_nom']) ?></td>
                                <td><?= number_format((float)$r['poids'], 2) ?></td>
                                <td><?= number_format((float)$r['montant_du'], 0, ',', ' ') ?> FCFA</td>
                                <td style="color:var(--success)"><?= number_format((float)$r['montant_paye'], 0, ',', ' ') ?> FCFA</td>
                                <td style="color:<?= (float)$r['solde'] > 0 ? 'var(--danger)' : 'var(--success)' ?>;font-weight:700">
                                    <?= number_format((float)$r['solde'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td>
                                    <?php
                                    $badges = ['paye' => 'badge-success', 'partiel' => 'badge-warning', 'non_paye' => 'badge-danger'];
                                    $labels = ['paye' => 'Payé', 'partiel' => 'Partiel', 'non_paye' => 'Non payé'];
                                    ?>
                                    <span class="badge <?= $badges[$r['statut']] ?? 'badge-gray' ?>">
                                        <?= $labels[$r['statut']] ?? $r['statut'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="11">
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <h3>Aucune donnée pour cette période</h3>
                                </div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Graphique mensuel
new Chart(document.getElementById('chartMensuel'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($moisLabels) ?>,
        datasets: [
            {
                label: 'Colis',
                data: <?= json_encode($moisColis) ?>,
                backgroundColor: 'rgba(59,130,246,.7)',
                borderColor: '#1e40af',
                borderWidth: 1,
                borderRadius: 4,
                yAxisID: 'y'
            },
            {
                label: 'Montant (FCFA)',
                data: <?= json_encode($moisMontant) ?>,
                type: 'line',
                borderColor: '#16a34a',
                backgroundColor: 'rgba(22,163,74,.1)',
                borderWidth: 2,
                fill: true,
                tension: .3,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y:  { beginAtZero: true, position: 'left' },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
        }
    }
});

function exportRapportPDF() {
    const periode = '<?= date('d/m/Y', strtotime($dateDebut)) ?>' + ' au ' + '<?= date('d/m/Y', strtotime($dateFin)) ?>';

    const kpis = [
        { label: 'Periode', value: periode },
        { label: 'Total arrivages', value: '<?= (int)$resume['nb_arrivages'] ?>' },
        { label: 'Total colis', value: '<?= (int)$resume['nb_colis'] ?>' },
        { label: 'Total kilos', value: '<?= number_format((float)$resume['total_kilos'], 2) ?> kg' },
        { label: 'Total montant', value: '<?= number_format((float)$resume['total_montant'], 0, ',', ' ') ?> FCFA' },
        { label: 'Total encaisse', value: '<?= number_format($totalEnc, 0, ',', ' ') ?> FCFA' },
        { label: 'Total dettes', value: '<?= number_format($totalDette, 0, ',', ' ') ?> FCFA' }
    ];

    const rows = [];
    document.querySelectorAll('#tableRapport tbody tr').forEach(function(tr) {
        const cells = tr.querySelectorAll('td');
        if (cells.length > 1) {
            rows.push(Array.from(cells).map(td => td.textContent.trim()));
        }
    });

    exportBilanPDF({
        date: periode,
        title: 'Rapport periode ' + periode,
        entete: 'Gestion Import/Export',
        kpis: kpis,
        tables: [{
            title: 'Rapport detaille',
            headers: ['Date','Client','Tel','Colis','Type','Transitaire','Poids','Du','Paye','Solde','Statut'],
            rows: rows
        }]
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
