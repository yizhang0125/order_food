# QR Code Table Ordering System

A PHP-based restaurant ordering system that uses QR codes for table identification and ordering.

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer
- XAMPP/WAMP/LAMP server

## Installation

1. Clone this repository to your web server directory
2. Import the database structure:
   ```sql
   mysql -u root -p < database/food_ordering.sql
   ```

3. Install dependencies using Composer:
   ```bash
   composer require endroid/qr-code
   ```

4. Configure the database connection in `config/Database.php` if needed

5. Make sure your web server has write permissions for generating QR codes

## Features

- Admin panel to generate QR codes for tables
- Unique token generation for each table
- Secure QR code generation using the endroid/qr-code library
- Bootstrap-based responsive admin interface

## Usage

1. Access the admin panel at `admin/generate_qr.php`
2. Enter a table number to generate a unique QR code
3. The QR code will contain a token and table information
4. Customers can scan the QR code to access the ordering system

## Security

- Each table has a unique token
- QR codes are generated with table-specific information
- Token validation for each order
- Session-based admin authentication 