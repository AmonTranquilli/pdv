<?php
session_start();

require_once 'includes/conexao.php'; 

if (!isset($_SESSION['checkout_cliente_data'])) {
    header("Location: pre-checkout.php");
    exit();
}

$cliente_data = $_SESSION['checkout_cliente_data'];
$carrinho = $_SESSION['carrinho'] ?? [];

$taxa_entrega = 0.00;
$sqlConfig = "SELECT taxa_entrega FROM configuracoes_loja WHERE id = 1";
$resultConfig = $conn->query($sqlConfig);
if ($resultConfig && $resultConfig->num_rows > 0) {
    $config = $resultConfig->fetch_assoc();
    $taxa_entrega = (float)($config['taxa_entrega'] ?? 0.00);
}

$subtotal = 0;
foreach ($carrinho as $key => $item) {
    $carrinho[$key]['preco_total'] = (float)($item['preco'] ?? 0);
    $subtotal += $carrinho[$key]['preco_total'];
}

$total_geral = $subtotal + $taxa_entrega;

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento - Minha Hamburgueria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="public/css/cardapio.css">
    <style>
        :root {
            --primary-color: #FF5722;
            --secondary-color: #4CAF50;
            --text-dark: #333;
            --text-light: #f8f8f8;
            --background-light: #ffffff;
            --background-dark: #f0f2f5;
            --border-color: #e0e0e0;
            --shadow-light: 0 2px 10px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 5px 15px rgba(0, 0, 0, 0.12);
            --border-radius-base: 10px;
            --transition-base: all 0.3s ease-in-out;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-dark); color: var(--text-dark); margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .checkout-container { max-width: 700px; margin: 40px auto; background-color: var(--background-light); padding: 30px; border-radius: var(--border-radius-base); box-shadow: var(--shadow-medium); flex-grow: 1; }
        h1 { text-align: center; color: var(--primary-color); margin-bottom: 30px; font-size: 2.2em; font-weight: 700; }
        h2 { color: var(--primary-color); margin-top: 30px; margin-bottom: 20px; font-size: 1.6em; font-weight: 600; border-bottom: 2px solid var(--border-color); padding-bottom: 10px; }
        .section-box { background-color: #f9f9f9; border: 1px solid var(--border-color); border-radius: var(--border-radius-base); padding: 20px; margin-bottom: 25px; box-shadow: var(--shadow-light); }
        .client-details p { margin: 8px 0; font-size: 1.05em; line-height: 1.5; }
        .client-details strong { color: var(--primary-color); }
        .order-summary-items { list-style: none; padding: 0; margin: 0 0 20px 0; }
        .order-summary-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #eee; font-size: 1em; }
        .order-summary-item:last-child { border-bottom: none; }
        .order-summary-item .item-name { font-weight: 500; color: #555; }
        .order-summary-item .item-price { font-weight: 600; color: var(--text-dark); }
        .order-summary-totals { margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color); }
        .order-summary-line { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 1.1em; }
        .order-summary-line.total { font-size: 1.3em; font-weight: 700; color: var(--primary-color); margin-top: 15px; }
        .order-summary-line span:first-child { color: #555; }
        .order-summary-line.total span:first-child { color: var(--primary-color); }
        .coupon-section { margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color); }
        .coupon-section label { display: block; margin-bottom: 10px; font-weight: 600; color: #555; }
        .coupon-input-group { display: flex; gap: 10px; }
        .coupon-input-group input[type="text"] { flex-grow: 1; padding: 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius-base); font-size: 1em; transition: var(--transition-base); }
        .coupon-input-group input[type="text"]:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(255, 87, 34, 0.2); outline: none; }
        .coupon-input-group button { padding: 12px 20px; background-color: var(--secondary-color); color: white; border: none; border-radius: var(--border-radius-base); font-weight: 600; cursor: pointer; transition: var(--transition-base); }
        .coupon-input-group button:hover { background-color: #388E3C; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
        .coupon-message { margin-top: 10px; font-size: 0.9em; color: #888; }
        .payment-methods-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-top: 20px; }
        .payment-option { background-color: #ffffff; border: 2px solid var(--border-color); border-radius: var(--border-radius-base); padding: 20px; text-align: center; cursor: pointer; transition: var(--transition-base); box-shadow: var(--shadow-light); display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px; }
        .payment-option:hover { border-color: var(--primary-color); transform: translateY(-3px); box-shadow: var(--shadow-medium); }
        .payment-option.selected { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(255, 87, 34, 0.3); background-color: #fffaf7; }
        .payment-option input[type="radio"] { display: none; }
        .payment-option i { font-size: 3em; color: #666; margin-bottom: 10px; transition: color 0.3s ease; }
        .payment-option.selected i { color: var(--primary-color); }
        .payment-option span { font-weight: 600; font-size: 1.1em; color: var(--text-dark); }
        #trocoParaGroup { margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color); display: none; }
        #trocoParaGroup label { display: block; margin-bottom: 10px; font-weight: 600; color: #555; }
        #trocoParaGroup input[type="number"] { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius-base); font-size: 1em; box-sizing: border-box; transition: var(--transition-base); }
        #trocoParaGroup input[type="number"]:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(255, 87, 34, 0.2); outline: none; }
        .action-buttons { display: flex; gap: 15px; margin-top: 30px; flex-direction: column; }
        .btn-action { display: block; width: 100%; padding: 15px; border: none; border-radius: var(--border-radius-base); font-size: 1.1em; font-weight: bold; cursor: pointer; transition: var(--transition-base); text-align: center; text-decoration: none; }
        .btn-finish-order { background-color: var(--primary-color); color: white; }
        .btn-finish-order:hover:not(:disabled) { background-color: #E64A19; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); }
        .btn-finish-order:disabled { background-color: #ccc; cursor: not-allowed; } /* Estilo para o botão desabilitado */
        .btn-back { background-color: #6c757d; color: white; }
        .btn-back:hover { background-color: #5a6268; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); }
        .message-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
        .message-modal-overlay.visible { opacity: 1; visibility: visible; }
        .message-modal-content { background-color: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); max-width: 400px; width: 90%; text-align: center; transform: translateY(20px); transition: transform 0.3s ease; }
        .message-modal-overlay.visible .message-modal-content { transform: translateY(0); }
        .message-modal-content h3 { color: var(--primary-color); margin-bottom: 15px; font-size: 1.8em; }
        .message-modal-content p { font-size: 1.1em; color: #333; margin-bottom: 25px; white-space: pre-wrap; }
        .message-modal-content button { background-color: var(--primary-color); color: white; padding: 12px 25px; border: none; border-radius: 8px; font-size: 1em; font-weight: bold; cursor: pointer; transition: background-color 0.2s ease, transform 0.2s ease; }
        .message-modal-content button:hover { background-color: #E64A19; transform: translateY(-2px); }
        @media (max-width: 600px) {
            .checkout-container { margin: 20px 15px; padding: 20px; }
            h1 { font-size: 1.8em; margin-bottom: 20px; }
            h2 { font-size: 1.4em; margin-top: 20px; }
            .payment-methods-grid { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .btn-action { padding: 12px; font-size: 1em; }
            .coupon-input-group { flex-direction: column; }
            .coupon-input-group button { width: 100%; }
        }
    </style>
</head>
<body>

    <div class="checkout-container">
        <h1>Finalizar Pedido - Pagamento</h1>

        <div class="section-box client-details">
            <h2><i class="fas fa-user-circle"></i> Seus Dados</h2>
            <p><strong>Nome:</strong> <?= htmlspecialchars($cliente_data['nome']) ?></p>
            <p><strong>Telefone:</strong> <?= htmlspecialchars($cliente_data['telefone']) ?></p>
            <p><strong>Endereço:</strong> <?= htmlspecialchars($cliente_data['endereco']) ?>,
                <?= htmlspecialchars($cliente_data['numero'] ?? 'S/N') ?> -
                <?= htmlspecialchars($cliente_data['bairro']) ?>
                <?= !empty($cliente_data['complemento']) ? '('.htmlspecialchars($cliente_data['complemento']).')' : '' ?>
            </p>
            <?php if (!empty($cliente_data['ponto_referencia'])): ?>
                <p><strong>Ponto de Referência:</strong> <?= htmlspecialchars($cliente_data['ponto_referencia']) ?></p>
            <?php endif; ?>
        </div>

        <div class="section-box order-summary">
            <h2><i class="fas fa-shopping-cart"></i> Resumo do Pedido</h2>
            <ul class="order-summary-items">
                <?php foreach ($carrinho as $item): ?>
                    <li class="order-summary-item">
                        <span class="item-name"><?= htmlspecialchars($item['quantidade']) ?>x <?= htmlspecialchars($item['nome_produto'] ?? $item['nome']) ?></span>
                        <span class="item-price">R$ <?= number_format($item['preco_total'], 2, ',', '.') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="order-summary-totals">
                <div class="order-summary-line">
                    <span>Subtotal:</span>
                    <span>R$ <?= number_format($subtotal, 2, ',', '.') ?></span>
                </div>
                <div class="order-summary-line">
                    <span>Taxa de Entrega:</span>
                    <span>R$ <?= number_format($taxa_entrega, 2, ',', '.') ?></span>
                </div>
                <div class="order-summary-line total">
                    <span>Total Geral:</span>
                    <span>R$ <?= number_format($total_geral, 2, ',', '.') ?></span>
                </div>
            </div>
            <div class="coupon-section">
                <label for="couponCode">Possui um cupom de desconto?</label>
                <div class="coupon-input-group">
                    <input type="text" id="couponCode" placeholder="Digite seu cupom">
                    <button type="button" id="applyCouponBtn">Aplicar</button>
                </div>
                <p class="coupon-message">O cupom será aplicado no total do pedido.</p>
            </div>
        </div>

        <div class="section-box payment-methods">
            <h2><i class="fas fa-wallet"></i> Escolha o Método de Pagamento</h2>
            <div class="payment-methods-grid">
                <label class="payment-option" for="paymentMoney">
                    <input type="radio" id="paymentMoney" name="payment_method" value="dinheiro" checked>
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Dinheiro</span>
                </label>
                <label class="payment-option" for="paymentCard">
                    <input type="radio" id="paymentCard" name="payment_method" value="cartao">
                    <i class="fas fa-credit-card"></i>
                    <span>Cartão</span>
                </label>
                <label class="payment-option" for="paymentPix">
                    <input type="radio" id="paymentPix" name="payment_method" value="pix">
                    <i class="fas fa-qrcode"></i>
                    <span>Pix</span>
                </label>
            </div>
            <div id="trocoParaGroup" class="form-group">
                <label for="trocoPara">Troco para (opcional):</label>
                <input type="number" id="trocoPara" name="troco_para" placeholder="Valor para o troco (ex: <?= number_format($total_geral + 5, 2, ',', '.') ?>)" step="0.01" min="<?= number_format($total_geral, 2, '.', '') ?>">
                <small>Informe o valor se precisar de troco.</small>
            </div>
        </div>

        <div class="action-buttons">
            <button type="button" class="btn-action btn-finish-order" id="btnFinalizarPedido">
                Finalizar Pedido
            </button>
            <button type="button" class="btn-action btn-back" onclick="window.location.href='pre-checkout.php'">
                Voltar
            </button>
        </div>
    </div>

    <div id="message-modal-overlay" class="message-modal-overlay">
        <div class="message-modal-content">
            <h3 id="message-modal-title"></h3>
            <p id="message-modal-text"></p>
            <button id="message-modal-ok-btn">Entendi</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const paymentOptions = document.querySelectorAll('.payment-option');
            const trocoParaGroup = document.getElementById('trocoParaGroup');
            const paymentMoneyRadio = document.getElementById('paymentMoney');
            const trocoParaInput = document.getElementById('trocoPara');
            const totalGeral = parseFloat(<?= json_encode($total_geral) ?>);
            const messageModalOverlay = document.getElementById('message-modal-overlay');
            const messageModalTitle = document.getElementById('message-modal-title');
            const messageModalText = document.getElementById('message-modal-text');
            const messageModalOkBtn = document.getElementById('message-modal-ok-btn');

            function showMessageModal(title, message) {
                messageModalTitle.textContent = title;
                messageModalText.textContent = message;
                messageModalOverlay.classList.add('visible');
            }

            messageModalOkBtn.addEventListener('click', () => {
                messageModalOverlay.classList.remove('visible');
            });

            function updatePaymentSelection() {
                paymentOptions.forEach(option => {
                    const radio = option.querySelector('input[type="radio"]');
                    if (radio.checked) {
                        option.classList.add('selected');
                    } else {
                        option.classList.remove('selected');
                    }
                });
                if (paymentMoneyRadio.checked) {
                    trocoParaGroup.style.display = 'block';
                    trocoParaInput.setAttribute('min', totalGeral.toFixed(2));
                } else {
                    trocoParaGroup.style.display = 'none';
                    trocoParaInput.value = '';
                }
            }

            paymentOptions.forEach(option => {
                option.addEventListener('click', () => {
                    const radio = option.querySelector('input[type="radio"]');
                    radio.checked = true;
                    updatePaymentSelection();
                });
            });

            updatePaymentSelection();

            const btnFinalizarPedido = document.getElementById('btnFinalizarPedido');
            
            // --- CORREÇÃO APLICADA AQUI ---
            btnFinalizarPedido.addEventListener('click', async () => {
                // 1. Desabilita o botão e muda o texto assim que ele é clicado
                btnFinalizarPedido.disabled = true;
                btnFinalizarPedido.textContent = 'Processando...';

                try {
                    const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked');
                    if (!selectedPaymentMethod) {
                        showMessageModal('Erro', 'Por favor, selecione um método de pagamento.');
                        throw new Error("Validação falhou"); // Lança um erro para ser pego pelo catch
                    }

                    let trocoPara = null;
                    let troco = null;
                    const formaPagamento = selectedPaymentMethod.value;

                    if (formaPagamento === 'dinheiro') {
                        const trocoParaValue = parseFloat(trocoParaInput.value);
                        if (!isNaN(trocoParaValue)) {
                            if (trocoParaValue < totalGeral) {
                                showMessageModal('Erro no Troco', 'O valor para troco deve ser igual ou maior que o total do pedido.');
                                throw new Error("Validação falhou");
                            }
                            trocoPara = trocoParaValue;
                            troco = trocoParaValue - totalGeral;
                        } else {
                            trocoPara = totalGeral;
                            troco = 0;
                        }
                    }

                    const orderData = {
                        cliente_data: <?= json_encode($cliente_data) ?>,
                        carrinho: <?= json_encode($carrinho) ?>,
                        total_pedido: totalGeral,
                        forma_pagamento: formaPagamento,
                        troco_para: trocoPara,
                        troco: troco,
                        observacoes_pedido: '' // Futuramente pegar de um campo
                    };

                    const response = await fetch('public/api/processar_pedido.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(orderData)
                    });

                    const result = await response.json();

                    if (result.success) {
                        window.location.href = `confirmacao_pedido.php?pedido_id=${result.pedido_id}`;
                    } else {
                        showMessageModal('Erro ao Finalizar Pedido', result.message || 'Ocorreu um erro inesperado.');
                        throw new Error("API retornou erro"); // Lança erro para ser pego pelo catch
                    }
                } catch (error) {
                    console.error('Erro no processo de finalização:', error);
                    // 2. Se qualquer erro ocorrer (validação, rede, API), reabilita o botão
                    btnFinalizarPedido.disabled = false;
                    btnFinalizarPedido.textContent = 'Finalizar Pedido';
                    if (error.message.includes("Validação falhou")) {
                        // Não mostra modal de erro de rede se foi só validação
                    } else {
                         showMessageModal('Erro de Conexão', 'Não foi possível conectar ao servidor para finalizar o pedido. Tente novamente.');
                    }
                }
            });

            const applyCouponBtn = document.getElementById('applyCouponBtn');
            applyCouponBtn.addEventListener('click', () => {
                const couponCode = document.getElementById('couponCode').value.trim();
                if (couponCode) {
                    showMessageModal('Cupom', `Cupom "${couponCode}" aplicado! (Funcionalidade de cupom será implementada futuramente)`);
                } else {
                    showMessageModal('Cupom', 'Por favor, digite um código de cupom.');
                }
            });
        });
    </script>
</body>
</html>
