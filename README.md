# Gestion Import/Export - Documentation

## Prérequis

- PHP 8.0+
- MySQL 5.7+ ou MariaDB 10.3+
- Serveur web : Apache (XAMPP / WAMP / Laragon) ou Nginx

## Installation en local

### 1. Copier les fichiers
Placez le dossier `projet-import-export/` dans votre répertoire web :
- XAMPP/WAMP : `htdocs/import-export/`
- Laragon : `www/import-export/`

### 2. Créer la base de données
1. Ouvrez **phpMyAdmin** (http://localhost/phpmyadmin)
2. Créez une base de données nommée `import_export`
3. Importez le fichier `database.sql` :
   - Onglet "Importer" > Choisir le fichier `database.sql` > Exécuter

### 3. Configurer la connexion
Ouvrez `includes/config.php` et modifiez si nécessaire :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'import_export');
define('DB_USER', 'root');      // Votre utilisateur MySQL
define('DB_PASS', '');          // Votre mot de passe MySQL
```

### 4. Accéder à l'application
Ouvrez votre navigateur : http://localhost/import-export/

---

## Structure du projet

```
projet-import-export/
├── index.php              # Tableau de bord
├── database.sql           # Script SQL complet (tables + données de test)
├── includes/
│   ├── config.php         # Configuration BDD + fonctions utilitaires
│   ├── header.php         # En-tête HTML + navigation
│   └── footer.php         # Pied de page + scripts
├── css/
│   └── style.css          # Feuille de style principale
├── js/
│   └── app.js             # JavaScript global
├── pages/
│   ├── arrivages.php      # Gestion des arrivages
│   ├── colis.php          # Gestion des colis
│   ├── clients.php        # Gestion des clients
│   ├── transitaires.php   # Gestion des transitaires
│   ├── paiements.php      # Paiements et historique
│   ├── bilan.php          # Bilan journalier + export PDF
│   ├── dettes.php         # Suivi des dettes
│   └── rapports.php       # Rapports & exports CSV/PDF
└── api/
    ├── proprietaires.php  # API : liste propriétaires d'un colis
    ├── clients_search.php # API : recherche client en temps réel
    └── colis_info.php     # API : info colis par code
```

---

## Fonctionnalités

### Tableau de bord
- Stats du jour : colis, kilos, montant, dettes
- Graphiques : colis/jour, revenus/jour, statut paiements
- Derniers arrivages et dettes en cours

### Arrivages (CRUD complet)
- Date, transitaire, nombre de colis, poids, coût
- Export CSV

### Colis
- Code automatique : `CODE_JJMMAA` (ex: A109_290326)
- Types : Individuel ou Mixte
- Colis mixte : plusieurs propriétaires avec poids et montant propres
- Export CSV

### Clients & Transitaires
- CRUD complet
- Historique des paiements par client

### Paiements
- Enregistrement paiements partiels ou complets
- Modes : Espèce, Virement, Mobile Money, Chèque
- Génération de reçu PDF (jsPDF)

### Bilan journalier
- Récapitulatif complet par date
- Export PDF professionnel avec Chart.js

### Rapports
- Filtre par période, client, type de colis
- Export CSV et PDF

---

## Technologies utilisées

| Technologie | Usage |
|-------------|-------|
| PHP 8.x (PDO) | Backend / requêtes sécurisées |
| MySQL | Base de données |
| HTML5 + CSS3 | Interface |
| JavaScript ES6+ | Interactions |
| Chart.js 4.x | Graphiques |
| jsPDF + autoTable | Export PDF |
| Font Awesome 6 | Icônes SVG |

---

## Sécurité

- PDO avec requêtes préparées (protection injection SQL)
- Sanitisation XSS de toutes les sorties
- Aucune donnée utilisateur non filtrée n'est affichée brute

---

## Données de test

Le script SQL inclut des données de test :
- 3 transitaires
- 6 clients
- 3 arrivages
- 9 colis (individuels et mixtes)
- Des propriétaires avec différents statuts de paiement
