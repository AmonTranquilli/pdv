<?php
session_start();
// Inclua o arquivo de conexão com o banco de dados
require_once __DIR__ . '/../includes/conexao.php'; // Ajuste o caminho se necessário

// Verifica se o usuário está logado e tem permissão de acesso (admin)
if (!isset($_SESSION['usuario_logado']) || $_SESSION['nivel_acesso'] !== 'admin') {
    header("Location: ../login.php"); // Redireciona para a página de login se não for admin
    exit();
}

$mensagem = ''; // Para exibir mensagens de sucesso ou erro

// Lógica para Exclusão de Adicional
if (isset($_GET['action']) && $_GET['action'] === 'excluir' && isset($_GET['id'])) {
    $id_adicional_excluir = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    // Começa uma transação para garantir a integridade
    $conn->begin_transaction();
    try {
        // Opcional: Primeiro, verifique se o adicional está associado a algum produto
        // Se estiver, você pode impedir a exclusão ou avisar o usuário.
        // Por simplicidade, vamos permitir a exclusão que será tratada pelas FKs CASCADE DELETE.

        // Excluir adicional
        $stmt = $conn->prepare("DELETE FROM adicionais WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id_adicional_excluir);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $conn->commit();
                    $mensagem = "<p class='mensagem sucesso'>Adicional excluído com sucesso!</p>";
                } else {
                    $conn->rollback();
                    $mensagem = "<p class='mensagem erro'>Adicional não encontrado ou já foi excluído.</p>";
                }
            } else {
                throw new Exception("Erro ao excluir adicional: " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Erro na preparação da exclusão: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $mensagem = "<p class='mensagem erro'>Erro: " . $e->getMessage() . "</p>";
    }
}

// Lógica para buscar todos os adicionais
$adicionais = [];
$sql = "SELECT id, nome, preco, descricao, ativo FROM adicionais ORDER BY nome ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $adicionais[] = $row;
    }
} else {
    $mensagem = "<p class='mensagem aviso'>Nenhum adicional encontrado.</p>";
}

$conn->close(); // Fecha a conexão
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Adicionais - Admin</title>
    <link rel="stylesheet" href="../public/css/admin.css"> <style>
        /* Estilos básicos para a tabela e botões dentro do painel admin */
        .container-admin {
            padding: 20px;
            margin-left: 250px; /* Espaço para a sidebar */
        }
        .header-admin {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header-admin h1 {
            margin: 0;
            color: #333;
        }
        .btn-add {
            background-color: #28a745;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }
        .btn-add:hover {
            background-color: #218838;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            color: #555;
            font-weight: 600;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        .acoes {
            display: flex;
            gap: 10px;
        }
        .btn-editar, .btn-excluir, .btn-toggle-ativo {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
        }
        .btn-editar {
            background-color: #007bff;
            color: white;
        }
        .btn-editar:hover {
            background-color: #0056b3;
        }
        .btn-excluir {
            background-color: #dc3545;
            color: white;
        }
        .btn-excluir:hover {
            background-color: #c82333;
        }
        .status-ativo {
            color: #28a745;
            font-weight: bold;
        }
        .status-inativo {
            color: #ffc107;
            font-weight: bold;
        }
        .mensagem {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .mensagem.sucesso {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .mensagem.erro {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .mensagem.aviso {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Inclui o header do admin ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; // Inclui a sidebar do admin ?>

    <div class="container-admin">
        <div class="header-admin">
            <h1>Gerenciar Adicionais</h1>
            <a href="form_adicional.php" class="btn-add">Adicionar Novo Adicional</a>
        </div>

        <?= $mensagem ?>

        <?php if (!empty($adicionais)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Preço</th>
                        <th>Descrição</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adicionais as $adicional): ?>
                        <tr>
                            <td><?= htmlspecialchars($adicional['id']) ?></td>
                            <td><?= htmlspecialchars($adicional['nome']) ?></td>
                            <td>R$ <?= number_format($adicional['preco'], 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars($adicional['descricao'] ?? 'N/A') ?></td>
                            <td>
                                <span class="<?= $adicional['ativo'] ? 'status-ativo' : 'status-inativo' ?>">
                                    <?= $adicional['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td class="acoes">
                                <a href="form_adicional.php?id=<?= htmlspecialchars($adicional['id']) ?>" class="btn-editar">Editar</a>
                                <a href="adicionais.php?action=excluir&id=<?= htmlspecialchars($adicional['id']) ?>" class="btn-excluir" onclick="return confirm('Tem certeza que deseja excluir este adicional?');">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; // Inclui o footer do admin ?>
</body>
</html>

