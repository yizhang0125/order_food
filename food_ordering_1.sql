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
INSERT INTO `categories` VALUES (1,'Appetizers','Start your meal with our delicious appetizers','active','2025-03-08 09:24:33','2025-03-10 14:59:49'),(3,'Desserts','Sweet treats to end your meal','active','2025-03-08 09:24:33','2025-03-19 05:46:02'),(4,'Beverages','Refreshing drinks and beverages','active','2025-03-08 09:24:33','2025-03-10 14:57:39'),(5,'Noodles','','active','2025-03-08 10:08:38','2025-03-08 10:08:38'),(6,'Food','','active','2025-03-10 16:19:24','2025-03-19 05:43:57');
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_items`
--

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` VALUES (1,'Ice Cream','Enjoy our creamy ice cream in a convenient cup, available in a variety of delicious flavors.',6.00,NULL,'available','2025-03-08 09:35:19',3,'uploads/menu_items/67cc0f579aa28.jpg'),(2,'Ramen','Enjoy a hearty bowl of Japanese ramen, featuring savory broth, springy noodles, and toppings like vegetables and a soft-boiled egg. Regional flavors vary, but each bowl offers a comforting blend of textures and tastes.\r\n\r\n',12.00,NULL,'available','2025-03-08 10:09:00',5,'uploads/menu_items/67cc173c954ff.jpg'),(3,'Shrimp Fritters',' Crispy outside, soft inside, served with a spicy honey drizzle',18.00,NULL,'available','2025-03-10 16:17:20',1,'uploads/menu_items/67cf1090a7931.jpg'),(4,'Lemon Tea','Lemon tea can be made with either black or green tea, and it\'s often sweetened with honey or sugar',2.00,NULL,'available','2025-03-10 16:18:29',4,'uploads/menu_items/67cf10d521fec.png'),(5,'Chinese Rice Porridge ','Congee is typically made with white rice, such as jasmine or japonica, which provides a smooth and silky texture when cooked with a high water ratio',6.00,NULL,'available','2025-03-10 16:20:11',6,'uploads/menu_items/67f611bf0f7eb.jpg'),(6,'Basil Appetizer Kabobs','Balsamic & Basil Appetizer Kabobs are fresh and flavorful skewers featuring tomatoes, mozzarella, and basil, lightly drizzled with balsamic vinegar for a tangy finish',15.00,NULL,'available','2025-03-19 07:00:40',1,'uploads/menu_items/67da6b9899905.jpg'),(7,'Tauhu Sumbat ','Tauhu Sumbat with Spicy Peanut Sauce features crispy fried tofu stuffed with fresh vegetables, served with a rich and spicy peanut sauce that combines roasted peanuts and chili for a bold flavor.',8.00,NULL,'available','2025-03-19 07:05:08',1,'uploads/menu_items/67da6ca4d3836.jpg'),(8,'Latte','A latte is a classic Italian coffee drink made with espresso and steamed milk, offering a smooth and creamy texture with a balanced flavor.',3.00,NULL,'available','2025-03-19 07:07:59',4,'uploads/menu_items/67da6d4f5cb57.jpg'),(9,'Matcha Green Tea Latte','A Matcha Green Tea Latte is a creamy, energizing drink blending matcha powder with steamed milk, offering a vibrant green color and earthy flavor.',12.00,NULL,'available','2025-03-19 07:10:35',4,'uploads/menu_items/67da71a0d42a5.jpg'),(10,'Caramel Topped Ice Cream ','A Caramel Topped Ice Cream Dessert combines creamy ice cream with a crunchy base and is finished with a rich, warm caramel drizzle, perfect for indulging.',6.00,NULL,'available','2025-03-19 07:14:41',3,'uploads/menu_items/67da6f37299ff.jpg'),(11,'Cendol (Malaysia)','Cendol in Malaysia is a traditional dessert featuring pandan jelly noodles, coconut milk, and palm sugar syrup, served over shaved ice for a refreshing treat.\r\n\r\n',5.00,NULL,'available','2025-03-19 07:17:52',3,'uploads/menu_items/67f610cd13b58.jpg'),(12,'Tom Yum','Tom Yum is a spicy and sour Thai soup, typically made with lemongrass, galangal, and seafood, offering a flavorful and aromatic experience.',18.00,NULL,'available','2025-03-19 07:21:42',6,'uploads/menu_items/67da7086694d8.jpg'),(13,'Burger','A burger is a classic dish featuring a cooked patty, typically beef or chicken, served on a bun with various toppings like cheese, lettuce, and condiments.',10.00,NULL,'available','2025-03-19 07:22:32',6,'uploads/menu_items/67da70b801206.jpg'),(14,'Spaghetti Carbonara','Spaghetti Carbonara is a rich Italian pasta dish made with spaghetti, bacon or pancetta, eggs, parmesan cheese, and black pepper, creating a creamy and savory sauce.\r\n\r\n',15.00,NULL,'available','2025-03-19 07:25:22',5,'uploads/menu_items/67da7162da1f8.jpg');
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
) ENGINE=InnoDB AUTO_INCREMENT=199 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,1,1,6.00,'2025-03-10 03:49:43',NULL),(2,1,2,1,12.00,'2025-03-10 03:49:43',NULL),(3,2,1,2,6.00,'2025-03-10 05:54:55',NULL),(4,3,1,1,6.00,'2025-03-10 13:01:09',NULL),(5,4,1,1,6.00,'2025-03-10 13:26:08',NULL),(6,5,1,1,6.00,'2025-03-10 13:28:35',NULL),(7,6,1,1,6.00,'2025-03-10 13:29:15',NULL),(8,7,1,1,6.00,'2025-03-10 13:36:01',NULL),(9,8,1,2,6.00,'2025-03-10 13:36:48',NULL),(10,9,1,1,6.00,'2025-03-10 13:37:26',NULL),(11,10,1,1,6.00,'2025-03-10 13:37:46',NULL),(12,11,1,1,6.00,'2025-03-10 13:41:56',NULL),(13,12,1,1,6.00,'2025-03-10 13:42:24',NULL),(14,13,1,1,6.00,'2025-03-10 13:45:18',NULL),(15,14,1,1,6.00,'2025-03-10 13:46:57',NULL),(16,15,1,1,6.00,'2025-03-10 13:47:09',NULL),(17,16,1,1,6.00,'2025-03-10 13:47:31',NULL),(18,17,1,1,6.00,'2025-03-10 13:49:37',NULL),(19,18,1,1,6.00,'2025-03-10 14:01:12',NULL),(20,19,1,1,6.00,'2025-03-10 14:17:28',NULL),(21,20,3,2,18.00,'2025-03-12 03:29:19',NULL),(22,20,4,3,2.00,'2025-03-12 03:29:19',NULL),(23,20,5,1,6.00,'2025-03-12 03:29:19',NULL),(24,21,3,2,18.00,'2025-03-12 05:19:02','no chili'),(25,21,4,2,2.00,'2025-03-12 05:19:02','no ice'),(26,21,1,2,6.00,'2025-03-12 05:19:02',NULL),(27,21,5,2,6.00,'2025-03-12 05:19:02',NULL),(28,21,2,1,12.00,'2025-03-12 05:19:02',NULL),(29,22,3,1,18.00,'2025-03-12 05:41:38','no chili\n'),(30,23,5,1,6.00,'2025-03-12 05:45:20','no chili\n'),(31,24,1,1,6.00,'2025-03-12 05:48:46','give me more spon'),(32,24,5,1,6.00,'2025-03-12 05:48:46','no cili'),(33,24,2,1,12.00,'2025-03-12 05:48:46','no egg'),(34,25,2,1,12.00,'2025-03-12 05:54:31',NULL),(35,26,1,1,6.00,'2025-03-12 06:25:23',NULL),(37,28,1,3,6.00,'2025-03-13 01:46:50','add 1 spon'),(38,28,5,5,6.00,'2025-03-13 01:46:50','No vegetable'),(39,29,2,1,12.00,'2025-03-13 02:09:20','no cili vegetable\n'),(40,30,5,1,6.00,'2025-03-13 02:13:27',NULL),(41,31,2,1,12.00,'2025-03-13 02:16:30',NULL),(42,32,4,1,2.00,'2025-03-13 02:32:08',NULL),(43,33,5,1,6.00,'2025-03-13 02:49:22',NULL),(44,34,3,1,18.00,'2025-03-13 03:08:46',NULL),(45,35,5,1,6.00,'2025-03-13 03:10:49',NULL),(46,36,2,1,12.00,'2025-03-13 05:16:26',NULL),(47,36,5,1,6.00,'2025-03-13 05:16:26',NULL),(48,37,1,1,6.00,'2025-03-13 05:19:37',NULL),(49,37,2,1,12.00,'2025-03-13 05:19:37',NULL),(50,38,3,1,18.00,'2025-03-13 05:21:25',NULL),(51,39,1,1,6.00,'2025-03-13 05:31:15',NULL),(52,39,5,1,6.00,'2025-03-13 05:31:15',NULL),(53,40,4,1,2.00,'2025-03-13 05:34:59',NULL),(54,41,4,1,2.00,'2025-03-13 05:38:33',NULL),(55,42,5,1,6.00,'2025-03-13 05:41:39',NULL),(56,43,4,1,2.00,'2025-03-13 05:43:14',NULL),(57,44,2,1,12.00,'2025-03-13 05:49:00',NULL),(58,45,5,1,6.00,'2025-03-13 05:51:38',NULL),(59,46,4,1,2.00,'2025-03-13 05:52:57',NULL),(60,47,4,1,2.00,'2025-03-13 05:54:41',NULL),(61,48,3,1,18.00,'2025-03-13 06:09:59',NULL),(62,49,3,1,18.00,'2025-03-13 06:20:37',NULL),(63,50,3,1,18.00,'2025-03-13 07:17:25',NULL),(64,51,4,1,2.00,'2025-03-13 07:22:48',NULL),(65,52,4,1,2.00,'2025-03-13 07:25:05',NULL),(66,53,4,1,2.00,'2025-03-13 07:26:18',NULL),(67,53,1,1,6.00,'2025-03-13 07:26:18',NULL),(68,54,5,1,6.00,'2025-03-13 07:33:57',NULL),(69,55,1,1,6.00,'2025-03-13 07:52:43',NULL),(70,55,4,1,2.00,'2025-03-13 07:52:43',NULL),(71,56,5,1,6.00,'2025-03-13 11:43:25',NULL),(72,57,5,1,6.00,'2025-03-13 12:37:31',NULL),(73,58,4,1,2.00,'2025-03-13 12:39:52',NULL),(74,59,5,1,6.00,'2025-03-14 02:48:04',NULL),(75,60,2,1,12.00,'2025-03-14 02:48:26',NULL),(76,61,3,1,18.00,'2025-03-17 01:42:39',NULL),(77,62,5,1,6.00,'2025-03-17 01:43:14',NULL),(78,63,4,1,2.00,'2025-03-17 01:50:32',NULL),(79,64,5,1,6.00,'2025-03-17 02:18:12',NULL),(80,65,1,1,6.00,'2025-03-17 03:04:18',NULL),(81,65,2,1,12.00,'2025-03-17 03:04:18',NULL),(82,66,5,1,6.00,'2025-03-17 03:04:48',NULL),(83,67,5,1,6.00,'2025-03-17 03:09:06',NULL),(84,67,2,1,12.00,'2025-03-17 03:09:06',NULL),(85,68,3,1,18.00,'2025-03-17 03:09:34',NULL),(86,69,1,1,6.00,'2025-03-17 03:34:19',NULL),(87,69,2,1,12.00,'2025-03-17 03:34:19',NULL),(88,70,4,1,2.00,'2025-03-17 03:34:49',NULL),(89,71,1,1,6.00,'2025-03-17 03:38:44',NULL),(90,71,5,1,6.00,'2025-03-17 03:38:44',NULL),(91,72,3,1,18.00,'2025-03-17 05:56:14',NULL),(92,73,3,1,18.00,'2025-03-17 05:57:24',NULL),(93,74,3,1,18.00,'2025-03-17 05:58:31',NULL),(94,75,3,1,18.00,'2025-03-17 05:58:48',NULL),(95,76,3,1,18.00,'2025-03-17 05:59:35',NULL),(96,76,4,1,2.00,'2025-03-17 05:59:35',NULL),(97,77,4,1,2.00,'2025-03-17 06:00:44',NULL),(98,78,3,1,18.00,'2025-03-17 06:16:03',NULL),(99,79,3,1,18.00,'2025-03-17 06:16:17',NULL),(100,80,1,1,6.00,'2025-03-17 06:16:52',NULL),(101,80,5,1,6.00,'2025-03-17 06:16:52',NULL),(102,81,3,2,18.00,'2025-03-17 06:19:24',NULL),(103,81,4,2,2.00,'2025-03-17 06:19:24',NULL),(104,81,1,1,6.00,'2025-03-17 06:19:24',NULL),(105,82,5,1,6.00,'2025-03-17 06:19:36',NULL),(106,83,4,3,2.00,'2025-03-17 06:33:44',NULL),(107,83,1,1,6.00,'2025-03-17 06:33:44',NULL),(108,84,2,4,12.00,'2025-03-17 06:33:54',NULL),(109,85,1,2,6.00,'2025-03-17 07:00:01',NULL),(110,85,5,3,6.00,'2025-03-17 07:00:01',NULL),(111,86,4,1,2.00,'2025-03-17 07:00:15',NULL),(112,86,2,2,12.00,'2025-03-17 07:00:15',NULL),(113,87,1,3,6.00,'2025-03-17 07:23:16',NULL),(114,88,2,3,12.00,'2025-03-17 07:23:28',NULL),(115,89,4,4,2.00,'2025-03-17 07:28:59',NULL),(116,90,5,2,6.00,'2025-03-17 07:29:07',NULL),(117,91,3,1,18.00,'2025-03-17 07:30:53',NULL),(118,92,2,4,12.00,'2025-03-17 07:39:45',NULL),(119,92,5,1,6.00,'2025-03-17 07:39:45',NULL),(120,93,4,1,2.00,'2025-03-17 07:39:59',NULL),(121,93,1,1,6.00,'2025-03-17 07:39:59',NULL),(122,94,4,1,2.00,'2025-03-17 07:46:21',NULL),(123,95,3,1,18.00,'2025-03-17 07:46:42',NULL),(124,96,5,1,6.00,'2025-03-17 07:52:49',NULL),(125,96,1,1,6.00,'2025-03-17 07:52:49',NULL),(126,97,5,1,6.00,'2025-03-17 07:54:51',NULL),(127,98,4,1,2.00,'2025-03-17 08:03:58',NULL),(128,99,1,1,6.00,'2025-03-17 08:04:07',NULL),(129,100,2,3,12.00,'2025-03-18 07:38:33',NULL),(130,101,4,1,2.00,'2025-03-18 08:07:42',NULL),(131,102,4,1,2.00,'2025-03-18 08:07:59',NULL),(132,103,4,1,2.00,'2025-03-18 08:09:05',NULL),(133,104,3,1,18.00,'2025-03-18 08:20:39',NULL),(134,105,4,1,2.00,'2025-03-18 11:35:37','cigyeqo'),(135,106,4,1,2.00,'2025-03-18 11:36:59','ekbdo'),(136,107,1,1,6.00,'2025-03-18 11:37:43',NULL),(137,108,3,4,18.00,'2025-03-18 12:59:16',NULL),(138,108,5,5,6.00,'2025-03-18 12:59:16',NULL),(139,108,4,1,2.00,'2025-03-18 12:59:16',NULL),(140,109,1,1,6.00,'2025-03-19 00:31:28',NULL),(141,110,1,1,6.00,'2025-03-19 00:31:39',NULL),(142,110,5,2,6.00,'2025-03-19 00:31:39',NULL),(143,111,4,1,2.00,'2025-03-19 00:34:07',NULL),(144,112,4,1,2.00,'2025-03-19 00:37:17',NULL),(145,113,2,1,12.00,'2025-03-19 01:30:01',NULL),(146,113,5,2,6.00,'2025-03-19 01:30:01',NULL),(147,113,1,2,6.00,'2025-03-19 01:30:01',NULL),(148,113,4,2,2.00,'2025-03-19 01:30:01',NULL),(149,114,3,2,18.00,'2025-03-19 01:31:00',NULL),(150,115,3,7,18.00,'2025-03-19 01:31:17',NULL),(151,115,4,3,2.00,'2025-03-19 01:31:17',NULL),(152,116,4,3,2.00,'2025-03-19 01:39:46',NULL),(153,116,2,1,12.00,'2025-03-19 01:39:46',NULL),(154,116,1,1,6.00,'2025-03-19 01:39:46',NULL),(155,117,3,1,18.00,'2025-03-19 01:41:08',NULL),(156,117,4,3,2.00,'2025-03-19 01:41:08',NULL),(157,118,5,1,6.00,'2025-03-19 01:45:56',NULL),(158,119,2,4,12.00,'2025-03-19 01:48:03',NULL),(159,119,3,3,18.00,'2025-03-19 01:48:03',NULL),(160,119,1,1,6.00,'2025-03-19 01:48:03',NULL),(161,120,3,1,18.00,'2025-03-19 01:49:45',NULL),(162,121,4,3,2.00,'2025-03-19 01:50:20',NULL),(163,122,5,1,6.00,'2025-03-19 01:50:27',NULL),(164,123,3,1,18.00,'2025-03-19 02:04:52',NULL),(165,124,1,4,6.00,'2025-03-19 02:10:13',NULL),(166,125,4,1,2.00,'2025-03-19 02:10:47',NULL),(167,126,3,3,18.00,'2025-03-19 02:12:18',NULL),(168,127,4,1,2.00,'2025-03-19 02:12:24',NULL),(169,128,4,2,2.00,'2025-03-19 02:12:53',NULL),(170,129,3,1,18.00,'2025-03-19 02:13:27',NULL),(171,130,3,1,18.00,'2025-03-19 02:17:55',NULL),(172,131,3,1,18.00,'2025-03-19 02:18:59',NULL),(173,132,4,3,2.00,'2025-03-19 02:20:43',NULL),(174,132,5,1,6.00,'2025-03-19 02:20:43',NULL),(175,133,3,1,18.00,'2025-03-19 02:28:23',NULL),(176,134,4,1,2.00,'2025-03-19 02:28:45',NULL),(177,135,3,1,18.00,'2025-03-19 03:32:21',NULL),(178,136,3,1,18.00,'2025-03-19 03:33:42',NULL),(179,137,3,1,18.00,'2025-03-19 05:51:28',NULL),(180,138,3,1,18.00,'2025-03-19 05:55:03',NULL),(181,138,4,2,2.00,'2025-03-19 05:55:03',NULL),(182,139,4,1,2.00,'2025-03-19 06:28:37',NULL),(183,140,1,1,6.00,'2025-03-19 06:31:40','dadsadsadsadsa'),(184,141,3,1,18.00,'2025-03-19 06:34:55',NULL),(185,142,3,1,18.00,'2025-03-19 06:38:06',NULL),(186,143,4,1,2.00,'2025-03-19 06:39:47',NULL),(187,144,1,1,6.00,'2025-03-19 06:42:16',NULL),(188,145,3,1,18.00,'2025-03-19 06:43:55',NULL),(189,146,3,1,18.00,'2025-03-19 06:46:49',NULL),(190,147,1,1,6.00,'2025-03-19 06:51:00',NULL),(191,148,6,1,15.00,'2025-03-20 07:42:13',NULL),(192,148,7,2,8.00,'2025-03-20 07:42:13',NULL),(193,148,9,1,12.00,'2025-03-20 07:42:13',NULL),(194,149,7,1,8.00,'2025-03-21 01:30:19',NULL),(195,150,8,1,3.00,'2025-04-09 00:32:29','sdwe'),(196,151,7,1,8.00,'2025-04-09 00:32:55','uyty'),(197,152,8,1,3.00,'2025-04-09 00:37:16',NULL),(198,153,8,1,3.00,'2025-09-02 03:02:42','no ice');
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,27,'completed',19.08,'2025-03-10 03:49:43'),(2,27,'completed',12.72,'2025-03-10 05:54:55'),(3,27,'completed',6.36,'2025-03-10 13:01:09'),(4,27,'completed',6.36,'2025-03-10 13:26:08'),(5,27,'completed',6.36,'2025-03-10 13:28:35'),(6,27,'pending',6.36,'2025-03-10 13:29:15'),(7,27,'pending',6.36,'2025-03-10 13:36:01'),(8,27,'pending',12.72,'2025-03-10 13:36:48'),(9,27,'pending',6.36,'2025-03-10 13:37:26'),(10,27,'pending',6.36,'2025-03-10 13:37:46'),(11,27,'pending',6.36,'2025-03-10 13:41:56'),(12,27,'pending',6.36,'2025-03-10 13:42:24'),(13,27,'processing',6.36,'2025-03-10 13:45:18'),(14,27,'completed',6.36,'2025-03-10 13:46:57'),(15,27,'completed',6.36,'2025-03-10 13:47:09'),(16,27,'pending',6.36,'2025-03-10 13:47:31'),(17,27,'cancelled',6.36,'2025-03-10 13:49:37'),(18,30,'completed',6.36,'2025-03-10 14:01:12'),(19,30,'completed',6.36,'2025-03-10 14:17:28'),(20,33,'completed',50.88,'2025-03-12 03:29:19'),(21,34,'completed',80.56,'2025-03-12 05:19:02'),(22,34,'completed',19.08,'2025-03-12 05:41:38'),(23,34,'cancelled',6.36,'2025-03-12 05:45:20'),(24,34,'completed',25.44,'2025-03-12 05:48:46'),(25,34,'processing',12.72,'2025-03-12 05:54:31'),(26,34,'completed',6.36,'2025-03-12 06:25:23'),(28,33,'completed',50.88,'2025-03-13 01:46:50'),(29,33,'completed',12.72,'2025-03-13 02:09:20'),(30,36,'completed',6.36,'2025-03-13 02:13:27'),(31,36,'completed',12.72,'2025-03-13 02:16:30'),(32,36,'completed',2.12,'2025-03-13 02:32:08'),(33,40,'completed',6.36,'2025-03-13 02:49:22'),(34,40,'completed',19.08,'2025-03-13 03:08:46'),(35,41,'completed',6.36,'2025-03-13 03:10:49'),(36,42,'completed',19.08,'2025-03-13 05:16:26'),(37,42,'completed',19.08,'2025-03-13 05:19:37'),(38,42,'completed',19.08,'2025-03-13 05:21:25'),(39,42,'completed',12.72,'2025-03-13 05:31:15'),(40,43,'completed',2.12,'2025-03-13 05:34:59'),(41,43,'completed',2.12,'2025-03-13 05:38:33'),(42,43,'completed',6.36,'2025-03-13 05:41:39'),(43,43,'completed',2.12,'2025-03-13 05:43:14'),(44,43,'completed',12.72,'2025-03-13 05:49:00'),(45,43,'completed',6.36,'2025-03-13 05:51:38'),(46,43,'completed',2.12,'2025-03-13 05:52:57'),(47,43,'completed',2.12,'2025-03-13 05:54:41'),(48,43,'completed',19.08,'2025-03-13 06:09:59'),(49,44,'completed',19.08,'2025-03-13 06:20:37'),(50,44,'completed',19.08,'2025-03-13 07:17:25'),(51,44,'completed',2.12,'2025-03-13 07:22:48'),(52,44,'completed',2.12,'2025-03-13 07:25:05'),(53,44,'completed',8.48,'2025-03-13 07:26:18'),(54,44,'completed',6.36,'2025-03-13 07:33:57'),(55,44,'cancelled',8.48,'2025-03-13 07:52:43'),(56,43,'completed',6.36,'2025-03-13 11:43:25'),(57,43,'completed',6.36,'2025-03-13 12:37:31'),(58,43,'completed',2.12,'2025-03-13 12:39:52'),(59,44,'completed',6.36,'2025-03-14 02:48:04'),(60,44,'completed',12.72,'2025-03-14 02:48:26'),(61,43,'completed',19.08,'2025-03-17 01:42:39'),(62,43,'completed',6.36,'2025-03-17 01:43:14'),(63,43,'completed',2.12,'2025-03-17 01:50:32'),(64,43,'completed',6.36,'2025-03-17 02:18:12'),(65,43,'completed',19.08,'2025-03-17 03:04:18'),(66,43,'completed',6.36,'2025-03-17 03:04:48'),(67,43,'completed',19.08,'2025-03-17 03:09:06'),(68,43,'completed',19.08,'2025-03-17 03:09:34'),(69,43,'completed',19.08,'2025-03-17 03:34:19'),(70,43,'completed',2.12,'2025-03-17 03:34:49'),(71,43,'completed',12.72,'2025-03-17 03:38:44'),(72,44,'cancelled',19.08,'2025-03-17 05:56:14'),(73,44,'cancelled',19.08,'2025-03-17 05:57:24'),(74,44,'cancelled',19.08,'2025-03-17 05:58:31'),(75,44,'completed',19.08,'2025-03-17 05:58:48'),(76,44,'completed',21.20,'2025-03-17 05:59:35'),(77,44,'completed',2.12,'2025-03-17 06:00:44'),(78,44,'completed',19.08,'2025-03-17 06:16:03'),(79,44,'completed',19.08,'2025-03-17 06:16:17'),(80,44,'completed',12.72,'2025-03-17 06:16:52'),(81,44,'completed',48.76,'2025-03-17 06:19:24'),(82,44,'completed',6.36,'2025-03-17 06:19:36'),(83,43,'completed',12.72,'2025-03-17 06:33:44'),(84,43,'completed',50.88,'2025-03-17 06:33:54'),(85,36,'completed',31.80,'2025-03-17 07:00:01'),(86,36,'completed',27.56,'2025-03-17 07:00:15'),(87,44,'completed',19.08,'2025-03-17 07:23:16'),(88,44,'completed',38.16,'2025-03-17 07:23:28'),(89,44,'completed',8.48,'2025-03-17 07:28:59'),(90,44,'completed',12.72,'2025-03-17 07:29:07'),(91,44,'completed',19.08,'2025-03-17 07:30:53'),(92,44,'completed',57.24,'2025-03-17 07:39:45'),(93,44,'completed',8.48,'2025-03-17 07:39:59'),(94,43,'completed',2.12,'2025-03-17 07:46:21'),(95,43,'completed',19.08,'2025-03-17 07:46:42'),(96,43,'completed',12.72,'2025-03-17 07:52:49'),(97,43,'completed',6.36,'2025-03-17 07:54:51'),(98,43,'completed',2.12,'2025-03-17 08:03:58'),(99,43,'completed',6.36,'2025-03-17 08:04:07'),(100,44,'completed',38.16,'2025-03-18 07:38:33'),(101,44,'completed',2.12,'2025-03-18 08:07:42'),(102,44,'completed',2.12,'2025-03-18 08:07:59'),(103,44,'completed',2.12,'2025-03-18 08:09:05'),(104,44,'completed',19.08,'2025-03-18 08:20:39'),(105,36,'completed',2.12,'2025-03-18 11:35:37'),(106,36,'completed',2.12,'2025-03-18 11:36:59'),(107,36,'completed',6.36,'2025-03-18 11:37:43'),(108,44,'completed',110.24,'2025-03-18 12:59:16'),(109,44,'completed',6.36,'2025-03-19 00:31:28'),(110,44,'completed',19.08,'2025-03-19 00:31:39'),(111,43,'completed',2.12,'2025-03-19 00:34:07'),(112,44,'completed',2.12,'2025-03-19 00:37:17'),(113,44,'completed',42.40,'2025-03-19 01:30:01'),(114,34,'completed',38.16,'2025-03-19 01:31:00'),(115,34,'completed',139.92,'2025-03-19 01:31:17'),(116,44,'completed',25.44,'2025-03-19 01:39:46'),(117,34,'completed',25.44,'2025-03-19 01:41:08'),(118,34,'completed',6.36,'2025-03-19 01:45:56'),(119,34,'completed',114.48,'2025-03-19 01:48:03'),(120,34,'completed',19.08,'2025-03-19 01:49:45'),(121,34,'completed',6.36,'2025-03-19 01:50:20'),(122,34,'completed',6.36,'2025-03-19 01:50:27'),(123,44,'cancelled',19.08,'2025-03-19 02:04:52'),(124,44,'cancelled',25.44,'2025-03-19 02:10:13'),(125,44,'cancelled',2.12,'2025-03-19 02:10:47'),(126,34,'cancelled',57.24,'2025-03-19 02:12:18'),(127,34,'cancelled',2.12,'2025-03-19 02:12:24'),(128,34,'cancelled',4.24,'2025-03-19 02:12:53'),(129,34,'completed',19.08,'2025-03-19 02:13:27'),(130,43,'completed',19.08,'2025-03-19 02:17:55'),(131,43,'cancelled',19.08,'2025-03-19 02:18:59'),(132,43,'completed',12.72,'2025-03-19 02:20:43'),(133,43,'completed',19.08,'2025-03-19 02:28:23'),(134,43,'completed',2.12,'2025-03-19 02:28:45'),(135,43,'completed',19.08,'2025-03-19 03:32:21'),(136,43,'completed',19.08,'2025-03-19 03:33:42'),(137,44,'completed',19.08,'2025-03-19 05:51:28'),(138,44,'completed',23.32,'2025-03-19 05:55:03'),(139,44,'completed',2.12,'2025-03-19 06:28:37'),(140,44,'completed',6.36,'2025-03-19 06:31:40'),(141,44,'completed',19.08,'2025-03-19 06:34:55'),(142,44,'completed',19.08,'2025-03-19 06:38:06'),(143,44,'completed',2.12,'2025-03-19 06:39:47'),(144,44,'completed',6.36,'2025-03-19 06:42:16'),(145,44,'completed',19.08,'2025-03-19 06:43:55'),(146,44,'completed',19.08,'2025-03-19 06:46:49'),(147,44,'completed',6.36,'2025-03-19 06:51:00'),(148,36,'completed',45.58,'2025-03-20 07:42:13'),(149,36,'completed',8.48,'2025-03-21 01:30:19'),(150,36,'completed',3.18,'2025-04-09 00:32:29'),(151,36,'completed',8.48,'2025-04-09 00:32:55'),(152,36,'completed',3.18,'2025-04-09 00:37:16'),(153,44,'pending',3.18,'2025-09-02 03:02:42');
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
) ENGINE=InnoDB AUTO_INCREMENT=466 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,27,19.08,'pending','2025-03-12 07:34:27',NULL,NULL),(2,27,19.08,'pending','2025-03-12 07:34:36',NULL,NULL),(3,27,19.08,'pending','2025-03-12 07:34:38',NULL,NULL),(4,27,19.08,'pending','2025-03-12 07:34:44',NULL,NULL),(5,27,19.08,'pending','2025-03-12 07:34:53',NULL,NULL),(6,27,19.08,'pending','2025-03-12 07:35:23',NULL,NULL),(7,27,19.08,'pending','2025-03-12 07:35:53',NULL,NULL),(8,27,19.08,'pending','2025-03-12 07:35:57',NULL,NULL),(9,27,19.08,'pending','2025-03-12 07:35:59',NULL,NULL),(10,27,19.08,'pending','2025-03-12 07:36:30',NULL,NULL),(11,27,19.08,'pending','2025-03-12 07:37:00',NULL,NULL),(12,27,19.08,'pending','2025-03-12 07:37:31',NULL,NULL),(13,27,19.08,'pending','2025-03-12 07:38:02',NULL,NULL),(14,27,19.08,'pending','2025-03-12 07:38:33',NULL,NULL),(15,27,19.08,'pending','2025-03-12 07:39:03',NULL,NULL),(16,27,19.08,'pending','2025-03-12 07:39:33',NULL,NULL),(17,27,19.08,'pending','2025-03-12 07:40:03',NULL,NULL),(18,27,19.08,'pending','2025-03-12 07:40:33',NULL,NULL),(19,27,19.08,'pending','2025-03-12 07:41:03',NULL,NULL),(20,27,19.08,'pending','2025-03-12 07:41:33',NULL,NULL),(21,27,19.08,'pending','2025-03-12 07:41:41',NULL,NULL),(22,27,19.08,'pending','2025-03-12 07:41:42',NULL,NULL),(23,27,19.08,'pending','2025-03-12 07:42:12',NULL,NULL),(24,27,19.08,'pending','2025-03-12 07:42:31',NULL,NULL),(25,27,19.08,'pending','2025-03-12 07:42:31',NULL,NULL),(26,27,19.08,'pending','2025-03-12 07:42:31',NULL,NULL),(27,27,19.08,'pending','2025-03-12 07:42:32',NULL,NULL),(28,27,19.08,'pending','2025-03-12 07:42:32',NULL,NULL),(29,27,19.08,'completed','2025-03-12 07:43:03',NULL,NULL),(30,27,19.08,'completed','2025-03-12 07:43:34',NULL,NULL),(31,27,19.08,'completed','2025-03-12 07:43:35',NULL,NULL),(32,27,19.08,'completed','2025-03-12 07:44:06',NULL,NULL),(33,27,19.08,'completed','2025-03-12 07:44:29',NULL,NULL),(34,27,19.08,'completed','2025-03-12 07:45:00',NULL,NULL),(35,27,19.08,'completed','2025-03-12 07:45:06',NULL,NULL),(36,27,19.08,'completed','2025-03-12 07:45:07',NULL,NULL),(37,27,19.08,'completed','2025-03-12 07:45:07',NULL,NULL),(38,27,19.08,'completed','2025-03-12 07:45:07',NULL,NULL),(39,27,19.08,'completed','2025-03-12 07:45:37',NULL,NULL),(40,27,19.08,'completed','2025-03-12 07:46:07',NULL,NULL),(41,27,19.08,'completed','2025-03-12 07:46:23',NULL,NULL),(42,27,19.08,'completed','2025-03-12 07:46:23',NULL,NULL),(43,27,19.08,'completed','2025-03-12 07:46:24',NULL,NULL),(44,27,19.08,'completed','2025-03-12 07:46:24',NULL,NULL),(45,27,19.08,'completed','2025-03-12 07:46:55',NULL,NULL),(46,27,19.08,'completed','2025-03-12 07:47:22',NULL,NULL),(47,27,19.08,'completed','2025-03-12 07:47:53',NULL,NULL),(48,27,19.08,'completed','2025-03-12 07:47:57',NULL,NULL),(49,27,19.08,'completed','2025-03-12 07:48:28',NULL,NULL),(50,27,19.08,'completed','2025-03-12 07:49:54',NULL,NULL),(51,27,19.08,'completed','2025-03-12 07:49:54',NULL,NULL),(52,27,19.08,'completed','2025-03-12 07:49:55',NULL,NULL),(53,27,19.08,'completed','2025-03-12 07:50:25',NULL,NULL),(54,27,19.08,'completed','2025-03-12 07:50:31',NULL,NULL),(55,27,19.08,'completed','2025-03-12 07:50:32',NULL,NULL),(56,27,19.08,'completed','2025-03-12 07:51:02',NULL,NULL),(57,27,19.08,'completed','2025-03-12 07:51:10',NULL,NULL),(58,27,19.08,'completed','2025-03-12 07:51:12',NULL,NULL),(59,24,25.44,'completed','2025-03-12 07:59:39',NULL,NULL),(60,24,25.44,'completed','2025-03-12 08:00:10',NULL,NULL),(61,24,25.44,'completed','2025-03-12 08:00:41',NULL,NULL),(62,24,25.44,'completed','2025-03-12 08:01:12',NULL,NULL),(63,24,25.44,'completed','2025-03-12 08:01:18',NULL,NULL),(64,22,19.08,'completed','2025-03-12 08:01:22',NULL,NULL),(65,22,19.08,'completed','2025-03-12 08:01:52',NULL,NULL),(66,22,19.08,'completed','2025-03-12 08:02:22',NULL,NULL),(67,22,19.08,'completed','2025-03-12 08:02:52',NULL,NULL),(68,24,25.44,'completed','2025-03-12 08:03:24',NULL,NULL),(69,24,25.44,'completed','2025-03-12 08:03:54',NULL,NULL),(70,24,25.44,'completed','2025-03-12 08:04:25',NULL,NULL),(71,24,25.44,'completed','2025-03-12 08:04:56',NULL,NULL),(72,24,25.44,'completed','2025-03-12 08:05:27',NULL,NULL),(73,24,25.44,'completed','2025-03-12 08:05:57',NULL,NULL),(74,24,25.44,'completed','2025-03-12 08:06:22',NULL,NULL),(75,24,25.44,'completed','2025-03-12 08:06:52',NULL,NULL),(76,24,25.44,'completed','2025-03-12 08:07:23',NULL,NULL),(77,24,25.44,'completed','2025-03-12 08:07:54',NULL,NULL),(78,24,25.44,'completed','2025-03-12 08:08:25',NULL,NULL),(79,24,25.44,'completed','2025-03-12 08:08:53',NULL,NULL),(80,24,25.44,'completed','2025-03-12 08:09:24',NULL,NULL),(81,24,25.44,'completed','2025-03-12 08:09:55',NULL,NULL),(82,24,25.44,'completed','2025-03-12 08:10:26',NULL,NULL),(83,24,25.44,'completed','2025-03-12 08:10:57',NULL,NULL),(84,21,80.56,'completed','2025-03-12 08:23:47',100.00,19.44),(85,21,80.56,'completed','2025-03-12 08:23:57',100.00,19.44),(86,18,6.36,'completed','2025-03-12 08:24:24',10.00,3.64),(87,18,6.36,'completed','2025-03-12 08:24:55',10.00,3.64),(88,18,6.36,'completed','2025-03-12 08:25:26',10.00,3.64),(89,18,6.36,'completed','2025-03-12 08:25:56',10.00,3.64),(90,19,6.36,'completed','2025-03-12 08:30:14',10.00,3.64),(91,5,6.36,'completed','2025-03-12 08:33:14',10.00,3.64),(92,4,6.36,'completed','2025-03-12 08:43:05',20.00,13.64),(93,28,50.88,'completed','2025-03-13 01:47:58',60.00,9.12),(96,29,12.72,'completed','2025-03-13 02:11:32',20.00,7.28),(97,30,6.36,'completed','2025-03-13 02:14:11',7.00,0.64),(98,31,12.72,'completed','2025-03-13 02:17:01',20.00,7.28),(99,32,2.12,'completed','2025-03-13 02:32:35',3.00,0.88),(100,33,6.36,'completed','2025-03-13 02:50:34',12.00,5.64),(101,34,19.08,'completed','2025-03-13 03:09:17',20.00,0.92),(102,35,6.36,'completed','2025-03-13 03:14:51',10.00,3.64),(103,38,19.08,'completed','2025-03-13 05:24:26',20.00,0.92),(104,37,19.08,'completed','2025-03-13 05:24:43',20.00,0.92),(105,36,19.08,'completed','2025-03-13 05:24:56',20.00,0.92),(106,39,12.72,'completed','2025-03-13 05:32:20',20.00,7.28),(107,40,2.12,'completed','2025-03-13 05:35:17',10.00,7.88),(108,41,2.12,'completed','2025-03-13 05:38:52',10.00,7.88),(109,42,6.36,'completed','2025-03-13 05:41:57',7.00,0.64),(110,43,2.12,'completed','2025-03-13 05:43:34',23.00,20.88),(111,44,12.72,'completed','2025-03-13 05:49:37',13.00,0.28),(112,45,6.36,'completed','2025-03-13 05:52:04',10.00,3.64),(113,46,2.12,'completed','2025-03-13 05:53:19',5.00,2.88),(114,47,2.12,'completed','2025-03-13 05:55:16',10.00,7.88),(115,48,19.08,'completed','2025-03-13 06:10:35',20.00,0.92),(116,49,19.08,'completed','2025-03-13 06:21:00',20.00,0.92),(117,50,19.08,'completed','2025-03-13 07:17:50',20.00,0.92),(118,51,2.12,'completed','2025-03-13 07:23:12',3.00,0.88),(119,52,2.12,'completed','2025-03-13 07:25:39',3.00,0.88),(120,53,8.48,'completed','2025-03-13 07:26:35',22.00,13.52),(121,54,6.36,'completed','2025-03-13 07:54:14',10.00,3.64),(122,56,6.36,'completed','2025-03-13 11:44:07',23.00,16.64),(123,58,2.12,'completed','2025-03-13 12:42:35',8.00,5.88),(124,57,6.36,'completed','2025-03-13 12:42:49',9.00,2.64),(125,59,19.08,'completed','2025-03-17 01:40:56',20.00,0.92),(126,60,19.08,'completed','2025-03-17 01:40:56',20.00,0.92),(127,20,152.64,'completed','2025-03-17 01:41:35',152.65,0.01),(128,20,152.64,'completed','2025-03-17 01:41:35',152.65,0.01),(129,20,152.64,'completed','2025-03-17 01:41:35',152.65,0.01),(235,61,33.92,'completed','2025-03-17 03:01:39',100.00,66.08),(236,62,33.92,'completed','2025-03-17 03:01:39',100.00,66.08),(237,63,33.92,'completed','2025-03-17 03:01:39',100.00,66.08),(238,64,33.92,'completed','2025-03-17 03:01:39',100.00,66.08),(239,1,69.96,'completed','2025-03-17 03:02:58',70.00,0.04),(240,2,69.96,'completed','2025-03-17 03:02:58',70.00,0.04),(241,3,69.96,'completed','2025-03-17 03:02:58',70.00,0.04),(242,14,69.96,'completed','2025-03-17 03:02:58',70.00,0.04),(243,15,69.96,'completed','2025-03-17 03:02:58',70.00,0.04),(247,65,44.52,'completed','2025-03-17 03:06:20',100.00,55.48),(248,66,44.52,'completed','2025-03-17 03:06:20',100.00,55.48),(249,67,19.08,'completed','2025-03-17 03:32:42',70.00,31.84),(250,68,19.08,'completed','2025-03-17 03:32:42',70.00,31.84),(251,69,19.08,'completed','2025-03-17 03:37:16',23.00,1.80),(252,70,2.12,'completed','2025-03-17 03:37:16',23.00,1.80),(253,71,12.72,'completed','2025-03-17 03:39:15',23.00,10.28),(254,75,19.08,'completed','2025-03-17 06:11:38',50.00,7.60),(255,76,21.20,'completed','2025-03-17 06:11:38',50.00,7.60),(256,77,2.12,'completed','2025-03-17 06:11:38',50.00,7.60),(257,26,6.36,'completed','2025-03-17 06:17:37',800.00,259.40),(258,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(259,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(260,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(261,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(262,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(263,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(264,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(265,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(266,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(267,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(268,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(269,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(270,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(271,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(272,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(273,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(274,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(275,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(276,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(277,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(278,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(279,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(280,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(281,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(282,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(283,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(284,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(285,27,19.08,'completed','2025-03-17 06:17:37',800.00,259.40),(286,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(287,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(288,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(289,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(290,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(291,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(292,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(293,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(294,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(295,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(296,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(297,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(298,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(299,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(300,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(301,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(302,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(303,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(304,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(305,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(306,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(307,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(308,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(309,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(310,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(311,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(312,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(313,27,19.08,'completed','2025-03-17 06:17:57',800.00,265.76),(314,78,19.08,'completed','2025-03-17 06:18:11',60.00,9.12),(315,79,19.08,'completed','2025-03-17 06:18:11',60.00,9.12),(316,80,12.72,'completed','2025-03-17 06:18:11',60.00,9.12),(317,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(318,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(319,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(320,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(321,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(322,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(323,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(324,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(325,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(326,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(327,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(328,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(329,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(330,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(331,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(332,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(333,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(334,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(335,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(336,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(337,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(338,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(339,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(340,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(341,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(342,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(343,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(344,27,19.08,'completed','2025-03-17 06:23:18',600.00,65.76),(345,81,48.76,'completed','2025-03-17 06:23:30',60.00,4.88),(346,82,6.36,'completed','2025-03-17 06:23:30',60.00,4.88),(347,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(348,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(349,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(350,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(351,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(352,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(353,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(354,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(355,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(356,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(357,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(358,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(359,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(360,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(361,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(362,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(363,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(364,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(365,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(366,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(367,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(368,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(369,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(370,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(371,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(372,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(373,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(374,27,19.08,'completed','2025-03-17 06:24:04',600.00,65.76),(375,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(376,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(377,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(378,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(379,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(380,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(381,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(382,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(383,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(384,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(385,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(386,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(387,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(388,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(389,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(390,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(391,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(392,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(393,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(394,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(395,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(396,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(397,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(398,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(399,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(400,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(401,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(402,27,19.08,'completed','2025-03-17 06:31:20',600.00,65.76),(403,83,12.72,'completed','2025-03-17 06:34:27',70.00,6.40),(404,84,50.88,'completed','2025-03-17 06:34:27',70.00,6.40),(405,85,31.80,'completed','2025-03-17 07:01:54',60.00,0.64),(406,86,27.56,'completed','2025-03-17 07:01:54',60.00,0.64),(407,87,19.08,'completed','2025-03-17 07:24:11',57.24,0.00),(408,88,38.16,'completed','2025-03-17 07:24:11',57.24,0.00),(409,89,8.48,'completed','2025-03-17 07:29:48',22.00,0.80),(410,90,12.72,'completed','2025-03-17 07:29:48',22.00,0.80),(411,91,19.08,'completed','2025-03-17 07:33:14',22.00,2.92),(412,94,2.12,'completed','2025-03-17 08:05:04',59.00,10.24),(413,95,19.08,'completed','2025-03-17 08:05:04',59.00,10.24),(414,96,12.72,'completed','2025-03-17 08:05:04',59.00,10.24),(415,97,6.36,'completed','2025-03-17 08:05:04',59.00,10.24),(416,98,2.12,'completed','2025-03-17 08:05:04',59.00,10.24),(417,99,6.36,'completed','2025-03-17 08:05:04',59.00,10.24),(418,92,57.24,'completed','2025-03-17 08:06:21',66.00,0.28),(419,93,8.48,'completed','2025-03-17 08:06:21',66.00,0.28),(420,100,38.16,'completed','2025-03-18 08:18:40',50.00,5.48),(421,101,2.12,'completed','2025-03-18 08:18:40',50.00,5.48),(422,102,2.12,'completed','2025-03-18 08:18:40',50.00,5.48),(423,103,2.12,'completed','2025-03-18 08:18:40',50.00,5.48),(424,104,19.08,'completed','2025-03-18 11:34:25',20.00,0.92),(425,105,2.12,'completed','2025-03-19 00:27:26',11.00,0.40),(426,106,2.12,'completed','2025-03-19 00:27:26',11.00,0.40),(427,107,6.36,'completed','2025-03-19 00:27:26',11.00,0.40),(428,109,6.36,'completed','2025-03-19 00:32:04',26.00,0.56),(429,110,19.08,'completed','2025-03-19 00:32:04',26.00,0.56),(430,114,38.16,'completed','2025-03-19 01:32:09',200.00,21.92),(431,115,139.92,'completed','2025-03-19 01:32:09',200.00,21.92),(432,112,2.12,'completed','2025-03-19 01:32:15',55.00,10.48),(433,113,42.40,'completed','2025-03-19 01:32:15',55.00,10.48),(434,117,25.44,'completed','2025-03-19 01:41:48',29.00,3.56),(435,116,25.44,'completed','2025-03-19 01:41:55',30.00,4.56),(436,119,114.48,'completed','2025-03-19 01:48:57',200.00,85.52),(437,118,6.36,'completed','2025-03-19 01:51:07',40.00,1.84),(438,120,19.08,'completed','2025-03-19 01:51:07',40.00,1.84),(439,121,6.36,'completed','2025-03-19 01:51:07',40.00,1.84),(440,122,6.36,'completed','2025-03-19 01:51:07',40.00,1.84),(441,111,2.12,'completed','2025-03-19 02:19:33',10.00,7.88),(442,130,19.08,'completed','2025-03-19 02:22:33',40.00,8.20),(443,132,12.72,'completed','2025-03-19 02:22:33',40.00,8.20),(444,133,19.08,'completed','2025-03-19 02:32:28',22.00,0.80),(445,134,2.12,'completed','2025-03-19 02:32:28',22.00,0.80),(446,129,19.08,'completed','2025-03-19 02:42:55',20.00,0.92),(447,135,19.08,'completed','2025-03-19 03:34:18',40.00,1.84),(448,136,19.08,'completed','2025-03-19 03:34:18',40.00,1.84),(449,137,19.08,'completed','2025-03-19 05:52:52',20.00,0.92),(450,108,110.24,'completed','2025-03-19 05:56:36',1000.00,866.44),(451,138,23.32,'completed','2025-03-19 05:56:36',1000.00,866.44),(452,139,2.12,'completed','2025-03-19 06:29:14',30.00,27.88),(453,140,6.36,'completed','2025-03-19 06:32:02',70.00,63.64),(454,141,19.08,'completed','2025-03-19 06:35:20',20.00,0.92),(455,142,19.08,'completed','2025-03-19 06:38:30',20.00,0.92),(456,143,2.12,'completed','2025-03-19 06:40:07',3.00,0.88),(457,144,6.36,'completed','2025-03-19 06:42:45',10.00,3.64),(458,145,19.08,'completed','2025-03-19 06:44:33',20.00,0.92),(459,146,19.08,'completed','2025-03-19 06:47:27',20.00,0.92),(460,147,6.36,'completed','2025-03-19 06:51:22',10.00,3.64),(461,148,45.58,'completed','2025-04-09 00:39:35',70.90,2.00),(462,149,8.48,'completed','2025-04-09 00:39:35',70.90,2.00),(463,150,3.18,'completed','2025-04-09 00:39:35',70.90,2.00),(464,151,8.48,'completed','2025-04-09 00:39:35',70.90,2.00),(465,152,3.18,'completed','2025-04-09 00:39:35',70.90,2.00);
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'all','Access to all system features'),(2,'manage_menu','Add, edit, or remove menu items'),(3,'view_sales','View sales reports and analytics'),(4,'manage_orders','Handle customer orders'),(5,'table_management_qr','Manage table assignments and generate QR codes'),(6,'kitchen_view','View and update kitchen order status');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=234 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_codes`
--

LOCK TABLES `qr_codes` WRITE;
/*!40000 ALTER TABLE `qr_codes` DISABLE KEYS */;
INSERT INTO `qr_codes` VALUES (63,30,'1e5f2194fb6346673d5c0f2a15a81497','table_15_1741613138.png','2025-03-10 21:25:38','2025-03-10 23:25:38',0),(65,30,'1e3f8d7bb0c37f8b689f9c3a3e3e4aaf','table_15_1741615282.png','2025-03-10 22:01:22','2025-03-11 00:01:22',0),(66,30,'c771f2d8db31bcdf25f3f3635f5d68f6','table_15_1741615681.png','2025-03-10 22:08:01','2025-03-11 00:08:01',1),(89,39,'67d180fa1b0e18bfab29112e85d79d40','table_2_1741833210.png','2025-03-13 10:33:30','2025-03-13 12:33:30',0),(93,40,'c5db7d2c90ae8b23b0d10e1640333662','table_19_1741835385.png','2025-03-13 11:09:45','2025-03-13 13:09:45',1),(96,42,'796f7b3df32a4a757cd5f3a992a6d88d','table_25_1741843821.png','2025-03-13 13:30:21','2025-03-13 13:32:20',0),(97,42,'4fca424e86d4198346ed4ccb41606eb5','table_25_1741844045.png','2025-03-13 13:34:05','2025-03-13 15:34:05',1),(172,27,'a3897944914a252c48ee63935d9a4e0a','table_12_1742365588.png','2025-03-19 14:26:28','2025-03-19 16:26:28',0),(173,33,'5fc45c42049bf789a564763427d8d2f2','table_16_1742365591.png','2025-03-19 14:26:31','2025-03-19 16:26:31',1),(174,34,'f81a708b084a73540fab1dd03726afb9','table_17_1742365593.png','2025-03-19 14:26:33','2025-03-19 16:26:33',0),(175,34,'75a6c85161d51089cc3b01b279757710','table_17_1742365678.png','2025-03-19 14:27:58','2025-03-19 16:27:58',0),(177,34,'fff8722706738f372e71b757b2760ff7','table_17_1742365737.png','2025-03-19 14:28:57','2025-03-19 16:28:57',1),(178,27,'d0234b5643d6fe07c43888d19c3d82d4','table_12_1742365801.png','2025-03-19 14:30:01','2025-03-19 16:30:01',0),(181,27,'b295b7dea639424a4214bd3a5aac2caf','table_12_1742366016.png','2025-03-19 14:33:36','2025-03-19 16:33:36',0),(189,27,'de9ac08ae5d43a8c32f31d1425975783','table_12_1742366345.png','2025-03-19 14:39:05','2025-03-19 16:39:05',0),(200,44,'e4af91a2fb0a704a8b12c1c8d0e3163e','table_29_1742367146.png','2025-03-19 14:52:26','2025-03-19 16:52:26',0),(201,41,'6bb78d04af2ba5ef2a931c60b7534cbf','table_20_1742373676.png','2025-03-19 16:41:16','2025-03-19 18:41:16',0),(202,41,'baeb77c1c4251c06a52a7a90778d10fd','table_20_1742374207.png','2025-03-19 16:50:07','2025-03-19 18:50:07',0),(203,41,'c39fb73b0321c750a1bee086acde933a','table_20_1742374233.png','2025-03-19 16:50:33','2025-03-19 18:50:33',1),(204,43,'10ec3ccf998369d31047ef5ddab92cd7','table_28_1742392024.png','2025-03-19 21:47:04','2025-03-19 23:47:04',1),(205,27,'4cc53d181d3fb6f5b21e57080c88cf24','table_12_1742392036.png','2025-03-19 21:47:16','2025-03-19 23:47:16',0),(206,27,'4408d8dc36dff6effe873413d92047b2','table_12_1742392561.png','2025-03-19 21:56:01','2025-03-19 23:56:01',1),(209,39,'231bceefe475398d7df470c96a8f8dbf','table_2_1742438336.png','2025-03-20 10:38:56','2025-03-20 12:38:56',1),(215,36,'e8b52db406e82f4583288a1062f5aca6','table_1_1744177369.png','2025-04-09 13:42:49','2025-04-09 15:42:49',1),(216,44,'ad927ff91ce7b177c9b5183ccef3c448','table_29_1755665617.png','2025-08-20 12:53:38','2025-08-20 14:53:38',0),(217,44,'a3a741b2eefc6cf6e88e8d4561d873f1','table_29_1756782034.png','2025-09-02 11:00:34','2025-09-02 13:00:34',0),(218,44,'25e5484526431e87286b433c83a08ba2','table_29_1756791973.png','2025-09-02 13:46:13','2025-09-02 15:46:13',0),(219,44,'cbf636a4d29e575c50c97d56b99bec40','table_29_1756791988.png','2025-09-02 13:46:28','2025-09-02 15:46:28',0),(220,44,'fe29133fa926a9be7488f061664db1e9','table_29_1756792024.png','2025-09-02 13:47:04','2025-09-02 15:47:04',0),(221,44,'5de3e7e13575a0f32bff13de1cfbe3ec','table_29_1756792028.png','2025-09-02 13:47:08','2025-09-02 15:47:08',0),(222,44,'e285b8643dfb6d12d9584ccd9e05f825','table_29_1756792198.png','2025-09-02 13:49:58','2025-09-02 15:49:58',1),(223,45,'1f4b01e0cbc03d462d820bc6bbd7f376','table_30_1756793003.png','2025-09-02 14:03:23','2025-09-02 16:03:23',0),(224,45,'5f56e2a50e46200dce32f51b67a866ad','table_30_1756793009.png','2025-09-02 14:03:29','2025-09-02 16:03:29',0),(225,45,'7d04c213336e76c40e3a966750fe5547','table_30_1756793045.png','2025-09-02 14:04:05','2025-09-02 16:04:05',0),(226,45,'41e3d149d42392f3c6db574e8d887f92','table_30_1756795519.png','2025-09-02 14:45:19','2025-09-02 16:45:19',0),(227,45,'1230b0634f967bf0772a8d9743248c1a','table_30_1756795600.png','2025-09-02 14:46:40','2025-09-02 16:46:40',0),(228,45,'a3620827bdb394b595d86332cb0de5bc','table_30_1756795648.png','2025-09-02 14:47:28','2025-09-02 16:47:28',0),(229,45,'aef5374bdce45adb94a1800eb23770ab','table_30_1756795693.png','2025-09-02 14:48:13','2025-09-02 16:48:13',0),(230,45,'bafe62a7e5da235ff11775f06e4c3e16','table_30_1756795865.png','2025-09-02 14:51:05','2025-09-02 16:51:05',0),(231,45,'9f3fc195263c182dbf3c974cbc518c6c','table_30_1756795874.png','2025-09-02 14:51:14','2025-09-02 16:51:14',0),(232,45,'730ca5afaad0e64b7b31e187b7c2e100','table_30_1756795879.png','2025-09-02 14:51:19','2025-09-02 16:51:19',0),(233,45,'29920a8662734f0d5a139b91827b9962','table_30_1756795923.png','2025-09-02 14:52:03','2025-09-02 16:52:03',1);
/*!40000 ALTER TABLE `qr_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_number` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `position` enum('manager','supervisor','waiter','kitchen') NOT NULL,
  `employment_type` enum('full-time','part-time') NOT NULL DEFAULT 'full-time',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_number` (`employee_number`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff`
--

LOCK TABLES `staff` WRITE;
/*!40000 ALTER TABLE `staff` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_permissions`
--

DROP TABLE IF EXISTS `staff_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_permissions` (
  `staff_id` int NOT NULL,
  `permission_id` int NOT NULL,
  PRIMARY KEY (`staff_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `staff_permissions_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`),
  CONSTRAINT `staff_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_permissions`
--

LOCK TABLES `staff_permissions` WRITE;
/*!40000 ALTER TABLE `staff_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_permissions` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tables`
--

LOCK TABLES `tables` WRITE;
/*!40000 ALTER TABLE `tables` DISABLE KEYS */;
INSERT INTO `tables` VALUES (27,12,'active','2025-03-10 03:46:48'),(30,15,'active','2025-03-10 13:25:38'),(33,16,'active','2025-03-12 03:15:08'),(34,17,'active','2025-03-12 05:17:15'),(36,1,'active','2025-03-13 02:13:03'),(39,2,'active','2025-03-13 02:33:30'),(40,19,'active','2025-03-13 02:48:03'),(41,20,'active','2025-03-13 03:10:10'),(42,25,'active','2025-03-13 05:15:41'),(43,28,'active','2025-03-13 05:34:35'),(44,29,'active','2025-03-13 06:20:15'),(45,30,'active','2025-09-02 06:03:23');
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

-- Dump completed on 2025-09-02 15:54:17
