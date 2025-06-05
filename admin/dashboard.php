<?php
session_start();
require_once '../includes/conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: login.php");
    exit();
}

$nome_usuario = $_SESSION['nome_usuario'];
$nivel_acesso = $_SESSION['nivel_acesso'];
$page_title = 'Dashboard';

// Define o fuso horário para garantir consistência nas operações de data/hora
date_default_timezone_set('America/Sao_Paulo'); // Altere para o fuso horário da sua loja, se diferente

// Obtém a data via GET ou assume hoje
$dataSelecionada = $_GET['data'] ?? date('Y-m-d');
$exibindoOutroDia = $dataSelecionada !== date('Y-m-d');

// Sanitiza e valida a data com segurança
try {
    $dataObj = new DateTime($dataSelecionada);
    $dataSelecionada = $dataObj->format('Y-m-d');
} catch (Exception $e) {
    // Em caso de data inválida, volta para a data de hoje
    $dataSelecionada = date('Y-m-d');
}

// --- Consultas ao Banco de Dados usando Prepared Statements ---

// Total de pedidos do dia
$stmt_pedidos_hoje = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM pedidos 
    WHERE DATE(data_pedido) = ?
");
$stmt_pedidos_hoje->bind_param("s", $dataSelecionada);
$stmt_pedidos_hoje->execute();
$pedidosHoje = $stmt_pedidos_hoje->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_pedidos_hoje->close();

// Faturamento (exclui cancelados)
$stmt_faturamento_hoje = $conn->prepare("
    SELECT SUM(total_pedido) as total 
    FROM pedidos 
    WHERE DATE(data_pedido) = ? 
    AND LOWER(TRIM(status)) != 'cancelado'
");
$stmt_faturamento_hoje->bind_param("s", $dataSelecionada);
$stmt_faturamento_hoje->execute();
$faturamentoHoje = $stmt_faturamento_hoje->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_faturamento_hoje->close();

// Clientes cadastrados no dia
$stmt_total_clientes = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM clientes 
    WHERE DATE(data_cadastro) = ?
");
$stmt_total_clientes->bind_param("s", $dataSelecionada);
$stmt_total_clientes->execute();
$totalClientes = $stmt_total_clientes->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_total_clientes->close();

// Contagem por status
$stmt_por_status = $conn->prepare("
    SELECT status, COUNT(*) as total 
    FROM pedidos 
    WHERE DATE(data_pedido) = ? 
    GROUP BY status
");
$stmt_por_status->bind_param("s", $dataSelecionada);
$stmt_por_status->execute();
$porStatus = $stmt_por_status->get_result();
$statusData = [];
while ($row = $porStatus->fetch_assoc()) {
    $statusData[$row['status']] = $row['total'];
}
$stmt_por_status->close();

// Faturamento por forma de pagamento
$stmt_por_pagamento = $conn->prepare("
    SELECT forma_pagamento, SUM(total_pedido) as total 
    FROM pedidos 
    WHERE DATE(data_pedido) = ? 
    AND LOWER(TRIM(status)) != 'cancelado' 
    GROUP BY forma_pagamento
");
$stmt_por_pagamento->bind_param("s", $dataSelecionada);
$stmt_por_pagamento->execute();
$porPagamento = $stmt_por_pagamento->get_result();
$pagamentoData = [];
while ($row = $porPagamento->fetch_assoc()) {
    $pagamentoData[$row['forma_pagamento']] = $row['total'];
}
$stmt_por_pagamento->close();

ob_start();
?>

<style>
    .dashboard-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .card {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        text-align: center;
    }

    .card h2 {
        font-size: 1rem;
        color: #666;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .card h2 .fa {
        color: #4a90e2;
        font-size: 1.3rem;
    }

    .card p {
        font-size: 2.2rem;
        font-weight: bold;
        color: #222;
        margin: 0;
    }

    ul.status-list, ul.pagamento-list {
        list-style: none;
        padding-left: 0;
        margin: 0;
    }

    ul.status-list li, ul.pagamento-list li {
        font-size: 1.1rem;
        margin-bottom: 8px;
        border-bottom: 1px solid #eee;
        padding-bottom: 4px;
        display: flex;
        justify-content: space-between;
    }

    ul.status-list li strong, ul.pagamento-list li strong {
        color: #000;
    }

    .date-selector {
        font-size: 1rem;
        padding: 6px 12px;
        border-radius: 5px;
        border: 1px solid #ccc;
    }
</style>

<div class="dashboard-top">
    <h1>📊 Painel do Dia</h1>
    <form method="get" style="display: flex; gap: 10px; align-items: center;">
        <input class="date-selector" type="date" name="data" value="<?= $dataSelecionada ?>" onchange="this.form.submit()">
        <a href="dashboard.php" class="date-selector" style="text-decoration: none; background: #4a90e2; color: white; border: none; padding: 6px 12px; border-radius: 5px;" type="button">Hoje</a>
    </form>
</div>

<?php if ($exibindoOutroDia): ?>
    <div style="margin-bottom: 20px; padding: 10px 15px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 5px;">
        📅 Exibindo dados de: <strong><?= date('d/m/Y', strtotime($dataSelecionada)) ?></strong>
    </div>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="card">
        <h2><i class="fa fa-shopping-cart"></i> Pedidos do Dia</h2>
        <p><?= $pedidosHoje ?></p>
    </div>
    <div class="card">
        <h2><i class="fa fa-dollar-sign"></i> Faturamento do Dia</h2>
        <p>R$ <?= number_format($faturamentoHoje, 2, ',', '.') ?></p>
    </div>
    <div class="card">
        <h2><i class="fa fa-users"></i> Cadastros do Dia</h2>
        <p><?= $totalClientes ?></p>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <h2><i class="fa fa-clipboard-list"></i> Pedidos por Status</h2>
        <ul class="status-list">
            <?php if (empty($statusData)): ?>
                <li><span>Nenhum pedido para esta data.</span></li>
            <?php else: ?>
                <?php foreach ($statusData as $status => $total): ?>
                    <li><span><?= ucfirst($status) ?></span> <strong><?= $total ?></strong></li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
    <div class="card">
        <h2><i class="fa fa-credit-card"></i> Faturamento por Forma de Pagamento</h2>
        <ul class="pagamento-list">
            <?php if (empty($pagamentoData)): ?>
                <li><span>Nenhum faturamento para esta data.</span></li>
            <?php else: ?>
                <?php foreach ($pagamentoData as $forma => $valor): ?>
                    <li><span><?= ucfirst($forma) ?></span> <strong>R$ <?= number_format($valor, 2, ',', '.') ?></strong></li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include 'template_admin.php';
?>
