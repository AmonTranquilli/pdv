<?php
session_start();
require_once '../../includes/conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit();
}

$mensagem_sucesso = '';
$mensagem_erro = '';
$entregador = null;
$entregador_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$entregador_id) {
    $_SESSION['mensagem_erro'] = "ID do entregador inválido.";
    header("Location: index.php");
    exit();
}

// Lógica para atualizar o entregador
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $codigo = trim($_POST['codigo_entregador']);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $id_para_atualizar = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (empty($nome) || empty($codigo) || !$id_para_atualizar) {
        $mensagem_erro = "Todos os campos são obrigatórios.";
    } else {
        // Verifica se o novo código já existe em OUTRO entregador
        $stmt_check = $conn->prepare("SELECT id FROM entregadores WHERE codigo_entregador = ? AND id != ?");
        $stmt_check->bind_param("si", $codigo, $id_para_atualizar);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $mensagem_erro = "Este código de entregador já está em uso por outro utilizador.";
        } else {
            // Atualiza os dados
            $stmt_update = $conn->prepare("UPDATE entregadores SET nome = ?, codigo_entregador = ?, ativo = ? WHERE id = ?");
            $stmt_update->bind_param("ssii", $nome, $codigo, $ativo, $id_para_atualizar);
            if ($stmt_update->execute()) {
                $_SESSION['mensagem_sucesso'] = "Dados do entregador atualizados com sucesso!";
                header("Location: index.php");
                exit();
            } else {
                $mensagem_erro = "Erro ao atualizar os dados: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
        $stmt_check->close();
    }
}

// Busca os dados atuais do entregador para preencher o formulário
$stmt_select = $conn->prepare("SELECT nome, codigo_entregador, ativo FROM entregadores WHERE id = ?");
$stmt_select->bind_param("i", $entregador_id);
$stmt_select->execute();
$result = $stmt_select->get_result();
if ($result->num_rows === 1) {
    $entregador = $result->fetch_assoc();
} else {
    $_SESSION['mensagem_erro'] = "Entregador não encontrado.";
    header("Location: index.php");
    exit();
}
$stmt_select->close();

$page_title = 'Editar Entregador';
ob_start();
?>

<div class="container">
    <h1><i class="fas fa-edit"></i> Editar Entregador</h1>

    <?php if ($mensagem_erro): ?>
        <div class="mensagem erro"><?php echo $mensagem_erro; ?></div>
    <?php endif; ?>

    <div class="form-container card">
        <form action="editar_entregador.php?id=<?php echo $entregador_id; ?>" method="POST">
            <input type="hidden" name="id" value="<?php echo $entregador_id; ?>">
            <div class="form-group">
                <label for="nome">Nome do Entregador:</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($entregador['nome']); ?>" required>
            </div>
            <div class="form-group">
                <label for="codigo_entregador">Código Único:</label>
                <input type="text" id="codigo_entregador" name="codigo_entregador" value="<?php echo htmlspecialchars($entregador['codigo_entregador']); ?>" required>
            </div>
            <div class="form-group form-check">
                <input type="checkbox" id="ativo" name="ativo" class="form-check-input" <?php echo $entregador['ativo'] ? 'checked' : ''; ?>>
                <label for="ativo" class="form-check-label">Ativo</label>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include '../template_admin.php';
$conn->close();
?>
