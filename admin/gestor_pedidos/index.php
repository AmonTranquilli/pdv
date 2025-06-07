<?php
session_start(); 

$page_title = 'Gestor de Pedidos'; 

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: /pdv/admin/login.php"); 
    exit;
}

$baseUrl = '../../public'; 

ob_start(); 
?>

<style>
    .header-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .btn-finalizar-dia {
        background-color: #28a745;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        font-size: 0.9em;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    .btn-finalizar-dia:hover {
        background-color: #218838;
    }
</style>

<link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/kanban_style.css">

<div class="admin-container-gestor"> 
    <header class="kanban-page-header header-controls">
        <h1><i class="fas fa-tasks"></i> <?php echo htmlspecialchars($page_title); ?></h1>
        
        <button id="btnFinalizarDia" class="btn-finalizar-dia">
            <i class="fas fa-power-off"></i> Finalizar Turno
        </button>
    </header>
    
    <div class="kanban-board-wrapper">
        <main class="kanban-board">
            <div class="kanban-column" id="coluna-pendente" data-status="pendente"><h2><i class="fas fa-hourglass-start"></i> Pendente</h2><div class="cards-container" id="container-pendente"></div></div>
            <div class="kanban-column" id="coluna-preparando" data-status="preparando"><h2><i class="fas fa-utensils"></i> Preparando</h2><div class="cards-container" id="container-preparando"></div></div>
            <div class="kanban-column" id="coluna-em_entrega" data-status="em_entrega"><h2><i class="fas fa-motorcycle"></i> Em Entrega</h2><div class="cards-container" id="container-em_entrega"></div></div>
            <div class="kanban-column" id="coluna-finalizado" data-status="finalizado"><h2><i class="fas fa-flag-checkered"></i> Finalizado</h2><div class="cards-container" id="container-finalizado"></div></div>
            <div class="kanban-column" id="coluna-cancelado" data-status="cancelado"><h2><i class="fas fa-times-circle"></i> Cancelado</h2><div class="cards-container" id="container-cancelado"></div></div>
        </main>
    </div>
</div>

<div id="modalDetalhesPedido" class="modal-overlay">
    <div class="modal-content">
        <span class="modal-close-btn" onclick="fecharModalDetalhes()">&times;</span>
        <h2>Detalhes do Pedido: <span id="modalPedidoId"></span></h2>
        <div id="modalCorpoDetalhes"></div>
        <div class="modal-actions">
            <button id="modalBtnAceitar" class="btn-aceitar">Aceitar Pedido</button>
            <button id="modalBtnCancelar" class="btn-cancelar">Cancelar Pedido</button>
        </div>
    </div>
</div>

<div id="notificationModal" class="modal-overlay">
    <div class="modal-content notification-modal">
        <h2 id="notificationTitle">Aviso</h2>
        <p id="notificationMessage">Mensagem aqui.</p>
        <div id="notificationActions" class="modal-actions">
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
$page_cont ent = ob_get_clean();
include '../template_admin.php'; 
?>
