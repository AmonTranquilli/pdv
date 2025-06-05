<?php
session_start();
// Caminho para a conexão. De 'admin/pedidos/' para 'includes/conexao.php' (na raiz).
require_once '../../includes/conexao.php'; 

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Redireciona para a página de login do admin. De 'admin/pedidos/' para 'admin/login.php'.
    header("Location: ../login.php"); 
    exit();
}

$pedido = null;
$itens_pedido = [];
$mensagem_erro = '';
$mensagem_sucesso = ''; // Para exibir mensagens de sucesso após ações

// Verifica se há mensagem de sucesso na sessão (ex: após um cancelamento bem-sucedido)
if (isset($_SESSION['mensagem_sucesso'])) {
    $mensagem_sucesso = $_SESSION['mensagem_sucesso'];
    unset($_SESSION['mensagem_sucesso']);
}
// Verifica se há mensagem de erro na sessão
if (isset($_SESSION['mensagem_erro'])) { 
    $mensagem_erro = $_SESSION['mensagem_erro'];
    unset($_SESSION['mensagem_erro']);
}


// 2. Tenta carregar os detalhes do pedido (se um ID for fornecido via GET)
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $pedido_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($pedido_id === false || $pedido_id <= 0) {
        $mensagem_erro = "ID de pedido inválido.";
    } else {
        // Consulta para buscar os dados do pedido principal
        $stmt_pedido = $conn->prepare("SELECT * FROM pedidos WHERE id = ?");
        $stmt_pedido->bind_param("i", $pedido_id);
        $stmt_pedido->execute();
        $result_pedido = $stmt_pedido->get_result();

        if ($result_pedido->num_rows > 0) {
            $pedido = $result_pedido->fetch_assoc();

            // Consulta para buscar os itens deste pedido
            $stmt_itens = $conn->prepare("SELECT * FROM itens_pedido WHERE id_pedido = ?");
            $stmt_itens->bind_param("i", $pedido_id);
            $stmt_itens->execute();
            $result_itens = $stmt_itens->get_result();

            while ($item = $result_itens->fetch_assoc()) {
                $itens_pedido[] = $item;
            }
            $stmt_itens->close();

        } else {
            $mensagem_erro = "Pedido não encontrado.";
        }
        $stmt_pedido->close();
    }
} else {
    $mensagem_erro = "Nenhum ID de pedido fornecido.";
}

// Inicia o buffer de saída para incluir o conteúdo no template
ob_start();
?>

<div class="container">
    <?php if ($mensagem_erro): ?>
        <p class="mensagem erro"><?php echo htmlspecialchars($mensagem_erro); ?></p>
        <div class="message-info"> <a href="pedidos.php" class="btn btn-secondary">Voltar para a Lista de Pedidos</a>
        </div>
    <?php elseif ($pedido): ?>
        <h1>Detalhes do Pedido #<?php echo htmlspecialchars($pedido['id']); ?></h1>

        <?php if ($mensagem_sucesso): ?>
            <p class="mensagem sucesso"><?php echo htmlspecialchars($mensagem_sucesso); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['mensagem_erro']) && empty($mensagem_erro)): // Se houver erro de outra página (ex: cancelar_pedido.php) ?>
            <p class="mensagem erro"><?php echo htmlspecialchars($_SESSION['mensagem_erro']); ?></p>
            <?php unset($_SESSION['mensagem_erro']); ?>
        <?php endif; ?>

        <div class="pedido-info">
            <h2>Informações do Cliente e Entrega</h2>
            <p><strong>Cliente:</strong> <?php echo htmlspecialchars($pedido['nome_cliente']); ?></p>
            <p><strong>Telefone:</strong> <?php echo htmlspecialchars($pedido['telefone_cliente']); ?></p>
            <p><strong>Endereço:</strong> 
                <?php 
                echo htmlspecialchars($pedido['endereco_entrega']) . ", " . htmlspecialchars($pedido['numero_entrega']);
                if (!empty($pedido['complemento_entrega'])) {
                    echo " (" . htmlspecialchars($pedido['complemento_entrega']) . ")";
                }
                echo " - " . htmlspecialchars($pedido['bairro_entrega']);
                ?>
            </p>
            <p><strong>Observações do Pedido:</strong> 
                <?php echo !empty($pedido['observacoes_pedido']) ? htmlspecialchars($pedido['observacoes_pedido']) : 'Nenhuma observação.'; ?>
            </p>
        </div>

        <div class="pedido-info mt-4">
            <h2>Detalhes do Pedido</h2>
            <p><strong>Data/Hora:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></p>
            <p><strong>Forma de Pagamento:</strong> <?php echo htmlspecialchars(ucfirst($pedido['forma_pagamento'])); ?></p>
            <?php if ($pedido['troco_para'] !== null): ?>
                <p><strong>Troco Para:</strong> R$ <?php echo number_format($pedido['troco_para'], 2, ',', '.'); ?></p>
            <?php endif; ?>
            <p><strong>Total do Pedido:</strong> R$ <?php echo number_format($pedido['total_pedido'], 2, ',', '.'); ?></p>
            <p><strong>Status:</strong> 
                <span class="status-<?php echo htmlspecialchars($pedido['status']); ?>">
                    <?php echo htmlspecialchars(ucfirst($pedido['status'])); ?>
                </span>
            </p>
        </div>

        <div class="pedido-info mt-4">
            <h2>Itens do Pedido</h2>
            <?php if (!empty($itens_pedido)): ?>
                <table class="data-table small-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Quantidade</th>
                            <th>Preço Unitário</th>
                            <th>Subtotal</th>
                            <th>Observação do Item</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens_pedido as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['nome_produto']); ?></td>
                                <td><?php echo htmlspecialchars($item['quantidade']); ?></td>
                                <td>R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($item['quantidade'] * $item['preco_unitario'], 2, ',', '.'); ?></td>
                                <td>
                                    <?php echo !empty($item['observacao_item']) ? htmlspecialchars($item['observacao_item']) : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Nenhum item encontrado para este pedido.</p>
            <?php endif; ?>
        </div>

        <div class="mt-4 action-buttons">
            <a href="pedidos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar para a Lista de Pedidos</a>
            
            <?php 
            // Mostra o botão Cancelar apenas se o status não for 'cancelado' ou 'entregue'
            if ($pedido['status'] !== 'cancelado' && $pedido['status'] !== 'entregue'): 
            ?>
                <a href="cancelar_pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-danger" 
                   onclick="return confirm('Tem certeza que deseja cancelar este pedido? Esta ação é irreversível e o estoque será devolvido, se aplicável.');">
                    <i class="fas fa-times-circle"></i> Cancelar Pedido
                </a>
            <?php endif; ?>

            <?php 
            // NOVO: Mostra o botão Apagar apenas se o status for 'cancelado' ou 'concluido'
            $status_permitidos_para_exclusao = ['cancelado', 'concluido'];
            if (in_array($pedido['status'], $status_permitidos_para_exclusao)) :
            ?>
                <a href="excluir_pedido.php?id=<?php echo $pedido['id']; ?>" 
                   class="btn btn-danger" 
                   onclick="return confirmarExclusaoPedido(<?php echo $pedido['id']; ?>);">
                    <i class="fas fa-trash-alt"></i> Apagar Pedido
                </a>
            <?php endif; ?>

        </div>

    <?php endif; ?>
</div>

<?php
// --- FIM DO BUFFER DE SAÍDA ---
$page_content = ob_get_clean();

// Inclui o template principal do painel administrativo.
// Caminho para o template: sobe um nível para 'admin' e lá encontra o template.
include '../template_admin.php'; 
?>