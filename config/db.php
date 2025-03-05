<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env file
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    die('Error: .env file not found. Ensure it exists in the config directory.');
}

$jwt_secret_key = $_ENV['JWT_SECRET_KEY'] ?? 'defaultFallbackKey';

$host = 'localhost';
$db_name = 'kasba';
$username = 'root';
$password = '';

try {
    // Connect to MySQL without selecting a database
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if the database exists and create it if not
    $db_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db_name'")->fetchColumn();
    if (!$db_exists) {
        $conn->exec("CREATE DATABASE $db_name");
        echo "Database created successfully.<br>";
    }

    // Select the database
    $conn->exec("USE $db_name");

    // Helper function to add columns automatically if they do not exist
    function ensureColumnExists($conn, $db_name, $table, $column, $definition)
    {
        $column_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'")->fetchColumn();
        if (!$column_exists) {
            $conn->exec("ALTER TABLE $table ADD $column $definition");
            echo "Added column '$column' to '$table' table.<br>";
        }
    }

    // Ensure `users` table with columns
    $table_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'users'")->fetchColumn();
    if (!$table_exists) {
        $conn->exec("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            user_type ENUM('admin') NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            -- created_at DATETIME NOT NULL,
            -- updated_at DATETIME NOT NULL
        )");

        echo "Users table created.<br>";
    }
    // --------------------------------------------
    // Vérifier si la table 'services' existe déjà
    $table_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'services'")->fetchColumn();
    if (!$table_exists) {
        // Créer la table 'services' si elle n'existe pas
        $conn->exec("CREATE TABLE services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
        echo "Services table created.<br>";
    }
    // --------------------------------------------
    // // Ensure `testimonies` table
    $table_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'testimonies'")->fetchColumn();
    if (!$table_exists) {
        $conn->exec("CREATE TABLE testimonies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            location VARCHAR(255) NOT NULL,
            image_url TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "Testimonies table created.<br>";
    }
    // --------------------------------------------
    // // Check and create blog table
    // $table_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'blog'")->fetchColumn();
    // if (!$table_exists) {
    //     $conn->exec("CREATE TABLE blog (
    //     id INT AUTO_INCREMENT PRIMARY KEY,
    //     title VARCHAR(255) NOT NULL,
    //     content TEXT NOT NULL,
    //     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    //     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    // )");
    //     echo "Blog table created.<br>";
    // }
    // ensureColumnExists($conn, $db_name, 'blog', 'image_url', 'TEXT NULL');
    // --------------------------------------------
    // // table events
    $table_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'events'")->fetchColumn();
    if (!$table_exists) {
        // Create the 'events' table
        $conn->exec("CREATE TABLE events (
           id INT AUTO_INCREMENT PRIMARY KEY,
           title VARCHAR(255) NOT NULL,
           description TEXT NOT NULL,
           event_date DATE NOT NULL,
           cover_image VARCHAR(255) DEFAULT NULL,
           created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
           updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
       )");
        echo "Table 'events' created successfully.<br>";
    }
    // // Check if the 'event_media' table exists
    $table_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'event_media'")->fetchColumn();
    if (!$table_exists) {
        // Create the 'event_media' table
        $conn->exec("CREATE TABLE event_media (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            file_path TEXT NOT NULL,
            file_type ENUM('photo', 'video') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )");
        echo "Table 'event_media' created successfully.<br>";
    }
    // --------------------------------------------
    // // Check if the 'contact' table exists
    $table_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'contact'")->fetchColumn();
    if (!$table_exists) {
        // Create the 'contact' table
        $conn->exec("CREATE TABLE contact (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
        echo "Table 'contact' created successfully.<br>";
    }
    // --------------------------------------------
    // // Vérifier si la table `reservations` existe
    $table_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'reservations'")->fetchColumn();
    if (!$table_exists) {
        // Créer la table `reservations`
        $conn->exec("CREATE TABLE reservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(15) NOT NULL, -- Numéro de téléphone
            service_type VARCHAR(255) NOT NULL, -- Type de service
            reservation_date DATETIME NOT NULL, -- Date et heure de la réservation
            status ENUM('pending', 'confirmed', 'cancelled'),  -- Statut de la réservation
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Date de création
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP -- Date de mise à jour
        )");
        echo "Table 'reservations' créée avec succès.<br>";
    }
    // -------------------------------------------------


    // --------------------------------------------
    // Ensure 'apartments' table exists
    $table_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'apartments'")->fetchColumn();
    if (!$table_exists) {
        $conn->exec("CREATE TABLE apartments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        apartment_name VARCHAR(255) NOT NULL,
        location VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        description TEXT NOT NULL,
        cover_image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
        echo "Table 'apartments' created successfully.<br>";
    }

    // Ensure 'apartment_images' table exists
    $table_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'apartment_images'")->fetchColumn();
    if (!$table_exists) {
        $conn->exec("CREATE TABLE apartment_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        apartment_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE
    )");
        echo "Table 'apartment_images' created successfully.<br>";
    }
   
    // --------------------------------------------
    // Check if the 'reservations' table exists
    $table_exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'Apreservations'")->fetchColumn();

    if (!$table_exists) {
        // Create 'Apreservations' table
        $conn->exec("CREATE TABLE Apreservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            apartment_id INT NOT NULL,
            phone_number VARCHAR(15) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            status ENUM('pending', 'confirmed', 'cancelled'),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            -- FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE
        )");

        echo "Table 'Apreservations' created successfully.<br>";
    }
    ensureColumnExists($conn, $db_name, 'Apreservations', 'name', 'VARCHAR(100)');

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
