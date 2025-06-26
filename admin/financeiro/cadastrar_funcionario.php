<?php
// Arquivo: /admin/financeiro/cadastrar_funcionario.php
session_start();

$page_title = 'Cadastrar Novo Funcionário';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: /pdv/admin/login.php");
    exit;
}

require_once '../../includes/conexao.php';

$mensagem_erro = '';
$mensagem_sucesso = '';

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e limpa os dados do formulário
    $nome = trim($_POST['nome']);
    $cargo = trim($_POST['cargo']);
    $valor_diaria = str_replace(',', '.', $_POST['valor_diaria']); // Aceita vírgula e ponto
    $periodo_pagamento = $_POST['periodo_pagamento'];
    $data_admissao = $_POST['data_admissao'];

    // Validação simples
    if (empty($nome) || empty($valor_diaria) || empty($data_admissao)) {
        $mensagem_erro = "Por favor, preencha todos os campos obrigatórios (Nome, Valor da Diária, Data de Admissão).";
    } else {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO funcionarios (nome, cargo, valor_diaria, periodo_pagamento, data_admissao) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("ssdds", $nome, $cargo, $valor_diaria, $periodo_pagamento, $data_admissao);

            if ($stmt->execute()) {
                $_SESSION['mensagem_sucesso'] = "Funcionário cadastrado com sucesso!";
                header("Location: funcionarios.php"); // Redireciona para a lista de funcionários
                exit;
            } else {
                $mensagem_erro = "Erro ao cadastrar funcionário: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $mensagem_erro = "Erro no banco de dados: " . $e->getMessage();
        }
    }
}

$conn->close();

ob_start();
?>

<style>
    .form-container {
        background-color: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        max-width: 700px;
        margin: 0 auto;
    }

    .form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
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

    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 2rem;
        border-top: 1px solid #eee;
        padding-top: 1.5rem;
    }

    .btn {
        padding: 12px 25px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        cursor: pointer;
        border: none;
    }

    .btn-success {
        background-color: var(--secondary-color, #28a745);
        color: white;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
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
</style>

<div class="form-header">
    <h1><i class="fas fa-user-plus"></i> Cadastrar Novo Funcionário</h1>
    <a href="funcionarios.php" class="btn-secondary" style="background-color: #6c757d; padding: 10px 15px;">Voltar para a Lista</a>
</div>

<?php if (!empty($mensagem_erro)): ?>
    <div class="alert alert-danger"><?= $mensagem_erro ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST" action="cadastrar_funcionario.php">
        <div class="form-group">
            <label for="nome">Nome Completo</label>
            <input type="text" id="nome" name="nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="cargo">Cargo (Ex: Cozinheiro, Caixa)</label>
            <input type="text" id="cargo" name="cargo" value="<?= htmlspecialchars($_POST['cargo'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="valor_diaria">Valor da Diária (R$)</label>
            <input type="text" id="valor_diaria" name="valor_diaria" required placeholder="Ex: 80,00" value="<?= htmlspecialchars($_POST['valor_diaria'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="periodo_pagamento">Período de Pagamento</label>
            <select id="periodo_pagamento" name="periodo_pagamento">
                <option value="semanal" <?= (($_POST['periodo_pagamento'] ?? 'semanal') == 'semanal') ? 'selected' : '' ?>>Semanal</option>
                <option value="quinzenal" <?= (($_POST['periodo_pagamento'] ?? '') == 'quinzenal') ? 'selected' : '' ?>>Quinzenal</option>
                <option value="mensal" <?= (($_POST['periodo_pagamento'] ?? '') == 'mensal') ? 'selected' : '' ?>>Mensal</option>
            </select>
        </div>
        <div class="form-group">
            <label for="data_admissao">Data de Admissão</label>
            <input type="date" id="data_admissao" name="data_admissao" required value="<?= htmlspecialchars($_POST['data_admissao'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-success">Salvar Funcionário</button>
        </div>
    </form>
</div>

<?php
// Inclui o template principal do painel admin
$page_content = ob_get_clean();
include '../template_admin.php';
?>