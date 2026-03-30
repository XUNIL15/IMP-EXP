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
3. Importez le fichier `database.sql`

### 3. Configurer la connexion
Modifiez `includes/config.php`

### 4. Accéder à l'application
http://localhost/import-export/