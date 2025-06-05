<?php
session_start(); // Inicia a sessão para acessar o carrinho
require_once 'includes/conexao.php'; // Inclui o arquivo de conexão com o banco de dados

// Define o caminho base do projeto, consistente com o index.php
// ATENÇÃO: Se o seu projeto não está em um subdiretório como 'pdv' (ex: acessa direto por http://localhost/),\
// Mude esta variável para $basePath = '/';
$basePath = '/pdv/'; // <--- AJUSTE ESTA LINHA SE NECESSÁRIO!

// --- Lógica para buscar configurações da loja do banco de dados ---
$nome_hamburgueria = "Minha Hamburgueria"; // Valor padrão
$horario_funcionamento_descricao = "Horário não definido"; // Descrição textual
$pedido_minimo_valor = "0.00"; // Alterado para float para comparação
$hora_abertura_db = "00:00:00";
$hora_fechamento_db = "23:59:59";
$dias_abertura_db = "";

$sqlConfig = "SELECT nome_hamburgueria, horario_funcionamento, pedido_minimo, hora_abertura, hora_fechamento, dias_abertura FROM configuracoes_loja WHERE id = 1";
$resultConfig = $conn->query($sqlConfig);
if ($resultConfig && $resultConfig->num_rows > 0) {
    $config = $resultConfig->fetch_assoc();
    $nome_hamburgueria = htmlspecialchars($config['nome_hamburgueria']);
    $horario_funcionamento_descricao = htmlspecialchars($config['horario_funcionamento']);
    $pedido_minimo_valor = floatval($config['pedido_minimo']); // Converte para float
    $hora_abertura_db = $config['hora_abertura'];
    $hora_fechamento_db = $config['hora_fechamento'];
    $dias_abertura_db = $config['dias_abertura'];
}

// --- Lógica para determinar o status da loja (Aberta/Fechada) ---
date_default_timezone_set('America/Sao_Paulo'); // Defina seu fuso horário local
$current_time = new DateTime(); // Horário atual
$current_day_of_week = (int)$current_time->format('N'); // 1 (para Segunda-feira) a 7 (para Domingo)

$loja_status = "Loja Fechada"; // Status padrão
$loja_status_class = "fechada";
$is_loja_aberta = false; // Flag para controlar o status da loja

$dias_abertos_array = explode(',', $dias_abertura_db);

if (in_array($current_day_of_week, $dias_abertos_array)) {
    // A loja está configurada para abrir hoje
    $open_time = DateTime::createFromFormat('H:i:s', $hora_abertura_db);
    $close_time = DateTime::createFromFormat('H:i:s', $hora_fechamento_db);

    // Se o horário de fechamento for menor que o de abertura (ex: fecha na manhã do dia seguinte)
    if ($close_time < $open_time) {
        // Se a hora atual for maior que a de abertura OU menor que a de fechamento (passou da meia-noite)
        if ($current_time->format('H:i:s') >= $open_time->format('H:i:s') || $current_time->format('H:i:s') < $close_time->format('H:i:s')) {
            $loja_status = "Loja Aberta";
            $loja_status_class = "aberta";
            $is_loja_aberta = true;
        }
    } else {
        // Horário de fechamento no mesmo dia
        if ($current_time->format('H:i:s') >= $open_time->format('H:i:s') && $current_time->format('H:i:s') < $close_time->format('H:i:s')) {
            $loja_status = "Loja Aberta";
            $loja_status_class = "aberta";
            $is_loja_aberta = true;
        }
    }
}

// --- Lógica para processar requisições AJAX de estoque ---
if (isset($_POST['action']) && $_POST['action'] === 'check_stock') {
    header('Content-Type: application/json'); // Define o cabeçalho para JSON
    $carrinho = $_SESSION['carrinho'] ?? [];
    $errors = [];

    if (empty($carrinho)) {
        echo json_encode(['success' => false, 'message' => 'Carrinho vazio.']);
        exit();
    }

    foreach ($carrinho as $itemId => $item) {
        $produto_id = $item['id'];
        $quantidade_carrinho = $item['quantidade'];

        // Consulta o estoque atual do produto E a coluna 'controla_estoque'
        // A verificação de estoque só ocorrerá se 'controla_estoque' for 1
        $stmt = $conn->prepare("SELECT nome, estoque, controla_estoque FROM produtos WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $produto_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $produto_db = $result->fetch_assoc();
                $stmt->close();

                if ($produto_db) {
                    // Verifica se o produto tem controle de estoque (controla_estoque = 1)
                    if (isset($produto_db['controla_estoque']) && $produto_db['controla_estoque'] == 1) {
                        // Se controla estoque, verifica a quantidade
                        if (isset($produto_db['estoque'])) {
                            $nome_produto = htmlspecialchars($produto_db['nome']);
                            $quantidade_estoque = $produto_db['estoque'];

                            if ($quantidade_carrinho > $quantidade_estoque) {
                                $errors[] = "Não temos " . $quantidade_carrinho . " unidades de '" . $nome_produto . "' em estoque. Disponível: " . $quantidade_estoque . ".";
                            }
                        } else {
                            $errors[] = "Erro: Coluna 'estoque' não encontrada para o produto '" . htmlspecialchars($item['nome']) . "'. Verifique a estrutura da tabela 'produtos'.";
                            error_log("Erro: Coluna 'estoque' não encontrada para o produto ID: " . $produto_id);
                        }
                    }
                    // Se 'controla_estoque' for 0, não faz a verificação de quantidade.
                } else {
                    // Produto não encontrado no banco de dados (pode ter sido removido)
                    $errors[] = "O produto '" . htmlspecialchars($item['nome']) . "' não foi encontrado em nosso catálogo.";
                    error_log("Erro: Produto ID " . $produto_id . " não encontrado no banco de dados.");
                }
            } else {
                $errors[] = "Erro ao executar consulta de estoque para o produto '" . htmlspecialchars($item['nome']) . "': " . $stmt->error;
                error_log("Erro ao executar consulta de estoque para o produto ID " . $produto_id . ": " . $stmt->error);
            }
        } else {
            $errors[] = "Erro na preparação da consulta de estoque para o produto '" . htmlspecialchars($item['nome']) . "': " . $conn->error;
            error_log("Erro na preparação da consulta de estoque: " . $conn->error);
        }
    }

    if (empty($errors)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'messages' => $errors]);
    }
    exit(); // Encerra o script PHP após enviar a resposta JSON
}

// O carrinho é armazenado na sessão
// Não precisamos calcular o total aqui, o JS fará isso ao carregar
// Apenas para garantir que a variável de sessão existe.
$carrinho = $_SESSION['carrinho'] ?? [];

// Fechar a conexão, já que não faremos mais consultas diretas nesta página
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seu Carrinho - Minha Hamburgueria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="public/css/cardapio.css">
    <style>
        /* Regra universal para box-sizing: border-box */
        *, *::before, *::after {
            box-sizing: border-box;
        }

        /* Estilos específicos para o carrinho.php */
        body {
            background-color: #f8f8f8;
            padding-top: 60px; /* Espaço para o header fixo */
            padding-bottom: 90px; /* Espaço para o footer fixo */
            margin: 0; /* Garante que não haja margens extras */
        }
        .header-carrinho {
            background-color: #fff;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }
        .header-carrinho .back-arrow {
            font-size: 1.5em;
            color: #333;
            text-decoration: none;
            padding: 5px;
        }
        .header-carrinho h1 {
            margin: 0;
            font-size: 1.3em;
            color: #333;
            flex-grow: 1; /* Permite que o título ocupe o espaço central */
            text-align: center;
        }
        .header-carrinho .btn-limpar {
            background: none;
            border: none;
            color: #FF5722;
            font-weight: 600;
            font-size: 0.9em;
            cursor: pointer;
            padding: 5px;
            transition: color 0.2s ease;
        }
        .header-carrinho .btn-limpar:hover {
            color: #E64A19;
        }

        .container-carrinho {
            max-width: 600px; /* Reduzido para parecer mais com mobile */
            margin: 20px auto;
            padding: 0 15px; /* Padding lateral para o conteúdo */
        }
        .carrinho-vazio {
            text-align: center;
            font-size: 1.2em;
            color: #777;
            padding: 40px 20px;
            border: 1px dashed #ccc;
            border-radius: 8px;
            margin-bottom: 30px;
            background-color: #fff;
        }
        .carrinho-vazio .btn-continuar {
            display: inline-block;
            margin-top: 20px;
            background-color: #FF5722;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.2s ease;
        }
        .carrinho-vazio .btn-continuar:hover {
            background-color: #E64A19;
        }

        /* Novo estilo para os itens do carrinho (cards) */
        .carrinho-item-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 15px;
            display: flex;
            align-items: center; /* Centraliza verticalmente a imagem e o texto */
            padding: 15px;
            position: relative;
            overflow: hidden;
        }
        .carrinho-item-card .item-image {
            width: 70px; /* Imagem ligeiramente menor */
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 15px;
            flex-shrink: 0; /* Não permite que a imagem encolha */
        }
        .carrinho-item-card .item-details {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0; /* Permite que o item shrink abaixo do tamanho de seu conteúdo */
        }
        .carrinho-item-card .item-name {
            font-size: 1.1em;
            font-weight: 600;
            color: #333;
            margin-bottom: 3px; /* Margem reduzida */
            white-space: normal; /* Permite que o texto quebre linha */
            word-break: break-word; /* Força a quebra de palavras longas */
        }
        .carrinho-item-card .item-obs,
        .carrinho-item-card .item-adicionais {
            font-size: 0.8em; /* Fonte ligeiramente menor */
            color: #777;
            margin-bottom: 3px; /* Margem reduzida */
            display: block;
            white-space: normal; /* Permite que o texto quebre linha */
            word-break: break-word; /* Força a quebra de palavras longas */
        }
        .carrinho-item-card .item-price {
            font-size: 1em; /* Preço ligeiramente menor */
            font-weight: bold;
            color: #FF5722;
            margin-top: 8px; /* Margem reduzida */
        }

        .carrinho-item-card .item-actions {
            display: flex;
            flex-direction: column; /* Empilha o controle de quantidade e o botão de remover */
            align-items: flex-end; /* Alinha as ações à direita */
            gap: 5px; /* Espaçamento reduzido */
            margin-left: auto; /* Empurra para a direita */
            flex-shrink: 0;
        }
        .carrinho-item-card .quantidade-controle {
            display: flex;
            align-items: center;
            background-color: #f0f0f0;
            border-radius: 20px; /* Botões mais arredondados */
            overflow: hidden;
            padding: 0px; /* Removido padding extra para compactar */
        }
        .carrinho-item-card .quantidade-controle button {
            background-color: #f0f0f0;
            border: none;
            width: 30px; /* Botões ligeiramente menores */
            height: 30px;
            font-size: 1.1em; /* Tamanho da fonte ajustado */
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            box-sizing: border-box;
            color: #333;
        }
        .carrinho-item-card .quantidade-controle button:hover:not(:disabled) {
            background-color: #e0e0e0;
        }
        .carrinho-item-card .quantidade-controle button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .carrinho-item-card .quantidade-controle span {
            font-weight: bold;
            min-width: 20px; /* Largura mínima ligeiramente menor */
            text-align: center;
            font-size: 0.9em; /* Tamanho da fonte ajustado */
            padding: 0 2px; /* Pequeno padding horizontal para o número */
            color: #333;
        }
        .carrinho-item-card .btn-remover-item {
            background: none;
            border: none;
            color: #999;
            font-size: 1.1em; /* Ícone ligeiramente menor */
            cursor: pointer;
            transition: color 0.2s ease;
            padding: 3px; /* Padding menor */
        }
        .carrinho-item-card .btn-remover-item:hover {
            color: #dc3545;
        }

        /* Botão "Adicionar mais produtos" */
        .btn-add-more-products {
            display: block;
            width: calc(100% - 30px); /* Ajuste para padding do container */
            max-width: 570px; /* Mesma largura do container-carrinho - padding */
            margin: 20px auto 30px auto;
            background-color: #FF5722;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1em;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        .btn-add-more-products:hover {
            background-color: #E64A19;
            transform: translateY(-2px);
        }

        /* Rodapé Fixo (Avançar) */
        .footer-checkout {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            /* Estilos atualizados para o botão Avançar */
            background: linear-gradient(to right, #FF5722, #E64A19); /* Gradiente */
            color: white;
            padding: 18px 25px; /* Aumentado para ser maior */
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 -4px 15px rgba(0,0,0,0.2); /* Sombra mais forte */
            z-index: 1000;
            text-decoration: none;
            border: none;
            outline: none;
            font-size: 1.3em; /* Fonte maior */
            font-weight: bold;
            border-top-left-radius: 15px; /* Cantos arredondados na parte superior */
            border-top-right-radius: 15px;
            transition: all 0.3s ease; /* Transição suave para hover */
        }
        .footer-checkout:hover {
            background: linear-gradient(to right, #E64A19, #FF5722); /* Inverte o gradiente no hover */
            transform: translateY(-3px); /* Efeito de "levantar" */
            box-shadow: 0 -6px 20px rgba(0,0,0,0.3); /* Sombra ainda mais forte no hover */
        }
        .footer-checkout .checkout-text {
            font-size: 1em; /* Mantém o tamanho relativo ao pai */
            font-weight: bold;
        }
        .footer-checkout .checkout-total {
            font-size: 1.1em; /* Mantém o tamanho relativo ao pai */
            font-weight: bold;
        }

        /* Estilos para o Modal de Confirmação (mantidos do anterior) */
        .confirmation-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .confirmation-modal-overlay.visible {
            opacity: 1;
            visibility: visible;
        }

        .confirmation-modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 90%;
            text-align: center;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .confirmation-modal-overlay.visible .confirmation-modal-content {
            transform: translateY(0);
        }

        .confirmation-modal-content img {
            width: 100px; /* Ajuste o tamanho da imagem */
            height: auto;
            margin-bottom: 20px;
        }

        .confirmation-modal-content p {
            font-size: 1.1em;
            color: #333;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .confirmation-modal-buttons {
            display: flex;
            flex-direction: column; /* Botões em coluna para mobile */
            gap: 15px;
        }

        .confirmation-modal-buttons button {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .confirmation-modal-buttons .btn-confirm-yes {
            background-color: #FF5722; /* Cor primária do seu cardápio */
            color: white;
        }

        .confirmation-modal-buttons .btn-confirm-yes:hover {
            background-color: #E64A19;
            transform: translateY(-2px);
        }

        .confirmation-modal-buttons .btn-confirm-no {
            background-color: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
        }

        .confirmation-modal-buttons .btn-confirm-no:hover {
            background-color: #e0e0e0;
            transform: translateY(-2px);
        }

        /* Estilos do Modal de Loja Fechada */
        .store-closed-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .store-closed-modal-overlay.visible {
            opacity: 1;
            visibility: visible;
        }

        .store-closed-modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            max-width: 450px;
            width: 90%;
            text-align: center;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .store-closed-modal-overlay.visible .store-closed-modal-content {
            transform: translateY(0);
        }

        .store-closed-modal-content h2 {
            color: #FF5722;
            margin-bottom: 15px;
            font-size: 1.8em;
        }

        .store-closed-modal-content p {
            font-size: 1.1em;
            color: #333;
            margin-bottom: 20px;
        }

        .store-closed-modal-content .schedule-info {
            background-color: #f0f0f0;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: left;
        }

        .store-closed-modal-content .schedule-info h3 {
            color: #333;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.2em;
        }

        .store-closed-modal-content .schedule-info p {
            margin: 5px 0;
            font-size: 0.95em;
            color: #555;
        }

        .store-closed-modal-content .btn-ok {
            background-color: #FF5722;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            margin-top: 25px;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .store-closed-modal-content .btn-ok:hover {
            background-color: #E64A19;
            transform: translateY(-2px);
        }

        /* Estilos do Modal de Pedido Mínimo */
        .min-order-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .min-order-modal-overlay.visible {
            opacity: 1;
            visibility: visible;
        }

        .min-order-modal-content { /* CORRIGIDO: de .min-order-content para .min-order-modal-content */
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 90%;
            text-align: center;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .min-order-modal-overlay.visible .min-order-modal-content {
            transform: translateY(0);
        }

        .min-order-modal-content h2 {
            color: #FF5722;
            margin-bottom: 15px;
            font-size: 1.8em;
        }

        .min-order-modal-content p {
            font-size: 1.1em;
            color: #333;
            margin-bottom: 20px;
        }

        .min-order-modal-content .btn-ok {
            background-color: #FF5722;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            margin-top: 25px;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .min-order-modal-content .btn-ok:hover {
            background-color: #E64A19;
            transform: translateY(-2px);
        }

        /* NOVO: Estilos para o Modal de Erro de Estoque */
        .stock-error-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .stock-error-modal-overlay.visible {
            opacity: 1;
            visibility: visible;
        }

        .stock-error-modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            max-width: 450px;
            width: 90%;
            text-align: center; /* Centraliza o conteúdo */
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .stock-error-modal-overlay.visible .stock-error-modal-content {
            transform: translateY(0);
        }

        .stock-error-modal-content h2 {
            color: #FF5722;
            margin-bottom: 15px;
            font-size: 1.8em;
        }

        .stock-error-modal-content #stock-error-messages {
            margin-bottom: 20px; /* Adiciona espaço abaixo das mensagens */
        }

        .stock-error-modal-content #stock-error-messages p {
            font-size: 1.1em; /* Aumenta um pouco a fonte */
            color: #333; /* Cor mais escura para melhor contraste */
            margin: 8px 0; /* Espaçamento entre as mensagens */
            text-align: center; /* Centraliza o texto das mensagens */
        }

        .stock-error-modal-content .btn-ok {
            background-color: #FF5722;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            margin-top: 25px;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .stock-error-modal-content .btn-ok:hover {
            background-color: #E64A19;
            transform: translateY(-2px);
        }

        /* NOVO: Estilos para o Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 2001; /* Acima de outros modais */
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .loading-overlay.visible {
            opacity: 1;
            visibility: visible;
        }

        .loading-overlay .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #FF5722;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }

        .loading-overlay p {
            font-size: 1.1em;
            color: #555;
            font-weight: 600;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* NOVO: Estilos para o Modal de Mensagem Genérica (Erro de Conexão, etc.) */
        .message-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .message-modal-overlay.visible {
            opacity: 1;
            visibility: visible;
        }

        .message-modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 90%;
            text-align: center;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .message-modal-overlay.visible .message-modal-content {
            transform: translateY(0);
        }

        .message-modal-content h2 {
            color: #FF5722;
            margin-bottom: 15px;
            font-size: 1.8em;
        }

        .message-modal-content p {
            font-size: 1.1em;
            color: #333;
            margin-bottom: 20px;
        }

        .message-modal-content .btn-ok {
            background-color: #FF5722;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            margin-top: 25px;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .message-modal-content .btn-ok:hover {
            background-color: #E64A19;
            transform: translateY(-2px);
        }


        /* Responsividade (Ajustes para telas menores) */
        @media (max-width: 768px) {
            .header-carrinho h1 {
                font-size: 1.2em; /* Ligeiramente maior para melhor legibilidade */
            }
            .header-carrinho .btn-limpar {
                font-size: 0.85em; /* Ligeiramente maior */
            }
            .container-carrinho {
                padding: 0 10px; /* Padding reduzido para a tela não passar */
                max-width: none; /* Permite ocupar a largura total do pai */
            }
            .carrinho-item-card {
                padding: 10px; /* Padding reduzido no card do item */
                flex-direction: row;
                align-items: center;
            }
            .carrinho-item-card .item-image {
                width: 50px; /* Imagem menor para mobile */
                height: 50px;
                margin-right: 10px;
            }
            .carrinho-item-card .item-name {
                font-size: 0.95em; /* Texto do nome ligeiramente menor */
                margin-bottom: 2px;
            }
            .carrinho-item-card .item-obs,
            .carrinho-item-card .item-adicionais {
                font-size: 0.7em; /* Fonte menor */
                margin-bottom: 2px;
            }
            .carrinho-item-card .item-price {
                font-size: 0.85em; /* Fonte do preço menor */
                margin-top: 5px;
            }
            .carrinho-item-card .item-actions {
                gap: 3px; /* Espaçamento menor */
            }
            .carrinho-item-card .quantidade-controle {
                padding: 0; /* Garante que não há padding */
            }
            .carrinho-item-card .quantidade-controle button {
                width: 26px; /* Botões ainda menores */
                height: 26px;
                font-size: 0.9em;
            }
            .carrinho-item-card .quantidade-controle span {
                font-size: 0.8em;
                min-width: 16px; /* Largura mínima reduzida */
                padding: 0 1px; /* Padding ainda menor */
            }
            .carrinho-item-card .btn-remover-item {
                font-size: 0.95em; /* Ícone menor */
                padding: 2px; /* Padding menor */
            }
            .btn-add-more-products {
                font-size: 1em;
                padding: 12px 15px;
            }
            .footer-checkout {
                padding: 15px 20px; /* Aumentado o padding para mobile também */
                font-size: 1.2em; /* Fonte um pouco menor que desktop, mas maior que antes */
            }
            .footer-checkout .checkout-text {
                font-size: 1em;
            }
            .footer-checkout .checkout-total {
                font-size: 1.1em;
            }
            .confirmation-modal-content {
                padding: 20px;
            }
            .confirmation-modal-buttons button {
                font-size: 0.9em;
                padding: 10px 15px;
            }
            .store-closed-modal-content {
                padding: 20px;
            }
            .store-closed-modal-content h2 {
                font-size: 1.5em;
            }
            .store-closed-modal-content p {
                font-size: 1em;
            }
            .store-closed-modal-content .schedule-info h3 {
                font-size: 1.1em;
            }
            .store-closed-modal-content .schedule-info p {
                font-size: 0.85em;
            }
            .store-closed-modal-content .btn-ok {
                padding: 10px 20px;
                font-size: 0.9em;
            }
            .min-order-modal-content {
                padding: 20px;
            }
            .min-order-modal-content h2 {
                font-size: 1.5em;
            }
            .min-order-modal-content p {
                font-size: 1em;
            }
            .min-order-modal-content .btn-ok {
                padding: 10px 20px;
                font-size: 0.9em;
            }
             .stock-error-modal-content {
                padding: 20px;
            }
            .stock-error-modal-content h2 {
                font-size: 1.5em;
            }
            .stock-error-modal-content #stock-error-messages p {
                font-size: 0.9em;
            }
            .stock-error-modal-content .btn-ok {
                padding: 10px 20px;
                font-size: 0.9em;
            }
            .message-modal-content {
                padding: 20px;
            }
            .message-modal-content h2 {
                font-size: 1.5em;
            }
            .message-modal-content p {
                font-size: 1em;
            }
            .message-modal-content .btn-ok {
                padding: 10px 20px;
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>

<header class="header-carrinho">
    <a href="index.php" class="back-arrow"><i class="fas fa-chevron-left"></i></a>
    <h1>Carrinho</h1>
    <button id="btn-limpar-carrinho" class="btn-limpar">Limpar</button>
</header>

<div class="container-carrinho">
    <?php if (empty($carrinho)): ?>
        <div class="carrinho-vazio">
            <p>Seu carrinho está vazio. Adicione alguns produtos!</p>
            <a href="index.php" class="btn btn-continuar">Voltar ao Cardápio</a>
        </div>
    <?php else: ?>
        <div id="carrinho-itens-container">
            </div>

        <a href="index.php" class="btn-add-more-products">Adicionar mais produtos</a>
    <?php endif; ?>
</div>

<!-- O botão "Avançar" agora é um botão normal e o JavaScript controlará o redirecionamento -->
<button class="footer-checkout" id="btn-avancar-checkout">
    <span class="checkout-text">Avançar</span>
    <span class="checkout-total" id="total-carrinho-final">R$ 0,00</span>
</button>

<div id="confirmation-modal-overlay" class="confirmation-modal-overlay">
    <div class="confirmation-modal-content">
        <img src="https://placehold.co/100x100/FF5722/FFFFFF?text=!" alt="Ícone de Aviso">
        <p id="confirmation-message">Deseja remover este item do seu carrinho?</p>
        <div class="confirmation-modal-buttons">
            <button id="btn-confirm-yes" class="btn-confirm-yes">Sim, remover</button>
            <button id="btn-confirm-no" class="btn-confirm-no">Não</button>
        </div>
    </div>
</div>

<!-- Modal de Loja Fechada -->
<div id="store-closed-modal-overlay" class="store-closed-modal-overlay">
    <div class="store-closed-modal-content">
        <h2>Loja Fechada</h2>
        <p>Desculpe, não é possível realizar pedidos no momento.</p>
        <div class="schedule-info">
            <h3>Horário de Funcionamento:</h3>
            <p><?= htmlspecialchars($horario_funcionamento_descricao) ?></p>
            <p>Dias de Abertura:
                <?php
                $dias_semana = [
                    1 => 'Segunda-feira',
                    2 => 'Terça-feira',
                    3 => 'Quarta-feira',
                    4 => 'Quinta-feira',
                    5 => 'Sexta-feira',
                    6 => 'Sábado',
                    7 => 'Domingo'
                ];
                $dias_abertos_nomes = [];
                foreach ($dias_abertos_array as $dia_num) {
                    if (isset($dias_semana[$dia_num])) {
                        $dias_abertos_nomes[] = $dias_semana[$dia_num];
                    }
                }
                echo implode(', ', $dias_abertos_nomes);
                ?>
            </p>
        </div>
        <button id="btn-store-closed-ok" class="btn-ok">Entendi</button>
    </div>
</div>

<!-- Novo Modal de Pedido Mínimo Não Atingido -->
<div id="min-order-modal-overlay" class="min-order-modal-overlay">
    <div class="min-order-modal-content">
        <h2>Pedido Mínimo</h2>
        <p>O valor total do seu pedido não atingiu o mínimo necessário de <span id="min-order-value-display">R$ 0,00</span>.</p>
        <button id="btn-min-order-ok" class="btn-ok">Entendi</button>
    </div>
</div>

<!-- NOVO: Modal de Erro de Estoque -->
<div id="stock-error-modal-overlay" class="stock-error-modal-overlay">
    <div class="stock-error-modal-content">
        <h2>Problema de Estoque</h2>
        <div id="stock-error-messages">
            <!-- Mensagens de erro de estoque serão inseridas aqui pelo JS -->
        </div>
        <button id="btn-stock-error-ok" class="btn-ok">Entendi</button>
    </div>
</div>

<!-- NOVO: Modal de Mensagem Genérica (para erros de conexão, etc.) -->
<div id="message-modal-overlay" class="message-modal-overlay">
    <div class="message-modal-content">
        <h2 id="message-modal-title"></h2>
        <p id="message-modal-text"></p>
        <button id="btn-message-modal-ok" class="btn-ok">Entendi</button>
    </div>
</div>

<!-- NOVO: Overlay de Carregamento -->
<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
    <p>Verificando estoque...</p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- Lógica de Interação com a API do Carrinho ---
        const carrinhoItensContainer = document.getElementById('carrinho-itens-container');
        const totalCarrinhoFinal = document.getElementById('total-carrinho-final');
        const btnLimparCarrinho = document.getElementById('btn-limpar-carrinho');
        const btnAvancarCheckout = document.getElementById('btn-avancar-checkout');

        // Elementos do Modal de Confirmação
        const confirmationModalOverlay = document.getElementById('confirmation-modal-overlay');
        const confirmationMessage = document.getElementById('confirmation-message');
        const btnConfirmYes = document.getElementById('btn-confirm-yes');
        const btnConfirmNo = document.getElementById('btn-confirm-no');
        let itemIdToRemove = null;

        // Elementos do Modal de Loja Fechada
        const storeClosedModalOverlay = document.getElementById('store-closed-modal-overlay');
        const btnStoreClosedOk = document.getElementById('btn-store-closed-ok');

        // Elementos do Novo Modal de Pedido Mínimo
        const minOrderModalOverlay = document.getElementById('min-order-modal-overlay');
        const minOrderValueDisplay = document.getElementById('min-order-value-display');
        const btnMinOrderOk = document.getElementById('btn-min-order-ok');

        // NOVO: Elementos do Modal de Erro de Estoque e Loading
        const stockErrorModalOverlay = document.getElementById('stock-error-modal-overlay');
        const stockErrorMessagesDiv = document.getElementById('stock-error-messages');
        const btnStockErrorOk = document.getElementById('btn-stock-error-ok');
        const loadingOverlay = document.getElementById('loading-overlay');

        // NOVO: Elementos do Modal de Mensagem Genérica
        const messageModalOverlay = document.getElementById('message-modal-overlay');
        const messageModalTitle = document.getElementById('message-modal-title');
        const messageModalText = document.getElementById('message-modal-text');
        const btnMessageModalOk = document.getElementById('btn-message-modal-ok');


        // Variáveis PHP para o status da loja e pedido mínimo
        const isLojaAberta = <?php echo $is_loja_aberta ? 'true' : 'false'; ?>;
        const pedidoMinimo = parseFloat(<?php echo $pedido_minimo_valor; ?>); // Valor mínimo do PHP

        let currentCartTotal = 0; // Variável para armazenar o total atual do carrinho

        // NOVO: Função para exibir o modal de mensagem genérica
        function showMessageModal(title, message) {
            messageModalTitle.textContent = title;
            messageModalText.textContent = message;
            messageModalOverlay.classList.add('visible');
        }

        // Função para fazer requisições à API do carrinho
        async function gerenciarCarrinhoAPI(action, data = {}) {
            try {
                const response = await fetch('public/api/carrinho_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action, ...data })
                });
                const result = await response.json();
                if (!result.success) {
                    console.error('Erro na API do carrinho:', result.message);
                    // Usar o modal de mensagem genérica para erros da API
                    showMessageModal('Erro no Carrinho', result.message || 'Ocorreu um erro ao processar seu carrinho.');
                }
                return result;
            } catch (error) {
                console.error('Erro de rede ou servidor ao gerenciar carrinho:', error);
                showMessageModal('Erro de Conexão', 'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.');
                return { success: false, message: 'Erro de conexão com o servidor.' };
            }
        }

        // Função para atualizar o carrinho na interface, buscando dados do backend
        async function atualizarCarrinhoUI() {
            const result = await gerenciarCarrinhoAPI('get_cart');
            if (result.success) {
                const carrinhoDoBackend = result.cart;
                carrinhoItensContainer.innerHTML = '';
                currentCartTotal = 0; // Reseta o total

                if (carrinhoDoBackend.length === 0) {
                    // Se o carrinho estiver vazio, redireciona para recarregar a página e mostrar a mensagem de carrinho vazio
                    window.location.href = 'carrinho.php';
                    return;
                }

                carrinhoDoBackend.forEach(item => {
                    const itemCard = document.createElement('div');
                    itemCard.className = 'carrinho-item-card';
                    itemCard.setAttribute('data-item-id', item.item_carrinho_id);

                    let adicionaisHtml = '';
                    if (item.adicionais && item.adicionais.length > 0) {
                        const adicionaisNomes = item.adicionais.map(ad => `${ad.nome} (+R$ ${parseFloat(ad.preco).toFixed(2).replace('.', ',')})`);
                        adicionaisHtml = `<span class="item-adicionais">Adicionais: ${adicionaisNomes.join(', ')}</span>`;
                    }

                    const disableDiminuir = item.quantidade === 1 ? 'disabled' : '';
                    const imageUrl = item.imagem && item.imagem !== '' ? item.imagem : 'https://placehold.co/80x80/FF5722/FFFFFF?text=Item';

                    // Calcula o preço total do item (quantidade * preço base + adicionais)
                    let itemTotalPrice = parseFloat(item.preco_unitario) * item.quantidade;
                    if (item.adicionais && item.adicionais.length > 0) {
                        item.adicionais.forEach(ad => {
                            itemTotalPrice += parseFloat(ad.preco) * item.quantidade; // Adiciona o preço do adicional pela quantidade do item
                        });
                    }


                    itemCard.innerHTML = `
                        <img src="${imageUrl}" alt="${item.nome}" class="item-image" onerror="this.onerror=null;this.src='https://placehold.co/80x80/cccccc/333333?text=Sem+Imagem';">
                        <div class="item-details">
                            <span class="item-name">${item.quantidade}x ${item.nome}</span>
                            ${item.obs ? `<span class="item-obs">Obs: ${item.obs}</span>` : ''}
                            ${adicionaisHtml}
                            <span class="item-price">R$ ${itemTotalPrice.toFixed(2).replace('.', ',')}</span>
                        </div>
                        <div class="item-actions">
                            <div class="quantidade-controle">
                                <button class="diminuir-quantidade" data-item-id="${item.item_carrinho_id}" ${disableDiminuir}>−</button>
                                <span class="quantidade-item">${item.quantidade}</span>
                                <button class="aumentar-quantidade" data-item-id="${item.item_carrinho_id}">+</button>
                            </div>
                            <button class="btn-remover-item" data-item-id="${item.item_carrinho_id}" data-item-nome="${item.nome}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                    carrinhoItensContainer.appendChild(itemCard);

                    currentCartTotal += itemTotalPrice;
                });

                totalCarrinhoFinal.textContent = `R$ ${currentCartTotal.toFixed(2).replace('.', ',')}`;
                minOrderValueDisplay.textContent = `R$ ${pedidoMinimo.toFixed(2).replace('.', ',')}`; // Atualiza o valor no modal

                // Adiciona/Re-adiciona listeners de evento
                addEventListenersToCartItems();

            } else {
                carrinhoItensContainer.innerHTML = '<p class="carrinho-vazio">Erro ao carregar carrinho. Por favor, tente novamente.</p>';
                totalCarrinhoFinal.textContent = 'R$ 0,00';
                btnAvancarCheckout.style.display = 'none';
            }
        }

        // Função para adicionar listeners aos botões de controle de quantidade e remover
        function addEventListenersToCartItems() {
            document.querySelectorAll('.diminuir-quantidade').forEach(btn => {
                btn.addEventListener('click', async (event) => {
                    const itemId = event.target.getAttribute('data-item-id');
                    await gerenciarCarrinhoAPI('update_quantity', { itemId, quantity: 1, action_type: 'decrease' });
                    atualizarCarrinhoUI();
                });
            });

            document.querySelectorAll('.aumentar-quantidade').forEach(btn => {
                btn.addEventListener('click', async (event) => {
                    const itemId = event.target.getAttribute('data-item-id');
                    await gerenciarCarrinhoAPI('update_quantity', { itemId, quantity: 1, action_type: 'increase' });
                    atualizarCarrinhoUI();
                });
            });

            document.querySelectorAll('.btn-remover-item').forEach(btn => {
                btn.addEventListener('click', (event) => {
                    itemIdToRemove = event.target.closest('.btn-remover-item').getAttribute('data-item-id');
                    const itemName = event.target.closest('.btn-remover-item').getAttribute('data-item-nome');
                    confirmationMessage.textContent = `Deseja remover "${itemName}" do seu carrinho?`;
                    confirmationModalOverlay.classList.add('visible');
                });
            });
        }

        // Eventos para os botões do modal de confirmação
        btnConfirmYes.addEventListener('click', async () => {
            if (itemIdToRemove) {
                await gerenciarCarrinhoAPI('remove_item', { itemId: itemIdToRemove });
                atualizarCarrinhoUI();
                itemIdToRemove = null;
            }
            confirmationModalOverlay.classList.remove('visible');
        });

        btnConfirmNo.addEventListener('click', () => {
            itemIdToRemove = null;
            confirmationModalOverlay.classList.remove('visible');
        });

        // Evento para o botão "Limpar Carrinho" no header
        btnLimparCarrinho.addEventListener('click', async () => {
            confirmationMessage.textContent = 'Tem certeza que deseja limpar todo o carrinho?';
            btnConfirmYes.onclick = async () => {
                await gerenciarCarrinhoAPI('clear_cart');
                atualizarCarrinhoUI();
                confirmationModalOverlay.classList.remove('visible');
                btnConfirmYes.onclick = null;
            };
            btnConfirmNo.onclick = () => {
                confirmationModalOverlay.classList.remove('visible');
                btnConfirmNo.onclick = null;
            };
            confirmationModalOverlay.classList.add('visible');
        });

        // Evento para o botão "Avançar" do checkout
        btnAvancarCheckout.addEventListener('click', async (e) => { // Adicionado 'async'
            e.preventDefault(); // Impede o redirecionamento imediato

            if (!isLojaAberta) {
                // 1. Loja Fechada
                storeClosedModalOverlay.classList.add('visible');
            } else if (currentCartTotal < pedidoMinimo) {
                // 2. Pedido Mínimo Não Atingido
                minOrderModalOverlay.classList.add('visible');
            } else {
                // 3. Tudo certo com loja aberta e pedido mínimo, agora verifica o estoque via AJAX
                loadingOverlay.classList.add('visible'); // Mostra o loading

                try {
                    const response = await fetch('carrinho.php', { // Requisição para o próprio carrinho.php
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded', // Formato de dados para POST
                        },
                        body: 'action=check_stock' // Envia a ação para o PHP
                    });

                    const data = await response.json();

                    loadingOverlay.classList.remove('visible'); // Esconde o loading

                    if (data.success) {
                        // Se o estoque está ok, redireciona
                        window.location.href = 'pre-checkout.php';
                    } else {
                        // Se há problemas de estoque, exibe o modal de erro de estoque
                        stockErrorMessagesDiv.innerHTML = ''; // Limpa mensagens antigas
                        data.messages.forEach(msg => {
                            const p = document.createElement('p');
                            p.textContent = msg;
                            stockErrorMessagesDiv.appendChild(p);
                        });
                        stockErrorModalOverlay.classList.add('visible');
                    }
                } catch (error) {
                    console.error('Erro na verificação de estoque:', error);
                    loadingOverlay.classList.remove('visible');
                    // Usa o modal de mensagem genérica para erros de conexão
                    showMessageModal('Erro de Conexão', 'Não foi possível conectar ao servidor para verificar o estoque. Tente novamente.');
                }
            }
        });

        // Evento para o botão "Entendi" do modal de loja fechada
        btnStoreClosedOk.addEventListener('click', () => {
            storeClosedModalOverlay.classList.remove('visible');
        });

        // Evento para o botão "Entendi" do modal de pedido mínimo
        btnMinOrderOk.addEventListener('click', () => {
            minOrderModalOverlay.classList.remove('visible');
        });

        // NOVO: Evento para o botão "Entendi" do modal de erro de estoque
        btnStockErrorOk.addEventListener('click', () => {
            stockErrorModalOverlay.classList.remove('visible');
        });

        // NOVO: Evento para o botão "Entendi" do modal de mensagem genérica
        btnMessageModalOk.addEventListener('click', () => {
            messageModalOverlay.classList.remove('visible');
        });


        // Inicializa a UI do carrinho ao carregar a página
        atualizarCarrinhoUI();
    });
</script>
</body>
</html>
