CREATE TABLE IF NOT EXISTS `administradores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL, 
  `nome_completo` varchar(255) DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- O hash abaixo é para 'senhaSuperSecreta123'. Gerar um novo hash se mudar a senha.

INSERT INTO `administradores` (`usuario`, `senha`, `nome_completo`) VALUES
('admin', '$2y$10$TDfZI0UV4B8zS7GNPu/5te1Jns4J8qknk1bLfZjKZmlmYQsSHZb8q', 'Administrador Principal');
