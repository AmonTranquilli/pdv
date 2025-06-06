<?php
session_start();
// Caminho para a conexão, ajustado para dentro de 'admin/pedidos'
require_once '../../includes/conexao.php'; // Caminho para a conexão

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php"); 
    exit();
}

// --- LÓGICA DO FILTRO DE DATA MELHORADA ---
$data_hoje = date('Y-m-d'); // Obtém a data de hoje no formato YYYY-MM-DD

// Se nenhum filtro de data for aplicado, assume a data de hoje por defeito.
$data_filtro = isset($_GET['data_filtro']) ? $_GET['data_filtro'] : $data_hoje;

$sql_where = "";
$params = [];
$types = "";

// Valida se a data está num formato correto (YYYY-MM-DD) e aplica o filtro
if (!empty($data_filtro) && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $data_filtro)) {
    $sql_where = " WHERE DATE(data_pedido) = ?";
    $params[] = $data_filtro;
    $types .= "s"; // 's' para string
} else {
    // Se a data for inválida ou vazia (ao clicar em "Ver Todos"), não aplica nenhum filtro de data
    $data_filtro = ''; 
}
// --- FIM DA LÓGICA DO FILTRO ---

// Inicia o buffer de saída para incluir o conteúdo no template
ob_start();
?>

<!-- Estilos específicos para esta página -->
<style>
    .header-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    .header-controls h1 {
        margin: 0;
    }
    .filter-form .date-filter-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .filter-form label {
        display: none; /* Oculta o label "Filtrar por data" para um visual mais limpo */
    }
    .filter-form input[type="date"] {
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 0.9rem;
    }
    /* Estilos dos botões já devem vir do admin.css, mas podemos garantir aqui */
    .filter-form .btn {
        padding: 8px 12px;
        text-decoration: none;
        font-size: 0.9rem;
        border-radius: 5px;
        cursor: pointer;
        border: none;
        color: white;
        display: inline-block;
    }
    .filter-form .btn-primary { background-color: #007bff; }
    .filter-form .btn-info { background-color: #17a2b8; }
    .filter-form .btn-secondary { background-color: #6c757d; }
</style>

<div class="container">
    <div class="header-controls">
        <h1><i class="fas fa-list-alt"></i> Lista de Pedidos</h1>
        
        <!-- Formulário de Filtro por Data com novo estilo -->
        <form action="pedidos.php" method="GET" class="filter-form">
            <div class="date-filter-group">
                <label for="data_filtro">Filtrar por data:</label>
                <input type="date" id="data_filtro" name="data_filtro" value="<?php echo htmlspecialchars($data_filtro); ?>" onchange="this.form.submit()">
                <a href="pedidos.php?data_filtro=<?php echo $data_hoje; ?>" class="btn btn-info">Hoje</a>
                <a href="pedidos.php?data_filtro=" class="btn btn-secondary">Ver Todos</a>
            </div>
        </form>
    </div>

    <div class="message-area">
        <?php
        // Exibe mensagens de sucesso ou erro, se houver
        if (isset($_SESSION['mensagem_sucesso'])) {
            echo '<p class="mensagem sucesso">' . htmlspecialchars($_SESSION['mensagem_sucesso']) . '</p>';
            unset($_SESSION['mensagem_sucesso']);
        }
        if (isset($_SESSION['mensagem_erro'])) {
            echo '<p class="mensagem erro">' . htmlspecialchars($_SESSION['mensagem_erro']) . '</p>';
            unset($_SESSION['mensagem_erro']);
        }
        ?>
    </div>

    <?php if (!empty($data_filtro)): ?>
        <p class="filter-info">Mostrando pedidos para o dia: <strong><?php echo date('d/m/Y', strtotime($data_filtro)); ?></strong></p>
    <?php else: ?>
        <p class="filter-info">Mostrando todos os pedidos.</p>
    <?php endif; ?>

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
            // Consulta para buscar os pedidos, agora com o filtro
            $sql = "SELECT id, id_cliente, nome_cliente, telefone_cliente, endereco_entrega, numero_entrega, bairro_entrega, complemento_entrega, referencia_entrega, data_pedido, total_pedido, forma_pagamento, troco_para, troco, status FROM pedidos" . $sql_where . " ORDER BY data_pedido DESC";
            
            $stmt = $conn->prepare($sql);

            // Binda os parâmetros se houver filtro
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
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
                            if (!empty($pedido['bairro_entrega'])) {
                                echo " - " . htmlspecialchars($pedido['bairro_entrega']);
                            }
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
                            <span class="status-<?php echo htmlspecialchars(strtolower($pedido['status'])); ?>">
                                <?php echo htmlspecialchars(ucfirst($pedido['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></td>
                        <td>
                            <a href="detalhes_pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-info btn-sm" title="Ver Detalhes">
                                <i class="fas fa-eye"></i> Detalhes
                            </a>
                            <a href="excluir_pedido.php?id=<?php echo $pedido['id']; ?>" 
                               class="btn btn-danger btn-sm" 
                               title="Apagar Pedido"
                               onclick="return confirmarExclusaoPedido(<?php echo $pedido['id']; ?>);">
                                <i class="fas fa-trash-alt"></i> Apagar
                            </a>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                 if (!empty($data_filtro)) {
                    echo '<tr><td colspan="12" style="text-align:center;">Nenhum pedido encontrado para a data selecionada.</td></tr>'; 
                 } else {
                    echo '<tr><td colspan="12" style="text-align:center;">Nenhum pedido encontrado.</td></tr>';
                 }
            }
            $stmt->close();
            $conn->close();
            ?>
        </tbody>
    </table>
</div>

<?php
// --- FIM DO BUFFER DE SAÍDA ---
$page_content = ob_get_clean();

// Inclui o template principal do painel administrativo.
include '../template_admin.php'; 
?>
