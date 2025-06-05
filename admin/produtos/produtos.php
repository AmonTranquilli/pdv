<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho para a conexão

// Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php"); // Redireciona para o login
    exit();
}

// Lógica para listar produtos do banco de dados
$produtos = [];
// Consulta para buscar produtos e o nome da categoria associada (usando JOIN)
$sql = "SELECT
            p.id,
            p.nome,
            p.descricao,
            p.preco,
            p.estoque,
            p.controla_estoque,
            p.imagem,
            p.ativo, -- ADICIONADO: Seleciona a coluna 'ativo'
            c.nome AS nome_categoria
        FROM
            produtos p
        LEFT JOIN
            categorias c ON p.id_categoria = c.id
        ORDER BY
            p.nome ASC";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $produtos[] = $row;
    }
}

$conn->close(); // Fecha a conexão com o banco de dados

// Define o título da página para o template
$page_title = 'Gerenciar Produtos';

// --- INÍCIO DO BUFFER DE SAÍDA ---
ob_start();
?>

<h1>Gerenciar Produtos</h1>

<a href="adicionar.php" class="btn btn-success">Adicionar Novo Produto</a>

<?php if (empty($produtos)) : ?>
    <p class="text-center">Nenhum produto cadastrado ainda.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome do Produto</th>
                <th>Preço</th>
                <th>Estoque</th>
                <th>Controla Estoque</th>
                <th>Categoria</th>
                <th>Imagem</th>
                <th>Status</th> <th class="text-center">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produtos as $produto) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($produto['id']); ?></td>
                    <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                    <td>R$ <?php echo htmlspecialchars(number_format($produto['preco'], 2, ',', '.')); ?></td>
                    <td><?php echo htmlspecialchars($produto['estoque']); ?></td>
                    <td><?php echo $produto['controla_estoque'] ? 'Sim' : 'Não'; ?></td>
                    <td><?php echo htmlspecialchars($produto['nome_categoria'] ?? 'N/A'); ?></td> <!-- CORREÇÃO AQUI -->
                    <td>
                        <?php if (!empty($produto['imagem'])) : ?>
                            <img src="<?php echo htmlspecialchars($produto['imagem']); ?>" alt="Imagem do produto" style="width: 50px; height: 50px; object-fit: cover;">
                        <?php else : ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td><?php echo $produto['ativo'] ? '<span style="color: green; font-weight: bold;">Ativo</span>' : '<span style="color: red; font-weight: bold;">Inativo</span>'; ?></td> <td class="text-center">
                        <a href="editar.php?id=<?php echo $produto['id']; ?>" class="btn btn-warning btn-sm">Editar</a>
                        <a href="excluir.php?id=<?php echo $produto['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir este produto?');">Excluir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
// --- FIM DO BUFFER DE SAÍDA ---
$page_content = ob_get_clean();

// Inclui o template principal do painel administrativo.
include '../template_admin.php'; // template_admin.php está um nível acima (em 'admin/')
?>
