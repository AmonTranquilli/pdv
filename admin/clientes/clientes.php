<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho para a conexão

// Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php"); // Redireciona para o login
    exit();
}

// Consulta total de clientes cadastrados
$totalClientes = 0;
$resultCount = $conn->query("SELECT COUNT(*) as total FROM clientes");
if ($resultCount && $rowCount = $resultCount->fetch_assoc()) {
    $totalClientes = $rowCount['total'];
}

// Lógica para listar clientes do banco de dados
$clientes = [];
// ATUALIZAÇÃO: Adicionado 'data_cadastro' na consulta
$sql = "SELECT id, nome, telefone, endereco, ncasa, bairro, cep, complemento, ponto_referencia, data_cadastro FROM clientes ORDER BY nome ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clientes[] = $row;
    }
}

$conn->close(); // Fecha a conexão com o banco de dados

// Define o título da página para o template
$page_title = 'Gerenciar Clientes';

// --- INÍCIO DO BUFFER DE SAÍDA ---
ob_start();
?>

<h1>Gerenciar Clientes</h1>

<div class="card-total-clientes">
    <i class="fa fa-users" aria-hidden="true"></i>
    <div class="info">
        Total de clientes cadastrados:<br>
        <span><?= $totalClientes ?></span>
    </div>
</div>

<a href="adicionar.php" class="btn btn-success">Adicionar Novo Cliente</a>

<?php if (empty($clientes)) : ?>
    <p class="text-center">Nenhum cliente cadastrado ainda.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Telefone</th>
                <th>Endereço</th>
                <th>Nº Casa</th>
                <th>Bairro</th>
                <th>CEP</th>
                <th>Complemento</th>
                <th>Ponto de Referência</th>
                <th>Data de Cadastro</th> <!-- NOVA COLUNA NO CABEÇALHO -->
                <th class="text-center">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $cliente) : ?>
                <tr>
                    <td><?= htmlspecialchars($cliente['id']) ?></td>
                    <td><?= htmlspecialchars($cliente['nome']) ?></td>
                    <td><?= htmlspecialchars($cliente['telefone'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($cliente['endereco'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($cliente['ncasa'] ?? 'S/N') ?></td>
                    <td><?= htmlspecialchars($cliente['bairro'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($cliente['cep'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($cliente['complemento'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($cliente['ponto_referencia'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($cliente['data_cadastro']))) ?></td> <!-- NOVA CÉLULA DE DADOS -->
                    <td class="text-center">
                        <a href="editar.php?id=<?= $cliente['id'] ?>" class="btn btn-warning btn-sm">Editar</a>
                        <a href="excluir.php?id=<?= $cliente['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir este cliente?');">Excluir</a>
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
include '../template_admin.php';
?>
