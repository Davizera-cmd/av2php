CREATE DATABASE IF NOT EXISTS meu_ecommerce_db;
USE meu_ecommerce_db;

CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL, 
  `endereco` text,
  `foto` varchar(255) DEFAULT NULL,
  `pdf` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `produtos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `preco` decimal(10,2) NOT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `produtos` (`nome`, `preco`, `imagem`) VALUES
('Puxador Shell Zen - Vecchio Cobre 64mm', 75.25, 'puxador_shell.jpg'),
('Puxador Beetle Zen - Rosa 40mm', 55.25, 'puxador_beetle.jpg'),
('Puxador Sottile Zen - Fosco 160mm', 51.36, 'puxador_sottile.jpg'),
('Puxador Móveis Creta Zen - Níquel Escovado 192mm', 145.70, 'puxador_creta.jpg'),
('Puxador Citizen 45 Graus - Zen', 39.99, 'puxador_citizen.jpg'),
('Puxador Alça Manico di Cotello Zen -Gold Escovado', 281.50, 'puxador_alca.jpg');

CREATE TABLE IF NOT EXISTS carrinho_compras (
  id INT NOT NULL AUTO_INCREMENT,
  cliente_id INT DEFAULT NULL,
  produto_id INT DEFAULT NULL,
  quantidade INT NOT NULL,
  preco_unitario DECIMAL(10,2) NOT NULL,
  data_compra TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY (cliente_id),
  KEY (produto_id),
  CONSTRAINT fk_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `log_alteracoes_clientes` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `cliente_id` int DEFAULT NULL,
  `alterado_por_admin_id` int DEFAULT NULL COMMENT 'ID do admin que fez a alteração, se aplicável',
  `campo_alterado` varchar(100) NOT NULL,
  `valor_antigo` text,
  `valor_novo` text,
  `data_alteracao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`),
  KEY `cliente_id_log_fk` (`cliente_id`),
  KEY `admin_id_log_fk` (`alterado_por_admin_id`),
  CONSTRAINT `log_alteracoes_clientes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
