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
    <link rel="stylesheet" href="public/css/carrinho.css">
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

// COLE ESTA FUNÇÃO COMPLETA NO LUGAR DA SUA ATUAL
async function atualizarCarrinhoUI() {
    const result = await gerenciarCarrinhoAPI('get_cart');
    if (result.success) {
         console.log(result.cart);
        const carrinhoDoBackend = result.cart;
        carrinhoItensContainer.innerHTML = '';
        currentCartTotal = 0; // Reseta o total

        if (carrinhoDoBackend.length === 0) {
            window.location.href = 'carrinho.php';
            return;
        }

        carrinhoDoBackend.forEach(item => {
    const itemCard = document.createElement('div');
    itemCard.className = 'carrinho-item-card';
    itemCard.setAttribute('data-item-id', item.item_carrinho_id);

    // --- LÓGICA PARA EXIBIR DETALHES ---
    let detalhesHtml = '';
    // 1. Cria a lista de opções (Combos, Adicionais, etc.)
    if (item.opcoes && item.opcoes.length > 0) {
        const optionsList = item.opcoes.map(opt => `<li>+ ${opt.nome}</li>`).join('');
        detalhesHtml += `<ul class="item-options-list">${optionsList}</ul>`;
    }
    // 2. Adiciona as observações, se existirem
    if (item.obs && item.obs.trim() !== '') {
        detalhesHtml += `<p class="item-obs"><b>Obs:</b> ${item.obs}</p>`;
    }

    // --- CÁLCULO DE PREÇO CORRETO ---
    // O preço total da linha é o preço unitário (que já inclui adicionais) x quantidade.
    const itemTotalPrice = parseFloat(item.preco_unitario) * item.quantidade;

    const disableDiminuir = item.quantidade === 1 ? 'disabled' : '';
    const imageUrl = item.imagem || 'public/img/default-product.png';

    // --- ESTRUTURA HTML FINAL ---
    itemCard.innerHTML = `
        <img src="${imageUrl}" alt="${item.nome}" class="item-image" onerror="this.onerror=null;this.src='public/img/default-product.png';">
        <div class="item-details">
            <span class="item-name">${item.quantidade}x ${item.nome}</span>
            ${detalhesHtml}
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
        minOrderValueDisplay.textContent = `R$ ${pedidoMinimo.toFixed(2).replace('.', ',')}`;

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