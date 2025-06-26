<?php
// Arquivo: /admin/financeiro/editar_funcionario.php
session_start();

$page_title = 'Editar Funcionário';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: /pdv/admin/login.php");
    exit;
}

require_once '../../includes/conexao.php';

$mensagem_erro = '';
$mensagem_sucesso = '';
$funcionario = null;

// 1. Pega o ID do funcionário da URL
$funcionario_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$funcionario_id) {
    header("Location: funcionarios.php");
    exit;
}

// 2. Verifica se o formulário foi enviado (método POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e limpa os dados do formulário
    $nome = trim($_POST['nome']);
    $cargo = trim($_POST['cargo']);
    $valor_diaria = str_replace(',', '.', $_POST['valor_diaria']);
    $periodo_pagamento = $_POST['periodo_pagamento'];
    $data_admissao = $_POST['data_admissao'];
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    // Validação
    if (empty($nome) || empty($valor_diaria) || empty($data_admissao)) {
        $mensagem_erro = "Por favor, preencha todos os campos obrigatórios.";
        // Recarrega os dados do POST para o formulário
        $funcionario = $_POST;
        $funcionario['id'] = $funcionario_id;
    } else {
        try {
            // Prepara o comando UPDATE
            $stmt = $conn->prepare(
                "UPDATE funcionarios SET nome = ?, cargo = ?, valor_diaria = ?, periodo_pagamento = ?, data_admissao = ?, ativo = ? WHERE id = ?"
            );
            $stmt->bind_param("ssddssi", $nome, $cargo, $valor_diaria, $periodo_pagamento, $data_admissao, $ativo, $funcionario_id);

            if ($stmt->execute()) {
                $_SESSION['mensagem_sucesso'] = "Dados do funcionário atualizados com sucesso!";
                header("Location: funcionarios.php");
                exit;
            } else {
                $mensagem_erro = "Erro ao atualizar funcionário: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $mensagem_erro = "Erro no banco de dados: " . $e->getMessage();
        }
    }
} else {
    // 3. Se não for POST, busca os dados do funcionário no banco para preencher o formulário
    $stmt_fetch = $conn->prepare("SELECT * FROM funcionarios WHERE id = ?");
    $stmt_fetch->bind_param("i", $funcionario_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows === 1) {
        $funcionario = $result->fetch_assoc();
    } else {
        // Se não encontrou o funcionário, redireciona para a lista
        header("Location: funcionarios.php");
        exit;
    }
    $stmt_fetch->close();
}

$conn->close();

ob_start();
?>

<style>
    /* ====================================================== */
    /* === ESTILOS MELHORADOS PARA O CHECKBOX (TOGGLE SWITCH) === */
    /* ====================================================== */

    /* Container para alinhar o checkbox customizado e o label */
    .form-check {
        display: flex;
        align-items: center;
        gap: 12px;
        /* Espaço entre o interruptor e o texto */
        cursor: pointer;
    }

    /* Esconde o checkbox original do navegador */
    .form-check input[type="checkbox"] {
        display: none;
    }

    /* O corpo do nosso novo interruptor */
    .toggle-switch {
        position: relative;
        width: 50px;
        height: 28px;
        background-color: #ccc;
        border-radius: 14px;
        /* Totalmente arredondado */
        transition: background-color 0.3s ease;
    }

    /* A "bolinha" do interruptor */
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

    /* Estilo QUANDO o checkbox original está MARCADO */
    .form-check input:checked+.toggle-switch {
        background-color: var(--secondary-color, #28a745);
        /* Verde de sucesso */
    }

    .form-check input:checked+.toggle-switch::before {
        transform: translateX(22px);
        /* Move a bolinha para a direita */
    }
</style>

<div class="form-header">
    <h1><i class="fas fa-user-edit"></i> Editar Funcionário</h1>
    <a href="funcionarios.php" class="btn btn-secondary" style="background-color: #6c757d; padding: 10px 15px;">Voltar para a Lista</a>
</div>

<?php if (!empty($mensagem_erro)): ?>
    <div class="alert alert-danger"><?= $mensagem_erro ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST" action="editar_funcionario.php?id=<?= $funcionario_id ?>">
        <div class="form-group">
            <label for="nome">Nome Completo</label>
            <input type="text" id="nome" name="nome" required value="<?= htmlspecialchars($funcionario['nome'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="cargo">Cargo</label>
            <input type="text" id="cargo" name="cargo" value="<?= htmlspecialchars($funcionario['cargo'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="valor_diaria">Valor da Diária (R$)</label>
            <input type="text" id="valor_diaria" name="valor_diaria" required value="<?= htmlspecialchars(number_format($funcionario['valor_diaria'] ?? 0, 2, ',', '.')) ?>">
        </div>
        <div class="form-group">
            <label for="periodo_pagamento">Período de Pagamento</label>
            <select id="periodo_pagamento" name="periodo_pagamento">
                <option value="semanal" <?= (($funcionario['periodo_pagamento'] ?? '') == 'semanal') ? 'selected' : '' ?>>Semanal</option>
                <option value="quinzenal" <?= (($funcionario['periodo_pagamento'] ?? '') == 'quinzenal') ? 'selected' : '' ?>>Quinzenal</option>
                <option value="mensal" <?= (($funcionario['periodo_pagamento'] ?? '') == 'mensal') ? 'selected' : '' ?>>Mensal</option>
            </select>
        </div>
        <div class="form-group">
            <label for="data_admissao">Data de Admissão</label>
            <input type="date" id="data_admissao" name="data_admissao" required value="<?= htmlspecialchars($funcionario['data_admissao'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-check" for="ativo"> <input type="checkbox" id="ativo" name="ativo" value="1" <?= (($funcionario['ativo'] ?? 1) == 1) ? 'checked' : '' ?>>
                <div class="toggle-switch"></div> <span>Funcionário Ativo</span>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
        </div>
    </form>
</div>

<?php
$page_content = ob_get_clean();
include '../template_admin.php';
?>