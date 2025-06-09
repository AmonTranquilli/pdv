<?php
session_start();
require_once '../../includes/conexao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Lógica para buscar todos os adicionais do banco, incluindo a nova coluna de imagem
$adicionais = [];
$sql = "SELECT id, nome, preco, imagem, descricao, ativo, controla_estoque, estoque FROM adicionais ORDER BY nome ASC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $adicionais[] = $row;
    }
}
$conn->close();

$page_title = 'Gerenciar Adicionais';

ob_start();
?>

<div class="header-controls">
    <h1><i class="fas fa-plus-circle"></i> Gerenciar Adicionais</h1>
    <a href="adicionar_adicional.php" class="btn btn-success"><i class="fas fa-plus"></i> Adicionar Novo Adicional</a>
</div>

<div class="message-area">
    <?php
    // Exibe mensagens de feedback da sessão, se houver
    if (isset($_SESSION['feedback_mensagem'])) {
        $classe_feedback = ($_SESSION['feedback_sucesso'] ?? false) ? 'message-success' : 'message-error';
        echo '<p class="' . $classe_feedback . '">' . htmlspecialchars($_SESSION['feedback_mensagem']) . '</p>';
        unset($_SESSION['feedback_mensagem'], $_SESSION['feedback_sucesso']);
    }
    ?>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Imagem</th>
                <th>Nome</th>
                <th>Preço</th>
                <th>Estoque</th>
                <th>Status</th>
                <th class="text-center">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($adicionais)): ?>
                <tr>
                    <td colspan="7" class="text-center">Nenhum adicional cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($adicionais as $adicional): ?>
                    <tr>
                        <td><?= htmlspecialchars($adicional['id']) ?></td>
                        <td>
                            <img src="<?= htmlspecialchars($adicional['imagem'] ?? '/pdv/public/img/default-product.png'); ?>" alt="<?= htmlspecialchars($adicional['nome']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                        </td>
                        <td><?= htmlspecialchars($adicional['nome']) ?></td>
                        <td>R$ <?= number_format($adicional['preco'], 2, ',', '.') ?></td>
                        <td>
                            <?php
                            if ($adicional['controla_estoque']) {
                                echo htmlspecialchars($adicional['estoque']);
                            } else {
                                echo '<span style="color: #888;" title="Estoque não controlado">—</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $status_real_ativo = $adicional['ativo'];
                            if ($adicional['controla_estoque'] && $adicional['estoque'] <= 0) {
                                $status_real_ativo = false;
                            }
                            
                            $status_texto = $status_real_ativo ? 'Ativo' : 'Inativo';
                            $status_classe = $status_real_ativo ? 'status-concluido' : 'status-cancelado';
                            ?>
                            <span class="status-badge <?= $status_classe ?>">
                                <?= $status_texto ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="editar_adicional.php?id=<?= $adicional['id'] ?>" class="btn btn-warning btn-sm" title="Editar"><i class="fas fa-edit"></i></a>
                            <a href="excluir_adicional.php?id=<?= $adicional['id'] ?>" class="btn btn-danger btn-sm" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este adicional?');"><i class="fas fa-trash-alt"></i></a>
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
?>