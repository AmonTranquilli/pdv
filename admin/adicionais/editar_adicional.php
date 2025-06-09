<?php
session_start();
require_once '../../includes/conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit();
}

$mensagem_erro = '';
$adicional_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$adicional = null;

if (!$adicional_id) {
    $_SESSION['feedback_mensagem'] = "ID de adicional inválido.";
    $_SESSION['feedback_sucesso'] = false;
    header("Location: adicionais.php");
    exit();
}

// Lógica para carregar os dados atuais do adicional
$stmt_load = $conn->prepare("SELECT * FROM adicionais WHERE id = ?");
$stmt_load->bind_param("i", $adicional_id);
$stmt_load->execute();
$result = $stmt_load->get_result();
if ($result->num_rows === 1) {
    $adicional = $result->fetch_assoc();
} else {
    $_SESSION['feedback_mensagem'] = "Adicional não encontrado.";
    $_SESSION['feedback_sucesso'] = false;
    header("Location: adicionais.php");
    exit();
}
$stmt_load->close();

// Lógica para ATUALIZAR o adicional
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome']);
    $preco_raw = str_replace(',', '.', $_POST['preco'] ?? '0');
    $preco = filter_var($preco_raw, FILTER_VALIDATE_FLOAT);
    $descricao = trim($_POST['descricao']);
    $controla_estoque = isset($_POST['controla_estoque']) ? 1 : 0;
    $estoque_post = filter_var($_POST['estoque'], FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $caminho_imagem_atual = $_POST['imagem_atual'];

    $estoque = $controla_estoque ? $estoque_post : 0;
    $caminho_imagem_db = $caminho_imagem_atual;

    if (empty($nome) || $preco === false || $preco < 0) {
        $mensagem_erro = "Por favor, preencha o Nome e um Preço válido.";
    } else {
        // Lógica para upload de NOVA imagem
        if (isset($_FILES['nova_imagem']) && $_FILES['nova_imagem']['error'] == UPLOAD_ERR_OK) {
            $diretorio_uploads = '../../public/uploads/adicionais/';
            $nome_arquivo = uniqid() . '_' . basename($_FILES['nova_imagem']['name']);
            $caminho_completo_nova_imagem = $diretorio_uploads . $nome_arquivo;
            $caminho_imagem_db_nova = '/pdv/public/uploads/adicionais/' . $nome_arquivo;

            if (move_uploaded_file($_FILES['nova_imagem']['tmp_name'], $caminho_completo_nova_imagem)) {
                // Se o upload deu certo, apaga a imagem antiga se ela existir
                if (!empty($caminho_imagem_atual) && file_exists(str_replace('/pdv/', '../../', $caminho_imagem_atual))) {
                    unlink(str_replace('/pdv/', '../../', $caminho_imagem_atual));
                }
                $caminho_imagem_db = $caminho_imagem_db_nova; // Usa o caminho da nova imagem
            } else {
                $mensagem_erro = "Erro ao fazer upload da nova imagem.";
            }
        }

        if (empty($mensagem_erro)) {
            $stmt_update = $conn->prepare("UPDATE adicionais SET nome = ?, preco = ?, controla_estoque = ?, estoque = ?, descricao = ?, imagem = ?, ativo = ? WHERE id = ?");
            $stmt_update->bind_param("sdiissii", $nome, $preco, $controla_estoque, $estoque, $descricao, $caminho_imagem_db, $ativo, $adicional_id);

            if ($stmt_update->execute()) {
                $_SESSION['feedback_mensagem'] = "Adicional atualizado com sucesso!";
                $_SESSION['feedback_sucesso'] = true;
                header("Location: adicionais.php");
                exit();
            } else {
                $mensagem_erro = "Erro ao atualizar o adicional: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
}

$conn->close();
$page_title = 'Editar Adicional';
ob_start();
?>

<div class="header-controls">
    <h1><i class="fas fa-edit"></i> Editar Adicional</h1>
    <a href="adicionais.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar para a Lista</a>
</div>

<div class="page-content">
    <?php if ($mensagem_erro): ?>
        <p class="message-error"><?= htmlspecialchars($mensagem_erro); ?></p>
    <?php endif; ?>

    <form action="editar_adicional.php?id=<?= $adicional_id; ?>" method="POST" class="form-container" enctype="multipart/form-data">
        <input type="hidden" name="imagem_atual" value="<?= htmlspecialchars($adicional['imagem']); ?>">
        
        <div class="form-row">
            <div class="form-group">
                <label for="nome">Nome do Adicional:</label>
                <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($adicional['nome']); ?>" required>
            </div>
            <div class="form-group">
                <label for="preco">Preço (R$):</label>
                <input type="text" id="preco" name="preco" value="<?= htmlspecialchars(number_format($adicional['preco'], 2, ',', '.')); ?>" placeholder="Ex: 2,50" required>
            </div>
        </div>

        <div class="form-group">
            <label for="descricao">Descrição (Opcional):</label>
            <textarea id="descricao" name="descricao" rows="2"><?= htmlspecialchars($adicional['descricao']); ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Imagem Atual:</label>
            <?php if (!empty($adicional['imagem'])): ?>
                <img src="<?= htmlspecialchars($adicional['imagem']); ?>" alt="Imagem atual" style="max-width: 100px; display: block; margin-top: 5px; border-radius: 5px;">
            <?php else: ?>
                <p>Nenhuma imagem cadastrada.</p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="nova_imagem">Alterar Imagem (opcional):</label>
            <input type="file" id="nova_imagem" name="nova_imagem" accept="image/*">
        </div>

        <div class="form-row">
            <div class="form-group form-check">
                <input type="checkbox" id="controla_estoque" name="controla_estoque" value="1" <?= $adicional['controla_estoque'] ? 'checked' : ''; ?>>
                <label for="controla_estoque">Controlar Estoque</label>
            </div>
            <div class="form-group" id="estoque_group" style="<?= $adicional['controla_estoque'] ? 'display:block;' : 'display:none;'; ?>">
                <label for="estoque">Quantidade em Estoque:</label>
                <input type="number" id="estoque" name="estoque" min="0" value="<?= htmlspecialchars($adicional['estoque']); ?>">
            </div>
        </div>
        
        <div class="form-group form-check">
            <input type="checkbox" id="ativo" name="ativo" value="1" <?= $adicional['ativo'] ? 'checked' : ''; ?>>
            <label for="ativo">Adicional Ativo</label>
        </div>

        <div class="action-buttons" style="justify-content: center;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Alterações</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const controlaEstoqueCheckbox = document.getElementById('controla_estoque');
    const estoqueGroup = document.getElementById('estoque_group');

    controlaEstoqueCheckbox.addEventListener('change', function() {
        estoqueGroup.style.display = this.checked ? 'block' : 'none';
    });
});
</script>

<?php
$page_content = ob_get_clean();
include '../template_admin.php';
?>