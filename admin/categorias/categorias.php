<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho ajustado: ../../ para subir 2 níveis (pdv/admin/categorias/ -> pdv/)

// Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php"); // Caminho ajustado: ../ para ir para pdv/admin/login.php
    exit();
}

// Lógica para listar categorias do banco de dados
$categorias = [];
$sql = "SELECT id, nome FROM categorias ORDER BY nome ASC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categorias[] = $row;
    }
}

$conn->close();

// Variáveis que o template precisa
$page_title = 'Gerenciar Categorias'; // Título específico desta página

// *** INÍCIO DO BUFFER DE SAÍDA ***
// Tudo o que for impresso ou HTML estático a partir daqui será "capturado".
ob_start();
?>

<h1>Gerenciar Categorias</h1>

<a href="adicionar.php" class="btn btn-success">Adicionar Nova Categoria</a>

<?php if (empty($categorias)) : ?>
    <p class="text-center">Nenhuma categoria cadastrada ainda.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome da Categoria</th>
                <th class="text-center">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categorias as $categoria) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($categoria['id']); ?></td>
                    <td><?php echo htmlspecialchars($categoria['nome']); ?></td>
                    <td class="text-center">
                        <a href="editar.php?id=<?php echo $categoria['id']; ?>" class="btn btn-warning btn-sm">Editar</a>
                        <a href="excluir.php?id=<?php echo $categoria['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir esta categoria? Isso também removerá os produtos associados!');">Excluir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
// *** FIM DO BUFFER DE SAÍDA ***
// Captura todo o conteúdo do buffer e armazena na variável $page_content.
$page_content = ob_get_clean();

// Inclui o template principal do painel administrativo.
include '../template_admin.php'; // template_admin.php está um nível acima (em 'admin/')
?>