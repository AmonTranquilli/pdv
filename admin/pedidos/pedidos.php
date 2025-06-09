<?php
session_start();
require_once '../../includes/conexao.php'; 

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php"); 
    exit();
}

// Lógica do Filtro de Data
$data_hoje = date('Y-m-d');
$data_filtro = isset($_GET['data_filtro']) ? $_GET['data_filtro'] : $data_hoje;
$sql_where = "";
$params = [];
$types = "";

if (!empty($data_filtro)) {
    if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $data_filtro)) {
        $sql_where = " WHERE DATE(data_pedido) = ?";
        $params[] = $data_filtro;
        $types .= "s";
    } else {
        $data_filtro = ''; // Limpa se o formato for inválido
    }
}

$page_title = 'Histórico de Pedidos';
ob_start();
?>

<style>
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(0, 0, 0, 0.6);
        display: none; /* Começa escondido */
        justify-content: center; align-items: center;
        z-index: 1050; opacity: 0; transition: opacity 0.3s ease;
    }
    /* Classe para mostrar o modal com animação */
    .modal-overlay.visible {
        display: flex;
        opacity: 1;
    }
    .modal-content {
        background-color: #fff; padding: 30px; border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        width: 90%; max-width: 500px;
        text-align: center;
        transform: scale(0.95);
        transition: transform 0.3s ease;
    }
    .modal-overlay.visible .modal-content {
        transform: scale(1);
    }
    .modal-content h2 { color: #333; margin-top: 0; }
    .modal-content p { color: #666; font-size: 1.1em; }
    .modal-actions { margin-top: 25px; display: flex; justify-content: center; gap: 15px; }

    /* Estilos do Filtro de Data */
    .header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
    .header-controls h1 { margin: 0; }
    .filter-form .date-filter-group { display: flex; gap: 10px; align-items: center; }
    .filter-form input[type="date"] { padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 0.9rem; }
    .filter-form .btn { padding: 9px 15px; text-decoration: none; font-size: 0.9rem; border-radius: 5px; }
</style>

<div class="header-controls">
    <h1><i class="fas fa-history"></i> <?= htmlspecialchars($page_title) ?></h1>
    <form action="pedidos.php" method="GET" class="filter-form">
        <div class="date-filter-group">
            <input type="date" id="data_filtro" name="data_filtro" value="<?= htmlspecialchars($data_filtro); ?>" onchange="this.form.submit()">
            <a href="pedidos.php?data_filtro=<?= $data_hoje; ?>" class="btn btn-info">Hoje</a>
            <a href="pedidos.php" class="btn btn-secondary">Ver Todos</a>
        </div>
    </form>
</div>

<div class="message-area">
    <?php
    if (isset($_SESSION['mensagem_sucesso'])) {
        echo '<p class="message-success"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['mensagem_sucesso']) . '</p>';
        unset($_SESSION['mensagem_sucesso']);
    }
    if (isset($_SESSION['mensagem_erro'])) {
        echo '<p class="message-error"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($_SESSION['mensagem_erro']) . '</p>';
        unset($_SESSION['mensagem_erro']);
    }
    ?>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th> <th>Cliente</th> <th>Total</th> <th>Pagamento</th> <th>Status</th> <th>Data/Hora</th> <th class="text-center">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT id, nome_cliente, total_pedido, forma_pagamento, status, data_pedido FROM pedidos" . $sql_where . " ORDER BY data_pedido DESC";
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                while ($pedido = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($pedido['id']); ?></td>
                        <td><?= htmlspecialchars($pedido['nome_cliente']); ?></td>
                        <td>R$ <?= number_format($pedido['total_pedido'], 2, ',', '.'); ?></td>
                        <td><?= htmlspecialchars(ucfirst($pedido['forma_pagamento'])); ?></td>
                        <td><span class="status-badge status-<?= htmlspecialchars(strtolower($pedido['status'])); ?>"><?= htmlspecialchars(ucfirst($pedido['status'])); ?></span></td>
                        <td><?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></td>
                        <td class="text-center">
                            <a href="detalhes_pedido.php?id=<?= $pedido['id']; ?>" class="btn btn-info btn-sm" title="Ver Detalhes"><i class="fas fa-eye"></i></a>
                            <button type="button" class="btn btn-danger btn-sm btn-delete" data-pedido-id="<?= $pedido['id']; ?>" title="Apagar Pedido">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                <?php }
            } else {
                echo '<tr><td colspan="7" class="text-center">Nenhum pedido encontrado.</td></tr>';
            }
            $stmt->close();
            $conn->close();
            ?>
        </tbody>
    </table>
</div>

<div id="confirmDeleteModal" class="modal-overlay">
    <div class="modal-content">
        <h2>Confirmar Exclusão</h2>
        <p>Tem certeza que deseja apagar o pedido <strong id="pedidoIdParaExcluir">#0</strong>? Esta ação é irreversível.</p>
        <div class="modal-actions">
            <button type="button" id="btnCancelDelete" class="btn btn-secondary">Não, cancelar</button>
            <a href="#" id="btnConfirmDelete" class="btn btn-danger">Sim, apagar</a>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include '../template_admin.php'; 
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('confirmDeleteModal');
    if (!modal) return;

    const pedidoIdSpan = document.getElementById('pedidoIdParaExcluir');
    const btnConfirm = document.getElementById('btnConfirmDelete');
    const btnCancel = document.getElementById('btnCancelDelete');
    
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault(); 
            const pedidoId = this.dataset.pedidoId;
            
            pedidoIdSpan.textContent = `#${pedidoId}`;
            btnConfirm.href = `excluir_pedido.php?id=${pedidoId}`;
            
            modal.classList.add('visible');
        });
    });

    function fecharModal() {
        modal.classList.remove('visible');
    }

    btnCancel.addEventListener('click', fecharModal);

    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            fecharModal();
        }
    });
});
</script>