<?php
session_start(); // Necessário se seu template ou lógica de autenticação usa sessões

// 1. Defina o título da página (usado pelo template_admin.php)
$page_title = 'Gestor de Pedidos'; 

// 2. Autenticação (Exemplo - adapte à sua lógica de autenticação)
if (!isset($_SESSION['nome_usuario'])) { 
    header("Location: /pdv/admin/login.php"); 
    exit;
}

$baseUrl = '../../public'; 

ob_start(); 
?>

<!-- Conteúdo específico da página Kanban -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/kanban_style.css">

<div class="admin-container-gestor"> 
    <header class="kanban-page-header">
        <h1><i class="fas fa-tasks"></i> <?php echo htmlspecialchars($page_title); ?></h1>
    </header>

    <div class="kanban-board-wrapper">
        <main class="kanban-board">
            <div class="kanban-column" id="coluna-pendente" data-status="pendente">
                <h2><i class="fas fa-hourglass-start"></i> Pendente</h2> 
                <div class="cards-container" id="container-pendente">
                    <!-- Cards de pedidos pendentes serão inseridos aqui pelo JavaScript -->
                </div>
            </div>

            <div class="kanban-column" id="coluna-preparando" data-status="preparando">
                <h2><i class="fas fa-utensils"></i> Preparando</h2> 
                <div class="cards-container" id="container-preparando">
                    <!-- Cards de pedidos em preparo -->
                </div>
            </div>

            <div class="kanban-column" id="coluna-em_entrega" data-status="em_entrega">
                <h2><i class="fas fa-motorcycle"></i> Em Entrega</h2> 
                <div class="cards-container" id="container-em_entrega">
                    <!-- Cards de pedidos em entrega -->
                </div>
            </div>

            <div class="kanban-column" id="coluna-entregue" data-status="entregue">
                <h2><i class="fas fa-check-circle"></i> Entregue</h2> 
                <div class="cards-container" id="container-entregue">
                    <!-- Cards de pedidos entregues -->
                </div>
            </div>
            
            <div class="kanban-column" id="coluna-cancelado" data-status="cancelado">
                <h2><i class="fas fa-times-circle"></i> Cancelado</h2> 
                <div class="cards-container" id="container-cancelado">
                    <!-- Cards de pedidos cancelados -->
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal de Detalhes do Pedido (visibilidade controlada via CSS pela classe 'ativo') -->
<div id="modalDetalhesPedido" class="modal-overlay"> <!-- REMOVIDO style="display: none;" -->
    <div class="modal-content">
        <span class="modal-close-btn" onclick="fecharModalDetalhes()">&times;</span>
        <h2>Detalhes do Pedido: <span id="modalPedidoId"></span></h2>
        <div id="modalCorpoDetalhes">
            <p><strong>Cliente:</strong> <span id="modalClienteNome">Carregando...</span></p>
            <p><strong>Telefone:</strong> <span id="modalClienteTelefone">Carregando...</span></p>
            <p><strong>Endereço:</strong> <span id="modalClienteEndereco">Carregando...</span></p>
            <p><strong>Data do Pedido:</strong> <span id="modalDataPedido">Carregando...</span></p>
            <p><strong>Total:</strong> <span id="modalTotalPedido">Carregando...</span></p>
            <p><strong>Forma de Pagamento:</strong> <span id="modalFormaPagamento">Carregando...</span></p>
            <p id="paragrafoTrocoPara" style="display:none;"><strong>Troco Para:</strong> <span id="modalTrocoPara"></span></p>
            <p><strong>Itens:</strong></p>
            <ul id="modalListaItens"><li>Carregando...</li></ul>
            <p><strong>Observações do Pedido:</strong> <span id="modalObservacoesPedido">Nenhuma.</span></p>
        </div>
        <div class="modal-actions">
            <button id="modalBtnAceitar" class="btn-aceitar" title="Aceitar e mover para Preparando">Aceitar Pedido</button>
            <button id="modalBtnCancelar" class="btn-cancelar" title="Cancelar este pedido">Cancelar Pedido</button>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/kanban_script.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.body.classList.add('kanban-page-active');
    });
</script>

<?php
$page_content = ob_get_clean();
include '../template_admin.php'; 
?>
