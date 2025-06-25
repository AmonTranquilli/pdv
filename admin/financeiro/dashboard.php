<?php
// Arquivo: /admin/financeiro/dashboard.php (Versão Final Completa e Corrigida)
session_start();

$page_title = 'Dashboard Financeiro';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: /pdv/admin/login.php");
    exit;
}

require_once '../../includes/conexao.php';

// --- LÓGICA UNIFICADA PARA FILTRO E DATAS ---

// 1. Pega as datas do filtro (via GET) ou define o padrão
$data_inicio_filtro = $_GET['data_inicio'] ?? '';
$data_fim_filtro = $_GET['data_fim'] ?? '';

// 2. Se as datas não foram enviadas, define o padrão para a semana atual (de Segunda a Domingo)
if (empty($data_inicio_filtro) || empty($data_fim_filtro)) {
    $hoje = new DateTime();
    $dia_da_semana = (int)$hoje->format('N'); // 1 (para Segunda) a 7 (para Domingo)

    // Calcula o início da semana (última segunda-feira)
    $data_inicio_filtro = (clone $hoje)->modify('-' . ($dia_da_semana - 1) . ' days')->format('Y-m-d');

    // Calcula o fim da semana (próximo domingo)
    $data_fim_filtro = (clone $hoje)->modify('+' . (7 - $dia_da_semana) . ' days')->format('Y-m-d');
}

// 3. Cria o texto do cabeçalho para exibir o período
$resumo_periodo = "Exibindo resultados de <strong>" . date('d/m/Y', strtotime($data_inicio_filtro)) . "</strong> a <strong>" . date('d/m/Y', strtotime($data_fim_filtro)) . "</strong>";


// --- LÓGICA PARA BUSCAR E CALCULAR OS DADOS ---

// Inicializa todas as variáveis
$faturamento_bruto = 0;
$faturamento_cancelado = 0;
$pedidos_validos = 0;
$ticket_medio = 0;
$vendas_por_pagamento = ['dinheiro' => 0, 'cartao' => 0, 'pix' => 0];
$faturamento_por_dia = [];

try {
    // Query agora usa o período de datas definido
    $sql = "SELECT total_pedido, forma_pagamento, status, DATE(data_pedido) as dia 
            FROM pedidos 
            WHERE DATE(data_pedido) BETWEEN ? AND ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $data_inicio_filtro, $data_fim_filtro);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        // Inicializa o array do gráfico com todas as datas do intervalo com valor 0
        $periodo = new DatePeriod(new DateTime($data_inicio_filtro), new DateInterval('P1D'), (new DateTime($data_fim_filtro))->modify('+1 day'));
        foreach ($periodo as $data) {
            $faturamento_por_dia[$data->format('Y-m-d')] = 0;
        }

        while ($pedido = $result->fetch_assoc()) {
            if ($pedido['status'] == 'cancelado') {
                $faturamento_cancelado += (float)$pedido['total_pedido'];
            } else {
                $faturamento_bruto += (float)$pedido['total_pedido'];
                $pedidos_validos++;

                $forma_pagamento = strtolower($pedido['forma_pagamento']);
                if (isset($vendas_por_pagamento[$forma_pagamento])) {
                    $vendas_por_pagamento[$forma_pagamento] += (float)$pedido['total_pedido'];
                }

                if (isset($faturamento_por_dia[$pedido['dia']])) {
                    $faturamento_por_dia[$pedido['dia']] += (float)$pedido['total_pedido'];
                }
            }
        }

        if ($pedidos_validos > 0) {
            $ticket_medio = $faturamento_bruto / $pedidos_validos;
        }
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Erro no Dashboard Financeiro: " . $e->getMessage());
}
$conn->close();

// Prepara os dados do gráfico para o JavaScript
ksort($faturamento_por_dia); // Garante a ordem cronológica
$grafico_labels = array_map(fn($data) => date('d/m', strtotime($data)), array_keys($faturamento_por_dia));
$grafico_data = array_values($faturamento_por_dia);

ob_start();
?>

<style>
    .dashboard-header {
        margin-bottom: 2rem;
    }

    .dashboard-header h1 {
        margin: 0;
    }

    .dashboard-header p {
        color: #6c757d;
        font-size: 1rem;
    }

    .filter-form {
        display: flex;
        align-items: center;
        /* Alinhar verticalmente ao centro */
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
        /* Reduzindo a margem inferior */
        padding: 5px 0;
        /* Reduzindo o padding interno */
    }

    .filter-form .form-group {
        display: flex;
        flex-direction: column;
    }

    .filter-form label {
        font-size: 0.8em;
        font-weight: normal;
        color: #777;
        margin-bottom: 3px;
    }

    .filter-form input {
        padding: 8px;
        border-radius: 6px;
        border: 1px solid #ddd;
        font-size: 0.9em;
    }

    .filter-form .btn-filtrar {
        padding: 8px 15px;
        font-weight: normal;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9em;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
    }

    .stat-card {
        background-color: #ffffff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
    }

    .stat-card .icon {
        font-size: 2.2rem;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20px;
    }

    .stat-card .info h3 {
        margin: 0 0 5px 0;
        font-size: 0.85rem;
        color: #6c757d;
        text-transform: uppercase;
    }

    .stat-card .info p {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 700;
    }

    .stat-card .icon.faturamento {
        background-color: #d4edda;
        color: #155724;
    }

    .stat-card .icon.pedidos {
        background-color: #cce5ff;
        color: #004085;
    }

    .stat-card .icon.ticket {
        background-color: #fff3cd;
        color: #856404;
    }

    .stat-card .icon.cancelado {
        background-color: #f8d7da;
        color: #721c24;
    }

    .details-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-top: 20px;
    }

    .details-card {
        background-color: #ffffff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .details-card h2 {
        margin-top: 0;
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
    }

    .payment-summary {
        list-style: none;
        padding: 0;
    }

    .payment-summary li {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .payment-summary li:last-child {
        border-bottom: none;
    }

    .payment-summary li strong {
        font-weight: 600;
    }

    .chart-container {
        position: relative;
        height: 350px;
        width: 100%;
    }

    @media (max-width: 992px) {
        .details-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dashboard-header">
    <h1>Dashboard Financeiro</h1>
    <p><?= $resumo_periodo ?></p>
</div>

<div class="filter-container">
    <h3><i class="fas fa-filter"></i> Filtrar por Período</h3>
    <form method="GET" action="dashboard.php" class="filter-form">
        <div class="form-group">
            <label for="data_inicio">De:</label>
            <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($data_inicio_filtro) ?>" required>
        </div>
        <div class="form-group">
            <label for="data_fim">Até:</label>
            <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim_filtro) ?>" required>
        </div>
        <button type="submit" class="btn-filtrar">Filtrar</button>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="icon faturamento"><i class="fas fa-dollar-sign"></i></div>
        <div class="info">
            <h3>Faturamento Bruto</h3>
            <p>R$ <?= number_format($faturamento_bruto, 2, ',', '.') ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon pedidos"><i class="fas fa-check-circle"></i></div>
        <div class="info">
            <h3>Pedidos Válidos</h3>
            <p><?= $pedidos_validos ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon ticket"><i class="fas fa-chart-bar"></i></div>
        <div class="info">
            <h3>Ticket Médio</h3>
            <p>R$ <?= number_format($ticket_medio, 2, ',', '.') ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon cancelado"><i class="fas fa-times-circle"></i></div>
        <div class="info">
            <h3>Perdas (Cancelados)</h3>
            <p>R$ <?= number_format($faturamento_cancelado, 2, ',', '.') ?></p>
        </div>
    </div>
</div>

<div class="details-grid">
    <div class="details-card">
        <h2><i class="fas fa-chart-line"></i> Vendas por Dia</h2>
        <div class="chart-container">
            <canvas id="vendasSemanaChart"></canvas>
        </div>
    </div>
    <div class="details-card">
        <h2><i class="fas fa-wallet"></i> Vendas por Pagamento</h2>
        <ul class="payment-summary">
            <li><span><i class="fas fa-money-bill-wave"></i> Dinheiro</span> <strong>R$ <?= number_format($vendas_por_pagamento['dinheiro'], 2, ',', '.') ?></strong></li>
            <li><span><i class="fas fa-credit-card"></i> Cartão</span> <strong>R$ <?= number_format($vendas_por_pagamento['cartao'], 2, ',', '.') ?></strong></li>
            <li><span><i class="fas fa-qrcode"></i> Pix</span> <strong>R$ <?= number_format($vendas_por_pagamento['pix'], 2, ',', '.') ?></strong></li>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('vendasSemanaChart')?.getContext('2d');
        if (!ctx) return;

        const labels = <?= json_encode($grafico_labels) ?>;
        const data = <?= json_encode($grafico_data) ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Faturamento R$',
                    data: data,
                    backgroundColor: 'rgba(255, 87, 34, 0.7)',
                    borderColor: 'rgba(255, 87, 34, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += 'R$ ' + context.parsed.y.toFixed(2).replace('.', ',');
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<?php
$page_content = ob_get_clean();
include '../template_admin.php';
?>