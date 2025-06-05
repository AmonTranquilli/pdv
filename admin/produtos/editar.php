<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho para a conexão

// Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit();
}

$mensagem = '';
$sucesso = false;
$produto = null; // Para armazenar os dados do produto que está sendo editado
$categorias = []; // Para o select de categorias

// 1. Carrega as categorias para o campo de seleção
$sql_categorias = "SELECT id, nome FROM categorias ORDER BY nome ASC";
$result_categorias = $conn->query($sql_categorias);
if ($result_categorias->num_rows > 0) {
    while ($row = $result_categorias->fetch_assoc()) {
        $categorias[] = $row;
    }
}

// Variáveis para pré-preencher o formulário (inicializadas para evitar warnings)
$nome = '';
$descricao = '';
$preco = '';
$id_categoria = '';
$imagem_atual = '';
$estoque = 0;
$controla_estoque = 1; // Padrão
$ativo = 1; // Padrão

// 2. Tenta carregar os dados do produto (se um ID for fornecido via GET)
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $produto_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($produto_id === false || $produto_id <= 0) {
        $mensagem = "ID de produto inválido.";
        // Redireciona de volta para a lista de produtos se o ID for inválido
        header("Location: produtos.php");
        exit();
    } else {
        $stmt_produto = $conn->prepare("SELECT id, nome, descricao, preco, id_categoria, imagem, estoque, controla_estoque, ativo FROM produtos WHERE id = ?");
        $stmt_produto->bind_param("i", $produto_id);
        $stmt_produto->execute();
        $result_produto = $stmt_produto->get_result();

        if ($result_produto->num_rows === 1) {
            $produto = $result_produto->fetch_assoc();
            // Preenche as variáveis para o formulário
            $nome = $produto['nome'];
            $descricao = $produto['descricao'];
            $preco = $produto['preco'];
            $id_categoria = $produto['id_categoria'];
            $imagem_atual = $produto['imagem']; // Guarda o caminho da imagem atual
            $estoque = $produto['estoque'];
            $controla_estoque = $produto['controla_estoque'];
            $ativo = $produto['ativo']; // Carrega o status 'ativo'
        } else {
            $mensagem = "Produto não encontrado.";
            // Redireciona se o produto não for encontrado
            header("Location: produtos.php");
            exit();
        }
        $stmt_produto->close();
    }
} else if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    // Se não é POST e não tem ID via GET, significa que o acesso está incorreto.
    $mensagem = "Nenhum ID de produto fornecido para edição.";
    // Redireciona de volta para a lista de produtos
    header("Location: produtos.php");
    exit();
}


// 3. Lógica para processar o envio do formulário (UPDATE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['produto_id'])) {
    $produto_id = filter_var($_POST['produto_id'], FILTER_VALIDATE_INT);
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $preco = filter_var($_POST['preco'], FILTER_VALIDATE_FLOAT);
    $id_categoria_post = filter_var($_POST['id_categoria'], FILTER_VALIDATE_INT);
    $estoque_post = filter_var($_POST['estoque'], FILTER_VALIDATE_INT); 
    $controla_estoque = isset($_POST['controla_estoque']) ? 1 : 0;
    $ativo_post = isset($_POST['ativo']) ? 1 : 0; 
    
    $imagem_atual_hidden = $_POST['imagem_atual_hidden'] ?? ''; // Pega o caminho da imagem atual do campo oculto

    // A imagem para o banco de dados será a atual, a menos que uma nova seja enviada
    $caminho_imagem_db = $imagem_atual_hidden;

    // Validação básica
    if ($produto_id === false || $produto_id <= 0) {
        $mensagem = "ID do produto inválido para atualização.";
    } elseif (empty($nome) || $preco === false || $preco < 0) {
        $mensagem = "Por favor, preencha o Nome e o Preço corretamente.";
    } elseif ($controla_estoque && ($estoque_post === false || $estoque_post < 0)) {
        $mensagem = "Se o estoque for controlado, a quantidade deve ser um número válido e não negativo.";
    } else {
        // Lidar com o upload de UMA NOVA IMAGEM
        if (isset($_FILES['nova_imagem']) && $_FILES['nova_imagem']['error'] == UPLOAD_ERR_OK) {
            $diretorio_uploads = '../../public/uploads/produtos/';
            if (!is_dir($diretorio_uploads)) {
                mkdir($diretorio_uploads, 0777, true);
            }

            $nome_arquivo = uniqid() . '_' . basename($_FILES['nova_imagem']['name']);
            $caminho_completo_nova_imagem = $diretorio_uploads . $nome_arquivo;
            $caminho_imagem_db_nova = '/pdv/public/uploads/produtos/' . $nome_arquivo;

            if (move_uploaded_file($_FILES['nova_imagem']['tmp_name'], $caminho_completo_nova_imagem)) {
                // Se o upload da nova imagem foi bem-sucedido, use o novo caminho
                $caminho_imagem_db = $caminho_imagem_db_nova;
                // Opcional: Excluir a imagem antiga do servidor se uma nova foi enviada e a antiga existia
                if (!empty($imagem_atual_hidden) && file_exists(str_replace('/pdv/', '../../', $imagem_atual_hidden))) {
                    unlink(str_replace('/pdv/', '../../', $imagem_atual_hidden));
                }
            } else {
                $mensagem = "Erro ao fazer upload da nova imagem. O produto será atualizado sem alterar a imagem.";
            }
        }

        // NOVO: Lógica para controlar o status 'ativo' com base no 'estoque' e 'controla_estoque'
        $novo_status_ativo = $ativo_post; // Começa com o valor enviado pelo formulário

        if ($controla_estoque == 1) { // Apenas se o controle de estoque estiver ativado
            if ($estoque_post <= 0) {
                $novo_status_ativo = 0; // Se estoque <= 0, força para inativo
            } else {
                $novo_status_ativo = 1; // Se estoque > 0, força para ativo
            }
        }
        // Se 'controla_estoque' for 0, o 'ativo_post' (valor do checkbox) é mantido.
        
        // Atribui o valor do estoque que será usado na query (se controla_estoque=0, usamos 0 como padrão para estoque)
        $estoque_para_db = $controla_estoque ? $estoque_post : 0;


        // Se não houver erro de validação ou upload, tenta atualizar
        if (empty($mensagem) || strpos($mensagem, "Erro ao fazer upload") !== false) {
            $stmt = $conn->prepare("UPDATE produtos SET nome = ?, descricao = ?, preco = ?, id_categoria = ?, imagem = ?, estoque = ?, controla_estoque = ?, ativo = ? WHERE id = ?");

            $id_categoria_param = ($id_categoria_post !== false && $id_categoria_post > 0) ? $id_categoria_post : NULL;

            $stmt->bind_param("ssdisiiii", $nome, $descricao, $preco, $id_categoria_param, $caminho_imagem_db, $estoque_para_db, $controla_estoque, $novo_status_ativo, $produto_id);

            if ($stmt->execute()) {
                $mensagem = "Produto '" . htmlspecialchars($nome) . "' atualizado com sucesso!";
                $sucesso = true;
                // Atualiza a variável $imagem_atual caso uma nova imagem tenha sido salva
                $imagem_atual = $caminho_imagem_db;
                // Também atualiza o status ativo e estoque nas variáveis locais para refletir a mudança no formulário
                $produto['ativo'] = $novo_status_ativo;
                $produto['estoque'] = $estoque_para_db; 
                $ativo = $novo_status_ativo; 
                $estoque = $estoque_para_db; 
            } else {
                $mensagem = "Erro ao atualizar produto: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}


$conn->close();

// Define o título da página para o template
$page_title = 'Editar Produto';

// --- INÍCIO DO BUFFER DE SAÍDA ---
ob_start();
?>

<div class="container">
    <h1 style="text-align: center; margin-bottom: 25px;">Editar Produto</h1>

    <?php if (!empty($mensagem)) : ?>
        <p class="message-<?php echo $sucesso ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($mensagem); ?></p>
    <?php endif; ?>

    <?php if ($produto_id !== null && $produto !== null) : // Só exibe o formulário se o produto foi carregado ?>
        <div class="page-content"> <form action="editar.php?id=<?php echo htmlspecialchars($produto_id); ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="produto_id" value="<?php echo htmlspecialchars($produto_id); ?>">
                <input type="hidden" name="imagem_atual_hidden" value="<?php echo htmlspecialchars($imagem_atual ?? ''); ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome do Produto:</label>
                        <input type="text" id="nome" name="nome" required value="<?php echo htmlspecialchars($nome ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="preco">Preço:</label>
                        <input type="number" id="preco" name="preco" step="0.01" min="0" required value="<?php echo htmlspecialchars($preco ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="descricao">Descrição:</label>
                    <textarea id="descricao" name="descricao" rows="4"><?php echo htmlspecialchars($descricao ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="id_categoria">Categoria:</label>
                    <select id="id_categoria" name="id_categoria">
                        <option value="">-- Selecione uma Categoria --</option>
                        <?php foreach ($categorias as $cat) : ?>
                            <option value="<?php echo htmlspecialchars($cat['id']); ?>"
                                <?php echo (isset($id_categoria) && $id_categoria == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($categorias)) : ?>
                        <p class="message-info" style="font-size: 0.9em;">Nenhuma categoria encontrada. Por favor, adicione categorias primeiro.</p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Imagem Atual:</label>
                    <?php if (!empty($imagem_atual)) : ?>
                        <br>
                        <img src="<?php echo htmlspecialchars($imagem_atual); ?>" alt="Imagem atual do produto" style="max-width: 150px; height: auto; display: block; margin-top: 5px; border-radius: 5px;">
                        <br>
                    <?php else : ?>
                        <p>Nenhuma imagem atual.</p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="nova_imagem">Alterar Imagem (selecione uma nova):</label>
                    <input type="file" id="nova_imagem" name="nova_imagem" accept="image/*">
                    <small>Arquivos permitidos: JPG, JPEG, PNG, GIF. Tamanho máximo recomendado: 2MB.</small>
                </div>

                <div class="form-row">
                    <div class="form-group form-check">
                        <input type="checkbox" id="controla_estoque" name="controla_estoque" value="1" <?php echo ($controla_estoque ?? 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="controla_estoque">Controlar Estoque</label>
                    </div>

                    <div class="form-group" id="estoque_group">
                        <label for="estoque">Estoque (quantidade):</label>
                        <input type="number" id="estoque" name="estoque" min="0" required value="<?php echo htmlspecialchars($estoque ?? 0); ?>">
                    </div>
                </div>

                <div class="form-group form-check">
                    <input type="checkbox" id="ativo" name="ativo" value="1" <?php echo ($ativo ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="ativo">Produto Ativo (Disponível no cardápio)</label>
                </div>

                <div class="action-buttons" style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Atualizar Produto</button>
                    <a href="produtos.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Voltar para Produtos</a>
                </div>
            </form>
        </div>
    <?php else : ?>
        <p class="message-error">Não foi possível carregar o produto para edição. <?php echo htmlspecialchars($mensagem); ?></p>
        <div class="action-buttons" style="text-align: center; margin-top: 30px;">
               <a href="produtos.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Voltar para Produtos</a>
        </div>
    <?php endif; ?>

</div>

<script>
    // Script para controlar a visibilidade do campo de estoque
    // Encapsula o script em uma IIFE para evitar conflitos de variáveis
    (function() {
        const controlaEstoqueCheckbox = document.getElementById('controla_estoque');
        const estoqueGroup = document.getElementById('estoque_group');
        const estoqueInput = document.getElementById('estoque');
        const ativoCheckbox = document.getElementById('ativo');

        function toggleEstoqueField() {
            if (controlaEstoqueCheckbox.checked) {
                estoqueGroup.style.display = 'block'; // Mostra o campo
                estoqueInput.setAttribute('required', 'required'); // Torna o campo obrigatório

                // Lógica de desabilitar/marcar o ativo com base no estoque
                if (parseInt(estoqueInput.value) <= 0) {
                    ativoCheckbox.checked = false;
                    ativoCheckbox.disabled = true; // Desabilita para que o usuário não possa mudar
                } else {
                    // Se estoque > 0 e o controle de estoque está ativo, o checkbox 'ativo' deve ser habilitado
                    ativoCheckbox.disabled = false; 
                    // Opcional: Se o estoque for positivo, você pode querer forçar o produto a ser ativo
                    // ativoCheckbox.checked = true; 
                }
            } else {
                estoqueGroup.style.display = 'none'; // Oculta o campo
                estoqueInput.removeAttribute('required'); // Remove a obrigatoriedade
                estoqueInput.value = '0'; // Opcional: Zera o valor quando oculto
                
                // Habilita o checkbox 'ativo' se o controle de estoque for desabilitado
                ativoCheckbox.disabled = false;
            }
        }

        // Chama a função uma vez ao carregar a página para definir o estado inicial
        document.addEventListener('DOMContentLoaded', toggleEstoqueField);

        // Adiciona um listener para o evento de mudança do checkbox e do campo de estoque
        controlaEstoqueCheckbox.addEventListener('change', toggleEstoqueField);
        estoqueInput.addEventListener('input', toggleEstoqueField); // Monitora mudanças no estoque
    })(); // A IIFE é invocada imediatamente aqui
</script>

<?php
// --- FIM DO BUFFER DE SAÍDA ---
$page_content = ob_get_clean();

// Inclui o template principal do painel administrativo.
include '../template_admin.php'; // O template está um nível acima (em 'admin/')
?>
