<?php
session_start();

require_once __DIR__ . '/../../includes/conexao.php'; // Ajuste o caminho para a raiz do projeto

// Verifica se o usuário está logado e tem permissão de acesso (admin)
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || $_SESSION['nivel_acesso'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$mensagem = ''; // Para exibir mensagens de sucesso ou erro
$page_title = 'Gerenciar Cardápio'; // Título da página para o template_admin

// --- Lógica para buscar configurações da loja do banco de dados ---
$nome_hamburgueria = "Hamburgueria Padrão";
$horario_funcionamento = "Aberto todos os dias";
$pedido_minimo = "0.00";
$taxa_entrega = "0.00"; // Adicionado: Variável para a taxa de entrega
$hora_abertura = "00:00"; // Novas variáveis
$hora_fechamento = "23:59"; // Novas variáveis
$dias_abertura_array = []; // Array para os dias abertos

$sqlConfig = "SELECT nome_hamburgueria, horario_funcionamento, pedido_minimo, taxa_entrega, hora_abertura, hora_fechamento, dias_abertura FROM configuracoes_loja WHERE id = 1";
$resultConfig = $conn->query($sqlConfig);
if ($resultConfig && $resultConfig->num_rows > 0) {
    $config = $resultConfig->fetch_assoc();
    $nome_hamburgueria = htmlspecialchars($config['nome_hamburgueria']);
    $horario_funcionamento = htmlspecialchars($config['horario_funcionamento']);
    $pedido_minimo = htmlspecialchars(number_format($config['pedido_minimo'], 2, ',', '.'));
    $taxa_entrega = htmlspecialchars(number_format($config['taxa_entrega'], 2, ',', '.')); // Atribuir a taxa de entrega
    $hora_abertura = substr($config['hora_abertura'], 0, 5); // Formato HH:MM
    $hora_fechamento = substr($config['hora_fechamento'], 0, 5); // Formato HH:MM
    $dias_abertura_array = explode(',', $config['dias_abertura']);
} else {
    // Se não houver configurações, insere uma linha padrão
    $insertDefaultConfig = $conn->prepare("INSERT INTO configuracoes_loja (id, nome_hamburgueria, horario_funcionamento, pedido_minimo, taxa_entrega, hora_abertura, hora_fechamento, dias_abertura) VALUES (1, ?, ?, ?, ?, ?, ?, ?)");
    $default_name = "Minha Hamburgueria";
    $default_hours_desc = "Aberto de Terça a Domingo, das 18h às 23h";
    $default_min_order = 10.00;
    $default_delivery_fee = 5.00; // Valor padrão para a taxa de entrega
    $default_open_time = "18:00:00";
    $default_close_time = "23:00:00";
    $default_open_days = "2,3,4,5,6,7"; // Terça a Domingo
    $insertDefaultConfig->bind_param("ssddsss", $default_name, $default_hours_desc, $default_min_order, $default_delivery_fee, $default_open_time, $default_close_time, $default_open_days);
    $insertDefaultConfig->execute();
    $insertDefaultConfig->close();

    // Atualiza as variáveis com os valores padrão recém-inseridos
    $nome_hamburgueria = htmlspecialchars($default_name);
    $horario_funcionamento = htmlspecialchars($default_hours_desc);
    $pedido_minimo = htmlspecialchars(number_format($default_min_order, 2, ',', '.'));
    $taxa_entrega = htmlspecialchars(number_format($default_delivery_fee, 2, ',', '.'));
    $hora_abertura = substr($default_open_time, 0, 5);
    $hora_fechamento = substr($default_close_time, 0, 5);
    $dias_abertura_array = explode(',', $default_open_days);
}


// Lógica para buscar categorias para ordenação (agora por 'ordem' e depois por 'nome')
$categorias = [];
$sqlCategorias = "SELECT id, nome FROM categorias ORDER BY ordem ASC, nome ASC";
$resultCategorias = $conn->query($sqlCategorias);
if ($resultCategorias) {
    while ($cat = $resultCategorias->fetch_assoc()) {
        $categorias[] = $cat;
    }
}

// Lógica para salvar as configurações e a ordem das categorias
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction(); // Inicia uma transação

    try {
        // 1. Salvar Configurações Gerais da Loja
        $novo_nome = trim($_POST['nome_hamburgueria'] ?? '');
        $novo_horario = trim($_POST['horario_funcionamento'] ?? '');
        
        $novo_pedido_minimo_raw = str_replace(',', '.', trim($_POST['pedido_minimo'] ?? '0.00'));
        $novo_pedido_minimo = filter_var($novo_pedido_minimo_raw, FILTER_VALIDATE_FLOAT);

        $nova_taxa_entrega_raw = str_replace(',', '.', trim($_POST['taxa_entrega'] ?? '0.00')); // Captura a nova taxa de entrega
        $nova_taxa_entrega = filter_var($nova_taxa_entrega_raw, FILTER_VALIDATE_FLOAT); // Valida como float

        if ($novo_pedido_minimo === false || $nova_taxa_entrega === false) {
            throw new Exception("Valor de 'Pedido Mínimo' ou 'Taxa de Entrega' inválido.");
        }

        // Novas configurações de horário
        $nova_hora_abertura = trim($_POST['hora_abertura'] ?? '00:00');
        $nova_hora_fechamento = trim($_POST['hora_fechamento'] ?? '23:59');
        $novos_dias_abertura = isset($_POST['dias_abertura']) ? implode(',', $_POST['dias_abertura']) : '';

        // Validação básica de hora
        if (!preg_match("/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/", $nova_hora_abertura) || !preg_match("/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/", $nova_hora_fechamento)) {
            throw new Exception("Formato de hora inválido. Use HH:MM.");
        }

        // Atualiza a query SQL para incluir a taxa_entrega
        $stmtConfig = $conn->prepare("UPDATE configuracoes_loja SET nome_hamburgueria = ?, horario_funcionamento = ?, pedido_minimo = ?, taxa_entrega = ?, hora_abertura = ?, hora_fechamento = ?, dias_abertura = ? WHERE id = 1");
        if (!$stmtConfig) {
            throw new Exception("Erro na preparação da atualização de configurações: " . $conn->error);
        }
        // Atualiza os tipos de parâmetros para incluir o float da taxa_entrega
        $stmtConfig->bind_param("ssddsss", $novo_nome, $novo_horario, $novo_pedido_minimo, $nova_taxa_entrega, $nova_hora_abertura, $nova_hora_fechamento, $novos_dias_abertura);
        if (!$stmtConfig->execute()) {
            throw new Exception("Erro ao atualizar configurações da loja: " . $stmtConfig->error);
        }
        $stmtConfig->close();

        // 2. Salvar Ordem das Categorias
        $ordem_categorias_json = $_POST['ordem_categorias_json'] ?? '[]';
        $ordem_categorias = json_decode($ordem_categorias_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Dados de ordem das categorias inválidos (JSON erro: " . json_last_error_msg() . ")");
        }

        $stmtUpdateOrdem = $conn->prepare("UPDATE categorias SET ordem = ? WHERE id = ?");
        if (!$stmtUpdateOrdem) {
            throw new Exception("Erro na preparação da atualização de ordem de categorias: " . $conn->error);
        }

        foreach ($ordem_categorias as $ordem => $categoria_id) {
            $stmtUpdateOrdem->bind_param("ii", $ordem, $categoria_id);
            if (!$stmtUpdateOrdem->execute()) {
                throw new Exception("Erro ao atualizar ordem da categoria ID " . $categoria_id . ": " . $stmtUpdateOrdem->error);
            }
        }
        $stmtUpdateOrdem->close();

        $conn->commit(); // Confirma a transação
        $mensagem = "<p class='mensagem sucesso'>Configurações do cardápio salvas com sucesso!</p>";

        // Atualiza as variáveis PHP para refletir as mudanças no formulário após o POST
        $nome_hamburgueria = htmlspecialchars($novo_nome);
        $horario_funcionamento = htmlspecialchars($novo_horario);
        $pedido_minimo = htmlspecialchars(number_format($novo_pedido_minimo, 2, ',', '.'));
        $taxa_entrega = htmlspecialchars(number_format($nova_taxa_entrega, 2, ',', '.')); // Atualiza a variável da taxa de entrega
        $hora_abertura = htmlspecialchars($nova_hora_abertura);
        $hora_fechamento = htmlspecialchars($nova_hora_fechamento);
        $dias_abertura_array = explode(',', $novos_dias_abertura);
        
        // Recarrega as categorias para garantir que a ordem exibida esteja correta
        $categorias = [];
        $sqlCategorias = "SELECT id, nome FROM categorias ORDER BY ordem ASC, nome ASC";
        $resultCategorias = $conn->query($sqlCategorias);
        if ($resultCategorias) {
            while ($cat = $resultCategorias->fetch_assoc()) {
                $categorias[] = $cat;
            }
        }

    } catch (Exception $e) {
        $conn->rollback(); // Reverte a transação em caso de erro
        $mensagem = "<p class='mensagem erro'>Erro ao salvar configurações: " . $e->getMessage() . "</p>";
    }
}

$conn->close();

// Inicia o buffer de saída para capturar o HTML desta página
ob_start();
?>

<style>
    /* Estilos específicos para esta página */
    .container-admin {
        padding: 20px;
    }
    .form-cardapio-config {
        background-color: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        max-width: 700px;
        margin: 30px auto;
    }
    .form-cardapio-config h1, .form-cardapio-config h2 {
        text-align: center;
        color: #333;
        margin-bottom: 25px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #495057;
    }
    .form-group input[type="text"],
    .form-group input[type="time"], /* Adicionado tipo time */
    .form-group input[type="number"],
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 1em;
        color: #333;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-group input:focus,
    .form-group textarea:focus {
        border-color: #28a745;
        outline: none;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }
    .botoes-form {
        display: flex;
        justify-content: center;
        margin-top: 30px;
    }
    .btn-salvar {
        background-color: #28a745;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-size: 1.1em;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .btn-salvar:hover {
        background-color: #218838;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
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

    /* Estilos para a lista de categorias arrastável (exemplo) */
    #lista-categorias-ordenavel {
        list-style: none;
        padding: 0;
        border: 1px solid #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
    }
    #lista-categorias-ordenavel li {
        background-color: #fff;
        margin: 5px;
        padding: 10px 15px;
        border: 1px solid #eee;
        border-radius: 5px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: grab;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: background-color 0.2s ease, box-shadow 0.2s ease;
    }
    #lista-categorias-ordenavel li:hover {
        background-color: #f0f0f0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    #lista-categorias-ordenavel li.dragging {
        opacity: 0.7;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    /* Estilos para os checkboxes de dias da semana */
    .dias-semana-group {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
        justify-content: center; /* Centraliza os checkboxes */
    }
    .dias-semana-group label {
        background-color: #f0f0f0;
        padding: 8px 12px;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.2s ease;
        display: flex;
        align-items: center;
        gap: 5px;
        font-weight: normal;
    }
    .dias-semana-group input[type="checkbox"] {
        margin-right: 5px;
        width: auto; /* Reseta a largura para checkboxes */
    }
    .dias-semana-group input[type="checkbox"]:checked + span {
        font-weight: bold;
        color: #28a745;
    }
    .dias-semana-group input[type="checkbox"]:checked + span::before {
        content: '\2713'; /* Checkmark */
        margin-right: 5px;
        color: #28a745;
    }
    .dias-semana-group label:hover {
        background-color: #e0e0e0;
    }
</style>

<div class="container-admin">
    <div class="form-cardapio-config">
        <h1>Gerenciar Cardápio</h1>

        <?= $mensagem ?>

        <form action="index.php" method="POST">
            <h2>Configurações Gerais da Loja</h2>
            <div class="form-group">
                <label for="nome_hamburgueria">Nome da Hamburgueria:</label>
                <input type="text" id="nome_hamburgueria" name="nome_hamburgueria" value="<?= htmlspecialchars($nome_hamburgueria) ?>" required>
            </div>
            <div class="form-group">
                <label for="horario_funcionamento">Descrição do Horário de Funcionamento (texto para clientes):</label>
                <input type="text" id="horario_funcionamento" name="horario_funcionamento" value="<?= htmlspecialchars($horario_funcionamento) ?>" placeholder="Ex: Abre hoje às 18h" required>
                <small>Este texto aparecerá no cardápio para os clientes.</small>
            </div>
            <div class="form-group">
                <label for="pedido_minimo">Pedido Mínimo (R$):</label>
                <input type="text" id="pedido_minimo" name="pedido_minimo" value="<?= htmlspecialchars($pedido_minimo) ?>" placeholder="Ex: R$ 10,00" required>
            </div>
            <div class="form-group">
                <label for="taxa_entrega">Taxa de Entrega (R$):</label>
                <input type="text" id="taxa_entrega" name="taxa_entrega" value="<?= htmlspecialchars($taxa_entrega) ?>" placeholder="Ex: R$ 5,00" required>
            </div>

            <h2>Horário de Funcionamento para Sistema</h2>
            <p>Defina o horário e os dias que a loja estará disponível para pedidos online. <br> (Isso determinará o status 'Loja Aberta/Fechada')</p>
            <div class="form-group">
                <label for="hora_abertura">Hora de Abertura:</label>
                <input type="time" id="hora_abertura" name="hora_abertura" value="<?= htmlspecialchars($hora_abertura) ?>" required>
            </div>
            <div class="form-group">
                <label for="hora_fechamento">Hora de Fechamento:</label>
                <input type="time" id="hora_fechamento" name="hora_fechamento" value="<?= htmlspecialchars($hora_fechamento) ?>" required>
            </div>
            <div class="form-group">
                <label>Dias de Funcionamento:</label>
                <div class="dias-semana-group">
                    <?php
                    $dias_da_semana = [
                        1 => 'Segunda-feira',
                        2 => 'Terça-feira',
                        3 => 'Quarta-feira',
                        4 => 'Quinta-feira',
                        5 => 'Sexta-feira',
                        6 => 'Sábado',
                        7 => 'Domingo'
                    ];
                    foreach ($dias_da_semana as $num_dia => $nome_dia) :
                        $checked = in_array($num_dia, $dias_abertura_array) ? 'checked' : '';
                    ?>
                        <label>
                            <input type="checkbox" name="dias_abertura[]" value="<?= $num_dia ?>" <?= $checked ?>>
                            <span><?= $nome_dia ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <h2>Ordem das Categorias no Cardápio</h2>
            <p>Arraste e solte as categorias para definir a ordem de exibição no cardápio principal.</p>
            <ul id="lista-categorias-ordenavel">
                <?php if (!empty($categorias)): ?>
                    <?php foreach ($categorias as $categoria): ?>
                        <li draggable="true" data-id="<?= htmlspecialchars($categoria['id']) ?>">
                            <span><?= htmlspecialchars($categoria['nome']) ?></span>
                            <i class="fas fa-grip-vertical"></i>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>Nenhuma categoria cadastrada.</li>
                <?php endif; ?>
            </ul>
            <input type="hidden" name="ordem_categorias_json" id="ordem_categorias_json">

            <div class="botoes-form">
                <button type="submit" class="btn-salvar">Salvar Configurações do Cardápio</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Script para funcionalidade de arrastar e soltar (Drag and Drop)
    document.addEventListener('DOMContentLoaded', () => {
        const lista = document.getElementById('lista-categorias-ordenavel');
        let draggingItem = null;

        if (lista) {
            lista.addEventListener('dragstart', (e) => {
                draggingItem = e.target.closest('li');
                if (draggingItem) {
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', draggingItem.innerHTML);
                    draggingItem.classList.add('dragging');
                }
            });

            lista.addEventListener('dragover', (e) => {
                e.preventDefault();
                const targetItem = e.target.closest('li');
                if (draggingItem && targetItem && draggingItem !== targetItem) {
                    const rect = targetItem.getBoundingClientRect();
                    const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
                    lista.insertBefore(draggingItem, next && targetItem.nextSibling || targetItem);
                }
            });

            lista.addEventListener('dragend', () => {
                if (draggingItem) {
                    draggingItem.classList.remove('dragging');
                    draggingItem = null;
                    updateCategoryOrder();
                }
            });

            // Função para atualizar o campo hidden com a nova ordem
            function updateCategoryOrder() {
                const ordemInput = document.getElementById('ordem_categorias_json');
                const idsOrdenados = Array.from(lista.children).map(li => li.dataset.id);
                ordemInput.value = JSON.stringify(idsOrdenados);
            }

            // Chama a função ao carregar a página para inicializar o campo hidden
            updateCategoryOrder();
        }
    });
</script>

<?php
// Captura o conteúdo do buffer e atribui à variável $page_content
$page_content = ob_get_clean();

// Inclui o template administrativo que contém o header, sidebar e footer
include __DIR__ . '/../template_admin.php';
?>
