<?php
session_start();
require_once '../../includes/conexao.php';

// Proteção da página
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit();
}

$mensagem_erro = '';

// Mantém os valores preenchidos em caso de erro
$nome = $_POST['nome'] ?? '';
$preco = $_POST['preco'] ?? '';
$descricao = $_POST['descricao'] ?? '';
$controla_estoque = isset($_POST['controla_estoque']) ? 1 : 0;
$estoque = $_POST['estoque'] ?? 0;
// Por padrão, um novo adicional já começa ativo
$ativo = isset($_POST['ativo']) ? 1 : ($_SERVER['REQUEST_METHOD'] !== 'POST' ? 1 : 0);


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome']);
    $preco_raw = str_replace(',', '.', $_POST['preco'] ?? '0');
    $preco = filter_var($preco_raw, FILTER_VALIDATE_FLOAT);
    $descricao = trim($_POST['descricao']);
    $controla_estoque = isset($_POST['controla_estoque']) ? 1 : 0;
    $estoque_post = filter_var($_POST['estoque'], FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    $estoque = $controla_estoque ? $estoque_post : 0;
    $caminho_imagem_db = null; // Inicia como nulo

    // Validações
    if (empty($nome) || $preco === false || $preco < 0) {
        $mensagem_erro = "Por favor, preencha o Nome e um Preço válido.";
    } else {
        // Lógica de Upload de Imagem
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == UPLOAD_ERR_OK) {
            $diretorio_uploads = '../../public/uploads/adicionais/';
            if (!is_dir($diretorio_uploads)) {
                mkdir($diretorio_uploads, 0777, true);
            }
            $nome_arquivo = uniqid() . '_' . basename($_FILES['imagem']['name']);
            $caminho_completo_imagem = $diretorio_uploads . $nome_arquivo;
            
            // Caminho relativo para salvar no banco
            $caminho_imagem_db = '/pdv/public/uploads/adicionais/' . $nome_arquivo;

            if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $caminho_completo_imagem)) {
                $mensagem_erro = "Erro ao fazer upload da imagem.";
                $caminho_imagem_db = null; // Reseta o caminho se o upload falhar
            }
        }

        // Procede com a inserção apenas se não houver erro de upload
        if (empty($mensagem_erro)) {
            $stmt = $conn->prepare("INSERT INTO adicionais (nome, preco, controla_estoque, estoque, descricao, imagem, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sdiissi", $nome, $preco, $controla_estoque, $estoque, $descricao, $caminho_imagem_db, $ativo);

            if ($stmt->execute()) {
                $_SESSION['feedback_mensagem'] = "Adicional '" . htmlspecialchars($nome) . "' criado com sucesso!";
                $_SESSION['feedback_sucesso'] = true;
                header("Location: adicionais.php");
                exit();
            } else {
                $mensagem_erro = "Erro ao criar o adicional: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$conn->close();
$page_title = 'Adicionar Novo Adicional';
ob_start();
?>

<div class="header-controls">
    <h1><i class="fas fa-plus-circle"></i> Adicionar Adicional</h1>
    <a href="adicionais.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar para a Lista</a>
</div>

<div class="page-content">
    <?php if ($mensagem_erro): ?>
        <p class="message-error"><?= htmlspecialchars($mensagem_erro); ?></p>
    <?php endif; ?>

    <form action="adicionar_adicional.php" method="POST" class="form-container" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group">
                <label for="nome">Nome do Adicional:</label>
                <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($nome); ?>" required>
                <small>Ex: Extra Bacon, Borda de Catupiry...</small>
            </div>
            <div class="form-group">
                <label for="preco">Preço (R$):</label>
                <input type="text" id="preco" name="preco" value="<?= htmlspecialchars($preco); ?>" placeholder="Ex: 2,50" required>
            </div>
        </div>

        <div class="form-group">
            <label for="descricao">Descrição (Opcional):</label>
            <textarea id="descricao" name="descricao" rows="2"><?= htmlspecialchars($descricao); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="imagem">Imagem (Opcional):</label>
            <input type="file" id="imagem" name="imagem" accept="image/*">
        </div>

        <div class="form-row">
            <div class="form-group form-check">
                <input type="checkbox" id="controla_estoque" name="controla_estoque" value="1" <?php if ($controla_estoque) echo 'checked'; ?>>
                <label for="controla_estoque">Controlar Estoque</label>
            </div>
            <div class="form-group" id="estoque_group" style="<?= $controla_estoque ? 'display:block;' : 'display:none;'; ?>">
                <label for="estoque">Quantidade em Estoque:</label>
                <input type="number" id="estoque" name="estoque" min="0" value="<?= htmlspecialchars($estoque); ?>">
            </div>
        </div>
        
        <div class="form-group form-check">
            <input type="checkbox" id="ativo" name="ativo" value="1" <?php if ($ativo) echo 'checked'; ?>>
            <label for="ativo">Adicional Ativo</label>
        </div>

        <div class="action-buttons" style="justify-content: center;">
            <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Criar Adicional</button>
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