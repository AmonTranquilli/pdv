<?php
session_start();
// Caminho para a conexão, ajustado para dentro de 'admin/pedidos'
require_once '../../includes/conexao.php'; // Caminho para a conexão

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Redireciona para a página de login do admin (ajustado para subir 2 níveis)
    header("Location: ../login.php"); 
    exit();
}

// Inicia o buffer de saída para incluir o conteúdo no template
ob_start();
?>

<div class="container">
    <h1>Lista de Pedidos</h1>

    <div class="message-area">
        <?php
        // Exibe mensagens de sucesso ou erro, se houver
        if (isset($_SESSION['mensagem_sucesso'])) {
            echo '<p class="mensagem sucesso">' . $_SESSION['mensagem_sucesso'] . '</p>';
            unset($_SESSION['mensagem_sucesso']);
        }
        if (isset($_SESSION['mensagem_erro'])) {
            echo '<p class="mensagem erro">' . $_SESSION['mensagem_erro'] . '</p>';
            unset($_SESSION['mensagem_erro']);
        }
        ?>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>ID Pedido</th>
                <th>Cliente</th>
                <th>Telefone</th>
                <th>Endereço de Entrega</th>
                <th>Ponto de Referência</th>
                <th>Total</th>
                <th>Pagamento</th>
                <th>Troco Para</th>
                <th>Troco</th>
                <th>Status</th>
                <th>Data/Hora</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Consulta para buscar os pedidos
            // Removido 'observacoes_pedidos' da lista de colunas selecionadas
            $sql = "SELECT id, id_cliente, nome_cliente, telefone_cliente, endereco_entrega, numero_entrega, bairro_entrega, complemento_entrega, referencia_entrega, data_pedido, total_pedido, forma_pagamento, troco_para, troco, status FROM pedidos ORDER BY data_pedido DESC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while ($pedido = $result->fetch_assoc()) {
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pedido['id']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['nome_cliente']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['telefone_cliente']); ?></td>
                        <td>
                            <?php 
                            echo htmlspecialchars($pedido['endereco_entrega']) . ", ";
                            echo htmlspecialchars($pedido['numero_entrega']);
                            if (!empty($pedido['complemento_entrega'])) {
                                echo " (" . htmlspecialchars($pedido['complemento_entrega']) . ")";
                            }
                            echo " - " . htmlspecialchars($pedido['bairro_entrega']);
                            ?>
                        </td>
                        <td>
                            <?php 
                            echo !empty($pedido['referencia_entrega']) ? htmlspecialchars($pedido['referencia_entrega']) : '-';
                            ?>
                        </td>
                        <td>R$ <?php echo number_format($pedido['total_pedido'], 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($pedido['forma_pagamento'])); ?></td>
                        <td>
                            <?php 
                            if ($pedido['troco_para'] !== null && $pedido['troco_para'] > 0) {
                                echo "R$ " . number_format($pedido['troco_para'], 2, ',', '.');
                            } else {
                                echo "-";
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($pedido['troco'] !== null && $pedido['troco'] > 0) {
                                echo "R$ " . number_format($pedido['troco'], 2, ',', '.');
                            } else {
                                echo "-";
                            }
                            ?>
                        </td>
                        <td>
                            <span class="status-<?php echo htmlspecialchars($pedido['status']); ?>">
                                <?php echo htmlspecialchars(ucfirst($pedido['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></td>
                        <td>
                            <a href="detalhes_pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i> Detalhes
                            </a>
                            <a href="excluir_pedido.php?id=<?php echo $pedido['id']; ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirmarExclusaoPedido(<?php echo $pedido['id']; ?>);">
                                <i class="fas fa-trash-alt"></i> Apagar
                            </a>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                echo '<tr><td colspan="12">Nenhum pedido encontrado.</td></tr>'; 
            }
            ?>
        </tbody>
    </table>
</div>

<?php
// --- FIM DO BUFFER DE SAÍDA ---
$page_content = ob_get_clean();

// Inclui o template principal do painel administrativo.
// Ajustado para o caminho: sobe um nível para 'admin' e lá encontra o template.
include '../template_admin.php'; 
?>
