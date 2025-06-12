-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 12/06/2025 às 21:57
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `pdv_cardapio`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `adicionais`
--

CREATE TABLE `adicionais` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL DEFAULT 0.00,
  `permite_quantidade` tinyint(1) NOT NULL DEFAULT 0,
  `controla_estoque` tinyint(1) NOT NULL DEFAULT 1,
  `estoque` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `adicionais`
--

INSERT INTO `adicionais` (`id`, `nome`, `descricao`, `imagem`, `preco`, `permite_quantidade`, `controla_estoque`, `estoque`, `ativo`) VALUES
(1, 'Cebola Roxa', '', '/pdv/public/uploads/adicionais/68471332519b7_cebola.jpg', 3.00, 0, 0, 0, 1),
(2, 'Adicional de carne', '0', '/pdv/public/uploads/adicionais/6847abf388ec4_OIP.jpeg', 2.50, 0, 0, 0, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `adicionais_item_pedido`
--

CREATE TABLE `adicionais_item_pedido` (
  `id` int(11) NOT NULL,
  `id_item_pedido` int(11) NOT NULL,
  `id_adicional` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `preco_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `ordem` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categorias`
--

INSERT INTO `categorias` (`id`, `nome`, `ordem`) VALUES
(2, 'Bebidas', 2),
(3, 'Hamburguer Artesanal', 0),
(4, 'Fritas', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `endereco` varchar(255) NOT NULL,
  `ncasa` varchar(50) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `ponto_referencia` varchar(255) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `data_cadastro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`id`, `nome`, `telefone`, `endereco`, `ncasa`, `bairro`, `cep`, `ponto_referencia`, `complemento`, `data_cadastro`) VALUES
(9, 'Amon Tranquilli', '21977023133', 'Rua Alice Ribeiro', 'S/N', 'Campo Grande', '23090660', '', 'Quadra 24 Lote 18', '2025-06-03 23:29:01'),
(10, 'Mileni Reis', '21959110306', 'Rua Heitor da Motta ferreira', '317', 'Campo Grande', '', '', '', '2025-06-04 17:09:50');

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes_loja`
--

CREATE TABLE `configuracoes_loja` (
  `id` int(11) NOT NULL,
  `nome_hamburgueria` varchar(255) NOT NULL,
  `horario_funcionamento` varchar(255) DEFAULT NULL,
  `pedido_minimo` decimal(10,2) DEFAULT NULL,
  `hora_abertura` time DEFAULT '00:00:00',
  `hora_fechamento` time DEFAULT '23:59:59',
  `dias_abertura` varchar(255) DEFAULT '1,2,3,4,5,6,7',
  `taxa_entrega` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `configuracoes_loja`
--

INSERT INTO `configuracoes_loja` (`id`, `nome_hamburgueria`, `horario_funcionamento`, `pedido_minimo`, `hora_abertura`, `hora_fechamento`, `dias_abertura`, `taxa_entrega`) VALUES
(1, 'Prisma Lanches', 'Funcionamos de Quinta à Segunda das 18:00 até 23:59', 15.00, '00:00:00', '23:59:00', '1,2,3,4,5,6,7', 5.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `cupons`
--

CREATE TABLE `cupons` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `tipo_desconto` enum('porcentagem','valor_fixo','frete_gratis') NOT NULL,
  `valor_desconto` decimal(10,2) NOT NULL,
  `data_expiracao` datetime DEFAULT NULL,
  `usos_maximos` int(11) DEFAULT NULL,
  `usos_atuais` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `entregadores`
--

CREATE TABLE `entregadores` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `codigo_entregador` varchar(20) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `data_cadastro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `entregadores`
--

INSERT INTO `entregadores` (`id`, `nome`, `codigo_entregador`, `ativo`, `data_cadastro`) VALUES
(1, 'Amon Tranquilli', '1', 1, '2025-06-06 14:44:19');

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupos_opcoes`
--

CREATE TABLE `grupos_opcoes` (
  `id` int(11) NOT NULL,
  `id_produto_pai` int(11) NOT NULL,
  `nome_grupo` varchar(150) NOT NULL,
  `tipo_selecao` enum('UNICO','MULTIPLO') NOT NULL DEFAULT 'UNICO',
  `min_opcoes` int(11) NOT NULL DEFAULT 0,
  `max_opcoes` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `grupos_opcoes`
--

INSERT INTO `grupos_opcoes` (`id`, `id_produto_pai`, `nome_grupo`, `tipo_selecao`, `min_opcoes`, `max_opcoes`) VALUES
(4, 3, 'Turbine seu lanche com um COMBO', 'UNICO', 0, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens_grupo`
--

CREATE TABLE `itens_grupo` (
  `id` int(11) NOT NULL,
  `id_grupo_opcao` int(11) NOT NULL,
  `tipo` enum('SIMPLES','VINCULADO','COMBO') NOT NULL DEFAULT 'SIMPLES',
  `nome_item` varchar(150) NOT NULL,
  `preco_adicional` decimal(10,2) NOT NULL DEFAULT 0.00,
  `id_produto_vinculado` int(11) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `itens_grupo`
--

INSERT INTO `itens_grupo` (`id`, `id_grupo_opcao`, `tipo`, `nome_item`, `preco_adicional`, `id_produto_vinculado`, `ativo`) VALUES
(6, 4, 'COMBO', 'Combo Fritas + Coca lata 350ml', 9.99, NULL, 1),
(7, 4, 'COMBO', 'Combo Fritas + Coca lata Zero 350ml', 9.99, NULL, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens_grupo_combo`
--

CREATE TABLE `itens_grupo_combo` (
  `id` int(11) NOT NULL,
  `id_item_grupo` int(11) NOT NULL COMMENT 'ID do item na tabela itens_grupo (que é do tipo COMBO)',
  `id_produto_componente` int(11) NOT NULL COMMENT 'ID do produto que compõe este combo (ex: Fritas)',
  `quantidade` decimal(10,2) NOT NULL DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `itens_grupo_combo`
--

INSERT INTO `itens_grupo_combo` (`id`, `id_item_grupo`, `id_produto_componente`, `quantidade`) VALUES
(1, 6, 10, 1.00),
(2, 6, 9, 1.00),
(3, 7, 8, 1.00),
(4, 7, 10, 1.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens_pedido`
--

CREATE TABLE `itens_pedido` (
  `id` int(11) NOT NULL,
  `id_pedido` int(11) DEFAULT NULL,
  `id_produto` int(11) DEFAULT NULL,
  `nome_produto` varchar(255) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  `observacao_item` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `itens_pedido`
--

INSERT INTO `itens_pedido` (`id`, `id_pedido`, `id_produto`, `nome_produto`, `quantidade`, `preco_unitario`, `observacao_item`) VALUES
(174, 117, 3, 'Smash burguer', 1, 22.90, ''),
(175, 118, 4, 'Coca-Cola Zero 2Litros', 1, 15.00, ''),
(182, 124, 3, 'Smash burguer', 1, 35.39, ''),
(183, 125, 3, 'Smash burguer', 1, 45.89, '');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `nome_cliente` varchar(255) NOT NULL,
  `telefone_cliente` varchar(20) NOT NULL,
  `endereco_entrega` varchar(255) NOT NULL,
  `numero_entrega` varchar(50) DEFAULT NULL,
  `bairro_entrega` varchar(100) NOT NULL,
  `complemento_entrega` text DEFAULT NULL,
  `referencia_entrega` varchar(255) DEFAULT NULL,
  `data_pedido` datetime DEFAULT current_timestamp(),
  `total_pedido` decimal(10,2) NOT NULL,
  `forma_pagamento` varchar(50) NOT NULL,
  `troco_para` decimal(10,2) DEFAULT NULL,
  `troco` decimal(10,2) DEFAULT NULL,
  `observacoes_pedido` text DEFAULT NULL,
  `status` enum('pendente','preparando','em_entrega','finalizado','cancelado') NOT NULL DEFAULT 'pendente',
  `id_entregador` int(11) DEFAULT NULL,
  `arquivado` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pedidos`
--

INSERT INTO `pedidos` (`id`, `id_cliente`, `nome_cliente`, `telefone_cliente`, `endereco_entrega`, `numero_entrega`, `bairro_entrega`, `complemento_entrega`, `referencia_entrega`, `data_pedido`, `total_pedido`, `forma_pagamento`, `troco_para`, `troco`, `observacoes_pedido`, `status`, `id_entregador`, `arquivado`) VALUES
(117, 9, 'Amon Tranquilli', '21977023133', 'Rua Alice Ribeiro', 'S/N', 'Campo Grande', 'Quadra 24 Lote 18', '', '2025-06-10 03:16:09', 27.90, 'cartao', NULL, NULL, '', 'cancelado', NULL, 1),
(118, 9, 'Amon Tranquilli', '21977023133', 'Rua Alice Ribeiro', 'S/N', 'Campo Grande', 'Quadra 24 Lote 18', '', '2025-06-10 03:16:58', 20.00, 'cartao', NULL, NULL, '', 'cancelado', NULL, 1),
(124, 9, 'Amon Tranquilli', '21977023133', 'Rua Alice Ribeiro', 'S/N', 'Campo Grande', 'Quadra 24 Lote 18', '', '2025-06-12 11:24:32', 5.00, 'cartao', NULL, NULL, '', 'pendente', NULL, 0),
(125, 9, 'Amon Tranquilli', '21977023133', 'Rua Alice Ribeiro', 'S/N', 'Campo Grande', 'Quadra 24 Lote 18', '', '2025-06-12 14:50:51', 50.89, 'pix', NULL, NULL, '', 'pendente', NULL, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `estoque` int(11) NOT NULL DEFAULT 0,
  `controla_estoque` tinyint(1) NOT NULL DEFAULT 1,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `max_adicionais_opcionais` int(11) NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `nome`, `descricao`, `preco`, `id_categoria`, `imagem`, `estoque`, `controla_estoque`, `ativo`, `max_adicionais_opcionais`) VALUES
(2, 'Coca-Cola 2Litros', 'coquinha gelada', 15.00, 2, '/pdv/public/uploads/produtos/68373f542b325_Screenshot_5.png', 1, 1, 1, 10),
(3, 'Smash burguer', 'pao carne queijo ovo', 22.90, 3, '/pdv/public/uploads/produtos/6837b16a29945_Hamburgueria_Bob_Beef_-_Smash_Duplo_-_Foto_Tomas_Rangel.jpg', 0, 0, 1, 10),
(4, 'Coca-Cola Zero 2Litros', 'Coquinha zero', 15.00, 2, '/pdv/public/uploads/produtos/6837b4cbc0e5d_coca_cola_zero_pet_2l_23_1_490ec0e29bce8cc50dc0904868b15490.webp', 0, 0, 1, 10),
(5, 'Del Valle Uva 290ml', 'hmmmmm uvinha', 5.00, 2, '/pdv/public/uploads/produtos/6837c12474bae_dellvale uva.webp', 0, 0, 1, 10),
(6, 'X-tudo', 'burguer', 12.00, 3, '/pdv/public/uploads/produtos/683d2af63b84c_Hamburgueria_Bob_Beef_-_Smash_Duplo_-_Foto_Tomas_Rangel.jpg', 0, 0, 1, 10),
(7, 'Sprite 2 litros', '', 15.00, 2, '/pdv/public/uploads/produtos/6844f5616f7ee_Sprite.jpeg', 0, 0, 1, 10),
(8, 'Coca-Cola Zero Lata 350ml', '', 5.00, 2, '/pdv/public/uploads/produtos/6849c61633ef5_622533-coca-cola-zero-lata_1.jpg.webp', 0, 0, 1, 10),
(9, 'Coca-Cola lata 350ml', '', 5.00, 2, '/pdv/public/uploads/produtos/6849c5fa4c4eb_coca-cola-lata-350-ml-1.webp', 12, 1, 1, 10),
(10, 'Fritas M', 'Batata frita temperada no sal', 9.99, 4, '/pdv/public/uploads/produtos/6849c65b2f6d2_MC8421_2022-08-08_18_45_10_0_MC8421_0.webp', 0, 0, 1, 10),
(11, 'Refrigerante 2litros', 'Refrigerante', 0.00, 2, '/pdv/public/uploads/produtos/684a473ea28b6_1000000030.jpg', 0, 0, 1, 10);

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos_combo`
--

CREATE TABLE `produtos_combo` (
  `id` int(11) NOT NULL,
  `id_produto_pai` int(11) NOT NULL COMMENT 'O ID do produto que é o combo.',
  `id_produto_filho` int(11) NOT NULL COMMENT 'O ID do produto que faz parte do combo.',
  `quantidade_filho` decimal(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Quantas unidades do produto filho são consumidas.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto_adicional`
--

CREATE TABLE `produto_adicional` (
  `id` int(11) NOT NULL,
  `id_produto` int(11) NOT NULL,
  `id_adicional` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produto_adicional`
--

INSERT INTO `produto_adicional` (`id`, `id_produto`, `id_adicional`) VALUES
(29, 3, 2),
(30, 3, 1),
(31, 11, 2),
(32, 11, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome_usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel_acesso` varchar(20) NOT NULL DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome_usuario`, `senha`, `nivel_acesso`) VALUES
(1, 'Admin', '$2y$10$tdWSU9OtDoEKBL2cqlcyiudOVSr.VYian0kzE6gNaOvUHrAzj6bY.', 'admin');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `adicionais`
--
ALTER TABLE `adicionais`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `adicionais_item_pedido`
--
ALTER TABLE `adicionais_item_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_adicionais_item_pedido_item` (`id_item_pedido`),
  ADD KEY `fk_adicionais_item_pedido_adicional` (`id_adicional`);

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `configuracoes_loja`
--
ALTER TABLE `configuracoes_loja`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `cupons`
--
ALTER TABLE `cupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de tabela `entregadores`
--
ALTER TABLE `entregadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_entregador_unico` (`codigo_entregador`);

--
-- Índices de tabela `grupos_opcoes`
--
ALTER TABLE `grupos_opcoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_produto_pai` (`id_produto_pai`);

--
-- Índices de tabela `itens_grupo`
--
ALTER TABLE `itens_grupo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_grupo_opcao` (`id_grupo_opcao`),
  ADD KEY `id_produto_vinculado` (`id_produto_vinculado`);

--
-- Índices de tabela `itens_grupo_combo`
--
ALTER TABLE `itens_grupo_combo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_item_grupo` (`id_item_grupo`),
  ADD KEY `idx_id_produto_componente` (`id_produto_componente`);

--
-- Índices de tabela `itens_pedido`
--
ALTER TABLE `itens_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pedido` (`id_pedido`),
  ADD KEY `id_produto` (`id_produto`);

--
-- Índices de tabela `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_entregador` (`id_entregador`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_categoria` (`id_categoria`);

--
-- Índices de tabela `produtos_combo`
--
ALTER TABLE `produtos_combo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_produto_pai` (`id_produto_pai`),
  ADD KEY `idx_produto_filho` (`id_produto_filho`);

--
-- Índices de tabela `produto_adicional`
--
ALTER TABLE `produto_adicional`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_produto` (`id_produto`),
  ADD KEY `id_adicional` (`id_adicional`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome_usuario` (`nome_usuario`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `adicionais`
--
ALTER TABLE `adicionais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `adicionais_item_pedido`
--
ALTER TABLE `adicionais_item_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `configuracoes_loja`
--
ALTER TABLE `configuracoes_loja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `cupons`
--
ALTER TABLE `cupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `entregadores`
--
ALTER TABLE `entregadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `grupos_opcoes`
--
ALTER TABLE `grupos_opcoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `itens_grupo`
--
ALTER TABLE `itens_grupo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `itens_grupo_combo`
--
ALTER TABLE `itens_grupo_combo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `itens_pedido`
--
ALTER TABLE `itens_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

--
-- AUTO_INCREMENT de tabela `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `produtos_combo`
--
ALTER TABLE `produtos_combo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produto_adicional`
--
ALTER TABLE `produto_adicional`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `adicionais_item_pedido`
--
ALTER TABLE `adicionais_item_pedido`
  ADD CONSTRAINT `fk_adicionais_item_pedido_adicional` FOREIGN KEY (`id_adicional`) REFERENCES `adicionais` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_adicionais_item_pedido_item` FOREIGN KEY (`id_item_pedido`) REFERENCES `itens_pedido` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `grupos_opcoes`
--
ALTER TABLE `grupos_opcoes`
  ADD CONSTRAINT `grupos_opcoes_ibfk_1` FOREIGN KEY (`id_produto_pai`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `itens_grupo`
--
ALTER TABLE `itens_grupo`
  ADD CONSTRAINT `itens_grupo_ibfk_1` FOREIGN KEY (`id_grupo_opcao`) REFERENCES `grupos_opcoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itens_grupo_ibfk_2` FOREIGN KEY (`id_produto_vinculado`) REFERENCES `produtos` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `itens_grupo_combo`
--
ALTER TABLE `itens_grupo_combo`
  ADD CONSTRAINT `fk_combo_item_grupo` FOREIGN KEY (`id_item_grupo`) REFERENCES `itens_grupo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_combo_produto_componente` FOREIGN KEY (`id_produto_componente`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `itens_pedido`
--
ALTER TABLE `itens_pedido`
  ADD CONSTRAINT `itens_pedido_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itens_pedido_ibfk_2` FOREIGN KEY (`id_produto`) REFERENCES `produtos` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`id_entregador`) REFERENCES `entregadores` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `produtos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `produtos_combo`
--
ALTER TABLE `produtos_combo`
  ADD CONSTRAINT `fk_combo_filho` FOREIGN KEY (`id_produto_filho`) REFERENCES `produtos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_combo_pai` FOREIGN KEY (`id_produto_pai`) REFERENCES `produtos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `produto_adicional`
--
ALTER TABLE `produto_adicional`
  ADD CONSTRAINT `pa_adicional_fk` FOREIGN KEY (`id_adicional`) REFERENCES `adicionais` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pa_produto_fk` FOREIGN KEY (`id_produto`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
