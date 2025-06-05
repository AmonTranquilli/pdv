<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho ajustado para a conexão

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php"); // Redireciona para o login se não estiver logado
    exit();
}

$mensagem = '';
$sucesso = false;
$categoria_id = null;
$nome_categoria_atual = ''; // Para pré-preencher o formulário

// 2. Verifica se um ID de categoria foi passado via GET (para carregar os dados)
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $categoria_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($categoria_id === false || $categoria_id <= 0) {
        $mensagem = "ID de categoria inválido.";
        // Redireciona para a lista se o ID for inválido
        header("Location: categorias.php");
        exit();
    } else {
        // Carrega os dados da categoria do banco de dados
        $stmt = $conn->prepare("SELECT id, nome FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $categoria_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $categoria = $result->fetch_assoc();
            $nome_categoria_atual = htmlspecialchars($categoria['nome']);
        } else {
            $mensagem = "Categoria não encontrada.";
            // Redireciona se a categoria não for encontrada
            header("Location: categorias.php");
            exit();
        }
        $stmt->close();
    }
} else if ($_SERVER['REQUEST_METHOD'] != 'POST') { // Apenas redireciona se não é POST e não tem ID
    $mensagem = "Nenhum ID de categoria fornecido para edição.";
    // Redireciona para a lista se nenhum ID for fornecido
    header("Location: categorias.php");
    exit();
}


// 3. Processa o formulário quando enviado via POST (para atualizar os dados)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_categoria']) && isset($_POST['nome_categoria'])) {
    $id_para_atualizar = filter_var($_POST['id_categoria'], FILTER_VALIDATE_INT);
    $novo_nome_categoria = trim($_POST['nome_categoria']); // Remove espaços em branco

    if ($id_para_atualizar === false || $id_para_atualizar <= 0) {
        $mensagem = "ID de categoria inválido para atualização.";
    } elseif (empty($novo_nome_categoria)) {
        $mensagem = "O nome da categoria não pode ser vazio.";
    } else {
        // Prepara e executa a atualização da categoria
        $stmt = $conn->prepare("UPDATE categorias SET nome = ? WHERE id = ?");
        $stmt->bind_param("si", $novo_nome_categoria, $id_para_atualizar); // 's' para string, 'i' para inteiro

        if ($stmt->execute()) {
            $mensagem = "Categoria atualizada para '" . htmlspecialchars($novo_nome_categoria) . "' com sucesso!";
            $sucesso = true;
            // Atualiza o nome atual no formulário após a edição bem-sucedida
            $nome_categoria_atual = $novo_nome_categoria;
        } else {
            $mensagem = "Erro ao atualizar categoria: " . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close(); // Fecha a conexão com o banco de dados

// 4. Define o título da página para o template
$page_title = 'Editar Categoria';

// --- INÍCIO DO BLOCO DE CONTEÚDO ESPECÍFICO ---
// Inicia o buffer de saída para capturar todo o HTML e PHP desta página
ob_start();
?>

<div class="container">
    <h1 style="text-align: center; margin-bottom: 25px;">Editar Categoria</h1>

    <?php if (!empty($mensagem)) : ?>
        <p class="message-<?php echo $sucesso ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($mensagem); ?></p>
    <?php endif; ?>

    <?php if ($categoria_id !== null && $categoria_id > 0 && !empty($nome_categoria_atual)) : ?>
        <div class="page-content"> <form action="editar.php?id=<?php echo htmlspecialchars($categoria_id); ?>" method="POST">
                <input type="hidden" name="id_categoria" value="<?php echo htmlspecialchars($categoria_id); ?>">

                <div class="form-group">
                    <label for="nome_categoria">Nome da Categoria:</label>
                    <input type="text" id="nome_categoria" name="nome_categoria" value="<?php echo $nome_categoria_atual; ?>" required>
                </div>

                <div class="action-buttons" style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Atualizar Categoria</button>
                    <a href="categorias.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Voltar para Categorias</a>
                </div>
            </form>
        </div>
    <?php else : ?>
        <p class="message-error">Não foi possível carregar a categoria para edição. <?php echo htmlspecialchars($mensagem); ?></p>
        <div class="action-buttons" style="text-align: center; margin-top: 30px;">
            <a href="categorias.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Voltar para Categorias</a>
        </div>
    <?php endif; ?>
</div>

<?php
// --- FIM DO BLOCO DE CONTEÚDO ESPECÍFICO ---
// Captura todo o conteúdo do buffer e armazena na variável $page_content
$page_content = ob_get_clean();

// Inclui o template principal do painel administrativo.
include '../template_admin.php'; // O template está um nível acima (em 'admin/')
?>