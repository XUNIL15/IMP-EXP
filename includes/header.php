<?php
require_once __DIR__ . '/config.php';
requireAuth();
$currentUser = getCurrentUser();
$logoutUrl   = (isset($root) && $root === '../') ? '../logout.php' : 'logout.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' - ' : '' ?><?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= $root ?? '' ?>css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-ship"></i>
            <span class="brand-text">Import<strong>Export</strong></span>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li class="nav-section-label">Principal</li>
                <li>
                    <a href="<?= $root ?? '' ?>index.php" class="<?= (isset($activePage) && $activePage === 'dashboard') ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>Tableau de bord</span>
                    </a>
                </li>
                <li class="nav-section-label">Opérations</li>
                <li>
                    <a href="<?= $root ?? '' ?>pages/arrivages.php" class="<?= (isset($activePage) && $activePage === 'arrivages') ? 'active' : '' ?>">
                        <i class="fas fa-truck-loading"></i>
                        <span>Arrivages</span>
                    </a>
                </li>
                <li>
                    <a href="<?= $root ?? '' ?>pages/colis.php" class="<?= (isset($activePage) && $activePage === 'colis') ? 'active' : '' ?>">
                        <i class="fas fa-boxes"></i>
                        <span>Colis</span>
                    </a>
                </li>
                <li>
                    <a href="<?= $root ?? '' ?>pages/paiements.php" class="<?= (isset($activePage) && $activePage === 'paiements') ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Paiements</span>
                    </a>
                </li>
                <li class="nav-section-label">Gestion</li>
                <li>
                    <a href="<?= $root ?? '' ?>pages/clients.php" class="<?= (isset($activePage) && $activePage === 'clients') ? 'active' : '' ?>">
                        <i class="fas fa-users"></i>
                        <span>Clients</span>
                    </a>
                </li>
                <li>
                    <a href="<?= $root ?? '' ?>pages/transitaires.php" class="<?= (isset($activePage) && $activePage === 'transitaires') ? 'active' : '' ?>">
                        <i class="fas fa-handshake"></i>
                        <span>Transitaires</span>
                    </a>
                </li>
                <li class="nav-section-label">Rapports</li>
                <li>
                    <a href="<?= $root ?? '' ?>pages/bilan.php" class="<?= (isset($activePage) && $activePage === 'bilan') ? 'active' : '' ?>">
                        <i class="fas fa-file-invoice"></i>
                        <span>Bilan journalier</span>
                    </a>
                </li>
                <li>
                    <a href="<?= $root ?? '' ?>pages/dettes.php" class="<?= (isset($activePage) && $activePage === 'dettes') ? 'active' : '' ?>">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Dettes</span>
                    </a>
                </li>
                <li>
                    <a href="<?= $root ?? '' ?>pages/rapports.php" class="<?= (isset($activePage) && $activePage === 'rapports') ? 'active' : '' ?>">
                        <i class="fas fa-file-export"></i>
                        <span>Rapports & Export</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <span><i class="fas fa-code-branch"></i> v<?= APP_VERSION ?></span>
        </div>
    </aside>

    <!-- TOPBAR -->
    <header class="topbar">
        <button class="topbar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-title">
            <?= isset($pageTitle) ? sanitize($pageTitle) : 'Tableau de bord' ?>
        </div>
        <div class="topbar-right">
            <span class="topbar-date">
                <i class="fas fa-calendar-day"></i>
                <?= date('d/m/Y') ?>
            </span>
            <div class="topbar-user">
                <i class="fas fa-user-circle"></i>
                <span><?= sanitize($currentUser['nom']) ?></span>
            </div>
            <a href="<?= $logoutUrl ?>" class="btn-logout" title="Se déconnecter">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="main-content">
