<?php
session_start();
require_once 'includes/conexao.php'; // Caminho para a conexão com o banco de dados

$pedido_id = $_GET['pedido_id'] ?? null;
$pedido_detalhes = null;
$itens_pedido = [];
$tempo_espera = "30-40 minutos"; // Tempo de espera padrão

// --- Lógica para buscar a taxa de entrega das configurações da loja ---
$taxa_entrega_config = 0.00; // Valor padrão
$sqlConfig = "SELECT taxa_entrega FROM configuracoes_loja WHERE id = 1"; // Assumindo ID 1 para a configuração principal
$resultConfig = $conn->query($sqlConfig);
if ($resultConfig && $resultConfig->num_rows > 0) {
    $config = $resultConfig->fetch_assoc();
    $taxa_entrega_config = (float)($config['taxa_entrega'] ?? 0.00);
}

if ($pedido_id) {
    // Busca os detalhes do pedido principal
    $stmt_pedido = $conn->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt_pedido->bind_param("i", $pedido_id);
    $stmt_pedido->execute();
    $result_pedido = $stmt_pedido->get_result();
    $pedido_detalhes = $result_pedido->fetch_assoc();
    $stmt_pedido->close();

    if ($pedido_detalhes) {
        // Busca os itens do pedido
        $stmt_itens = $conn->prepare("SELECT ip.*, p.imagem FROM itens_pedido ip JOIN produtos p ON ip.id_produto = p.id WHERE ip.id_pedido = ?");
        $stmt_itens->bind_param("i", $pedido_id);
        $stmt_itens->execute();
        $result_itens = $stmt_itens->get_result();

        while ($item = $result_itens->fetch_assoc()) {
            // Busca os adicionais para cada item do pedido
            $stmt_adicionais = $conn->prepare("SELECT a.nome, a.preco FROM adicionais_item_pedido aip JOIN adicionais a ON aip.id_adicional = a.id WHERE aip.id_item_pedido = ?");
            $stmt_adicionais->bind_param("i", $item['id']);
            $stmt_adicionais->execute();
            $result_adicionais = $stmt_adicionais->get_result();
            $item['adicionais'] = $result_adicionais->fetch_all(MYSQLI_ASSOC);
            $stmt_adicionais->close();

            $itens_pedido[] = $item;
        }
        $stmt_itens->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Confirmado - Minha Hamburgueria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="public/css/cardapio.css">
    <style>
        /* Estilos para a página de confirmação */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
        }

        .confirmation-container {
            max-width: 700px;
            margin: 40px auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .confirmation-icon {
            color: #4CAF50; /* Verde de sucesso */
            font-size: 4em;
            margin-bottom: 20px;
        }

        h1 {
            color: #4CAF50;
            font-size: 2.5em;
            margin-bottom: 15px;
        }

        .thank-you-message {
            font-size: 1.2em;
            color: #555;
            margin-bottom: 25px;
        }

        .order-details-section {
            background-color: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }

        .order-details-section h2 {
            color: #FF5722;
            font-size: 1.6em;
            margin-bottom: 15px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .detail-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 1.05em;
        }

        .detail-line strong {
            color: #333;
        }

        .detail-line span {
            color: #555;
        }

        .order-items-list {
            list-style: none;
            padding: 0;
            margin-top: 20px;
            border-top: 1px dashed #ddd;
            padding-top: 15px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dotted #f0f0f0;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-item-info {
            flex-grow: 1;
        }

        .order-item-info strong {
            font-size: 1.1em;
            color: #333;
        }

        .order-item-info small {
            display: block;
            color: #777;
            font-size: 0.9em;
            margin-top: 3px;
        }
        .order-item-info .adicionais-list {
            font-size: 0.8em;
            color: #666;
            margin-top: 2px;
            padding-left: 15px;
        }

        .order-item-price {
            font-weight: bold;
            color: #FF5722;
            font-size: 1.1em;
        }

        .total-summary {
            border-top: 2px solid #eee;
            padding-top: 15px;
            margin-top: 20px;
            text-align: right;
        }

        .total-summary .total-line {
            display: flex;
            justify-content: space-between;
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .total-summary .total-line.grand-total {
            font-size: 1.5em;
            color: #FF5722;
        }

        .waiting-time {
            background-color: #fff3e0; /* Laranja claro */
            color: #e65100; /* Laranja escuro */
            padding: 15px;
            border-radius: 8px;
            margin-top: 30px;
            font-size: 1.1em;
            font-weight: 600;
        }

        .btn-back-home {
            background-color: #007bff;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            margin-top: 30px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .btn-back-home:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }

        @media (max-width: 600px) {
            .confirmation-container {
                margin: 20px 15px;
                padding: 20px;
            }
            h1 {
                font-size: 2em;
            }
            .thank-you-message {
                font-size: 1em;
            }
            .order-details-section {
                padding: 15px;
            }
            .detail-line, .order-item, .total-summary .total-line {
                font-size: 0.95em;
            }
            .total-summary .total-line.grand-total {
                font-size: 1.3em;
            }
            .waiting-time {
                font-size: 1em;
            }
            .btn-back-home {
                padding: 10px 20px;
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <?php if ($pedido_detalhes): ?>
            <i class="fas fa-check-circle confirmation-icon"></i>
            <h1>Pedido Recebido!</h1>
            <p class="thank-you-message">Obrigado por seu pedido na Minha Hamburgueria!</p>

            <div class="order-details-section">
                <h2><i class="fas fa-info-circle"></i> Detalhes do Pedido #<?= htmlspecialchars($pedido_detalhes['id']) ?></h2>
                <div class="detail-line">
                    <strong>Cliente:</strong> <span><?= htmlspecialchars($pedido_detalhes['nome_cliente']) ?></span>
                </div>
                <div class="detail-line">
                    <strong>Telefone:</strong> <span><?= htmlspecialchars($pedido_detalhes['telefone_cliente']) ?></span>
                </div>
                <div class="detail-line">
                    <strong>Endereço:</strong> <span><?= htmlspecialchars($pedido_detalhes['endereco_entrega']) ?>, <?= htmlspecialchars($pedido_detalhes['numero_entrega'] ?? 'S/N') ?> - <?= htmlspecialchars($pedido_detalhes['bairro_entrega']) ?></span>
                </div>
                <?php if (!empty($pedido_detalhes['complemento_entrega'])): ?>
                    <div class="detail-line">
                        <strong>Complemento:</strong> <span><?= htmlspecialchars($pedido_detalhes['complemento_entrega']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($pedido_detalhes['referencia_entrega'])): ?>
                    <div class="detail-line">
                        <strong>Ponto de Ref.:</strong> <span><?= htmlspecialchars($pedido_detalhes['referencia_entrega']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="detail-line">
                    <strong>Forma de Pagamento:</strong> <span><?= htmlspecialchars(ucfirst($pedido_detalhes['forma_pagamento'])) ?></span>
                </div>
                <?php if ($pedido_detalhes['forma_pagamento'] === 'dinheiro' && ($pedido_detalhes['troco_para'] ?? 0) > 0): ?>
                    <div class="detail-line">
                        <strong>Troco para:</strong> <span>R$ <?= number_format($pedido_detalhes['troco_para'] ?? 0, 2, ',', '.') ?></span>
                    </div>
                    <div class="detail-line">
                        <strong>Troco:</strong> <span>R$ <?= number_format($pedido_detalhes['troco'] ?? 0, 2, ',', '.') ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($pedido_detalhes['observacoes_pedido'])): ?>
                    <div class="detail-line">
                        <strong>Observações:</strong> <span><?= htmlspecialchars($pedido_detalhes['observacoes_pedido']) ?></span>
                    </div>
                <?php endif; ?>

                <ul class="order-items-list">
                    <h2><i class="fas fa-hamburger"></i> Itens do Pedido</h2>
                    <?php foreach ($itens_pedido as $item): ?>
                        <li class="order-item">
                            <div class="order-item-info">
                                <strong><?= htmlspecialchars($item['quantidade']) ?>x <?= htmlspecialchars($item['nome_produto']) ?></strong>
                                <?php if (!empty($item['observacao_item'])): ?>
                                    <small>Obs: <?= htmlspecialchars($item['observacao_item']) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($item['adicionais'])): ?>
                                    <div class="adicionais-list">
                                        Adicionais:
                                        <?php foreach ($item['adicionais'] as $adicional): ?>
                                            <span>- <?= htmlspecialchars($adicional['nome']) ?> (R$ <?= number_format($adicional['preco'], 2, ',', '.') ?>)</span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="order-item-price">R$ <?= number_format($item['quantidade'] * $item['preco_unitario'] + array_sum(array_column($item['adicionais'], 'preco')), 2, ',', '.') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="total-summary">
                    <div class="total-line">
                        <span>Subtotal:</span> <span>R$ <?= number_format($pedido_detalhes['total_pedido'] - $taxa_entrega_config, 2, ',', '.') ?></span>
                    </div>
                    <div class="total-line">
                        <span>Taxa de Entrega:</span> <span>R$ <?= number_format($taxa_entrega_config, 2, ',', '.') ?></span>
                    </div>
                    <div class="total-line grand-total">
                        <span>Total Geral:</span> <span>R$ <?= number_format($pedido_detalhes['total_pedido'], 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>

            <div class="waiting-time">
                <p>Tempo estimado de espera: <strong><?= htmlspecialchars($tempo_espera) ?></strong></p>
                <p>Aguarde! Em breve seu pedido estará a caminho.</p>
            </div>

            <a href="index.php" class="btn-back-home">Voltar ao Cardápio Inicial</a>

        <?php else: ?>
            <i class="fas fa-exclamation-triangle confirmation-icon" style="color: #dc3545;"></i>
            <h1>Erro no Pedido</h1>
            <p class="thank-you-message">Não foi possível encontrar os detalhes do seu pedido. Por favor, tente novamente ou entre em contato.</p>
            <a href="index.php" class="btn-back-home">Voltar ao Cardápio Inicial</a>
        <?php endif; ?>
    </div>
</body>
</html>
