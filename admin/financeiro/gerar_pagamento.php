<?php
// Arquivo: /admin/financeiro/gerar_pagamento.php (Versão Final com Layout Melhorado)
session_start();

$page_title = 'Gerar Folha de Pagamento';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: /pdv/admin/login.php");
    exit;
}

require_once '../../includes/conexao.php';

// --- Etapa 1: Obter dados do funcionário ---
$funcionario_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$funcionario_id) {
    header("Location: funcionarios.php");
    exit;
}

$stmt_func = $conn->prepare("SELECT id, nome, valor_diaria FROM funcionarios WHERE id = ?");
$stmt_func->bind_param("i", $funcionario_id);
$stmt_func->execute();
$result_func = $stmt_func->get_result();
$funcionario = $result_func->fetch_assoc();
$stmt_func->close();

if (!$funcionario) {
    die("Funcionário não encontrado.");
}

// --- LÓGICA DE DATAS E CÁLCULO ---
$calculo_detalhes = null;

// Define as datas padrão para a semana atual (Segunda a Domingo)
$hoje = new DateTime();
$dia_da_semana = (int)$hoje->format('N');
$data_inicio_padrao = (clone $hoje)->modify('-' . ($dia_da_semana - 1) . ' days')->format('Y-m-d');
$data_fim_padrao = (clone $hoje)->modify('+' . (7 - $dia_da_semana) . ' days')->format('Y-m-d');

$data_inicio = $_POST['data_inicio'] ?? $data_inicio_padrao;
$data_fim = $_POST['data_fim'] ?? $data_fim_padrao;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql_config = "SELECT dias_abertura FROM configuracoes_loja WHERE id = 1";
        $config = $conn->query($sql_config)->fetch_assoc();
        $dias_funcionamento_array = explode(',', $config['dias_abertura'] ?? '1,2,3,4,5,6,7');

        $periodo = new DatePeriod(new DateTime($data_inicio), new DateInterval('P1D'), (new DateTime($data_fim))->modify('+1 day'));
        $dias_uteis_no_periodo = 0;
        foreach ($periodo as $data) {
            if (in_array((int)$data->format('N'), $dias_funcionamento_array)) {
                $dias_uteis_no_periodo++;
            }
        }

        $stmt_faltas = $conn->prepare("SELECT COUNT(id) as total_faltas FROM registros_diarios_funcionarios WHERE id_funcionario = ? AND tipo_registro = 'FALTA_INJUSTIFICADA' AND data_registro BETWEEN ? AND ?");
        $stmt_faltas->bind_param("iss", $funcionario_id, $data_inicio, $data_fim);
        $stmt_faltas->execute();
        $faltas_injustificadas = (int)$stmt_faltas->get_result()->fetch_assoc()['total_faltas'];
        $stmt_faltas->close();
        $stmt_vales = $conn->prepare("SELECT SUM(valor) as total_vales FROM vales_funcionarios WHERE id_funcionario = ? AND id_pagamento_descontado IS NULL");
        $stmt_vales->bind_param("i", $funcionario_id);
        $stmt_vales->execute();
        $total_vales = (float)$stmt_vales->get_result()->fetch_assoc()['total_vales'];
        $stmt_vales->close();

        // d. Lógica de cálculo detalhada
        $valor_diaria = (float)$funcionario['valor_diaria'];
        $salario_bruto = $dias_uteis_no_periodo * $valor_diaria;
        $desconto_faltas = $faltas_injustificadas * $valor_diaria;
        $dias_a_pagar = $dias_uteis_no_periodo - $faltas_injustificadas;
        $valor_a_pagar = $salario_bruto - $desconto_faltas - $total_vales;

        // Guarda todos os detalhes para exibir na tela
        $calculo_detalhes = [
            'dias_uteis' => $dias_uteis_no_periodo,
            'faltas' => $faltas_injustificadas,
            'dias_pagos' => $dias_a_pagar,
            'salario_bruto' => $salario_bruto,
            'desconto_faltas' => $desconto_faltas,
            'total_vales' => $total_vales,
            'valor_final' => $valor_a_pagar
        ];
    } catch (Exception $e) {
        $mensagem_erro = "Erro ao calcular pagamento: " . $e->getMessage();
    }
}
$conn->close();
ob_start();
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
        padding: 10px 15px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
    }

    /* Novo layout em duas colunas */
    .payment-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        /* Duas colunas de tamanho igual */
        gap: 30px;
        align-items: flex-start;
    }

    /* Estilo do card principal */
    .card-style {
        background-color: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .card-style h3 {
        margin-top: 0;
        color: var(--primary-color);
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #555;
    }

    .form-group input,
    .form-group p {
        font-size: 1.1em;
        color: #333;
        margin: 0;
    }

    .form-group p strong {
        color: #000;
    }

    input[type="date"] {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
    }

    .btn-calculate {
        width: 100%;
        padding: 15px;
        border-radius: 8px;
        font-weight: bold;
        cursor: pointer;
        border: none;
        background-color: var(--primary-color);
        color: white;
        font-size: 1.1em;
        margin-top: 10px;
    }

    /* Estilos do resultado */
    .result-line {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #e9ecef;
        font-size: 1.05em;
    }

    .result-line.total {
        font-size: 1.3em;
        font-weight: bold;
        color: #28a745;
        border-top: 2px solid #ccc;
        margin-top: 10px;
        padding-top: 15px;
    }

    .btn-register-payment {
        width: 100%;
        margin-top: 30px;
        background-color: #28a745;
        padding: 15px;
        font-size: 1.1em;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }

    /* Responsividade para telas menores */
    @media (max-width: 992px) {
        .payment-layout {
            grid-template-columns: 1fr;
        }
    }

    .result-line.desconto {
        color: #dc3545;
        /* Vermelho para indicar um desconto */
        font-weight: 600;
    }
</style>

<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> Gerar Pagamento</h1>
    <a href="funcionarios.php" class="btn-secondary">Voltar para a Lista</a>
</div>

<div class="payment-layout">
    <div class="card-style">
        <h3>1. Dados e Período</h3>

        <div class="form-group">
            <label>Funcionário:</label>
            <p><strong><?= htmlspecialchars($funcionario['nome']) ?></strong></p>
        </div>
        <div class="form-group">
            <label>Valor da Diária:</label>
            <p><strong>R$ <?= number_format($funcionario['valor_diaria'], 2, ',', '.') ?></strong></p>
        </div>

        <form method="POST" action="gerar_pagamento.php?id=<?= $funcionario_id ?>">
            <div class="form-group">
                <label for="data_inicio">Data de Início:</label>
                <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" required>
            </div>
            <div class="form-group">
                <label for="data_fim">Data de Fim:</label>
                <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" required>
            </div>
            <button type="submit" class="btn-calculate">Calcular Pagamento</button>
        </form>
    </div>

    <div class="card-style">
        <h3>2. Resumo do Cálculo</h3>

        <?php if ($calculo_detalhes): ?>
            <div class="calculation-result">
                <h3 class="result-title">Resumo do Pagamento</h3>
                <div class="result-line">
                    <span>Total de dias de funcionamento:</span>
                    <strong><?= $calculo_detalhes['dias_uteis'] ?></strong>
                </div>
                <div class="result-line">
                    <span>Faltas:</span>
                    <strong><?= $calculo_detalhes['faltas'] ?></strong>
                </div>
                <div class="result-line">
                    <span>Total de diárias a pagar:</span>
                    <strong><?= $calculo_detalhes['dias_pagos'] ?></strong>
                </div>
                <hr style="border: none; border-top: 1px solid #eee; margin: 10px 0;">
                <div class="result-line">
                    <span>Salário Bruto (Dias de Funcionamento x valor):</span>
                    <strong>R$ <?= number_format($calculo_detalhes['salario_bruto'], 2, ',', '.') ?></strong>
                </div>
                <div class="result-line desconto">
                    <span>(-) Faltas no período:</span>
                    <strong>- R$ <?= number_format($calculo_detalhes['desconto_faltas'], 2, ',', '.') ?></strong>
                </div>
                <div class="result-line desconto">
                    <span>(-) Vales / Adiantamentos:</span>
                    <strong>- R$ <?= number_format($calculo_detalhes['total_vales'], 2, ',', '.') ?></strong>
                </div>
                <div class="result-line total">
                    <span>Valor Final a Pagar:</span>
                    <strong>R$ <?= number_format($calculo_detalhes['valor_final'], 2, ',', '.') ?></strong>
                </div>
                <button class="btn-register-payment">Confirmar e Registrar Pagamento</button>
            </div>
        <?php else: ?>
            <p>Preencha o período e clique em "Calcular" para ver o resumo do pagamento.</p>
        <?php endif; ?>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Pega o botão de registrar pagamento, se ele existir na página
        const btnRegisterPayment = document.querySelector('.btn-register-payment');

        // Adiciona o evento de clique SÓ se o botão existir
        if (btnRegisterPayment) {
            btnRegisterPayment.addEventListener('click', async function() {

                // Pede uma última confirmação ao usuário
                if (!confirm('Você confirma que realizou este pagamento e deseja registrá-lo no sistema? Esta ação não pode ser desfeita.')) {
                    return; // Se o usuário clicar em "Cancelar", a função para aqui
                }

                // Desabilita o botão para evitar cliques duplos
                this.disabled = true;
                this.textContent = 'Registrando...';

                // Coleta todos os dados necessários para a API diretamente das variáveis PHP
                const dadosPagamento = {
                    id_funcionario: <?= $funcionario['id'] ?>,
                    valor_pago: <?= $calculo_detalhes['valor_final'] ?? 0 ?>,
                    periodo_inicio: '<?= $data_inicio ?>',
                    periodo_fim: '<?= $data_fim ?>',
                    dias_trabalhados: <?= $calculo_detalhes['dias_pagos'] ?? 0 ?>,
                    faltas_descontadas: <?= $calculo_detalhes['faltas'] ?? 0 ?>
                };

                // Faz a chamada para a nova API
                try {
                    const response = await fetch('registrar_pagamento.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(dadosPagamento)
                    });
                    const result = await response.json();

                    alert(result.mensagem); // Mostra a mensagem de sucesso ou erro

                    if (result.sucesso) {
                        // Se o registro foi bem-sucedido, redireciona para a lista de funcionários
                        window.location.href = 'funcionarios.php';
                    } else {
                        // Se deu erro, reabilita o botão
                        this.disabled = false;
                        this.textContent = 'Confirmar e Registrar Pagamento';
                    }
                } catch (error) {
                    alert('Erro de conexão. Não foi possível registrar o pagamento.');
                    console.error('Erro:', error);
                    this.disabled = false;
                    this.textContent = 'Confirmar e Registrar Pagamento';
                }
            });
        }
    });
</script>
<?php
$page_content = ob_get_clean();
include '../template_admin.php';
?>