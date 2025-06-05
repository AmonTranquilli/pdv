<?php
session_start();
require_once 'includes/conexao.php'; // Caminho para a conexão com o banco de dados

// Redireciona se o carrinho estiver vazio
if (!isset($_SESSION['carrinho']) || empty($_SESSION['carrinho'])) {
    $_SESSION['mensagem_erro'] = "Seu carrinho está vazio. Adicione produtos antes de finalizar o pedido.";
    header("Location: carrinho.php");
    exit();
}

$carrinho = $_SESSION['carrinho'];
$total_pedido = 0;
foreach ($carrinho as $item) {
    $total_pedido += $item['preco'] * $item['quantidade'];
}

$mensagem_sucesso = '';
$mensagem_erro = '';

// Variáveis para pré-preencher o formulário em caso de erro ou de cliente existente
$nome_cliente = $_POST['nome_cliente'] ?? '';
$telefone_cliente = $_POST['telefone_cliente'] ?? ''; // Este é o telefone do formulário final
$endereco_entrega = $_POST['endereco_entrega'] ?? '';
$numero_entrega = $_POST['numero_entrega'] ?? '';
$bairro_entrega = $_POST['bairro_entrega'] ?? '';
$cep_entrega = $_POST['cep_entrega'] ?? '';
$complemento_entrega = $_POST['complemento_entrega'] ?? '';
$observacoes_pedido = $_POST['observacoes_pedido'] ?? '';
$forma_pagamento = $_POST['forma_pagamento'] ?? '';
$troco_para = $_POST['troco_para'] ?? '';
$nao_possui_numero_casa = isset($_POST['nao_possui_numero_casa']) ? true : false;
$is_new_client = isset($_POST['is_new_client']) ? (bool)$_POST['is_new_client'] : false; // Flag para novo cliente

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Coleta e sanitiza os dados do formulário
    $nome_cliente = trim(filter_var($_POST['nome_cliente'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $telefone_cliente = trim(filter_var($_POST['telefone_cliente'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)); // Telefone do campo oculto
    $endereco_entrega = trim(filter_var($_POST['endereco_entrega'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $bairro_entrega = trim(filter_var($_POST['bairro_entrega'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $cep_entrega = trim(filter_var($_POST['cep_entrega'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $observacoes_pedido = trim(filter_var($_POST['observacoes_pedido'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $forma_pagamento = trim(filter_var($_POST['forma_pagamento'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $troco_para = filter_var($_POST['troco_para'], FILTER_VALIDATE_FLOAT);

    // Lógica para 'numero_entrega' e 'complemento_entrega'
    $nao_possui_numero_casa = isset($_POST['nao_possui_numero_casa']) ? true : false;
    $numero_entrega_param = $nao_possui_numero_casa ? NULL : (empty(trim($_POST['numero_entrega'])) ? NULL : trim(filter_var($_POST['numero_entrega'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
    $complemento_entrega_param = empty(trim($_POST['complemento_entrega'])) ? NULL : trim(filter_var($_POST['complemento_entrega'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));


    // Validações
    if (empty($nome_cliente) || empty($telefone_cliente) || empty($endereco_entrega) || empty($bairro_entrega) || empty($forma_pagamento)) {
        $mensagem_erro = "Por favor, preencha todos os campos obrigatórios (Nome, Telefone, Endereço, Bairro, Forma de Pagamento).";
    } elseif ($forma_pagamento === 'dinheiro' && ($troco_para === false || $troco_para < $total_pedido)) {
        $mensagem_erro = "Para pagamento em dinheiro, o valor do troco deve ser válido e maior ou igual ao total do pedido.";
    } elseif (!$nao_possui_numero_casa && empty(trim($_POST['numero_entrega']))) {
        $mensagem_erro = "O campo 'Número' é obrigatório, a menos que você marque 'Não possui número da casa'.";
    } elseif ($nao_possui_numero_casa && empty(trim($_POST['complemento_entrega']))) {
        $mensagem_erro = "O campo 'Complemento' é obrigatório quando o cliente não possui número da casa.";
    } else {
        // Inicia uma transação
        $conn->begin_transaction();

        try {
            // Lógica para inserir ou atualizar cliente na tabela 'clientes'
            // Sempre verifica se o cliente existe pelo telefone
            $stmt_check_client = $conn->prepare("SELECT id FROM clientes WHERE telefone = ?");
            $stmt_check_client->bind_param("s", $telefone_cliente);
            $stmt_check_client->execute();
            $result_check_client = $stmt_check_client->get_result();
            
            if ($result_check_client->num_rows == 0) {
                // Cliente não existe, insere novo cliente
                // ATENÇÃO: AQUI FOI AJUSTADO 'numero' PARA 'ncasa'
                $stmt_insert_client = $conn->prepare("INSERT INTO clientes (nome, telefone, endereco, ncasa, bairro, cep, complemento) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_insert_client->bind_param("sssssss", 
                    $nome_cliente, 
                    $telefone_cliente, 
                    $endereco_entrega, 
                    $numero_entrega_param, // Usando o parâmetro que pode ser NULL
                    $bairro_entrega, 
                    $cep_entrega, 
                    $complemento_entrega_param
                );
                if (!$stmt_insert_client->execute()) {
                    throw new Exception("Erro ao cadastrar cliente: " . $stmt_insert_client->error);
                }
                $stmt_insert_client->close();
            } else {
                // Cliente existe, atualiza os dados (opcional, dependendo da sua regra de negócio)
                // Se o cliente existe e o formulário permite edição, você pode adicionar um UPDATE aqui.
                // Por simplicidade, vamos apenas garantir que o nome esteja atualizado se for o caso.
                $stmt_update_client = $conn->prepare("UPDATE clientes SET nome = ?, endereco = ?, ncasa = ?, bairro = ?, cep = ?, complemento = ? WHERE telefone = ?");
                $stmt_update_client->bind_param("sssssss",
                    $nome_cliente,
                    $endereco_entrega,
                    $numero_entrega_param,
                    $bairro_entrega,
                    $cep_entrega,
                    $complemento_entrega_param,
                    $telefone_cliente
                );
                if (!$stmt_update_client->execute()) {
                    // Não lança exceção fatal se a atualização falhar, apenas loga ou ignora
                    error_log("Erro ao atualizar cliente existente: " . $stmt_update_client->error);
                }
                $stmt_update_client->close();
            }
            $stmt_check_client->close();

            // 1. Inserir o pedido na tabela 'pedidos'
            $stmt_pedido = $conn->prepare("INSERT INTO pedidos (nome_cliente, telefone_cliente, endereco_entrega, numero_entrega, bairro_entrega, cep_entrega, complemento_entrega, observacoes_pedido, total_pedido, forma_pagamento, troco_para, data_pedido, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pendente')");
            
            // Tratamento do troco_para para ser NULL se não for dinheiro ou não for necessário
            $troco_para_param = ($forma_pagamento === 'dinheiro' && $troco_para > $total_pedido) ? $troco_para : NULL;

            $stmt_pedido->bind_param("ssssssds", 
                $nome_cliente, 
                $telefone_cliente, 
                $endereco_entrega, 
                $numero_entrega_param, // Pode ser NULL
                $bairro_entrega, 
                $cep_entrega, 
                $complemento_entrega_param, // Pode ser NULL
                $observacoes_pedido, 
                $total_pedido, 
                $forma_pagamento, 
                $troco_para_param
            );

            if (!$stmt_pedido->execute()) {
                throw new Exception("Erro ao registrar o pedido: " . $stmt_pedido->error);
            }
            $pedido_id = $conn->insert_id; // Obtém o ID do pedido recém-inserido
            $stmt_pedido->close();

            // 2. Inserir os itens do pedido na tabela 'itens_pedido' e atualizar estoque
            $stmt_item = $conn->prepare("INSERT INTO itens_pedido (id_pedido, id_produto, nome_produto, quantidade, preco_unitario, observacao_item) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_update_estoque = $conn->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND controla_estoque = 1");

            foreach ($carrinho as $item) {
                // Inserir item do pedido
                $stmt_item->bind_param("iisdds", 
                    $pedido_id, 
                    $item['id'], 
                    $item['nome'], 
                    $item['quantidade'], 
                    $item['preco_unitario'], 
                    $item['obs']
                );
                if (!$stmt_item->execute()) {
                    throw new Exception("Erro ao registrar item do pedido " . $item['nome'] . ": " . $stmt_item->error);
                }

                // Atualizar estoque (se o produto controla estoque)
                $stmt_update_estoque->bind_param("ii", $item['quantidade'], $item['id']);
                if (!$stmt_update_estoque->execute()) {
                    throw new Exception("Erro ao atualizar estoque do produto " . $item['nome'] . ": " . $stmt_update_estoque->error);
                }
            }
            $stmt_item->close();
            $stmt_update_estoque->close();

            // 3. Commit da transação
            $conn->commit();
            $_SESSION['carrinho'] = []; // Limpa o carrinho após o sucesso
            $mensagem_sucesso = "Pedido #" . $pedido_id . " realizado com sucesso! Você será redirecionado em breve.";

            // Redireciona para uma página de sucesso ou para o cardápio
            header("Refresh: 3; url=index.php"); // Redireciona após 3 segundos
            exit();

        } catch (Exception $e) {
            // Rollback em caso de erro
            $conn->rollback();
            $mensagem_erro = "Falha ao finalizar o pedido: " . $e->getMessage();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Pedido - Minha Hamburgueria</title>
    <link rel="stylesheet" href="public/css/cardapio.css">
    <style>
        /* Estilos específicos para o checkout.php */
        body {
            background-color: #f8f8f8;
            padding-top: 20px; /* Ajustado para remover o padding do cabeçalho fixo */
        }
        .container-checkout {
            max-width: 800px;
            margin: 30px auto;
            padding: 25px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 2.2em;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        .form-group input[type="text"],
        .form-group input[type="tel"],
        .form-group textarea,
        .form-group select,
        .form-group input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1em;
            color: #333;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #28a745;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-row .form-group {
            flex: 1;
            min-width: 250px;
            margin-bottom: 0;
        }
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .form-check input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }
        .form-check label {
            margin-bottom: 0;
            font-weight: normal;
        }
        .resumo-pedido {
            border-top: 1px solid #eee;
            padding-top: 20px;
            margin-top: 30px;
        }
        .resumo-pedido h2 {
            font-size: 1.8em;
            margin-bottom: 20px;
            color: #333;
        }
        .resumo-pedido ul {
            list-style: none;
            padding: 0;
            margin-bottom: 20px;
        }
        .resumo-pedido li {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #f0f0f0;
            font-size: 1.1em;
            color: #555;
        }
        .resumo-pedido li:last-child {
            border-bottom: none;
        }
        .resumo-pedido .total {
            font-size: 1.6em;
            font-weight: bold;
            text-align: right;
            color: #28a745;
            margin-top: 15px;
        }
        .botoes-checkout {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        .botoes-checkout .btn {
            flex: 1;
            min-width: 200px;
            padding: 15px 25px;
            font-size: 1.2em;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .botoes-checkout .btn-voltar {
            background-color: #6c757d;
            color: white;
        }
        .botoes-checkout .btn-voltar:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .botoes-checkout .btn-finalizar {
            background-color: #28a745;
            color: white;
        }
        .botoes-checkout .btn-finalizar:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .mensagem {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        .mensagem.sucesso {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .mensagem.erro {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Estilos para a tela de identificação (Passo 1) */
        .identification-step {
            text-align: center;
            margin-bottom: 30px;
        }
        .identification-step .input-group {
            margin-bottom: 20px;
        }
        .identification-step .input-group label {
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        .identification-step .input-group input {
            max-width: 350px;
            margin: 0 auto;
        }
        .identification-step .btn-avancar {
            background-color: #FF5722;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.2em;
            font-weight: 600;
            transition: background-color 0.3s ease;
            width: 100%;
            max-width: 350px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .identification-step .btn-avancar:hover {
            background-color: #E64A19;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .identification-step .privacy-text {
            font-size: 0.9em;
            color: #777;
            margin-top: 20px;
            padding: 0 20px;
        }
        /* Estilo para o spinner de carregamento */
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #FF5722;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-left: 10px;
            display: none; /* Oculto por padrão */
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* Estilo para campos readonly */
        .readonly-field {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        /* Estilos para a tela de revisão (Passo 2) */
        .review-step {
            /* Estilos para a nova etapa */
        }

        .address-confirmation {
            background-color: #f0f8ff;
            border: 1px solid #cceeff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
        }
        .address-confirmation p {
            margin: 0;
            font-size: 1.1em;
            color: #333;
            flex-grow: 1;
            text-align: left;
        }
        .address-confirmation .btn-edit-address {
            background-color: #007bff;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s ease;
            white-space: nowrap; /* Evita que o botão quebre a linha */
        }
        .address-confirmation .btn-edit-address:hover {
            background-color: #0056b3;
        }

        .coupon-section {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .coupon-section label {
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
        }
        .coupon-section input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .container-checkout {
                margin: 20px auto;
                padding: 15px;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .form-row .form-group {
                min-width: unset;
                width: 100%;
                margin-bottom: 15px;
            }
            .botoes-checkout {
                flex-direction: column;
            }
            .botoes-checkout .btn {
                min-width: unset;
                width: 100%;
            }
            .identification-step .input-group input,
            .identification-step .btn-avancar {
                max-width: 100%; /* Ocupa toda a largura disponível em mobile */
            }
            .address-confirmation {
                flex-direction: column;
                align-items: stretch;
            }
            .address-confirmation .btn-edit-address {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="container-checkout">
    <h1>Finalizar Pedido</h1>

    <?php if (!empty($mensagem_sucesso)): ?>
        <p class="mensagem sucesso"><?= htmlspecialchars($mensagem_sucesso) ?></p>
    <?php endif; ?>
    <?php if (!empty($mensagem_erro)): ?>
        <p class="mensagem erro"><?= htmlspecialchars($mensagem_erro) ?></p>
    <?php endif; ?>

    <form action="checkout.php" method="POST" id="checkout-form">
        <input type="hidden" name="is_new_client" id="is_new_client" value="<?= $is_new_client ? '1' : '0' ?>">
        
        <div id="identification-step">
            <h2>Identifique-se e Informe seu Endereço</h2>
            <div class="input-group">
                <label for="telefone_cliente_initial">Seu número de WhatsApp é:</label>
                <input type="tel" id="telefone_cliente_initial" name="telefone_cliente_initial" value="<?= htmlspecialchars($telefone_cliente) ?>" placeholder="(XX) XXXXX-XXXX" pattern="\(\d{2}\)\s?\d{5}-\d{4}" maxlength="15" required>
                <small>Formato: (21) 12345-6789</small>
                <span class="loading-spinner" id="phone-loading-spinner"></span>
            </div>

            <div id="address-fields-group">
                <div class="form-group">
                    <label for="nome_cliente_final">Seu nome e sobrenome:</label>
                    <input type="text" id="nome_cliente_final" name="nome_cliente" value="<?= htmlspecialchars($nome_cliente) ?>" placeholder="Nome e sobrenome" required>
                </div>
                <div class="form-group">
                    <label for="endereco_entrega">Rua/Avenida:</label>
                    <input type="text" id="endereco_entrega" name="endereco_entrega" value="<?= htmlspecialchars($endereco_entrega) ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group checkbox-and-input">
                        <div class="form-check">
                            <input type="checkbox" id="nao_possui_numero_casa" name="nao_possui_numero_casa" value="1" <?= $nao_possui_numero_casa ? 'checked' : '' ?>>
                            <label for="nao_possui_numero_casa">Não possui número da casa</label>
                        </div>
                        <div id="numero_entrega_group">
                            <label for="numero_entrega">Número:</label>
                            <input type="text" id="numero_entrega" name="numero_entrega" value="<?= htmlspecialchars($numero_entrega) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bairro_entrega">Bairro:</label>
                        <input type="text" id="bairro_entrega" name="bairro_entrega" value="<?= htmlspecialchars($bairro_entrega) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="cep_entrega">CEP (Opcional):</label>
                    <input type="text" id="cep_entrega" name="cep_entrega" value="<?= htmlspecialchars($cep_entrega) ?>">
                </div>
                <div class="form-group">
                    <label for="complemento_entrega">Complemento (Ex: Apt 101, Bloco B):</label>
                    <input type="text" id="complemento_entrega" name="complemento_entrega" value="<?= htmlspecialchars($complemento_entrega) ?>">
                </div>
            </div>
            <button type="button" id="btn-avancar-identificacao" class="btn-avancar">Avançar para Revisão</button>
            <p class="privacy-text">Para realizar seu pedido vamos precisar de suas informações, este é um ambiente protegido.</p>
        </div>

        <div id="review-step" style="display: none;">
            <input type="hidden" id="telefone_cliente_final" name="telefone_cliente" value="<?= htmlspecialchars($telefone_cliente) ?>">

            <h2>Revisão do Pedido</h2>
            <div class="resumo-pedido">
                <ul>
                    <?php foreach ($carrinho as $item): ?>
                        <li>
                            <span><?= htmlspecialchars($item['quantidade']) ?>x <?= htmlspecialchars($item['nome']) ?></span>
                            <span>R$ <?= number_format($item['preco'] * $item['quantidade'], 2, ',', '.') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="total">Total: R$ <?= number_format($total_pedido, 2, ',', '.') ?></p>
            </div>

            <h2>Endereço de Entrega Confirmado</h2>
            <div class="address-confirmation">
                <p id="confirmed-address-display">
                    </p>
                <button type="button" id="btn-edit-address" class="btn-edit-address">Editar</button>
            </div>

            <div class="coupon-section">
                <label for="coupon_code">Cupom Promocional (Opcional):</label>
                <input type="text" id="coupon_code" name="coupon_code" placeholder="Insira seu cupom aqui">
            </div>

            <h2>Forma de Pagamento</h2>
            <div class="form-group">
                <label for="forma_pagamento">Selecione a forma de pagamento:</label>
                <select id="forma_pagamento" name="forma_pagamento" required>
                    <option value="">-- Selecione --</option>
                    <option value="dinheiro" <?= ($forma_pagamento == 'dinheiro') ? 'selected' : '' ?>>Dinheiro</option>
                    <option value="cartao" <?= ($forma_pagamento == 'cartao') ? 'selected' : '' ?>>Cartão (Crédito/Débito)</option>
                    <option value="pix" <?= ($forma_pagamento == 'pix') ? 'selected' : '' ?>>PIX</option>
                </select>
            </div>
            <div class="form-group" id="troco_para_group" style="display: none;">
                <label for="troco_para">Troco para quanto? (R$)</label>
                <input type="number" id="troco_para" name="troco_para" step="0.01" min="<?= $total_pedido ?>" value="<?= htmlspecialchars($troco_para) ?>" placeholder="Ex: <?= number_format($total_pedido + 5, 2, ',', '.') ?>">
                <small>Informe o valor se precisar de troco.</small>
            </div>

            <h2>Observações do Pedido</h2>
            <div class="form-group">
                <label for="observacoes_pedido">Observações gerais (Opcional):</label>
                <textarea id="observacoes_pedido" name="observacoes_pedido" rows="3"><?= htmlspecialchars($observacoes_pedido) ?></textarea>
            </div>

            <div class="botoes-checkout">
                <a href="carrinho.php" class="btn btn-voltar">Voltar ao Carrinho</a>
                <button type="submit" class="btn btn-finalizar">Confirmar Pedido</button>
            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Elementos das etapas
        const identificationStep = document.getElementById('identification-step');
        const reviewStep = document.getElementById('review-step');

        // Elementos da etapa de identificação
        const telefoneClienteInitialInput = document.getElementById('telefone_cliente_initial');
        const btnAvancarIdentificacao = document.getElementById('btn-avancar-identificacao');
        const phoneLoadingSpinner = document.getElementById('phone-loading-spinner');
        const addressFieldsGroup = document.getElementById('address-fields-group'); // Novo grupo para campos de endereço

        // Elementos do formulário completo (que agora estão na etapa de identificação)
        const nomeClienteFinalInput = document.getElementById('nome_cliente_final');
        const enderecoEntregaInput = document.getElementById('endereco_entrega');
        const numeroEntregaInput = document.getElementById('numero_entrega');
        const bairroEntregaInput = document.getElementById('bairro_entrega');
        const cepEntregaInput = document.getElementById('cep_entrega');
        const complementoEntregaInput = document.getElementById('complemento_entrega');
        const naoPossuiNumeroCasaCheckbox = document.getElementById('nao_possui_numero_casa');
        const numeroEntregaGroup = document.getElementById('numero_entrega_group');
        const isNewClientHiddenInput = document.getElementById('is_new_client');

        // Elementos da etapa de revisão
        const telefoneClienteFinalInput = document.getElementById('telefone_cliente_final'); // Campo oculto que envia o telefone
        const confirmedAddressDisplay = document.getElementById('confirmed-address-display');
        const btnEditAddress = document.getElementById('btn-edit-address');
        const formaPagamentoSelect = document.getElementById('forma_pagamento');
        const trocoParaGroup = document.getElementById('troco_para_group');
        const trocoParaInput = document.getElementById('troco_para');

        // --- Funções de UI e Lógica ---

        // Lógica para mostrar/esconder campo de troco
        function toggleTrocoField() {
            if (formaPagamentoSelect.value === 'dinheiro') {
                trocoParaGroup.style.display = 'block';
                trocoParaInput.setAttribute('required', 'required');
            } else {
                trocoParaGroup.style.display = 'none';
                trocoParaInput.removeAttribute('required');
                trocoParaInput.value = '';
            }
        }

        formaPagamentoSelect.addEventListener('change', toggleTrocoField);
        toggleTrocoField(); // Chama ao carregar a página para definir o estado inicial

        // Lógica para o checkbox "Não possui número da casa"
        function toggleNumeroCasaField() {
            if (naoPossuiNumeroCasaCheckbox.checked) {
                numeroEntregaInput.value = 'S/N';
                numeroEntregaInput.setAttribute('disabled', 'disabled');
                numeroEntregaInput.removeAttribute('required');
                numeroEntregaInput.classList.add('readonly-field');
                complementoEntregaInput.setAttribute('required', 'required');
            } else {
                if (numeroEntregaInput.value === 'S/N') {
                    numeroEntregaInput.value = '';
                }
                numeroEntregaInput.removeAttribute('disabled');
                numeroEntregaInput.setAttribute('required', 'required');
                numeroEntregaInput.classList.remove('readonly-field');
                complementoEntregaInput.removeAttribute('required');
            }
        }

        naoPossuiNumeroCasaCheckbox.addEventListener('change', toggleNumeroCasaField);
        toggleNumeroCasaField(); // Chama ao carregar para definir o estado inicial

        // Máscara dinâmica para o telefone
        function applyPhoneMask(inputElement) {
            inputElement.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');

                if (value.length > 11) value = value.slice(0, 11);

                if (value.length >= 2 && value.length <= 6) {
                    value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
                } else if (value.length > 6) {
                    value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
                }
                e.target.value = value;
            });
        }
        applyPhoneMask(telefoneClienteInitialInput);

        // Função para preencher os campos de endereço e nome
        function fillAddressFields(clientData) {
            nomeClienteFinalInput.value = clientData.nome || '';
            enderecoEntregaInput.value = clientData.endereco || '';
            numeroEntregaInput.value = clientData.ncasa || '';
            bairroEntregaInput.value = clientData.bairro || '';
            cepEntregaInput.value = clientData.cep || '';
            complementoEntregaInput.value = clientData.complemento || '';

            // Ajusta o checkbox "Não possui número da casa"
            if (clientData.ncasa === 'S/N' || !clientData.ncasa) {
                naoPossuiNumeroCasaCheckbox.checked = true;
            } else {
                naoPossuiNumeroCasaCheckbox.checked = false;
            }
            toggleNumeroCasaField(); // Aplica a lógica do campo número/complemento
        }

        // Função para resetar e habilitar todos os campos da etapa de identificação
        function resetIdentificationStepFields() {
            nomeClienteFinalInput.value = '';
            enderecoEntregaInput.value = '';
            numeroEntregaInput.value = '';
            bairroEntregaInput.value = '';
            cepEntregaInput.value = '';
            complementoEntregaInput.value = '';
            
            setIdentificationFieldsReadonly(false); // Habilita todos os campos
            naoPossuiNumeroCasaCheckbox.removeAttribute('disabled'); // Habilita o checkbox
            naoPossuiNumeroCasaCheckbox.checked = false; // Desmarca o checkbox por padrão
            toggleNumeroCasaField(); // Garante o estado correto do campo número/complemento
            isNewClientHiddenInput.value = '1'; // Assume que é um novo cliente ao resetar
        }

        // Função auxiliar para definir/remover readonly e estilos dos campos da etapa de identificação
        function setIdentificationFieldsReadonly(isReadonly) {
            const fields = [
                nomeClienteFinalInput,
                enderecoEntregaInput,
                numeroEntregaInput,
                bairroEntregaInput,
                cepEntregaInput,
                complementoEntregaInput
            ];

            fields.forEach(field => {
                if (isReadonly) {
                    field.setAttribute('readonly', 'readonly');
                    field.classList.add('readonly-field');
                    field.removeAttribute('required'); // Não é obrigatório se já preenchido
                } else {
                    field.removeAttribute('readonly');
                    field.classList.remove('readonly-field');
                    // Define required para campos essenciais para um novo cadastro
                    if (field.id === 'nome_cliente_final' || field.id === 'endereco_entrega' || field.id === 'bairro_entrega') {
                        field.setAttribute('required', 'required');
                    }
                }
            });
            // O checkbox também deve ser desabilitado se os campos forem readonly
            if (isReadonly) {
                naoPossuiNumeroCasaCheckbox.setAttribute('disabled', 'disabled');
            } else {
                naoPossuiNumeroCasaCheckbox.removeAttribute('disabled');
            }
        }

        // Função para verificar o cliente e avançar para a próxima etapa
        async function checkClientAndAdvance() {
            const telefoneRaw = telefoneClienteInitialInput.value.replace(/\D/g, '');

            if (telefoneRaw.length < 10) { 
                alert('Por favor, digite um número de telefone válido (mínimo 10 dígitos).');
                return; 
            }

            phoneLoadingSpinner.style.display = 'inline-block';
            btnAvancarIdentificacao.setAttribute('disabled', 'disabled');
            document.querySelectorAll('.mensagem.erro').forEach(el => el.style.display = 'none');

            try {
                const response = await fetch('public/api/check_client.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ telefone: telefoneRaw })
                });
                const result = await response.json();

                if (result.success) {
                    telefoneClienteFinalInput.value = telefoneClienteInitialInput.value; // Passa o telefone formatado

                    if (result.client_exists && result.client_data) {
                        fillAddressFields(result.client_data);
                        setIdentificationFieldsReadonly(true); // Torna os campos readonly
                        isNewClientHiddenInput.value = '0'; // Não é um novo cliente
                    } else {
                        // Cliente não encontrado, reseta e habilita campos para novo cadastro
                        resetIdentificationStepFields();
                        isNewClientHiddenInput.value = '1'; // É um novo cliente
                        // Preenche apenas o nome se já tiver digitado algo antes de buscar o telefone
                        nomeClienteFinalInput.value = nomeClienteFinalInput.value || ''; 
                    }

                    // Prepara o endereço para exibição na etapa de revisão
                    updateConfirmedAddressDisplay();

                    // Esconde a etapa de identificação e mostra a de revisão
                    identificationStep.style.display = 'none';
                    reviewStep.style.display = 'block';

                } else {
                    alert(result.message || 'Erro ao verificar cliente. Tente novamente.');
                    resetIdentificationStepFields(); // Reseta campos em caso de erro da API
                }
            } catch (error) {
                console.error('Erro na requisição AJAX:', error);
                alert('Erro de conexão ao verificar cliente. Verifique sua internet e tente novamente.');
                resetIdentificationStepFields(); // Reseta campos em caso de erro de conexão
            } finally {
                phoneLoadingSpinner.style.display = 'none';
                btnAvancarIdentificacao.removeAttribute('disabled');
            }
        }

        // Função para atualizar o texto do endereço confirmado na etapa de revisão
        function updateConfirmedAddressDisplay() {
            const endereco = enderecoEntregaInput.value;
            const numero = numeroEntregaInput.value;
            const bairro = bairroEntregaInput.value;
            const cep = cepEntregaInput.value;
            const complemento = complementoEntregaInput.value;

            let addressText = `${endereco}, ${numero}`;
            if (complemento) {
                addressText += ` - ${complemento}`;
            }
            addressText += `<br>${bairro}`;
            if (cep) {
                addressText += ` - CEP: ${cep}`;
            }
            confirmedAddressDisplay.innerHTML = addressText;
        }

        // Event Listeners
        btnAvancarIdentificacao.addEventListener('click', checkClientAndAdvance);

        // Botão "Editar" na etapa de revisão
        btnEditAddress.addEventListener('click', () => {
            reviewStep.style.display = 'none';
            identificationStep.style.display = 'block';
            setIdentificationFieldsReadonly(false); // Torna os campos editáveis novamente
            naoPossuiNumeroCasaCheckbox.removeAttribute('disabled'); // Habilita o checkbox
            toggleNumeroCasaField(); // Garante o estado correto do campo número/complemento
        });

        // Debounce para a função checkClientAndAdvance (dispara a busca após um tempo sem digitação)
        let debounceTimer;
        telefoneClienteInitialInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const telefoneRaw = telefoneClienteInitialInput.value.replace(/\D/g, '');
            if (telefoneRaw.length >= 10) {
                debounceTimer = setTimeout(() => {
                    checkClientAndAdvance();
                }, 800); // Espera 800ms após a última digitação
            } else {
                // Se o telefone for muito curto, reseta os campos de endereço
                resetIdentificationStepFields();
            }
        });

        // Inicialização: Se houver mensagem de erro ou sucesso PHP, exibe a etapa correta
        <?php if (!empty($mensagem_sucesso) || !empty($mensagem_erro)): ?>
            // Se houver erro ou sucesso, significa que o POST foi feito, então mostra a etapa de revisão
            identificationStep.style.display = 'none';
            reviewStep.style.display = 'block';
            // Preenche o endereço confirmado (se o POST foi feito)
            updateConfirmedAddressDisplay();
            // Se o cliente já existia, os campos devem permanecer readonly
            <?php if (!$is_new_client): ?>
                setIdentificationFieldsReadonly(true);
            <?php endif; ?>
        <?php else: ?>
            // Caso contrário, mostra a etapa de identificação
            identificationStep.style.display = 'block';
            reviewStep.style.display = 'none';
        <?php endif; ?>

        // Se o telefone já estiver preenchido (ex: após um erro de validação PHP ou retorno),
        // e não houver mensagens de sucesso/erro, tenta verificar o cliente
        // Isso evita que a etapa de identificação seja pulada se a página for recarregada
        // e o telefone já estiver no input.
        <?php if (empty($mensagem_sucesso) && empty($mensagem_erro) && !empty($telefone_cliente)): ?>
            // Preenche os campos de endereço com os valores PHP se existirem
            fillAddressFields({
                nome: '<?= htmlspecialchars($nome_cliente) ?>',
                endereco: '<?= htmlspecialchars($endereco_entrega) ?>',
                ncasa: '<?= htmlspecialchars($numero_entrega) ?>',
                bairro: '<?= htmlspecialchars($bairro_entrega) ?>',
                cep: '<?= htmlspecialchars($cep_entrega) ?>',
                complemento: '<?= htmlspecialchars($complemento_entrega) ?>'
            });
            // Se o cliente não é novo (já existia), os campos devem ser readonly
            <?php if (!$is_new_client): ?>
                setIdentificationFieldsReadonly(true);
            <?php endif; ?>
            // Atualiza o display do endereço confirmado
            updateConfirmedAddressDisplay();
            // Mostra a etapa de revisão se o telefone já estiver preenchido
            identificationStep.style.display = 'none';
            reviewStep.style.display = 'block';
        <?php endif; ?>
    });
</script>
</body>
</html>
