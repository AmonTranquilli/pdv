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

// 2. Verifica se um ID de categoria foi passado via GET para exclusão
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $categoria_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($categoria_id === false || $categoria_id <= 0) {
        $mensagem = "ID de categoria inválido para exclusão.";
        $sucesso = false;
    } else {
        // --- LÓGICA SIMPLIFICADA: APENAS EXCLUI A CATEGORIA ---
        $stmt_categoria = $conn->prepare("DELETE FROM categorias WHERE id = ?");
        if ($stmt_categoria === false) {
            $mensagem = "Erro na preparação do DELETE de categoria: " . $conn->error;
            $sucesso = false;
        } else {
            $stmt_categoria->bind_param("i", $categoria_id);

            if ($stmt_categoria->execute()) {
                $mensagem = "Categoria excluída com sucesso!";
                $sucesso = true;
            } else {
                $mensagem = "Erro ao excluir categoria: " . $stmt_categoria->error;
                $sucesso = false;
            }
            $stmt_categoria->close();
        }
        // --- FIM DA LÓGICA SIMPLIFICADA ---
    }
} else {
    $mensagem = "Nenhum ID de categoria fornecido para exclusão.";
    $sucesso = false;
}

$conn->close(); // Fecha a conexão com o banco de dados

// 3. Define o título da página (embora esta página redirecione rapidamente)
$page_title = 'Excluir Categoria';

// --- INÍCIO DO BLOCO DE CONTEÚDO ESPECÍFICO (apenas para exibir a mensagem antes do redirecionamento) ---
ob_start();
?>

<h1>Excluir Categoria</h1>
<?php if (!empty($mensagem)) : ?>
    <p class="mensagem <?php echo $sucesso ? 'sucesso' : 'erro'; ?>"><?php echo $mensagem; ?></p>
<?php endif; ?>
<p>Você será redirecionado em breve...</p>

<?php
// --- FIM DO BLOCO DE CONTEÚDO ESPECÍFICO ---
$page_content = ob_get_clean();

// 4. Redireciona para a lista de categorias
// Redireciona após um pequeno delay para que o usuário veja a mensagem
header("Refresh: 3; url=categorias.php"); // Redireciona após 3 segundos
include '../template_admin.php'; // Inclui o template para exibir a mensagem e o layout
?>