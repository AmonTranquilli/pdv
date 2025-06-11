<?php
// Versão Completa e Final - 11/06/2025
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Lógica de ATUALIZAÇÃO (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['produto_id'])) {
    $conn->begin_transaction();
    try {
        // 1. Atualização dos dados básicos do produto
        $nome = trim($_POST['nome']);
        $descricao = trim($_POST['descricao']);
        $preco_raw = str_replace('.', '', $_POST['preco'] ?? '0');
        $preco_raw = str_replace(',', '.', $preco_raw);
        $preco = filter_var($preco_raw, FILTER_VALIDATE_FLOAT);
        $id_categoria_post = filter_var($_POST['id_categoria'], FILTER_VALIDATE_INT);
        $controla_estoque = isset($_POST['controla_estoque']) ? 1 : 0;
        $estoque_post = filter_var($_POST['estoque'], FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
        $ativo_post = isset($_POST['ativo']) ? 1 : 0;
        $max_adicionais = filter_var($_POST['max_adicionais_opcionais'], FILTER_VALIDATE_INT, ['options' => ['default' => 10]]);
        $imagem_atual = $_POST['imagem_atual_hidden'] ?? '';
        $caminho_imagem_db = $imagem_atual;

        if (empty($nome) || $preco === false) { throw new Exception("Nome e Preço são obrigatórios e devem ser válidos."); }

        if (isset($_FILES['nova_imagem']) && $_FILES['nova_imagem']['error'] == UPLOAD_ERR_OK) {
            $diretorio_uploads = '../../public/uploads/produtos/';
            if (!is_dir($diretorio_uploads)) { mkdir($diretorio_uploads, 0777, true); }
            $nome_arquivo = uniqid() . '_' . basename($_FILES['nova_imagem']['name']);
            $caminho_completo = $diretorio_uploads . $nome_arquivo;
            $caminho_imagem_db_nova = '/pdv/public/uploads/produtos/' . $nome_arquivo;
            if (move_uploaded_file($_FILES['nova_imagem']['tmp_name'], $caminho_completo)) {
                if (!empty($imagem_atual) && file_exists(str_replace('/pdv/', '../../', $imagem_atual))) {
                    @unlink(str_replace('/pdv/', '../../', $imagem_atual));
                }
                $caminho_imagem_db = $caminho_imagem_db_nova;
            }
        }
        
        $estoque_para_db = $controla_estoque ? $estoque_post : 0;
        
        $stmt_update_prod = $conn->prepare("UPDATE produtos SET nome = ?, descricao = ?, preco = ?, id_categoria = ?, imagem = ?, estoque = ?, controla_estoque = ?, ativo = ?, max_adicionais_opcionais = ? WHERE id = ?");
        $id_categoria_param = ($id_categoria_post > 0) ? $id_categoria_post : NULL;
        $stmt_update_prod->bind_param("ssdisiiisi", $nome, $descricao, $preco, $id_categoria_param, $caminho_imagem_db, $estoque_para_db, $controla_estoque, $ativo_post, $max_adicionais, $produto_id);
        if (!$stmt_update_prod->execute()) { throw new Exception("Erro ao atualizar dados do produto."); }
        $stmt_update_prod->close();

        // 2. Atualização das associações de adicionais
        $adicionais_selecionados = $_POST['adicionais'] ?? [];
        $stmt_delete_assoc = $conn->prepare("DELETE FROM produto_adicional WHERE id_produto = ?");
        $stmt_delete_assoc->bind_param("i", $produto_id);
        $stmt_delete_assoc->execute();
        $stmt_delete_assoc->close();
        if (!empty($adicionais_selecionados)) {
            $stmt_insert_assoc = $conn->prepare("INSERT INTO produto_adicional (id_produto, id_adicional) VALUES (?, ?)");
            foreach ($adicionais_selecionados as $adicional_id_selecionado) {
                $stmt_insert_assoc->bind_param("ii", $produto_id, $adicional_id_selecionado);
                $stmt_insert_assoc->execute();
            }
            $stmt_insert_assoc->close();
        }

        $conn->commit();
        $_SESSION['feedback_mensagem'] = "Produto atualizado com sucesso!";
        $_SESSION['feedback_sucesso'] = true;
        header("Location: editar.php?id=" . $produto_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $mensagem_feedback = "Erro ao atualizar: " . $e->getMessage();
        $sucesso_feedback = false;
    }
}

// Lógica para CARREGAR todos os dados da página
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

    $adicionais_associados_ids = [];
    $stmt_assoc = $conn->prepare("SELECT id_adicional FROM produto_adicional WHERE id_produto = ?");
    $stmt_assoc->bind_param("i", $produto_id);
    $stmt_assoc->execute();
    $result_assoc = $stmt_assoc->get_result();
    while ($row = $result_assoc->fetch_assoc()) {
        $adicionais_associados_ids[] = $row['id_adicional'];
    }
    $stmt_assoc->close();

    $grupos_opcoes = [];
    $stmt_grupos = $conn->prepare("SELECT * FROM grupos_opcoes WHERE id_produto_pai = ? ORDER BY nome_grupo ASC");
    $stmt_grupos->bind_param("i", $produto_id);
    $stmt_grupos->execute();
    $result_grupos = $stmt_grupos->get_result();
    
    while($grupo = $result_grupos->fetch_assoc()){
        $id_grupo = $grupo['id'];
        $grupo['itens'] = [];
        // A consulta agora inclui o novo campo 'tipo'
        $stmt_itens = $conn->prepare("SELECT ig.*, p.nome as nome_produto_vinculado FROM itens_grupo ig LEFT JOIN produtos p ON ig.id_produto_vinculado = p.id WHERE ig.id_grupo_opcao = ? ORDER BY ig.nome_item ASC");
        $stmt_itens->bind_param("i", $id_grupo);
        $stmt_itens->execute();
        $result_itens = $stmt_itens->get_result();
        while($item = $result_itens->fetch_assoc()){
            // Se o item for do tipo COMBO, buscamos seus componentes
            if ($item['tipo'] === 'COMBO') {
                $stmt_componentes = $conn->prepare("SELECT p.nome, igc.quantidade FROM itens_grupo_combo igc JOIN produtos p ON p.id = igc.id_produto_componente WHERE igc.id_item_grupo = ?");
                $stmt_componentes->bind_param("i", $item['id']);
                $stmt_componentes->execute();
                $item['componentes'] = $stmt_componentes->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_componentes->close();
            }
            $grupo['itens'][] = $item;
        }
        $stmt_itens->close();
        $grupos_opcoes[] = $grupo;
    }
    $stmt_grupos->close();

} catch (Exception $e) {
    $_SESSION['feedback_mensagem'] = $e->getMessage();
    $_SESSION['feedback_sucesso'] = false;
    header("Location: produtos.php");
    exit();
}

if (isset($_SESSION['feedback_mensagem'])) {
    $mensagem_feedback = $_SESSION['feedback_mensagem'];
    $sucesso_feedback = $_SESSION['feedback_sucesso'] ?? false;
    unset($_SESSION['feedback_mensagem'], $_SESSION['feedback_sucesso']);
}

$conn->close();
$page_title = 'Editar Produto';
ob_start();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .tabs-nav { display: flex; border-bottom: 2px solid #dee2e6; margin-bottom: 20px; }
    .tab-button { padding: 10px 20px; cursor: pointer; background: transparent; border: none; font-size: 1em; font-weight: 500; color: #6c757d; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all 0.2s ease-in-out; }
    .tab-button:hover { color: var(--primary-color); }
    .tab-button.active { font-weight: 700; color: var(--primary-color); border-bottom-color: var(--primary-color); }
    .tab-content { display: none; animation: fadeIn 0.4s; }
    .tab-content.active { display: block; }
    .section-divider { border-top: 1px solid #eee; margin-top: 25px; padding-top: 25px; }
    .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; }
    .checkbox-grid .form-check { background-color: #f8f9fa; padding: 10px 15px; border-radius: 5px; border: 1px solid #dee2e6; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .grupo-container { background: #fdfdfd; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 15px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
    .grupo-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 15px; margin-bottom: 15px; border-bottom: 1px solid #e9ecef; }
    .grupo-header h3 { margin: 0; font-size: 1.25em; }
    .grupo-info { font-size: 0.85em; color: #6c757d; margin-top: 5px; }
    .grupo-botoes-acao { display: flex; gap: 10px; }
    .grupo-itens ul { list-style: none; padding-left: 0; margin: 0; }
    .grupo-itens li { padding: 8px 12px; border-bottom: 1px dashed #e9ecef; display: flex; justify-content: space-between; align-items: center; }
    .grupo-itens li:last-child { border-bottom: none; }
    .item-tag { background-color: #e2f3ff; color: #0c5460; padding: 2px 6px; font-size: 0.75em; border-radius: 4px; margin-left: 8px; }
    .item-tag-combo { background-color: #d4edda; color: #155724; }
    .btn-apagar-item { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 0.9em; padding: 4px; }
    .btn-apagar-item:hover { color: #a71d2a; }
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; display: none; justify-content: center; align-items: center; }
    .modal-overlay.visible { display: flex; }
    .modal-content { background: #fff; padding: 30px; border-radius: 8px; width: 90%; max-width: 700px; position: relative; }
    .modal-close-btn { position: absolute; top: 10px; right: 15px; font-size: 1.8rem; cursor: pointer; border: none; background: none; color: #888; }
    .select2-container--default .select2-selection--single { height: calc(2.25rem + 2px); padding: .375rem .75rem; border: 1px solid #ced4da; line-height: 1.5; }
    .select2-container { width: 100% !important; }
    .form-group-toggle { display: none; }
    .form-group-toggle.visible { display: block; animation: fadeIn 0.5s; }
    .componentes-list { list-style: none; padding: 10px; margin-top: 15px; background: #f8f9fa; border-radius: 5px; max-height: 150px; overflow-y: auto; }
    .componentes-list li { background: #fff; padding: 8px; border-radius: 4px; margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #ddd;}
</style>

<div class="header-controls">
    <h1><i class="fas fa-edit"></i> Editar Produto: <?= htmlspecialchars($produto['nome']); ?></h1>
    <a href="produtos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>
<div class="page-content">
    <?php if ($mensagem_feedback): ?><p class="message-<?= $sucesso_feedback ? 'success' : 'error'; ?>"><?= htmlspecialchars($mensagem_feedback); ?></p><?php endif; ?>
    <div class="tabs-nav">
        <button type="button" class="tab-button active" data-tab="dadosProduto">Dados do Produto</button>
        <button type="button" class="tab-button" data-tab="adicionaisOpcoes">Adicionais e Opções</button>
    </div>
    <form action="editar.php?id=<?= $produto_id; ?>" method="POST" enctype="multipart/form-data" class="form-container">
        <input type="hidden" name="produto_id" value="<?= $produto_id; ?>">
        <div id="dadosProduto" class="tab-content active">
            <input type="hidden" name="imagem_atual_hidden" value="<?= htmlspecialchars($produto['imagem'] ?? ''); ?>">
            <div class="form-row">
                <div class="form-group"><label for="nome">Nome do Produto:</label><input type="text" id="nome" name="nome" required value="<?= htmlspecialchars($produto['nome']); ?>"></div>
                <div class="form-group"><label for="preco">Preço (R$):</label><input type="text" id="preco" name="preco" required value="<?= number_format($produto['preco'], 2, ',', '.'); ?>"></div>
            </div>
            <div class="form-group"><label for="max_adicionais_opcionais">Máximo de Adicionais Opcionais:</label><input type="number" id="max_adicionais_opcionais" name="max_adicionais_opcionais" min="0" value="<?= htmlspecialchars($produto['max_adicionais_opcionais'] ?? 10); ?>"><small>Define quantos adicionais (fora dos grupos) o cliente pode escolher. Use 0 para ilimitado.</small></div>
            <div class="form-group"><label for="descricao">Descrição:</label><textarea id="descricao" name="descricao" rows="4"><?= htmlspecialchars($produto['descricao']); ?></textarea></div>
            <div class="form-group"><label for="id_categoria">Categoria:</label><select id="id_categoria" name="id_categoria"><option value="">-- Sem Categoria --</option><?php foreach ($categorias as $cat) : ?><option value="<?= $cat['id']; ?>" <?= ($produto['id_categoria'] == $cat['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($cat['nome']); ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Imagem Atual:</label><?php if (!empty($produto['imagem'])): ?><img src="<?= htmlspecialchars($produto['imagem']); ?>" alt="Imagem atual" style="max-width: 100px; height: auto; display: block; margin-top: 5px; border-radius: 5px;"><?php else: ?><p>Nenhuma imagem cadastrada.</p><?php endif; ?></div>
            <div class="form-group"><label for="nova_imagem">Alterar Imagem:</label><input type="file" id="nova_imagem" name="nova_imagem" accept="image/*"></div>
            <div class="form-row">
                <div class="form-group form-check"><input type="checkbox" id="controla_estoque" name="controla_estoque" value="1" <?= ($produto['controla_estoque']) ? 'checked' : ''; ?>><label for="controla_estoque">Controlar Estoque</label></div>
                <div class="form-group" id="estoque_group" style="<?= ($produto['controla_estoque']) ? 'display:block;' : 'display:none;'; ?>"><label for="estoque">Quantidade em Estoque:</label><input type="number" id="estoque" name="estoque" min="0" value="<?= htmlspecialchars($produto['estoque']); ?>"></div>
            </div>
            <div class="form-group form-check"><input type="checkbox" id="ativo" name="ativo" value="1" <?= $produto['ativo'] ? 'checked' : ''; ?>><label for="ativo">Produto Ativo</label></div>
        </div>
        <div id="adicionaisOpcoes" class="tab-content">
            <div class="section-divider">
                <h2>Adicionais Opcionais</h2><p>Marque os extras que o cliente poderá adicionar a este produto.</p>
                <div class="form-group checkbox-grid"><?php if(empty($todos_adicionais)): ?><p>Nenhum adicional cadastrado. <a href="../adicionais/adicionar_adicional.php">Crie um agora</a>.</p><?php else: ?><?php foreach ($todos_adicionais as $adicional): ?><div class="form-check"><input type="checkbox" name="adicionais[]" value="<?= $adicional['id']; ?>" id="adicional_<?= $adicional['id']; ?>" <?= in_array($adicional['id'], $adicionais_associados_ids) ? 'checked' : '' ?>><label for="adicional_<?= $adicional['id']; ?>"><?= htmlspecialchars($adicional['nome']); ?></label></div><?php endforeach; ?><?php endif; ?></div>
            </div>
            <div class="section-divider">
                <h2>Grupos de Opções</h2>
                <div id="lista-grupos-container"><?php if (empty($grupos_opcoes)): ?><p id="mensagem-sem-grupos">Este produto ainda não tem grupos de opções.</p><?php else: ?><?php foreach ($grupos_opcoes as $grupo): ?><div class="grupo-container" id="grupo-container-<?= $grupo['id'] ?>"><div class="grupo-header"><div><h3 id="nome-grupo-<?= $grupo['id'] ?>"><?= htmlspecialchars($grupo['nome_grupo']) ?></h3><div class="grupo-info" id="info-grupo-<?= $grupo['id'] ?>">(<?= ucfirst(strtolower($grupo['tipo_selecao'])) ?> | Mín: <?= $grupo['min_opcoes'] ?> | Máx: <?= $grupo['max_opcoes'] ?>)</div></div><div class="grupo-botoes-acao"><button type="button" class="btn btn-success btn-sm btn-adicionar-item" data-id-grupo="<?= $grupo['id'] ?>"><i class="fas fa-plus"></i> Item</button><button type="button" class="btn btn-secondary btn-sm btn-editar-grupo" data-id-grupo="<?= $grupo['id'] ?>" data-nome-grupo="<?= htmlspecialchars($grupo['nome_grupo']) ?>" data-tipo-selecao="<?= $grupo['tipo_selecao'] ?>" data-min-opcoes="<?= $grupo['min_opcoes'] ?>" data-max-opcoes="<?= $grupo['max_opcoes'] ?>"><i class="fas fa-edit"></i> Editar</button><button type="button" class="btn btn-danger btn-sm btn-apagar-grupo" data-id-grupo="<?= $grupo['id'] ?>"><i class="fas fa-trash"></i> Apagar</button></div></div><div class="grupo-itens"><ul id="lista-itens-grupo-<?= $grupo['id'] ?>"><?php if(empty($grupo['itens'])): ?><li id="mensagem-sem-itens-<?= $grupo['id'] ?>">Nenhum item neste grupo ainda.</li><?php else: ?><?php foreach($grupo['itens'] as $item_opcao): ?><li id="item-opcao-<?= $item_opcao['id'] ?>"><span><?= htmlspecialchars($item_opcao['nome_item']) ?><?php if($item_opcao['tipo'] === 'VINCULADO'): ?><span class="item-tag">Estoque Vinculado</span><?php elseif($item_opcao['tipo'] === 'COMBO'): ?><span class="item-tag item-tag-combo">Combo</span><?php endif; ?></span><span>+ R$ <?= number_format($item_opcao['preco_adicional'], 2, ',', '.') ?><button type="button" class="btn-apagar-item" data-id-item="<?= $item_opcao['id'] ?>"><i class="fas fa-times-circle"></i></button></span></li><?php endforeach; ?><?php endif; ?></ul></div></div><?php endforeach; ?><?php endif; ?></div><br><button type="button" id="btnAdicionarGrupo" class="btn btn-info"><i class="fas fa-plus"></i> Adicionar Novo Grupo de Opções</button>
            </div>
        </div>
        <div class="action-buttons" style="justify-content: center; margin-top: 30px;"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Todas as Alterações</button></div>
    </form>
</div>
<div id="modalNovoGrupo" class="modal-overlay"> <div class="modal-content"> <button type="button" class="modal-close-btn" data-modal-id="modalNovoGrupo">&times;</button> <h2>Adicionar Novo Grupo de Opções</h2> <form id="formNovoGrupo"> <div class="form-group"><label for="nome_grupo">Nome do Grupo:</label><input type="text" id="nome_grupo" name="nome_grupo" class="form-control" required placeholder="Ex: Escolha sua Bebida"></div><div class="form-row"><div class="form-group"><label for="tipo_selecao">Tipo de Seleção:</label><select id="tipo_selecao" name="tipo_selecao" class="form-control"><option value="UNICO">Escolha Única</option><option value="MULTIPLO">Escolha Múltipla</option></select></div> </div><div class="form-row"><div class="form-group"><label for="min_opcoes">Mínimo de Opções:</label><input type="number" id="min_opcoes" name="min_opcoes" class="form-control" value="0" min="0"><small>0 para opcional.</small></div><div class="form-group"><label for="max_opcoes">Máximo de Opções:</label><input type="number" id="max_opcoes" name="max_opcoes" class="form-control" value="1" min="1"></div></div><div class="action-buttons"><button type="submit" class="btn btn-success">Criar Grupo</button></div></form></div></div>
<div id="modalEditarGrupo" class="modal-overlay"> <div class="modal-content"> <button type="button" class="modal-close-btn" data-modal-id="modalEditarGrupo">&times;</button> <h2>Editar Grupo de Opções</h2> <form id="formEditarGrupo"> <input type="hidden" id="id_grupo_edit" name="id_grupo"> <div class="form-group"><label for="nome_grupo_edit">Nome do Grupo:</label><input type="text" id="nome_grupo_edit" name="nome_grupo" class="form-control" required></div><div class="form-row"><div class="form-group"><label for="tipo_selecao_edit">Tipo de Seleção:</label><select id="tipo_selecao_edit" name="tipo_selecao" class="form-control"><option value="UNICO">Escolha Única</option><option value="MULTIPLO">Escolha Múltipla</option></select></div></div><div class="form-row"><div class="form-group"><label for="min_opcoes_edit">Mínimo de Opções:</label><input type="number" id="min_opcoes_edit" name="min_opcoes" class="form-control" value="0" min="0"></div><div class="form-group"><label for="max_opcoes_edit">Máximo de Opções:</label><input type="number" id="max_opcoes_edit" name="max_opcoes" class="form-control" value="1" min="1"></div></div><div class="action-buttons"><button type="submit" class="btn btn-primary">Salvar Alterações</button></div></form></div></div>
<div id="modalNovoItem" class="modal-overlay"> <div class="modal-content"> <button type="button" class="modal-close-btn" data-modal-id="modalNovoItem">&times;</button> <h2>Adicionar Novo Item ao Grupo</h2> <form id="formNovoItem"><input type="hidden" id="id_grupo_opcao_item" name="id_grupo_opcao"><div class="form-group"><label>Tipo de Item</label><div><input type="radio" id="tipoItemSimples" name="tipo_item" value="SIMPLES" checked> <label for="tipoItemSimples">Opção Simples</label><input type="radio" id="tipoItemVinculado" name="tipo_item" value="VINCULADO" style="margin-left: 15px;"> <label for="tipoItemVinculado">Vincular Produto</label><input type="radio" id="tipoItemCombo" name="tipo_item" value="COMBO" style="margin-left: 15px;"> <label for="tipoItemCombo">Criar Opção de Combo</label></div></div><div id="containerItemSimples" class="form-group-toggle visible"><div class="form-group"><label for="nome_item_simples">Nome da Opção:</label><input type="text" id="nome_item_simples" name="nome_item" class="form-control" placeholder="Ex: Mal passado, Sem cebola"></div></div><div id="containerItemVinculado" class="form-group-toggle"><div class="form-row"><div class="form-group"><label for="categoria_item_select">1. Categoria:</label><select id="categoria_item_select" class="form-control categoria-select" data-target="#produto_vinculado_select"><option value="">-- Selecione --</option><?php foreach ($categorias as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="produto_vinculado_select">2. Produto:</label><select id="produto_vinculado_select" name="id_produto_vinculado" class="form-control produto-select" style="width:100%;" disabled><option value="">-- Aguardando categoria --</option></select></div></div></div><div id="containerItemCombo" class="form-group-toggle"><div class="form-group"><label for="nome_item_combo">Nome da Opção de Combo:</label><input type="text" id="nome_item_combo" class="form-control" placeholder="Ex: Combo Fritas + Refri"></div><hr><h4>Componentes do Combo</h4><div class="form-row"><div class="form-group"><label>1. Categoria do Componente:</label><select class="form-control categoria-select" data-target="#produto_componente_select"><option value="">-- Selecione --</option><?php foreach ($categorias as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>2. Produto Componente:</label><select id="produto_componente_select" class="form-control produto-select" disabled><option value="">-- Aguardando categoria --</option></select></div></div><ul id="lista_componentes_combo" class="componentes-list"></ul></div><div class="form-group"><label for="preco_adicional_item">Preço Adicional da Opção (R$):</label><input type="text" id="preco_adicional_item" name="preco_adicional" class="form-control" value="0,00"></div><div class="action-buttons"><button type="submit" class="btn btn-success">Adicionar Item</button></div></form></div></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // FUNÇÃO AUXILIAR PARA CHAMADAS API
    async function apiCall(action, data) {
        const response = await fetch('/pdv/public/api/grupos_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action, ...data })
        });
        return response.json();
    }

    // LÓGICA DAS ABAS
    const tabs = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    function openTab(tabId) {
        tabContents.forEach(c => c.classList.remove('active'));
        tabs.forEach(t => t.classList.remove('active'));
        document.getElementById(tabId)?.classList.add('active');
        document.querySelector(`[data-tab='${tabId}']`)?.classList.add('active');
    }
    const activeTab = sessionStorage.getItem('activeTab') || 'dadosProduto';
    openTab(activeTab);
    tabs.forEach(tab => tab.addEventListener('click', e => {
        const tabId = e.currentTarget.dataset.tab;
        openTab(tabId);
        sessionStorage.setItem('activeTab', tabId);
    }));

    // LÓGICA GERAL DOS MODAIS
    function toggleModal(modalId, show) {
        document.getElementById(modalId)?.classList.toggle('visible', show);
    }
    document.querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => toggleModal(btn.dataset.modalId, false)));
    document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) toggleModal(m.id, false) }));
    
    // LÓGICA PARA CONTROLAR VISIBILIDADE DE ESTOQUE INDIVIDUAL
    const controlaEstoqueCheck = document.getElementById('controla_estoque');
    const estoqueGroup = document.getElementById('estoque_group');
    if(controlaEstoqueCheck && estoqueGroup){
        controlaEstoqueCheck.addEventListener('change', () => {
            estoqueGroup.style.display = controlaEstoqueCheck.checked ? 'block' : 'none';
        });
    }

    // --- GERENCIAMENTO DE GRUPOS E ITENS (LÓGICA COMPLETA RESTAURADA) ---
    const formNovoGrupo = document.getElementById('formNovoGrupo');
    const formEditarGrupo = document.getElementById('formEditarGrupo');
    const formNovoItem = document.getElementById('formNovoItem');

    document.getElementById('btnAdicionarGrupo')?.addEventListener('click', () => {
        if(formNovoGrupo) formNovoGrupo.reset();
        toggleModal('modalNovoGrupo', true);
    });

    if(formNovoGrupo){
        formNovoGrupo.addEventListener('submit', async function(e) {
            e.preventDefault();
            const dados = {
                id_produto_pai: <?= $produto_id; ?>,
                nome_grupo: this.elements.nome_grupo.value,
                tipo_selecao: this.elements.tipo_selecao.value,
                min_opcoes: this.elements.min_opcoes.value,
                max_opcoes: this.elements.max_opcoes.value,
            };
            const resultado = await apiCall('criar_grupo', dados);
            if (resultado.sucesso) {
                sessionStorage.setItem('activeTab', 'adicionaisOpcoes');
                window.location.reload();
            } else {
                alert(`Erro: ${resultado.mensagem}`);
            }
        });
    }

    const listaGruposContainer = document.getElementById('lista-grupos-container');
    if(listaGruposContainer){
        listaGruposContainer.addEventListener('click', async (e) => {
            const target = e.target.closest('.btn-editar-grupo, .btn-apagar-grupo, .btn-adicionar-item, .btn-apagar-item');
            if (!target) return;
            e.preventDefault();

            const idGrupo = target.dataset.idGrupo;
            const idItem = target.dataset.idItem;

            if (target.classList.contains('btn-adicionar-item')) {
                formNovoItem.reset();
                $('#produto_vinculado_select').val(null).trigger('change');
                $('#categoria_item_select').val('');
                $('#produto_vinculado_select').prop('disabled', true).html('<option value="">-- Aguardando categoria --</option>');
                document.getElementById('tipoItemSimples').checked = true;
                document.getElementById('tipoItemSimples').dispatchEvent(new Event('change', { bubbles:true }));
                formNovoItem.elements.id_grupo_opcao.value = idGrupo;
                toggleModal('modalNovoItem', true);
            }
            
            if (target.classList.contains('btn-editar-grupo')) {
                formEditarGrupo.elements.id_grupo.value = idGrupo;
                formEditarGrupo.elements.nome_grupo.value = target.dataset.nomeGrupo;
                formEditarGrupo.elements.tipo_selecao.value = target.dataset.tipoSelecao;
                formEditarGrupo.elements.min_opcoes.value = target.dataset.minOpcoes;
                formEditarGrupo.elements.max_opcoes.value = target.dataset.maxOpcoes;
                toggleModal('modalEditarGrupo', true);
            }

            if (target.classList.contains('btn-apagar-grupo')) {
                if (confirm(`Tem certeza que deseja apagar este grupo? Todos os itens dentro dele serão perdidos.`)) {
                    const resultado = await apiCall('delete_grupo', { id_grupo: idGrupo });
                    if (resultado.sucesso) {
                        document.getElementById(`grupo-container-${idGrupo}`).remove();
                    } else { alert(`Erro: ${resultado.mensagem}`); }
                }
            }
            
            if (target.classList.contains('btn-apagar-item')) {
                if (confirm('Deseja apagar este item?')) {
                    const resultado = await apiCall('delete_item', { id_item: idItem });
                    if (resultado.sucesso) {
                        document.getElementById(`item-opcao-${idItem}`).remove();
                    } else { alert(`Erro: ${resultado.mensagem}`); }
                }
            }
        });
    }

    if(formEditarGrupo){
         formEditarGrupo.addEventListener('submit', async function(e) {
            e.preventDefault();
            const idGrupo = this.elements.id_grupo.value;
            const dados = { id_grupo: idGrupo, nome_grupo: this.elements.nome_grupo.value, tipo_selecao: this.elements.tipo_selecao.value, min_opcoes: this.elements.min_opcoes.value, max_opcoes: this.elements.max_opcoes.value };
            const resultado = await apiCall('update_grupo', dados);
            if (resultado.sucesso) {
                document.getElementById(`nome-grupo-${idGrupo}`).textContent = dados.nome_grupo;
                document.getElementById(`info-grupo-${idGrupo}`).textContent = `(${dados.tipo_selecao.charAt(0).toUpperCase() + dados.tipo_selecao.slice(1).toLowerCase()} | Mín: ${dados.min_opcoes} | Máx: ${dados.max_opcoes})`;
                const btn = document.querySelector(`.btn-editar-grupo[data-id-grupo='${idGrupo}']`);
                Object.assign(btn.dataset, { nomeGrupo: dados.nome_grupo, tipoSelecao: dados.tipo_selecao, minOpcoes: dados.min_opcoes, maxOpcoes: dados.max_opcoes });
                toggleModal('modalEditarGrupo', false);
            } else { alert(`Erro: ${resultado.mensagem}`); }
        });
    }

    // --- LÓGICA DO MODAL DE NOVO ITEM ---
    
    // Controla a visibilidade das seções conforme o tipo de item selecionado
    document.querySelectorAll('input[name="tipo_item"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const isSimples = this.value === 'SIMPLES';
            const isVinculado = this.value === 'VINCULADO';
            const isCombo = this.value === 'COMBO';

            document.getElementById('containerItemSimples').classList.toggle('visible', isSimples);
            document.getElementById('containerItemVinculado').classList.toggle('visible', isVinculado);
            document.getElementById('containerItemCombo').classList.toggle('visible', isCombo);
            
            document.getElementById('nome_item_simples').required = isSimples;
            document.getElementById('nome_item_combo').required = isCombo;
        });
    });

    // Filtro de Categoria -> Produto (para item vinculado e para componentes do combo)
    $('.categoria-select').on('change', function() {
        const categoria_id = $(this).val();
        const target_selector = $(this).data('target');
        const produto_select = $(target_selector);

        produto_select.prop('disabled', true).html('<option value="">Carregando...</option>');
        if (!categoria_id) {
            produto_select.html('<option value="">-- Aguardando categoria --</option>').prop('disabled', true);
            return;
        }
        $.ajax({
            url: '/pdv/public/api/buscar_produtos_api.php',
            type: 'GET', dataType: 'json',
            data: { categoria_id: categoria_id, exclude: <?= $produto_id; ?> },
            success: function(produtos) {
                produto_select.prop('disabled', false).html('<option value="">-- Selecione --</option>');
                if (produtos.length > 0) {
                    $.each(produtos, (i, p) => produto_select.append(new Option(p.text, p.id)));
                } else {
                    produto_select.html('<option value="">Nenhum produto</option>');
                }
            },
            error: function() { produto_select.html('<option value="">Erro ao carregar</option>'); }
        });
    });

    // Inicializa os selects com a biblioteca Select2
    $('#produto_vinculado_select, #produto_componente_select').select2({
        placeholder: 'Aguardando categoria...',
        dropdownParent: $('#modalNovoItem .modal-content')
    });
    
    // Lógica para adicionar/remover componentes do combo na interface
    $('#produto_componente_select').on('change', function() {
        const id = $(this).val();
        if(!id) return;
        const nome = $(this).find('option:selected').text();
        if (document.getElementById(`componente_combo_${id}`)) { alert('Este componente já foi adicionado.'); return; }
        const li = `<li id="componente_combo_${id}"><span>${nome}</span><div><input type="hidden" name="componentes[${id}][id]" value="${id}"><input type="number" name="componentes[${id}][qtd]" value="1" min="1" class="form-control" style="width: 70px; display: inline-block;"><button type="button" class="btn btn-danger btn-sm btn-remover-componente">&times;</button></div></li>`;
        $('#lista_componentes_combo').append(li);
        $(this).val('').trigger('change');
        $('.categoria-select[data-target="#produto_componente_select"]').val('').trigger('change');
    });

    $('#lista_componentes_combo').on('click', '.btn-remover-componente', function(){ $(this).closest('li').remove(); });

    // Lógica de SUBMISSÃO do formulário de NOVO ITEM
    if(formNovoItem){
        formNovoItem.addEventListener('submit', async function(e) {
            e.preventDefault();
            const tipoSelecionado = this.elements.tipo_item.value;
            let dados = {
                id_grupo_opcao: this.elements.id_grupo_opcao.value,
                tipo: tipoSelecionado,
                preco_adicional: parseFloat(this.elements.preco_adicional.value.replace('.', '').replace(',', '.')) || 0,
            };

            if (tipoSelecionado === 'SIMPLES') {
                dados.nome_item = this.elements.nome_item_simples.value;
            } else if (tipoSelecionado === 'VINCULADO') {
                const data = $('#produto_vinculado_select').select2('data')[0];
                if (!data || !data.id) { alert('Selecione um produto para vincular.'); return; }
                dados.id_produto_vinculado = data.id;
                dados.nome_item = data.text;
            } else if (tipoSelecionado === 'COMBO') {
                dados.nome_item = this.elements.nome_item_combo.value;
                dados.componentes = [];
                document.querySelectorAll('#lista_componentes_combo li').forEach(item => {
                    dados.componentes.push({
                        id: item.querySelector('input[type=hidden]').value,
                        qtd: item.querySelector('input[type=number]').value
                    });
                });
                if (dados.componentes.length === 0) { alert('Adicione pelo menos um componente ao combo.'); return; }
            }

            const resultado = await apiCall('criar_item', dados);
            if (resultado.sucesso) {
                sessionStorage.setItem('activeTab', 'adicionaisOpcoes');
                window.location.reload();
            } else {
                alert(`Erro: ${resultado.mensagem}`);
            }
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();
include '../template_admin.php';
?>