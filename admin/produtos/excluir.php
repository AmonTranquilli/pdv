<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho para a conexão

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php"); // Redireciona para o login se não estiver logado
    exit();
}

$mensagem = '';
$sucesso = false;

// 2. Verifica se um ID de produto foi passado via GET para exclusão
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $produto_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($produto_id === false || $produto_id <= 0) {
        $mensagem = "ID de produto inválido para exclusão.";
        $sucesso = false;
    } else {
        // Inicia uma transação (opcional, mas boa prática para múltiplas operações)
        $conn->begin_transaction();

        try {
            // Primeiro, obtenha o caminho da imagem do produto antes de excluí-lo
            $stmt_select_imagem = $conn->prepare("SELECT imagem FROM produtos WHERE id = ?");
            if ($stmt_select_imagem === false) {
                throw new Exception("Erro na preparação da consulta de imagem: " . $conn->error);
            }
            $stmt_select_imagem->bind_param("i", $produto_id);
            $stmt_select_imagem->execute();
            $result_imagem = $stmt_select_imagem->get_result();
            $caminho_imagem_servidor = null;

            if ($result_imagem->num_rows === 1) {
                $produto_data = $result_imagem->fetch_assoc();
                $caminho_imagem_db = $produto_data['imagem'];

                // Converte o caminho do DB (ex: /pdv/public/uploads/produtos/...)
                // para um caminho físico no servidor (ex: ../../public/uploads/produtos/...)
                // Use a função realpath para resolver caminhos se houver problemas
                if (!empty($caminho_imagem_db)) {
                    $base_path = realpath(__DIR__ . '/../../'); // Vai para o diretório 'pdv/'
                    $caminho_imagem_servidor = $base_path . str_replace('/pdv/', '/', $caminho_imagem_db);
                }
            }
            $stmt_select_imagem->close();

            // Em seguida, exclui o produto
            $stmt_delete_produto = $conn->prepare("DELETE FROM produtos WHERE id = ?");
            if ($stmt_delete_produto === false) {
                throw new Exception("Erro na preparação do DELETE de produto: " . $conn->error);
            }
            $stmt_delete_produto->bind_param("i", $produto_id);

            if ($stmt_delete_produto->execute()) {
                // Se a exclusão do banco de dados for bem-sucedida, tenta apagar a imagem
                if (!empty($caminho_imagem_servidor) && file_exists($caminho_imagem_servidor)) {
                    if (unlink($caminho_imagem_servidor)) {
                        $mensagem_imagem = " Imagem também excluída.";
                    } else {
                        $mensagem_imagem = " Mas não foi possível excluir a imagem do servidor.";
                    }
                } else {
                    $mensagem_imagem = " Nenhuma imagem para excluir ou imagem não encontrada.";
                }

                $conn->commit(); // Confirma a transação se tudo deu certo
                $mensagem = "Produto excluído com sucesso!" . $mensagem_imagem;
                $sucesso = true;
            } else {
                throw new Exception("Erro ao excluir produto do banco de dados: " . $stmt_delete_produto->error);
            }
            $stmt_delete_produto->close();

        } catch (Exception $e) {
            $conn->rollback(); // Reverte a transação em caso de erro
            $mensagem = "Erro na exclusão do produto: " . $e->getMessage();
            $sucesso = false;
        }
    }
} else {
    $mensagem = "Nenhum ID de produto fornecido para exclusão.";
    $sucesso = false;
}

$conn->close(); // Fecha a conexão com o banco de dados

// Define o título da página (embora esta página redirecione rapidamente)
$page_title = 'Excluir Produto';

// --- INÍCIO DO BLOCO DE CONTEÚDO ESPECÍFICO (apenas para exibir a mensagem antes do redirecionamento) ---
ob_start();
?>

<h1>Excluir Produto</h1>
<?php if (!empty($mensagem)) : ?>
    <p class="mensagem <?php echo $sucesso ? 'sucesso' : 'erro'; ?>"><?php echo $mensagem; ?></p>
<?php endif; ?>
<p>Você será redirecionado em breve...</p>

<?php
// --- FIM DO BLOCO DE CONTEÚDO ESPECÍFICO ---
$page_content = ob_get_clean();

// Redireciona para a lista de produtos após um pequeno delay para que o usuário veja a mensagem
header("Refresh: 3; url=produtos.php");
include '../template_admin.php'; // Inclui o template para exibir a mensagem e o layout
?>