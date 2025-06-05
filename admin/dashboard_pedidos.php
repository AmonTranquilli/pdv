<?php
// Inicia a sessão. É crucial que session_start() seja a primeira coisa no arquivo,
// antes de qualquer saída para o navegador (HTML, espaços, etc.).
session_start();

// Inclui o arquivo de conexão com o banco de dados.
// Ajuste o caminho conforme a estrutura do seu projeto.
require_once '../includes/conexao.php';

// 1. Verifica se o usuário está logado usando a lógica fornecida pelo usuário
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se o usuário não estiver logado, redireciona para a página de login.
    header("Location: login.php"); // O caminho "login.php" assume que login.php está no mesmo diretório
    exit(); // Garante que o script pare de executar após o redirecionamento
}

// Inicia o buffer de saída para capturar o conteúdo HTML
ob_start();

// Define o título da página para o template admin
$page_title = 'Gestão de Pedidos - Kanban';

// O restante do HTML e JavaScript do Kanban
?>

<div class="kanban-main-wrapper"> <!-- Adicionado um wrapper para padding e consistência -->
    <h1 class="kanban-title">Gestão de Pedidos (Kanban)</h1>

    <div class="kanban-container">

        <!-- Coluna: Pedidos Novos -->
        <div class="kanban-column">
            <h2 class="kanban-column-header">Pedidos Novos</h2>
            <div id="new-orders-column" class="kanban-cards-container">
                <!-- Cards de pedidos novos serão inseridos aqui pelo JS -->
                <div class="kanban-card" data-order-id="12345">
                    <div class="kanban-card-info">
                        <span class="kanban-card-id">ID: #12345</span>
                        <span class="kanban-card-total">R$ 55,00</span>
                    </div>
                    <p class="kanban-card-client-name">Cliente: João Silva</p>
                    <p class="kanban-card-troco">Troco: R$ 5,00</p>
                    <div class="kanban-card-actions">
                        <button class="kanban-button kanban-button-accept">Aceitar</button>
                        <button class="kanban-button kanban-button-reject">Recusar</button>
                        <button class="kanban-button kanban-button-details">Detalhes</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna: Em Produção -->
        <div class="kanban-column">
            <h2 class="kanban-column-header">Em Produção</h2>
            <div id="in-production-column" class="kanban-cards-container">
                <!-- Cards de pedidos em produção serão inseridos aqui pelo JS -->
                <div class="kanban-card" data-order-id="12346">
                    <div class="kanban-card-info">
                        <span class="kanban-card-id">ID: #12346</span>
                        <span class="kanban-card-total">R$ 72,50</span>
                    </div>
                    <p class="kanban-card-client-name">Cliente: Maria Souza</p>
                    <p class="kanban-card-troco">Troco: Não</p>
                    <div class="kanban-card-actions">
                        <button class="kanban-button kanban-button-advance">Avançar para Entrega</button>
                        <button class="kanban-button kanban-button-details">Detalhes</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna: Em Entrega -->
        <div class="kanban-column">
            <h2 class="kanban-column-header">Em Entrega</h2>
            <div id="in-delivery-column" class="kanban-cards-container">
                <!-- Cards de pedidos em entrega serão inseridos aqui pelo JS -->
                <div class="kanban-card" data-order-id="12347">
                    <div class="kanban-card-info">
                        <span class="kanban-card-id">ID: #12347</span>
                        <span class="kanban-card-total">R$ 30,00</span>
                    </div>
                    <p class="kanban-card-client-name">Cliente: Pedro Santos</p>
                    <p class="kanban-card-troco">Troco: R$ 10,00</p>
                    <div class="kanban-card-actions">
                        <button class="kanban-button kanban-button-finish">Finalizar Pedido</button>
                        <button class="kanban-button kanban-button-details">Detalhes</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal de Detalhes do Pedido -->
    <div id="order-details-modal" class="modal-overlay">
        <div class="modal-content">
            <h2 class="modal-title">Detalhes do Pedido <span id="modal-order-id"></span></h2>
            <div id="modal-order-content" class="modal-body">
                <p><strong>Nome do Cliente:</strong> <span id="modal-client-name"></span></p>
                <p><strong>Telefone:</strong> <span id="modal-client-phone"></span></p>
                <p><strong>Endereço:</strong> <span id="modal-delivery-address"></span></p>
                <p><strong>Bairro:</strong> <span id="modal-delivery-bairro"></span></p>
                <p><strong>Complemento:</strong> <span id="modal-delivery-complemento"></span></p>
                <p><strong>Ponto de Referência:</strong> <span id="modal-delivery-referencia"></span></p>
                <p><strong>Forma de Pagamento:</strong> <span id="modal-payment-method"></span></p>
                <p><strong>Troco para:</strong> <span id="modal-troco-para"></span></p>
                <p><strong>Troco:</strong> <span id="modal-troco"></span></p>
                <p><strong>Observações:</strong> <span id="modal-observacoes"></span></p>
                <h3 class="modal-subtitle">Itens do Pedido:</h3>
                <ul id="modal-order-items" class="modal-list">
                    <!-- Itens do pedido serão carregados aqui -->
                </ul>
                <p class="modal-total">Total: <span id="modal-order-total"></span></p>
            </div>
            <button id="close-modal-btn" class="modal-close-button">Fechar</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const newOrdersColumn = document.getElementById('new-orders-column');
            const inProductionColumn = document.getElementById('in-production-column');
            const inDeliveryColumn = document.getElementById('in-delivery-column');
            const orderDetailsModal = document.getElementById('order-details-modal');
            const closeModalBtn = document.getElementById('close-modal-btn');

            // Função para abrir o modal de detalhes
            function openOrderDetailsModal(orderData) {
                document.getElementById('modal-order-id').textContent = `#${orderData.id}`;
                document.getElementById('modal-client-name').textContent = orderData.nome_cliente;
                document.getElementById('modal-client-phone').textContent = orderData.telefone_cliente;
                document.getElementById('modal-delivery-address').textContent = orderData.endereco_entrega + (orderData.numero_entrega && orderData.numero_entrega !== 'S/N' ? `, ${orderData.numero_entrega}` : '');
                document.getElementById('modal-delivery-bairro').textContent = orderData.bairro_entrega;
                document.getElementById('modal-delivery-complemento').textContent = orderData.complemento_entrega || 'N/A';
                document.getElementById('modal-delivery-referencia').textContent = orderData.referencia_entrega || 'N/A';
                document.getElementById('modal-payment-method').textContent = orderData.forma_pagamento;
                document.getElementById('modal-troco-para').textContent = orderData.troco_para && parseFloat(orderData.troco_para) > 0 ? `R$ ${parseFloat(orderData.troco_para).toFixed(2).replace('.', ',')}` : 'Não';
                document.getElementById('modal-troco').textContent = orderData.troco && parseFloat(orderData.troco) > 0 ? `R$ ${parseFloat(orderData.troco).toFixed(2).replace('.', ',')}` : 'Não';
                document.getElementById('modal-observacoes').textContent = orderData.observacoes_pedido || 'Nenhuma';
                document.getElementById('modal-order-total').textContent = `R$ ${parseFloat(orderData.total_pedido).toFixed(2).replace('.', ',')}`;

                const orderItemsList = document.getElementById('modal-order-items');
                orderItemsList.innerHTML = '';
                if (orderData.itens && orderData.itens.length > 0) {
                    orderData.itens.forEach(item => {
                        const listItem = document.createElement('li');
                        let itemTotalPrice = parseFloat(item.quantidade) * parseFloat(item.preco_unitario);
                        
                        let adicionaisText = '';
                        if (item.adicionais && item.adicionais.length > 0) {
                            const adicionaisNomes = item.adicionais.map(ad => {
                                itemTotalPrice += parseFloat(ad.preco_unitario) * parseFloat(item.quantidade); // Adiciona o preço do adicional pela quantidade do item
                                return `${ad.nome} (+R$ ${parseFloat(ad.preco_unitario).toFixed(2).replace('.', ',')})`;
                            });
                            adicionaisText = ` [Adicionais: ${adicionaisNomes.join(', ')}]`;
                        }

                        let obsText = item.observacao_item ? ` (Obs: ${item.observacao_item})` : '';
                        
                        listItem.textContent = `${item.quantidade}x ${item.nome_produto} - R$ ${parseFloat(item.preco_unitario).toFixed(2).replace('.', ',')} (Total: R$ ${itemTotalPrice.toFixed(2).replace('.', ',')})${adicionaisText}${obsText}`;
                        orderItemsList.appendChild(listItem);
                    });
                } else {
                    const listItem = document.createElement('li');
                    listItem.textContent = 'Nenhum item encontrado para este pedido.';
                    orderItemsList.appendChild(listItem);
                }

                orderDetailsModal.classList.add('visible'); // Mostra o modal
            }

            // Função para fechar o modal de detalhes
            closeModalBtn.addEventListener('click', () => {
                orderDetailsModal.classList.remove('visible'); // Esconde o modal
            });

            // Exemplo de dados de pedido para teste do modal
            const exampleOrderData = {
                id: '12345',
                nome_cliente: 'João Silva',
                telefone_cliente: '(21) 98765-4321',
                endereco_entrega: 'Rua Exemplo',
                numero_entrega: '123',
                bairro_entrega: 'Centro',
                complemento_entrega: 'Apto 101',
                referencia_entrega: 'Próximo à praça',
                forma_pagamento: 'Dinheiro',
                troco_para: '60.00',
                troco: '5.00',
                observacoes_pedido: 'Sem cebola extra',
                total_pedido: '55.00',
                itens: [
                    { id: 1, nome_produto: 'Hamburguer X', quantidade: 1, preco_unitario: '30.00', observacao_item: 'Bem passado', adicionais: [{id: 101, nome: 'Bacon', preco_unitario: '5.00'}] },
                    { id: 2, nome_produto: 'Batata Frita Grande', quantidade: 1, preco_unitario: '15.00', observacao_item: '', adicionais: [] },
                    { id: 3, nome_produto: 'Refrigerante Lata', quantidade: 2, preco_unitario: '5.00', observacao_item: 'Um Coca, um Guaraná', adicionais: [] },
                ]
            };

            // Adiciona Event Listeners de exemplo para os botões "Detalhes"
            document.querySelectorAll('.kanban-button-details').forEach(button => {
                button.addEventListener('click', () => {
                    // Em um cenário real, você buscaria os dados do pedido do backend
                    // Usando dados de exemplo por enquanto
                    openOrderDetailsModal(exampleOrderData);
                });
            });

            // Adicione aqui a lógica para carregar pedidos via AJAX e o Drag & Drop
            // Ex: fetchOrders();
        });
    </script>
</div>

<?php
// Captura o conteúdo do buffer e atribui à variável $page_content
$page_content = ob_get_clean();

// Inclui o template administrativo
include 'template_admin.php';
?>
