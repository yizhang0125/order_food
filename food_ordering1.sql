-- MySQL dump 10.13  Distrib 8.0.38, for Win64 (x86_64)
--
-- Host: localhost    Database: food_ordering1
-- ------------------------------------------------------
-- Server version	8.0.39

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'admin3','$2y$10$GCSbO2UxypDb1JJvBeCN.eVNXnnjIDKrgOkABlL4xA6DrMa88aOXm','admin3@example.com');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Appetizers','Start your meal with our delicious appetizers','active','2025-03-08 09:24:33','2025-03-10 14:59:49'),(3,'Desserts','Sweet treats to end your meal','active','2025-03-08 09:24:33','2025-03-08 09:24:33'),(4,'Beverages','Refreshing drinks and beverages','active','2025-03-08 09:24:33','2025-03-10 14:57:39'),(5,'Noodles','','active','2025-03-08 10:08:38','2025-03-08 10:08:38'),(6,'Food','','active','2025-03-10 16:19:24','2025-03-10 16:19:24');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('available','unavailable') DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `category_id` int DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_category` (`category_id`),
  CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_items`
--

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` VALUES (1,'Ice Cream','',6.00,NULL,'available','2025-03-08 09:35:19',3,'uploads/menu_items/67cc0f579aa28.jpg'),(2,'Ramen','',12.00,NULL,'available','2025-03-08 10:09:00',5,'uploads/menu_items/67cc173c954ff.jpg'),(3,'Shrimp Fritters',' Crispy outside, soft inside, served with a spicy honey drizzle',18.00,NULL,'available','2025-03-10 16:17:20',1,'uploads/menu_items/67cf1090a7931.jpg'),(4,'Lemon Tea','Lemon tea can be made with either black or green tea, and it\'s often sweetened with honey or sugar',2.00,NULL,'available','2025-03-10 16:18:29',4,'uploads/menu_items/67cf10d521fec.png'),(5,'Chinese Rice Porridge ','Congee is typically made with white rice, such as jasmine or japonica, which provides a smooth and silky texture when cooked with a high water ratio',6.00,NULL,'available','2025-03-10 16:20:11',6,'uploads/menu_items/67cf113b03f26.jpg');
/*!40000 ALTER TABLE `menu_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int DEFAULT NULL,
  `menu_item_id` int DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `special_instructions` text,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,1,1,6.00,'2025-03-10 03:49:43',NULL),(2,1,2,1,12.00,'2025-03-10 03:49:43',NULL),(3,2,1,2,6.00,'2025-03-10 05:54:55',NULL),(4,3,1,1,6.00,'2025-03-10 13:01:09',NULL),(5,4,1,1,6.00,'2025-03-10 13:26:08',NULL),(6,5,1,1,6.00,'2025-03-10 13:28:35',NULL),(7,6,1,1,6.00,'2025-03-10 13:29:15',NULL),(8,7,1,1,6.00,'2025-03-10 13:36:01',NULL),(9,8,1,2,6.00,'2025-03-10 13:36:48',NULL),(10,9,1,1,6.00,'2025-03-10 13:37:26',NULL),(11,10,1,1,6.00,'2025-03-10 13:37:46',NULL),(12,11,1,1,6.00,'2025-03-10 13:41:56',NULL),(13,12,1,1,6.00,'2025-03-10 13:42:24',NULL),(14,13,1,1,6.00,'2025-03-10 13:45:18',NULL),(15,14,1,1,6.00,'2025-03-10 13:46:57',NULL),(16,15,1,1,6.00,'2025-03-10 13:47:09',NULL),(17,16,1,1,6.00,'2025-03-10 13:47:31',NULL),(18,17,1,1,6.00,'2025-03-10 13:49:37',NULL),(19,18,1,1,6.00,'2025-03-10 14:01:12',NULL),(20,19,1,1,6.00,'2025-03-10 14:17:28',NULL),(21,20,3,2,18.00,'2025-03-12 03:29:19',NULL),(22,20,4,3,2.00,'2025-03-12 03:29:19',NULL),(23,20,5,1,6.00,'2025-03-12 03:29:19',NULL),(24,21,3,2,18.00,'2025-03-12 05:19:02','no chili'),(25,21,4,2,2.00,'2025-03-12 05:19:02','no ice'),(26,21,1,2,6.00,'2025-03-12 05:19:02',NULL),(27,21,5,2,6.00,'2025-03-12 05:19:02',NULL),(28,21,2,1,12.00,'2025-03-12 05:19:02',NULL),(29,22,3,1,18.00,'2025-03-12 05:41:38','no chili\n'),(30,23,5,1,6.00,'2025-03-12 05:45:20','no chili\n'),(31,24,1,1,6.00,'2025-03-12 05:48:46','give me more spon'),(32,24,5,1,6.00,'2025-03-12 05:48:46','no cili'),(33,24,2,1,12.00,'2025-03-12 05:48:46','no egg'),(34,25,2,1,12.00,'2025-03-12 05:54:31',NULL),(35,26,1,1,6.00,'2025-03-12 06:25:23',NULL),(36,27,3,1,18.00,'2025-03-12 06:25:52',NULL),(37,28,1,3,6.00,'2025-03-13 01:46:50','add 1 spon'),(38,28,5,5,6.00,'2025-03-13 01:46:50','No vegetable'),(39,29,2,1,12.00,'2025-03-13 02:09:20','no cili vegetable\n'),(40,30,5,1,6.00,'2025-03-13 02:13:27',NULL),(41,31,2,1,12.00,'2025-03-13 02:16:30',NULL),(42,32,4,1,2.00,'2025-03-13 02:32:08',NULL);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `table_id` int DEFAULT NULL,
  `status` enum('pending','processing','completed','cancelled') DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `table_id` (`table_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,27,'completed',19.08,'2025-03-10 03:49:43'),(2,27,'completed',12.72,'2025-03-10 05:54:55'),(3,27,'completed',6.36,'2025-03-10 13:01:09'),(4,27,'completed',6.36,'2025-03-10 13:26:08'),(5,27,'completed',6.36,'2025-03-10 13:28:35'),(6,27,'pending',6.36,'2025-03-10 13:29:15'),(7,27,'pending',6.36,'2025-03-10 13:36:01'),(8,27,'pending',12.72,'2025-03-10 13:36:48'),(9,27,'pending',6.36,'2025-03-10 13:37:26'),(10,27,'pending',6.36,'2025-03-10 13:37:46'),(11,27,'pending',6.36,'2025-03-10 13:41:56'),(12,27,'pending',6.36,'2025-03-10 13:42:24'),(13,27,'pending',6.36,'2025-03-10 13:45:18'),(14,27,'processing',6.36,'2025-03-10 13:46:57'),(15,27,'completed',6.36,'2025-03-10 13:47:09'),(16,27,'pending',6.36,'2025-03-10 13:47:31'),(17,27,'cancelled',6.36,'2025-03-10 13:49:37'),(18,30,'completed',6.36,'2025-03-10 14:01:12'),(19,30,'completed',6.36,'2025-03-10 14:17:28'),(20,33,'completed',50.88,'2025-03-12 03:29:19'),(21,34,'completed',80.56,'2025-03-12 05:19:02'),(22,34,'completed',19.08,'2025-03-12 05:41:38'),(23,34,'pending',6.36,'2025-03-12 05:45:20'),(24,34,'completed',25.44,'2025-03-12 05:48:46'),(25,34,'pending',12.72,'2025-03-12 05:54:31'),(26,34,'pending',6.36,'2025-03-12 06:25:23'),(27,34,'completed',19.08,'2025-03-12 06:25:52'),(28,33,'completed',50.88,'2025-03-13 01:46:50'),(29,33,'completed',12.72,'2025-03-13 02:09:20'),(30,36,'completed',6.36,'2025-03-13 02:13:27'),(31,36,'completed',12.72,'2025-03-13 02:16:30'),(32,36,'completed',2.12,'2025-03-13 02:32:08');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','completed') DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `cash_received` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`payment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,27,19.08,'pending','2025-03-12 07:34:27',NULL,NULL),(2,27,19.08,'pending','2025-03-12 07:34:36',NULL,NULL),(3,27,19.08,'pending','2025-03-12 07:34:38',NULL,NULL),(4,27,19.08,'pending','2025-03-12 07:34:44',NULL,NULL),(5,27,19.08,'pending','2025-03-12 07:34:53',NULL,NULL),(6,27,19.08,'pending','2025-03-12 07:35:23',NULL,NULL),(7,27,19.08,'pending','2025-03-12 07:35:53',NULL,NULL),(8,27,19.08,'pending','2025-03-12 07:35:57',NULL,NULL),(9,27,19.08,'pending','2025-03-12 07:35:59',NULL,NULL),(10,27,19.08,'pending','2025-03-12 07:36:30',NULL,NULL),(11,27,19.08,'pending','2025-03-12 07:37:00',NULL,NULL),(12,27,19.08,'pending','2025-03-12 07:37:31',NULL,NULL),(13,27,19.08,'pending','2025-03-12 07:38:02',NULL,NULL),(14,27,19.08,'pending','2025-03-12 07:38:33',NULL,NULL),(15,27,19.08,'pending','2025-03-12 07:39:03',NULL,NULL),(16,27,19.08,'pending','2025-03-12 07:39:33',NULL,NULL),(17,27,19.08,'pending','2025-03-12 07:40:03',NULL,NULL),(18,27,19.08,'pending','2025-03-12 07:40:33',NULL,NULL),(19,27,19.08,'pending','2025-03-12 07:41:03',NULL,NULL),(20,27,19.08,'pending','2025-03-12 07:41:33',NULL,NULL),(21,27,19.08,'pending','2025-03-12 07:41:41',NULL,NULL),(22,27,19.08,'pending','2025-03-12 07:41:42',NULL,NULL),(23,27,19.08,'pending','2025-03-12 07:42:12',NULL,NULL),(24,27,19.08,'pending','2025-03-12 07:42:31',NULL,NULL),(25,27,19.08,'pending','2025-03-12 07:42:31',NULL,NULL),(26,27,19.08,'pending','2025-03-12 07:42:31',NULL,NULL),(27,27,19.08,'pending','2025-03-12 07:42:32',NULL,NULL),(28,27,19.08,'pending','2025-03-12 07:42:32',NULL,NULL),(29,27,19.08,'completed','2025-03-12 07:43:03',NULL,NULL),(30,27,19.08,'completed','2025-03-12 07:43:34',NULL,NULL),(31,27,19.08,'completed','2025-03-12 07:43:35',NULL,NULL),(32,27,19.08,'completed','2025-03-12 07:44:06',NULL,NULL),(33,27,19.08,'completed','2025-03-12 07:44:29',NULL,NULL),(34,27,19.08,'completed','2025-03-12 07:45:00',NULL,NULL),(35,27,19.08,'completed','2025-03-12 07:45:06',NULL,NULL),(36,27,19.08,'completed','2025-03-12 07:45:07',NULL,NULL),(37,27,19.08,'completed','2025-03-12 07:45:07',NULL,NULL),(38,27,19.08,'completed','2025-03-12 07:45:07',NULL,NULL),(39,27,19.08,'completed','2025-03-12 07:45:37',NULL,NULL),(40,27,19.08,'completed','2025-03-12 07:46:07',NULL,NULL),(41,27,19.08,'completed','2025-03-12 07:46:23',NULL,NULL),(42,27,19.08,'completed','2025-03-12 07:46:23',NULL,NULL),(43,27,19.08,'completed','2025-03-12 07:46:24',NULL,NULL),(44,27,19.08,'completed','2025-03-12 07:46:24',NULL,NULL),(45,27,19.08,'completed','2025-03-12 07:46:55',NULL,NULL),(46,27,19.08,'completed','2025-03-12 07:47:22',NULL,NULL),(47,27,19.08,'completed','2025-03-12 07:47:53',NULL,NULL),(48,27,19.08,'completed','2025-03-12 07:47:57',NULL,NULL),(49,27,19.08,'completed','2025-03-12 07:48:28',NULL,NULL),(50,27,19.08,'completed','2025-03-12 07:49:54',NULL,NULL),(51,27,19.08,'completed','2025-03-12 07:49:54',NULL,NULL),(52,27,19.08,'completed','2025-03-12 07:49:55',NULL,NULL),(53,27,19.08,'completed','2025-03-12 07:50:25',NULL,NULL),(54,27,19.08,'completed','2025-03-12 07:50:31',NULL,NULL),(55,27,19.08,'completed','2025-03-12 07:50:32',NULL,NULL),(56,27,19.08,'completed','2025-03-12 07:51:02',NULL,NULL),(57,27,19.08,'completed','2025-03-12 07:51:10',NULL,NULL),(58,27,19.08,'completed','2025-03-12 07:51:12',NULL,NULL),(59,24,25.44,'completed','2025-03-12 07:59:39',NULL,NULL),(60,24,25.44,'completed','2025-03-12 08:00:10',NULL,NULL),(61,24,25.44,'completed','2025-03-12 08:00:41',NULL,NULL),(62,24,25.44,'completed','2025-03-12 08:01:12',NULL,NULL),(63,24,25.44,'completed','2025-03-12 08:01:18',NULL,NULL),(64,22,19.08,'completed','2025-03-12 08:01:22',NULL,NULL),(65,22,19.08,'completed','2025-03-12 08:01:52',NULL,NULL),(66,22,19.08,'completed','2025-03-12 08:02:22',NULL,NULL),(67,22,19.08,'completed','2025-03-12 08:02:52',NULL,NULL),(68,24,25.44,'completed','2025-03-12 08:03:24',NULL,NULL),(69,24,25.44,'completed','2025-03-12 08:03:54',NULL,NULL),(70,24,25.44,'completed','2025-03-12 08:04:25',NULL,NULL),(71,24,25.44,'completed','2025-03-12 08:04:56',NULL,NULL),(72,24,25.44,'completed','2025-03-12 08:05:27',NULL,NULL),(73,24,25.44,'completed','2025-03-12 08:05:57',NULL,NULL),(74,24,25.44,'completed','2025-03-12 08:06:22',NULL,NULL),(75,24,25.44,'completed','2025-03-12 08:06:52',NULL,NULL),(76,24,25.44,'completed','2025-03-12 08:07:23',NULL,NULL),(77,24,25.44,'completed','2025-03-12 08:07:54',NULL,NULL),(78,24,25.44,'completed','2025-03-12 08:08:25',NULL,NULL),(79,24,25.44,'completed','2025-03-12 08:08:53',NULL,NULL),(80,24,25.44,'completed','2025-03-12 08:09:24',NULL,NULL),(81,24,25.44,'completed','2025-03-12 08:09:55',NULL,NULL),(82,24,25.44,'completed','2025-03-12 08:10:26',NULL,NULL),(83,24,25.44,'completed','2025-03-12 08:10:57',NULL,NULL),(84,21,80.56,'completed','2025-03-12 08:23:47',100.00,19.44),(85,21,80.56,'completed','2025-03-12 08:23:57',100.00,19.44),(86,18,6.36,'completed','2025-03-12 08:24:24',10.00,3.64),(87,18,6.36,'completed','2025-03-12 08:24:55',10.00,3.64),(88,18,6.36,'completed','2025-03-12 08:25:26',10.00,3.64),(89,18,6.36,'completed','2025-03-12 08:25:56',10.00,3.64),(90,19,6.36,'completed','2025-03-12 08:30:14',10.00,3.64),(91,5,6.36,'completed','2025-03-12 08:33:14',10.00,3.64),(92,4,6.36,'completed','2025-03-12 08:43:05',20.00,13.64),(93,28,50.88,'completed','2025-03-13 01:47:58',60.00,9.12),(96,29,12.72,'completed','2025-03-13 02:11:32',20.00,7.28),(97,30,6.36,'completed','2025-03-13 02:14:11',7.00,0.64),(98,31,12.72,'completed','2025-03-13 02:17:01',20.00,7.28),(99,32,2.12,'completed','2025-03-13 02:32:35',3.00,0.88);
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_codes`
--

DROP TABLE IF EXISTS `qr_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qr_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `table_id` int NOT NULL,
  `token` varchar(32) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `table_id` (`table_id`),
  CONSTRAINT `qr_codes_ibfk_1` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_codes`
--

LOCK TABLES `qr_codes` WRITE;
/*!40000 ALTER TABLE `qr_codes` DISABLE KEYS */;
INSERT INTO `qr_codes` VALUES (47,27,'a64975a6f85be752592eae60f38da33a','table_12_1741578408.png','2025-03-10 11:46:48','2025-03-10 13:46:48',0),(50,27,'a884e95b524913e59a09e54b0af485c6','table_12_1741610094.png','2025-03-10 20:34:54','2025-03-10 22:34:54',0),(51,27,'28774ffb56741b7da0907045c2de0fa8','table_12_1741610110.png','2025-03-10 20:35:10','2025-03-10 22:35:10',0),(52,27,'4eca886cd5bc827e42c357f8b22669b4','table_12_1741610114.png','2025-03-10 20:35:14','2025-03-10 22:35:14',0),(53,27,'b95e5d964d788c69882ecbc7f6f6f81d','table_12_1741610209.png','2025-03-10 20:36:49','2025-03-10 22:36:49',0),(56,27,'616bd92fbabe1cab9eb74ba706b3479c','table_12_1741610223.png','2025-03-10 20:37:03','2025-03-10 22:37:03',0),(57,27,'f372c5c4a2c8ba770bcb321b9bcd8060','table_12_1741610223.png','2025-03-10 20:37:03','2025-03-10 22:37:03',0),(58,27,'f7ffbcab41051c1e71c5f89c2743059c','table_12_1741610991.png','2025-03-10 20:49:51','2025-03-10 22:49:51',0),(59,27,'caa6ce27e6caaed8b2732a5b877e3d15','table_12_1741610995.png','2025-03-10 20:49:55','2025-03-10 22:49:55',0),(60,27,'3e312b4bf32a38454f2cf70cde56c30a','table_12_1741611071.png','2025-03-10 20:51:11','2025-03-10 22:51:11',0),(61,27,'04f2700b3a62bc5418f1124cbd00b720','table_12_1741611602.png','2025-03-10 21:00:02','2025-03-10 23:00:02',0),(62,27,'2a0d23b713b118761cebbbb858eb2ccc','table_12_1741611630.png','2025-03-10 21:00:30','2025-03-10 23:00:30',0),(63,30,'1e5f2194fb6346673d5c0f2a15a81497','table_15_1741613138.png','2025-03-10 21:25:38','2025-03-10 23:25:38',0),(64,27,'9fc4c2a8c955b3e4a02585d485e03bbe','table_12_1741613181.png','2025-03-10 21:26:21','2025-03-10 23:26:21',1),(65,30,'1e3f8d7bb0c37f8b689f9c3a3e3e4aaf','table_15_1741615282.png','2025-03-10 22:01:22','2025-03-11 00:01:22',0),(66,30,'c771f2d8db31bcdf25f3f3635f5d68f6','table_15_1741615681.png','2025-03-10 22:08:01','2025-03-11 00:08:01',1),(70,33,'598215aa49b3caa462a181316267b7f5','table_16_1741749308.png','2025-03-12 11:15:08','2025-03-12 13:15:08',0),(71,34,'45e2f97e1faa5d84fb1f9319b073bace','table_17_1741756635.png','2025-03-12 13:17:15','2025-03-12 15:17:15',1),(73,33,'e59eb0a5491767192f3f5bb0f60e7e87','table_16_1741760246.png','2025-03-12 14:17:26','2025-03-12 16:17:26',0),(74,33,'0003ca8b7029356103341512647a7702','table_16_1741760687.png','2025-03-12 14:24:47','2025-03-12 16:24:47',0),(75,33,'08173b02bceda8735abd72acea64a766','table_16_1741760692.png','2025-03-12 14:24:52','2025-03-12 16:24:52',0),(76,33,'ab76a14ba4f12971eb33c8e5a224fb66','table_16_1741760696.png','2025-03-12 14:24:57','2025-03-12 16:24:57',0),(77,33,'4f1c9b1820bcea8bb84cdb955af1afc9','table_16_1741830240.png','2025-03-13 09:44:00','2025-03-13 11:44:00',0),(78,33,'7378b67b0c990bfb2c40287865c3b8e9','table_16_1741830276.png','2025-03-13 09:44:36','2025-03-13 11:44:36',0),(79,33,'7f9e19be267f9057a4b0fdc4105713e3','table_16_1741830368.png','2025-03-13 09:46:08','2025-03-13 11:46:08',0),(80,33,'e608b1d66b521c4a83e00ae6fa3d0522','table_16_1741831968.png','2025-03-13 10:12:48','2025-03-13 12:12:48',0),(84,33,'335adaf5d4d1814e7753a1ce2bd3a6b0','table_16_1741832737.png','2025-03-13 10:25:37','2025-03-13 12:25:37',0),(85,33,'ebe2344054cf83f32b5b0649bad1b497','table_16_1741832788.png','2025-03-13 10:26:28','2025-03-13 12:26:28',1),(88,36,'0f5672ab58dc1fbefe808fc4b9a19140','table_1_1741833160.png','2025-03-13 10:32:41','2025-03-13 12:32:41',1),(89,39,'67d180fa1b0e18bfab29112e85d79d40','table_2_1741833210.png','2025-03-13 10:33:30','2025-03-13 12:33:30',1);
/*!40000 ALTER TABLE `qr_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tables`
--

DROP TABLE IF EXISTS `tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tables` (
  `id` int NOT NULL AUTO_INCREMENT,
  `table_number` int NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_table` (`table_number`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tables`
--

LOCK TABLES `tables` WRITE;
/*!40000 ALTER TABLE `tables` DISABLE KEYS */;
INSERT INTO `tables` VALUES (27,12,'active','2025-03-10 03:46:48'),(30,15,'active','2025-03-10 13:25:38'),(33,16,'active','2025-03-12 03:15:08'),(34,17,'active','2025-03-12 05:17:15'),(36,1,'inactive','2025-03-13 02:13:03'),(39,2,'active','2025-03-13 02:33:30');
/*!40000 ALTER TABLE `tables` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-03-13 10:37:00
