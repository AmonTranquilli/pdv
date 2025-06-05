<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho ajustado para a conexão (subir 2 níveis)

// Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php"); // Caminho ajustado para o login (subir 1 nível)
    exit();
}

$mensagem = '';
$sucesso = false;

// Lógica para processar o envio do formulário
$nome_categoria = $_POST['nome_categoria'] ?? ''; // Mantém o valor preenchido em caso de erro

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome_categoria = trim($_POST['nome_categoria']);

    if (empty($nome_categoria)) {
        $mensagem = "O nome da categoria não pode ser vazio.";
    } else {
        $stmt = $conn->prepare("INSERT INTO categorias (nome) VALUES (?)");
        $stmt->bind_param("s", $nome_categoria);

        if ($stmt->execute()) {
            $mensagem = "Categoria '" . htmlspecialchars($nome_categoria) . "' adicionada com sucesso!";
            $sucesso = true;
            $nome_categoria = ''; // Limpa o campo após sucesso
        } else {
            $mensagem = "Erro ao adicionar categoria: " . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close(); // Fecha a conexão com o banco de dados

// Define o título da página que será usado no template
$page_title = 'Adicionar Categoria';

// --- INÍCIO DO BLOCO DE CONTEÚDO ESPECÍFICO ---
// Inicia o buffer de saída para capturar todo o HTML e PHP desta página
ob_start();
?>

<div class="container">
    <h1 style="text-align: center; margin-bottom: 25px;">Adicionar Nova Categoria</h1>

    <?php if (!empty($mensagem)) : ?>
        <p class="message-<?php echo $sucesso ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($mensagem); ?></p>
    <?php endif; ?>

    <div class="page-content"> <form action="adicionar.php" method="POST">
            <div class="form-group">
                <label for="nome_categoria">Nome da Categoria:</label>
                <input type="text" id="nome_categoria" name="nome_categoria" required value="<?php echo htmlspecialchars($nome_categoria); ?>">
            </div>

            <div class="action-buttons" style="text-align: center; margin-top: 30px;">
                <button type="submit" class="btn btn-success"><i class="fas fa-plus-circle"></i> Adicionar Categoria</button>
                <a href="categorias.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Voltar para Categorias</a>
            </div>
        </form>
    </div>
</div>

<?php
// --- FIM DO BLOCO DE CONTEÚDO ESPECÍFICO ---
// Captura todo o conteúdo do buffer e armazena na variável $page_content
$page_content = ob_get_clean();

// Inclui o template principal do painel administrativo.
// Ele usará $page_title e $page_content definidos acima.
include '../template_admin.php'; // O template está um nível acima (em 'admin/')
?>