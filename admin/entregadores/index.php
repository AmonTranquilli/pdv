<?php
session_start();
require_once '../../includes/conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit();
}

$mensagem_sucesso = '';
$mensagem_erro = '';

if (isset($_SESSION['mensagem_sucesso'])) {
    $mensagem_sucesso = $_SESSION['mensagem_sucesso'];
    unset($_SESSION['mensagem_sucesso']);
}
if (isset($_SESSION['mensagem_erro'])) {
    $mensagem_erro = $_SESSION['mensagem_erro'];
    unset($_SESSION['mensagem_erro']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entregador'])) {
    $nome = trim($_POST['nome']);
    $codigo = trim($_POST['codigo_entregador']);

    if (empty($nome) || empty($codigo)) {
        $mensagem_erro = "O nome e o código do entregador são obrigatórios.";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM entregadores WHERE codigo_entregador = ?");
        $stmt_check->bind_param("s", $codigo);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $mensagem_erro = "Este código de entregador já está em uso. Por favor, escolha outro.";
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO entregadores (nome, codigo_entregador) VALUES (?, ?)");
            $stmt_insert->bind_param("ss", $nome, $codigo);
            if ($stmt_insert->execute()) {
                $mensagem_sucesso = "Entregador adicionado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao adicionar entregador: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}

$entregadores = [];
$result = $conn->query("SELECT id, nome, codigo_entregador, ativo, data_cadastro FROM entregadores ORDER BY nome ASC");
if ($result) {
    $entregadores = $result->fetch_all(MYSQLI_ASSOC);
}

$page_title = 'Gerir Entregadores';
ob_start();
?>

<div class="container">
    <h1><i class="fas fa-motorcycle"></i> Gerir Entregadores</h1>

    <?php if ($mensagem_sucesso): ?>
        <div class="mensagem sucesso"><?php echo htmlspecialchars($mensagem_sucesso); ?></div>
    <?php endif; ?>
    <?php if ($mensagem_erro): ?>
        <div class="mensagem erro"><?php echo htmlspecialchars($mensagem_erro); ?></div>
    <?php endif; ?>

    <div class="form-container card">
        <h2>Adicionar Novo Entregador</h2>
        <form action="index.php" method="POST">
            <div class="form-group">
                <label for="nome">Nome do Entregador:</label>
                <input type="text" id="nome" name="nome" required>
            </div>
            <div class="form-group">
                <label for="codigo_entregador">Código Único:</label>
                <input type="text" id="codigo_entregador" name="codigo_entregador" required>
                <small>Crie um código simples para o entregador (ex: "JOAO1", "MOTO02").</small>
            </div>
            <div class="form-group">
                <button type="submit" name="add_entregador" class="btn btn-primary">Adicionar Entregador</button>
            </div>
        </form>
    </div>

    <h2>Entregadores Registados</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Código</th>
                <th>Status</th>
                <th>Data de Registo</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($entregadores)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;">Nenhum entregador registado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($entregadores as $entregador): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entregador['id']); ?></td>
                        <td><?php echo htmlspecialchars($entregador['nome']); ?></td>
                        <td><?php echo htmlspecialchars($entregador['codigo_entregador']); ?></td>
                        <td>
                            <span class="status-<?php echo $entregador['ativo'] ? 'ativo' : 'inativo'; ?>">
                                <?php echo $entregador['ativo'] ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($entregador['data_cadastro'])); ?></td>
                        <td>
                            <a href="editar_entregador.php?id=<?php echo $entregador['id']; ?>" class="btn btn-secondary btn-sm" title="Editar Entregador">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <a href="excluir_entregador.php?id=<?php echo $entregador['id']; ?>" class="btn btn-danger btn-sm" title="Excluir Entregador" onclick="return confirm('Tem certeza que deseja excluir este entregador? Esta ação não pode ser desfeita.');">
                                <i class="fas fa-trash-alt"></i> Excluir
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$page_content = ob_get_clean();
include '../template_admin.php';
$conn->close();
?>
