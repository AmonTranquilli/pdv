<?php
// Arquivo: /admin/financeiro/editar_funcionario.php (Versão Final com Abas)
session_start();

$page_title = 'Editar Funcionário';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: /pdv/admin/login.php");
    exit;
}
require_once '../../includes/conexao.php';

$mensagem_erro = '';
$funcionario_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$funcionario_id) {
    header("Location: funcionarios.php");
    exit;
}

// Lógica de ATUALIZAÇÃO quando o formulário de dados é enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_dados_cadastrais'])) {
    $nome = trim($_POST['nome']);
    $cargo = trim($_POST['cargo']);
    $valor_diaria = str_replace(',', '.', $_POST['valor_diaria']);
    $periodo_pagamento = $_POST['periodo_pagamento'];
    $data_admissao = $_POST['data_admissao'];
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if (empty($nome) || empty($valor_diaria) || empty($data_admissao)) {
        $mensagem_erro = "Nome, Valor da Diária e Data de Admissão são obrigatórios.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE funcionarios SET nome = ?, cargo = ?, valor_diaria = ?, periodo_pagamento = ?, data_admissao = ?, ativo = ? WHERE id = ?");
            $stmt->bind_param("ssdissi", $nome, $cargo, $valor_diaria, $periodo_pagamento, $data_admissao, $ativo, $funcionario_id);
            if ($stmt->execute()) {
                $_SESSION['feedback_mensagem'] = "Dados atualizados com sucesso!";
                $_SESSION['feedback_sucesso'] = true;
                header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $funcionario_id); // Recarrega a página para ver as mudanças
                exit;
            }
            $stmt->close();
        } catch (Exception $e) {
            $mensagem_erro = "Erro: " . $e->getMessage();
        }
    }
}

// Lógica para CARREGAR todos os dados da página
try {
    $stmt_func = $conn->prepare("SELECT * FROM funcionarios WHERE id = ?");
    $stmt_func->bind_param("i", $funcionario_id);
    $stmt_func->execute();
    $funcionario = $stmt_func->get_result()->fetch_assoc();
    $stmt_func->close();
    if (!$funcionario) {
        header("Location: funcionarios.php");
        exit;
    }

    $historico_ocorrencias = [];
    $valor_diaria = (float)$funcionario['valor_diaria'];

    // Busca Faltas
    $stmt_faltas = $conn->prepare("SELECT id, data_registro as data_evento, tipo_registro as detalhes, observacao FROM registros_diarios_funcionarios WHERE id_funcionario = ?");
    $stmt_faltas->bind_param("i", $funcionario_id);
    $stmt_faltas->execute();
    $result_faltas = $stmt_faltas->get_result();
    while ($falta = $result_faltas->fetch_assoc()) {
        $falta['tipo_evento'] = 'falta';
        $falta['valor_impacto'] = ($falta['detalhes'] === 'FALTA_INJUSTIFICADA') ? '- R$ ' . number_format($valor_diaria, 2, ',', '.') : 'Sem Desconto';
        $historico_ocorrencias[] = $falta;
    }
    $stmt_faltas->close();

    // Busca Vales
    $stmt_vales = $conn->prepare("SELECT id, data_vale as data_evento, valor, motivo as detalhes, id_pagamento_descontado FROM vales_funcionarios WHERE id_funcionario = ?");
    $stmt_vales->bind_param("i", $funcionario_id);
    $stmt_vales->execute();
    $result_vales = $stmt_vales->get_result();
    while ($vale = $result_vales->fetch_assoc()) {
        $vale['tipo_evento'] = 'vale';
        $vale['valor_impacto'] = '<strong style="color: #dc3545;">- R$ ' . number_format($vale['valor'], 2, ',', '.') . '</strong>';
        $historico_ocorrencias[] = $vale;
    }
    $stmt_vales->close();

    usort($historico_ocorrencias, fn($a, $b) => strtotime($b['data_evento']) - strtotime($a['data_evento']));
} catch (Exception $e) {
    $mensagem_erro = "Erro ao carregar dados: " . $e->getMessage();
}
$conn->close();

if (isset($_SESSION['feedback_mensagem'])) {
    $mensagem_feedback = $_SESSION['feedback_mensagem'];
    unset($_SESSION['feedback_mensagem']);
}

ob_start();
?>

<style>
    .page-container {
        max-width: 900px;
        margin: 0 auto;
    }

    .form-header {
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

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        text-align: center;
    }

    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
    }

    /* Estilos para as Abas */
    .tabs-nav {
        display: flex;
        border-bottom: 2px solid #dee2e6;
        margin-bottom: 25px;
    }

    .tab-button {
        padding: 12px 25px;
        cursor: pointer;
        background: transparent;
        border: none;
        font-size: 1.1em;
        font-weight: 500;
        color: #6c757d;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }

    .tab-button.active {
        font-weight: 700;
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
    }

    .tab-content {
        display: none;
        animation: fadeIn 0.5s;
    }

    .tab-content.active {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Estilos do Formulário e da Tabela */
    .form-container,
    .history-container {
        background-color: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
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
    .form-group select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 1em;
    }

    .form-check {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
    }

    .form-check input[type="checkbox"] {
        display: none;
    }

    .toggle-switch {
        position: relative;
        width: 50px;
        height: 28px;
        background-color: #ccc;
        border-radius: 14px;
        transition: background-color 0.3s ease;
    }

    .toggle-switch::before {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        background-color: white;
        border-radius: 50%;
        top: 4px;
        left: 4px;
        transition: transform 0.3s ease;
    }

    .form-check input:checked+.toggle-switch {
        background-color: var(--secondary-color, #28a745);
    }

    .form-check input:checked+.toggle-switch::before {
        transform: translateX(22px);
    }

    .form-actions {
        display: flex;
        margin-top: 2rem;
        border-top: 1px solid #eee;
        padding-top: 1.5rem;
    }

    .btn-success {
        background-color: var(--secondary-color, #28a745);
        color: white;
        padding: 12px 25px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
    }

    .table-container-history {
        max-height: 450px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    .styled-table {
        width: 100%;
        border-collapse: collapse;
    }

    .styled-table thead th {
        background-color: #f8f9fa;
        text-align: left;
        padding: 12px;
        position: sticky;
        top: 0;
    }

    .styled-table tbody td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
    }

    .tag-falta,
    .tag-vale {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.8em;
        font-weight: 600;
        color: white;
    }

    .tag-falta {
        background-color: #ffc107;
        color: #333;
    }

    .tag-vale {
        background-color: #6f42c1;
    }

    .status-descontado {
        color: #28a745;
        font-weight: bold;
    }

    .btn-delete-occurrence {
        background: none;
        border: none;
        color: #dc3545;
        cursor: pointer;
        font-size: 1.1em;
    }
</style>

<div class="page-container">
    <div class="form-header">
        <h1><i class="fas fa-user-edit"></i> Editar: <?= htmlspecialchars($funcionario['nome']) ?></h1>
        <a href="funcionarios.php" class="btn-secondary">Voltar</a>
    </div>

    <?php if (!empty($mensagem_erro)): ?><div class="alert alert-danger"><?= $mensagem_erro ?></div><?php endif; ?>
    <?php if (!empty($mensagem_feedback)): ?><div class="alert alert-success"><?= $mensagem_feedback ?></div><?php endif; ?>

    <div class="tabs-nav">
        <button type="button" class="tab-button active" data-tab="dadosCadastrais">Dados Cadastrais</button>
        <button type="button" class="tab-button" data-tab="historicoOcorrencias">Histórico de Ocorrências</button>
    </div>

    <div id="dadosCadastrais" class="tab-content active">
        <div class="form-container">
            <form method="POST" action="editar_funcionario.php?id=<?= $funcionario_id ?>">
                <input type="hidden" name="form_principal" value="1">
                <div class="form-group"><label for="nome">Nome Completo</label><input type="text" id="nome" name="nome" required value="<?= htmlspecialchars($funcionario['nome'] ?? '') ?>"></div>
                <div class="form-group"><label for="cargo">Cargo</label><input type="text" id="cargo" name="cargo" value="<?= htmlspecialchars($funcionario['cargo'] ?? '') ?>"></div>
                <div class="form-group"><label for="valor_diaria">Valor da Diária (R$)</label><input type="text" id="valor_diaria" name="valor_diaria" required value="<?= htmlspecialchars(number_format($funcionario['valor_diaria'] ?? 0, 2, ',', '.')) ?>"></div>
                <div class="form-group"><label for="periodo_pagamento">Período de Pagamento</label><select id="periodo_pagamento" name="periodo_pagamento">
                        <option value="semanal" <?= (($funcionario['periodo_pagamento'] ?? '') == 'semanal') ? 'selected' : '' ?>>Semanal</option>
                        <option value="quinzenal" <?= (($funcionario['periodo_pagamento'] ?? '') == 'quinzenal') ? 'selected' : '' ?>>Quinzenal</option>
                        <option value="mensal" <?= (($funcionario['periodo_pagamento'] ?? '') == 'mensal') ? 'selected' : '' ?>>Mensal</option>
                    </select></div>
                <div class="form-group"><label for="data_admissao">Data de Admissão</label><input type="date" id="data_admissao" name="data_admissao" required value="<?= htmlspecialchars($funcionario['data_admissao'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-check" for="ativo"><input type="checkbox" id="ativo" name="ativo" value="1" <?= (($funcionario['ativo'] ?? 1) == 1) ? 'checked' : '' ?>>
                        <div class="toggle-switch"></div><span>Funcionário Ativo</span>
                    </label></div>
                <div class="form-actions"><button type="submit" class="btn btn-success">Salvar Alterações</button></div>
            </form>
        </div>
    </div>

    <div id="historicoOcorrencias" class="tab-content">
        <div class="history-container">
            <h2><i class="fas fa-history"></i> Histórico de Faltas e Vales</h2>
            <div class="table-container-history">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Valor/Impacto</th>
                            <th>Detalhes</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico_ocorrencias as $ocorrencia): ?>
                            <tr id="ocorrencia-<?= $ocorrencia['tipo_evento'] ?>-<?= $ocorrencia['id'] ?>">
                                <td><?= date('d/m/Y', strtotime($ocorrencia['data_evento'])) ?></td>
                                <td><?php if ($ocorrencia['tipo_evento'] === 'falta'): ?><span class="tag-falta"><?= str_replace('_', ' ', $ocorrencia['detalhes']) ?></span><?php else: ?><span class="tag-vale">Vale</span><?php endif; ?></td>
                                <td><?= $ocorrencia['valor_impacto'] ?></td>
                                <td><?= htmlspecialchars($ocorrencia['tipo_evento'] === 'vale' ? $ocorrencia['detalhes'] : $ocorrencia['observacao']) ?></td>
                                <td><?php if ($ocorrencia['tipo_evento'] === 'vale'): ?><?= !empty($ocorrencia['id_pagamento_descontado']) ? '<span class="status-descontado">Descontado</span>' : 'Pendente' ?><?php endif; ?></td>
                                <td><?php if (!($ocorrencia['tipo_evento'] === 'vale' && !empty($ocorrencia['id_pagamento_descontado']))): ?><button type="button" class="btn-delete-occurrence" data-id="<?= $ocorrencia['id'] ?>" data-tipo="<?= $ocorrencia['tipo_evento'] ?>" title="Excluir"><i class="fas fa-trash-alt"></i></button><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($historico_ocorrencias)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">Nenhuma ocorrência registrada.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- LÓGICA DAS ABAS ---
        const tabs = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');

        // Função para abrir uma aba específica
        const openTab = (tabId) => {
            tabContents.forEach(c => c.classList.remove('active'));
            tabs.forEach(t => t.classList.remove('active'));
            document.getElementById(tabId)?.classList.add('active');
            document.querySelector(`[data-tab='${tabId}']`)?.classList.add('active');
            sessionStorage.setItem('activeEmployeeTab', tabId); // Salva a aba ativa
        };

        // Abre a última aba que estava ativa ou a primeira por padrão
        const activeTab = sessionStorage.getItem('activeEmployeeTab') || 'dadosCadastrais';
        openTab(activeTab);

        tabs.forEach(tab => {
            tab.addEventListener('click', () => openTab(tab.dataset.tab));
        });

        // --- LÓGICA PARA EXCLUIR OCORRÊNCIAS ---
        document.querySelector('.table-container-history')?.addEventListener('click', async function(e) {
            const targetButton = e.target.closest('.btn-delete-occurrence');
            if (!targetButton) return;

            const id = targetButton.dataset.id;
            const tipo = targetButton.dataset.tipo;

            if (confirm(`Tem certeza que deseja EXCLUIR este registro de ${tipo}?`)) {
                try {
                    const response = await fetch('remover_ocorrencia.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id,
                            tipo
                        })
                    });
                    const result = await response.json();

                    if (result.sucesso) {
                        document.getElementById(`ocorrencia-${tipo}-${id}`).remove();
                    }
                    alert(result.mensagem);
                } catch (error) {
                    alert('Erro de conexão.');
                }
            }
        });
    });
</script>

<?php
$page_content = ob_get_clean();
include '../template_admin.php';
?>