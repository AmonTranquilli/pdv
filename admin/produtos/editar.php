<?php
session_start();
require_once '../../includes/conexao.php';

// Proteção e validação do ID do produto
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit();
}
$produto_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$produto_id) {
    $_SESSION['feedback_mensagem'] = "ID de produto inválido.";
    $_SESSION['feedback_sucesso'] = false;
    header("Location: produtos.php");
    exit();
}

$mensagem_feedback = '';
$sucesso_feedback = false;

// Lógica para ATUALIZAR o produto e seus adicionais (quando o formulário é enviado via POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Garante que o ID do produto ainda está definido para as operações
    $produto_id_post = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    if ($produto_id_post !== $produto_id) {
        // Redireciona se o ID for perdido ou manipulado no post
        header("Location: produtos.php");
        exit();
    }

    $conn->begin_transaction();
    try {
        // --- 1. Atualização dos dados básicos do produto ---
        $nome = trim($_POST['nome']);
        $descricao = trim($_POST['descricao']);
        // Converte o preço de formato brasileiro (10,50) para formato de banco de dados (10.50)
        $preco_raw = str_replace('.', '', $_POST['preco'] ?? '0');
        $preco_raw = str_replace(',', '.', $preco_raw);
        $preco = filter_var($preco_raw, FILTER_VALIDATE_FLOAT);
        
        $id_categoria_post = filter_var($_POST['id_categoria'], FILTER_VALIDATE_INT);
        $controla_estoque = isset($_POST['controla_estoque']) ? 1 : 0;
        $estoque_post = filter_var($_POST['estoque'], FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
        $ativo_post = isset($_POST['ativo']) ? 1 : 0;
        $imagem_atual = $_POST['imagem_atual_hidden'] ?? '';
        $caminho_imagem_db = $imagem_atual;

        if (empty($nome) || $preco === false) { throw new Exception("Nome e Preço são obrigatórios e devem ser válidos."); }

        // Lógica de Upload de nova imagem
        if (isset($_FILES['nova_imagem']) && $_FILES['nova_imagem']['error'] == UPLOAD_ERR_OK) {
            $diretorio_uploads = '../../public/uploads/produtos/';
            if (!is_dir($diretorio_uploads)) { mkdir($diretorio_uploads, 0777, true); }
            $nome_arquivo = uniqid() . '_' . basename($_FILES['nova_imagem']['name']);
            $caminho_completo = $diretorio_uploads . $nome_arquivo;
            $caminho_imagem_db_nova = '/pdv/public/uploads/produtos/' . $nome_arquivo;
            if (move_uploaded_file($_FILES['nova_imagem']['tmp_name'], $caminho_completo)) {
                // Apaga a imagem antiga do servidor se o upload da nova deu certo
                if (!empty($imagem_atual) && file_exists(str_replace('/pdv/', '../../', $imagem_atual))) {
                    @unlink(str_replace('/pdv/', '../../', $imagem_atual));
                }
                $caminho_imagem_db = $caminho_imagem_db_nova;
            }
        }
        
        $estoque_para_db = $controla_estoque ? $estoque_post : 0;
        
        $stmt_update_prod = $conn->prepare("UPDATE produtos SET nome = ?, descricao = ?, preco = ?, id_categoria = ?, imagem = ?, estoque = ?, controla_estoque = ?, ativo = ? WHERE id = ?");
        $id_categoria_param = ($id_categoria_post > 0) ? $id_categoria_post : NULL;
        $stmt_update_prod->bind_param("ssdisiiii", $nome, $descricao, $preco, $id_categoria_param, $caminho_imagem_db, $estoque_para_db, $controla_estoque, $ativo_post, $produto_id);
        if (!$stmt_update_prod->execute()) { throw new Exception("Erro ao atualizar dados do produto."); }
        $stmt_update_prod->close();

        // --- 2. Atualização das associações de adicionais ---
        $adicionais_selecionados = $_POST['adicionais'] ?? [];

        // Limpa as associações antigas
        $stmt_delete_assoc = $conn->prepare("DELETE FROM produto_adicional WHERE id_produto = ?");
        $stmt_delete_assoc->bind_param("i", $produto_id);
        $stmt_delete_assoc->execute();
        $stmt_delete_assoc->close();

        // Insere as novas associações
        if (!empty($adicionais_selecionados)) {
            $stmt_insert_assoc = $conn->prepare("INSERT INTO produto_adicional (id_produto, id_adicional) VALUES (?, ?)");
            foreach ($adicionais_selecionados as $adicional_id_selecionado) {
                $stmt_insert_assoc->bind_param("ii", $produto_id, $adicional_id_selecionado);
                $stmt_insert_assoc->execute();
            }
            $stmt_insert_assoc->close();
        }

        $conn->commit();
        $sucesso_feedback = true;
        $mensagem_feedback = "Produto atualizado com sucesso!";

    } catch (Exception $e) {
        $conn->rollback();
        $sucesso_feedback = false;
        $mensagem_feedback = "Erro ao atualizar: " . $e->getMessage();
    }
}

// Lógica para CARREGAR os dados da página para exibição
try {
    $stmt_produto = $conn->prepare("SELECT * FROM produtos WHERE id = ?");
    $stmt_produto->bind_param("i", $produto_id);
    $stmt_produto->execute();
    $result_produto = $stmt_produto->get_result();
    $produto = $result_produto->fetch_assoc();
    $stmt_produto->close();
    if (!$produto) { throw new Exception("Produto não encontrado."); }

    $categorias = $conn->query("SELECT id, nome FROM categorias ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
    $todos_adicionais = $conn->query("SELECT id, nome FROM adicionais WHERE ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);

    $stmt_assoc = $conn->prepare("SELECT id_adicional FROM produto_adicional WHERE id_produto = ?");
    $stmt_assoc->bind_param("i", $produto_id);
    $stmt_assoc->execute();
    $result_assoc = $stmt_assoc->get_result();
    $adicionais_associados_ids = [];
    while ($row = $result_assoc->fetch_assoc()) {
        $adicionais_associados_ids[] = $row['id_adicional'];
    }
    $stmt_assoc->close();

} catch (Exception $e) {
    $_SESSION['feedback_mensagem'] = $e->getMessage();
    $_SESSION['feedback_sucesso'] = false;
    header("Location: produtos.php");
    exit();
}

$conn->close();
$page_title = 'Editar Produto';
ob_start();
?>

<!-- Estilos para as Abas e o Grid de Checkboxes -->
<style>
    .tabs-nav {
        display: flex;
        border-bottom: 2px solid #dee2e6;
        margin-bottom: 20px;
    }
    .tab-button {
        padding: 10px 20px;
        cursor: pointer;
        background-color: transparent;
        border: none;
        font-size: 1em;
        font-weight: 500;
        color: #6c757d;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px; /* Alinha com a borda inferior */
    }
    .tab-button.active {
        font-weight: 700;
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
    }
    .tab-content {
        display: none;
        animation: fadeIn 0.5s;
    }
    .tab-content.active {
        display: block;
    }
    .section-divider {
        border-top: 1px solid #eee;
        margin-top: 25px;
        padding-top: 25px;
    }
    .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 15px;
    }
    .checkbox-grid .form-check {
        background-color: #f8f9fa;
        padding: 10px 15px;
        border-radius: 5px;
        border: 1px solid #dee2e6;
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
</style>

<div class="header-controls">
    <h1><i class="fas fa-edit"></i> Editar Produto: <?= htmlspecialchars($produto['nome']); ?></h1>
    <a href="produtos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="page-content">
    <?php if ($mensagem_feedback): ?>
        <p class="message-<?= $sucesso_feedback ? 'success' : 'error'; ?>"><?= htmlspecialchars($mensagem_feedback); ?></p>
    <?php endif; ?>

    <!-- Abas de Navegação -->
    <div class="tabs-nav">
        <button type="button" class="tab-button" data-tab="dadosProduto">Dados do Produto</button>
        <button type="button" class="tab-button" data-tab="adicionaisOpcoes">Adicionais e Opções</button>
    </div>

    <form action="editar.php?id=<?= $produto_id; ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="produto_id" value="<?= $produto_id; ?>">
        <input type="hidden" name="imagem_atual_hidden" value="<?= htmlspecialchars($produto['imagem'] ?? ''); ?>">

        <!-- Conteúdo da Aba 1: Dados do Produto -->
        <div id="dadosProduto" class="tab-content">
            <div class="form-row">
                <div class="form-group">
                    <label for="nome">Nome do Produto:</label>
                    <input type="text" id="nome" name="nome" required value="<?= htmlspecialchars($produto['nome']); ?>">
                </div>
                <div class="form-group">
                    <label for="preco">Preço (R$):</label>
                    <input type="text" id="preco" name="preco" required value="<?= number_format($produto['preco'], 2, ',', '.'); ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="descricao">Descrição:</label>
                <textarea id="descricao" name="descricao" rows="4"><?= htmlspecialchars($produto['descricao']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="id_categoria">Categoria:</label>
                <select id="id_categoria" name="id_categoria">
                    <option value="">-- Sem Categoria --</option>
                    <?php foreach ($categorias as $cat) : ?>
                        <option value="<?= $cat['id']; ?>" <?= ($produto['id_categoria'] == $cat['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($cat['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div class="form-group">
                <label>Imagem Atual:</label>
                <?php if (!empty($produto['imagem'])): ?>
                    <img src="<?= htmlspecialchars($produto['imagem']); ?>" alt="Imagem atual" style="max-width: 100px; height: auto; display: block; margin-top: 5px; border-radius: 5px;">
                <?php else: ?>
                    <p>Nenhuma imagem cadastrada.</p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="nova_imagem">Alterar Imagem:</label>
                <input type="file" id="nova_imagem" name="nova_imagem" accept="image/*">
            </div>
             <div class="form-row">
                <div class="form-group form-check">
                    <input type="checkbox" id="controla_estoque" name="controla_estoque" value="1" <?= ($produto['controla_estoque']) ? 'checked' : ''; ?>>
                    <label for="controla_estoque">Controlar Estoque</label>
                </div>
                <div class="form-group" id="estoque_group" style="<?= ($produto['controla_estoque']) ? 'display:block;' : 'display:none;'; ?>">
                    <label for="estoque">Quantidade em Estoque:</label>
                    <input type="number" id="estoque" name="estoque" min="0" value="<?= htmlspecialchars($produto['estoque']); ?>">
                </div>
            </div>
            <div class="form-group form-check">
                 <input type="checkbox" id="ativo" name="ativo" value="1" <?= $produto['ativo'] ? 'checked' : ''; ?>>
                <label for="ativo">Produto Ativo</label>
            </div>
        </div>

        <!-- Conteúdo da Aba 2: Adicionais e Opções -->
        <div id="adicionaisOpcoes" class="tab-content">
            <div class="section-divider">
                <h2>Adicionais Opcionais</h2>
                <p>Marque os extras que o cliente poderá adicionar a este produto.</p>
                <div class="form-group checkbox-grid">
                    <?php if(empty($todos_adicionais)): ?>
                         <p>Nenhum adicional cadastrado. <a href="../adicionais/adicionar_adicional.php">Crie um agora</a>.</p>
                    <?php else: ?>
                        <?php foreach ($todos_adicionais as $adicional): ?>
                            <div class="form-check">
                                <input type="checkbox" name="adicionais[]" value="<?= $adicional['id']; ?>" id="adicional_<?= $adicional['id']; ?>"
                                    <?= in_array($adicional['id'], $adicionais_associados_ids) ? 'checked' : '' ?>>
                                <label for="adicional_<?= $adicional['id']; ?>"><?= htmlspecialchars($adicional['nome']); ?></label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-divider">
                <h2>Grupos de Opções (Combos)</h2>
                <p>Crie grupos de escolha para este produto. (Funcionalidade em breve)</p>
                <button type="button" class="btn btn-secondary" disabled><i class="fas fa-plus"></i> Adicionar Grupo</button>
            </div>
        </div>

        <div class="action-buttons" style="justify-content: center; margin-top: 30px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Alterações</button>
        </div>
    </form>
</div>

<!-- JavaScript para Abas e Estoque -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    function openTab(tabId) {
        tabContents.forEach(content => content.classList.remove('active'));
        tabs.forEach(tab => tab.classList.remove('active'));
        
        document.getElementById(tabId).classList.add('active');
        document.querySelector(`[data-tab='${tabId}']`).classList.add('active');
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', (event) => {
            // Previne o comportamento padrão do botão, que poderia submeter o formulário
            event.preventDefault(); 
            openTab(event.currentTarget.dataset.tab);
        });
    });

    // Abre a primeira aba por padrão
    openTab('dadosProduto');

    // Lógica do Estoque
    const controlaEstoqueCheckbox = document.getElementById('controla_estoque');
    const estoqueGroup = document.getElementById('estoque_group');
    if(controlaEstoqueCheckbox) {
        controlaEstoqueCheckbox.addEventListener('change', function() {
            estoqueGroup.style.display = this.checked ? 'block' : 'none';
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();
include '../template_admin.php';
?>
