CREATE DATABASE IF NOT EXISTS app_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE app_db;

CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    email      VARCHAR(100),
    role       ENUM('admin','user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, email, role) VALUES
('admin',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@cloudapp.id', 'admin'),
('budi',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'budi@email.com',    'user'),
('siti',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'siti@email.com',    'user');

CREATE TABLE IF NOT EXISTS categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL
);

INSERT INTO categories (name) VALUES
('Elektronik'), ('Pakaian'), ('Makanan & Minuman'), ('Peralatan Rumah'), ('Buku & Alat Tulis');

CREATE TABLE IF NOT EXISTS products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    price       DECIMAL(12,2) NOT NULL,
    category_id INT,
    stock       INT DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

INSERT INTO products (name, price, category_id, stock) VALUES
('Laptop ASUS VivoBook 14',      7500000,  1, 10),
('Keyboard Mekanikal Keychron',  850000,   1, 25),
('Mouse Logitech MX Master 3',   1200000,  1, 15),
('Kaos Polos Cotton Combed',     85000,    2, 100),
('Celana Jogger Pria',           150000,   2, 60),
('Kopi Arabika Gayo 250g',       75000,    3, 200),
('Teh Hijau Organik',            45000,    3, 150),
('Wajan Anti Lengket 24cm',      230000,   4, 30),
('Rak Buku Minimalis',           480000,   4, 12),
('Buku Algoritma & Pemrograman', 120000,   5, 40),
('Pulpen Pilot G2 (isi 12)',     55000,    5, 80),
('SSD Samsung 500GB SATA',       650000,   1, 20);
