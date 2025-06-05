<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho para a conexão

// Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php"); // Redireciona para o login
    exit();
}

$mensagem = '';
$sucesso = false;
$categorias = []; // Array para armazenar as categorias para o <select>

// Carrega as categorias para o campo de seleção
$sql_categorias = "SELECT id, nome FROM categorias ORDER BY nome ASC";
$result_categorias = $conn->query($sql_categorias);
if ($result_categorias->num_rows > 0) {
    while ($row = $result_categorias->fetch_assoc()) {
        $categorias[] = $row;
    }
}

// Lógica para processar o envio do formulário
// Mantém os valores preenchidos em caso de erro para não ter que digitar tudo de novo
$nome = $_POST['nome'] ?? '';
$descricao = $_POST['descricao'] ?? '';
$preco = $_POST['preco'] ?? '';
$id_categoria = $_POST['id_categoria'] ?? '';
$estoque = $_POST['estoque'] ?? 0;
$controla_estoque = isset($_POST['controla_estoque']) ? 1 : 0;
$ativo = isset($_POST['ativo']) ? 1 : 0; // NOVO: Captura o valor do checkbox 'ativo'

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $preco = filter_var($_POST['preco'], FILTER_VALIDATE_FLOAT);
    // id_categoria pode ser vazio se "-- Selecione uma Categoria --" for escolhido
    $id_categoria_post = filter_var($_POST['id_categoria'], FILTER_VALIDATE_INT);
    $estoque = filter_var($_POST['estoque'], FILTER_VALIDATE_INT);
    $controla_estoque = isset($_POST['controla_estoque']) ? 1 : 0;
    $ativo = isset($_POST['ativo']) ? 1 : 0; // NOVO: Captura novamente o valor postado

    // Variável para o caminho da imagem
    $caminho_imagem = '';

    // Validação básica
    if (empty($nome) || $preco === false || $preco < 0) {
        $mensagem = "Por favor, preencha o Nome e o Preço corretamente.";
    } elseif ($controla_estoque && ($estoque === false || $estoque < 0)) { // Valida estoque APENAS se controla estoque
        $mensagem = "Se o estoque for controlado, a quantidade deve ser um número válido e não negativo.";
    } else {
        // Lidar com o upload da imagem
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == UPLOAD_ERR_OK) {
            $diretorio_uploads = '../../public/uploads/produtos/'; // Ajuste este caminho conforme sua estrutura
            if (!is_dir($diretorio_uploads)) {
                mkdir($diretorio_uploads, 0777, true); // Cria o diretório se não existir
            }

            $nome_arquivo = uniqid() . '_' . basename($_FILES['imagem']['name']); // Nome único
            $caminho_completo_imagem = $diretorio_uploads . $nome_arquivo;

            // Define o caminho a ser salvo no banco de dados (relativo ao web root)
            $caminho_imagem_db = '/pdv/public/uploads/produtos/' . $nome_arquivo;

            if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminho_completo_imagem)) {
                // Upload bem-sucedido, usa o caminho para o banco
                $caminho_imagem = $caminho_imagem_db;
            } else {
                $mensagem = "Erro ao fazer upload da imagem.";
            }
        }

        // Se a imagem não foi enviada ou houve erro, mas não é um campo obrigatório
        if (empty($mensagem)) { // Só tenta inserir se não houver erro de validação ou upload
            // NOVO: Adiciona 'ativo' na query INSERT
            $stmt = $conn->prepare("INSERT INTO produtos (nome, descricao, preco, id_categoria, imagem, estoque, controla_estoque, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            // O id_categoria pode ser NULL se o usuário não selecionar uma categoria
            $id_categoria_param = ($id_categoria_post !== false && $id_categoria_post > 0) ? $id_categoria_post : NULL;

            // NOVO: Adiciona 'ativo' aos parâmetros bind_param
            $stmt->bind_param("ssdisiii", $nome, $descricao, $preco, $id_categoria_param, $caminho_imagem, $estoque, $controla_estoque, $ativo);

            if ($stmt->execute()) {
                $mensagem = "Produto '" . htmlspecialchars($nome) . "' adicionado com sucesso!";
                $sucesso = true;
                // Limpar campos do formulário após sucesso para nova adição
                $nome = $descricao = $preco = $id_categoria = '';
                $estoque = 0;
                $controla_estoque = 1; // Volta ao padrão de controlar estoque
                $ativo = 1; // NOVO: Reseta o checkbox 'ativo' para marcado por padrão
            } else {
                $mensagem = "Erro ao adicionar produto: " . $stmt->error;
            }
            $stmt->close();
        }
    }
} else {
    // NOVO: Define o estado inicial do checkbox 'ativo' quando a página é carregada pela primeira vez
    $ativo = 1; // Por padrão, o produto é ativo
}

$conn->close(); // Fecha a conexão

// Define o título da página para o template
$page_title = 'Adicionar Produto';

// --- INÍCIO DO BUFFER DE SAÍDA ---
ob_start();
?>

<div class="container">
    <h1 style="text-align: center; margin-bottom: 25px;">Adicionar Novo Produto</h1>

    <?php if (!empty($mensagem)) : ?>
        <p class="message-<?php echo $sucesso ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($mensagem); ?></p>
    <?php endif; ?>

    <div class="page-content"> <form action="adicionar.php" method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label for="nome">Nome do Produto:</label>
                    <input type="text" id="nome" name="nome" required value="<?php echo htmlspecialchars($nome); ?>">
                </div>

                <div class="form-group">
                    <label for="preco">Preço:</label>
                    <input type="number" id="preco" name="preco" step="0.01" min="0" required value="<?php echo htmlspecialchars($preco); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="descricao">Descrição:</label>
                <textarea id="descricao" name="descricao" rows="4"><?php echo htmlspecialchars($descricao); ?></textarea>
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
                <label for="imagem">Imagem do Produto:</label>
                <input type="file" id="imagem" name="imagem" accept="image/*">
                <small>Arquivos permitidos: JPG, JPEG, PNG, GIF. Tamanho máximo recomendado: 2MB.</small>
            </div>

            <div class="form-row">
                <div class="form-group form-check"> <input type="checkbox" id="controla_estoque" name="controla_estoque" value="1" <?php echo ($controla_estoque) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="controla_estoque">Controlar Estoque</label>
                </div>

                <div class="form-group" id="estoque_group">
                    <label for="estoque">Estoque inicial:</label>
                    <input type="number" id="estoque" name="estoque" min="0" required value="<?php echo htmlspecialchars($estoque); ?>">
                </div>
            </div>
            
            <div class="form-group form-check"> <input type="checkbox" id="ativo" name="ativo" value="1" <?php echo ($ativo) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="ativo">Produto Ativo (Disponível no cardápio)</label>
            </div>

            <div class="action-buttons" style="text-align: center; margin-top: 30px;">
                <button type="submit" class="btn btn-success"><i class="fas fa-plus-circle"></i> Adicionar Produto</button>
                <a href="produtos.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Voltar para Produtos</a>
            </div>
        </form>
    </div>
</div>

<script>
    // Script para controlar a visibilidade do campo de estoque
    const controlaEstoqueCheckbox = document.getElementById('controla_estoque');
    const estoqueGroup = document.getElementById('estoque_group');
    const estoqueInput = document.getElementById('estoque');

    function toggleEstoqueField() {
        if (controlaEstoqueCheckbox.checked) {
            estoqueGroup.style.display = 'block'; // Mostra o campo
            estoqueInput.setAttribute('required', 'required'); // Torna o campo obrigatório
        } else {
            estoqueGroup.style.display = 'none'; // Oculta o campo
            estoqueInput.removeAttribute('required'); // Remove a obrigatoriedade
            estoqueInput.value = '0'; // Opcional: Zera o valor quando oculto
        }
    }

    // Chama a função uma vez ao carregar a página para definir o estado inicial
    document.addEventListener('DOMContentLoaded', toggleEstoqueField);

    // Adiciona um listener para o evento de mudança do checkbox
    controlaEstoqueCheckbox.addEventListener('change', toggleEstoqueField);
</script>

<?php
// --- FIM DO BUFFER DE SAÍDA ---
$page_content = ob_get_clean();

// Inclui o template principal do painel administrativo.
include '../template_admin.php'; // O template está um nível acima (em 'admin/')
?>