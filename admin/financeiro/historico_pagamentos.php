<?php
// Arquivo: /admin/financeiro/historico_pagamentos.php (VERSÃO REFORMULADA)
session_start();

$page_title = 'Histórico de Pagamentos';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: /pdv/admin/login.php");
    exit;
}

require_once '../../includes/conexao.php';

// --- LÓGICA DO FILTRO ---
$filtro_funcionario_id = $_GET['funcionario_id'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';

// Monta a query SQL com base nos filtros
$sql = "SELECT 
            p.*, 
            f.nome as nome_funcionario,
            (SELECT SUM(v.valor) FROM vales_funcionarios v WHERE v.id_pagamento_descontado = p.id) as total_vales_descontados
        FROM 
            pagamentos_funcionarios p
        JOIN 
            funcionarios f ON p.id_funcionario = f.id";
$where_clauses = [];
$params = [];
$types = '';

if (!empty($filtro_funcionario_id)) {
    $where_clauses[] = "p.id_funcionario = ?";
    $params[] = $filtro_funcionario_id;
    $types .= 'i';
}
if (!empty($filtro_data_inicio)) {
    $where_clauses[] = "p.data_pagamento >= ?";
    $params[] = $filtro_data_inicio . " 00:00:00";
    $types .= 's';
}
if (!empty($filtro_data_fim)) {
    $where_clauses[] = "p.data_pagamento <= ?";
    $params[] = $filtro_data_fim . " 23:59:59";
    $types .= 's';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY p.data_pagamento DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$pagamentos = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Busca a lista de todos os funcionários para o filtro <select>
$funcionarios_para_filtro = $conn->query("SELECT id, nome FROM funcionarios ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$conn->close();

// --- CÁLCULO PARA OS CARDS DE RESUMO ---
$total_pago_periodo = 0;
foreach ($pagamentos as $pagamento) {
    $total_pago_periodo += $pagamento['valor_pago'];
}
$numero_de_pagamentos = count($pagamentos);

ob_start();
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    /* Estilos para os Cards de Resumo */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 2rem;
    }

    .stat-card {
        background-color: #fff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 1rem;
        color: #6c757d;
        text-transform: uppercase;
    }

    .stat-card p {
        margin: 0;
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-color);
    }

    /* Estilos para o container de filtros */
    .filter-container {
        background-color: #fff;
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .filter-form {
        display: flex;
        align-items: flex-end;
        gap: 20px;
        flex-wrap: wrap;
    }

    .form-group {
        flex: 1;
        min-width: 200px;
    }

    .form-group label {
        font-weight: 600;
        color: #555;
        margin-bottom: 8px;
        display: block;
    }

    .form-group select,
    .form-group input {
        width: 100%;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #ccc;
        box-sizing: border-box;
    }

    .btn {
        padding: 12px 25px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        border: none;
        cursor: pointer;
    }

    .btn-primary {
        background-color: var(--primary-color);
        color: white;
    }

    /* Estilos para a Tabela */
    .table-container {
        background-color: #fff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        overflow-x: auto;
    }

    .styled-table {
        width: 100%;
        border-collapse: collapse;
    }

    .styled-table thead th {
        background-color: #f8f9fa;
        text-align: left;
        padding: 15px;
        font-size: 0.85em;
        text-transform: uppercase;
        color: #6c757d;
    }

    .styled-table tbody td {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        color: #333;
    }

    .styled-table tbody tr:last-of-type td {
        border-bottom: none;
    }

    .details-cell {
        font-size: 0.9em;
        color: #666;
    }
</style>

<div class="page-header">
    <h1><i class="fas fa-history"></i> Histórico de Pagamentos</h1>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Valor Total Pago</h3>
        <p>R$ <?= number_format($total_pago_periodo, 2, ',', '.') ?></p>
    </div>
    <div class="stat-card">
        <h3>Nº de Pagamentos</h3>
        <p><?= $numero_de_pagamentos ?></p>
    </div>
</div>

<div class="filter-container">
    <form method="GET" action="historico_pagamentos.php" class="filter-form">
        <div class="form-group">
            <label for="funcionario_id">Filtrar por Funcionário</label>
            <select id="funcionario_id" name="funcionario_id">
                <option value="">Todos os Funcionários</option>
                <?php foreach ($funcionarios_para_filtro as $func): ?>
                    <option value="<?= $func['id'] ?>" <?= ($filtro_funcionario_id == $func['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($func['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="data_inicio">De:</label>
            <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($filtro_data_inicio) ?>">
        </div>
        <div class="form-group">
            <label for="data_fim">Até:</label>
            <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($filtro_data_fim) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
    </form>
</div>

<div class="table-container">
    <table class="styled-table">
        <thead>
            <tr>
                <th>Data do Pagamento</th>
                <th>Funcionário</th>
                <th>Período Referente</th>
                <th style="text-align: right;">Valor Pago</th>
                <th>Detalhes do Cálculo</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($pagamentos)): ?>
                <?php foreach ($pagamentos as $pagamento): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($pagamento['data_pagamento'])) ?></td>
                        <td><?= htmlspecialchars($pagamento['nome_funcionario']) ?></td>
                        <td><?= date('d/m/Y', strtotime($pagamento['periodo_inicio'])) ?> a <?= date('d/m/Y', strtotime($pagamento['periodo_fim'])) ?></td>
                        <td style="text-align: right; font-weight: bold; color: #28a745;">R$ <?= number_format($pagamento['valor_pago'], 2, ',', '.') ?></td>
                        <td class="details-cell">
                            Dias Pagos: <?= $pagamento['dias_trabalhados'] ?><br>
                            Faltas: <?= $pagamento['faltas_descontadas'] ?><br>
                            <strong style="color: #dc3545;">Vales: -R$ <?= number_format($pagamento['total_vales_descontados'] ?? 0, 2, ',', '.') ?></strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; font-style: italic;">Nenhum registro de pagamento encontrado para os filtros selecionados.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$page_content = ob_get_clean();
include '../template_admin.php';
?>