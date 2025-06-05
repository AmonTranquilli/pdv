<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho para a conexão

// Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php"); // Redireciona para o login
    exit();
}

$mensagem_sucesso = ''; // Usar para mensagens de sucesso
$mensagem_erro = '';    // Usar para mensagens de erro

// Lógica para processar o envio do formulário
// Mantém os valores preenchidos em caso de erro ou para resetar após sucesso
$nome = $_POST['nome'] ?? '';
$telefone = $_POST['telefone'] ?? '';
$endereco = $_POST['endereco'] ?? '';
$ncasa = $_POST['ncasa'] ?? '';
$bairro = $_POST['bairro'] ?? '';
// REMOVIDO: $cidade = $_POST['cidade'] ?? '';
$cep = $_POST['cep'] ?? '';
$complemento = $_POST['complemento'] ?? '';
$ponto_referencia = $_POST['ponto_referencia'] ?? ''; // NOVO: Ponto de Referência
// Esta variável reflete se a caixa "NÃO possui número da casa" foi marcada
$nao_possui_numero_casa = isset($_POST['nao_possui_numero_casa']) ? true : false; 


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Coleta e sanitiza os dados do formulário
    $nome = trim(filter_var($_POST['nome'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $telefone = trim(filter_var($_POST['telefone'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $endereco = trim(filter_var($_POST['endereco'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    // ncasa e complemento são sanitizados abaixo, após a lógica do checkbox
    $bairro = trim(filter_var($_POST['bairro'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    // REMOVIDO: $cidade = trim(filter_var($_POST['cidade'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $cep = trim(filter_var($_POST['cep'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    
    // Atualiza a variável do checkbox com base no POST
    $nao_possui_numero_casa = isset($_POST['nao_possui_numero_casa']) ? true : false;

    // Sanitiza complemento e ponto_referencia aqui, após a validação condicional
    $complemento = trim(filter_var($_POST['complemento'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $ponto_referencia = trim(filter_var($_POST['ponto_referencia'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)); // NOVO: Sanitiza Ponto de Referência

    // Validação básica: Nome, Telefone, Endereço, Bairro são obrigatórios
    if (empty($nome) || empty($telefone) || empty($endereco) || empty($bairro)) { // REMOVIDO: empty($cidade)
        $mensagem_erro = "Por favor, preencha todos os campos obrigatórios (Nome, Telefone, Endereço, Bairro).";
    } elseif ($nao_possui_numero_casa && (empty($complemento) && empty($ponto_referencia))) { // NOVO: Validação para Complemento OU Ponto de Referência
        // Se NÃO possui número da casa E (o complemento está vazio E o ponto de referência está vazio), é erro
        $mensagem_erro = "Como o cliente NÃO possui número da casa, o Complemento OU o Ponto de Referência se torna obrigatório.";
    } else {
        // Lógica para 'ncasa_param': se 'nao_possui_numero_casa' está marcado, é NULL no DB.
        // Caso contrário, usa o valor de 'ncasa' do POST, sanitizado.
        if ($nao_possui_numero_casa) {
            $ncasa_param = NULL;
            // Para o campo HTML, mantém 'S/N' se o checkbox estiver marcado
            $ncasa = 'S/N'; 
        } else {
            $ncasa = trim(filter_var($_POST['ncasa'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $ncasa_param = $ncasa;

            // Se o checkbox NÃO está marcado, 'ncasa' é obrigatório.
            if (empty($ncasa_param)) {
                $mensagem_erro = "Por favor, preencha o Nº Casa.";
            }
        }
    }

    // Se não houve erro de validação até aqui, procede com a inserção no banco
    if (empty($mensagem_erro)) {
        // ATUALIZAÇÃO: Removido 'cidade' e adicionado 'ponto_referencia' na query
        $stmt = $conn->prepare("INSERT INTO clientes (nome, telefone, endereco, ncasa, bairro, cep, complemento, ponto_referencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        // 's' para string para todos os campos. ncasa_param pode ser NULL, mas bind_param ainda espera 's'.
        // ATUALIZAÇÃO: Ordem e variáveis para bind_param
        $stmt->bind_param("ssssssss", $nome, $telefone, $endereco, $ncasa_param, $bairro, $cep, $complemento, $ponto_referencia);

        if ($stmt->execute()) {
            $mensagem_sucesso = "Cliente '" . htmlspecialchars($nome) . "' adicionado com sucesso!";
            // Limpar campos do formulário após sucesso para nova adição
            $nome = $telefone = $endereco = $ncasa = $bairro = $cep = $complemento = $ponto_referencia = ''; // NOVO: Limpa ponto_referencia
            $nao_possui_numero_casa = false; // Reseta o checkbox para o estado padrão
        } else {
            $mensagem_erro = "Erro ao adicionar cliente: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Define o título da página para o template
$page_title = 'Adicionar Cliente';

// --- INÍCIO DO BUFFER DE SAÍDA ---
ob_start();
?>

<div class="container">
    <h1 style="text-align: center; margin-bottom: 25px;">Adicionar Novo Cliente</h1>

    <?php if (!empty($mensagem_sucesso)) : ?>
        <p class="message-success"><?php echo htmlspecialchars($mensagem_sucesso); ?></p>
    <?php endif; ?>
    <?php if (!empty($mensagem_erro)) : ?>
        <p class="message-error"><?php echo htmlspecialchars($mensagem_erro); ?></p>
    <?php endif; ?>

    <div class="page-content"> 
        <form action="adicionar.php" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="nome">Nome do Cliente:</label>
                    <input type="text" id="nome" name="nome" required value="<?php echo htmlspecialchars($nome); ?>">
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone:</label>
                    <input type="text" id="telefone" name="telefone" maxlength="15" inputmode="numeric" required pattern="\(\d{2}\)\s?\d{5}-\d{4}" value="<?php echo htmlspecialchars($telefone); ?>" placeholder="(21) 12345-6789">
                    <small>Formato: (21) 12345-6789</small>
                </div>
            </div>

            <div class="form-group">
                <label for="endereco">Endereço:</label>
                <input type="text" id="endereco" name="endereco" value="<?php echo htmlspecialchars($endereco); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group checkbox-and-input"> 
                    <div class="form-check">
                        <input type="checkbox" id="nao_possui_numero_casa" name="nao_possui_numero_casa" value="1" <?php echo ($nao_possui_numero_casa) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="nao_possui_numero_casa">Cliente não possui número da casa</label>
                    </div>
                    <div id="ncasa_group">
                        <label for="ncasa">Nº Casa:</label>
                        <input type="text" id="ncasa" name="ncasa" value="<?php echo htmlspecialchars($ncasa); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="bairro">Bairro:</label>
                    <input type="text" id="bairro" name="bairro" value="<?php echo htmlspecialchars($bairro); ?>">
                </div>
            </div>

            <div class="form-row">
                <!-- REMOVIDO: Campo Cidade -->
                <!-- <div class="form-group">
                    <label for="cidade">Cidade:</label>
                    <input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($cidade); ?>">
                </div> -->
                <div class="form-group">
                    <label for="cep">CEP:</label>
                    <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($cep); ?>">
                    <small>(Opcional)</small>
                </div>
            </div>

            <div class="form-group">
                <label for="complemento">Complemento:</label>
                <input type="text" id="complemento" name="complemento" value="<?php echo htmlspecialchars($complemento); ?>">
            </div>

            <div class="form-group">
                <label for="ponto_referencia">Ponto de Referência:</label>
                <input type="text" id="ponto_referencia" name="ponto_referencia" value="<?php echo htmlspecialchars($ponto_referencia); ?>">
                <small>(Opcional)</small>
            </div>

            <div class="action-buttons" style="text-align: center; margin-top: 30px;">
                <button type="submit" class="btn btn-success"><i class="fas fa-plus-circle"></i> Adicionar Cliente</button>
                <a href="clientes.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Voltar para Clientes</a>
            </div>
        </form>
    </div>
</div>

<script>
    // Script para controlar a visibilidade e obrigatoriedade dos campos
    const naoPossuiNumeroCasaCheckbox = document.getElementById('nao_possui_numero_casa');
    const ncasaInput = document.getElementById('ncasa');
    const complementoInput = document.getElementById('complemento');
    const pontoReferenciaInput = document.getElementById('ponto_referencia'); // NOVO: Ponto de Referência

    function toggleCamposEndereco() {
        if (naoPossuiNumeroCasaCheckbox.checked) {
            ncasaInput.value = 'S/N'; // Define "S/N" (Sem Número)
            ncasaInput.setAttribute('disabled', 'disabled'); // DESABILITA o campo
            ncasaInput.style.backgroundColor = '#e0e0e0'; // Muda cor para indicar desabilitado
            ncasaInput.style.cursor = 'not-allowed'; // Muda cursor
            
            // Remove o atributo 'required' de complemento e ponto_referencia no JS
            // A validação de que pelo menos um dos dois deve ser preenchido será feita no PHP
            complementoInput.removeAttribute('required');
            pontoReferenciaInput.removeAttribute('required');
        } else {
            // Se o valor era 'S/N' quando desabilitado, limpa ao reabilitar
            if (ncasaInput.value === 'S/N') {
                ncasaInput.value = ''; 
            }
            ncasaInput.removeAttribute('disabled'); // HABILITA o campo
            ncasaInput.style.backgroundColor = '#fff'; // Retorna cor normal
            ncasaInput.style.cursor = 'auto'; // Retorna cursor normal
            // Garante que o required seja removido caso tenha sido adicionado de alguma forma
            complementoInput.removeAttribute('required'); 
            pontoReferenciaInput.removeAttribute('required');
        }
    }

    // Chama a função uma vez ao carregar a página para definir o estado inicial
    document.addEventListener('DOMContentLoaded', toggleCamposEndereco);

    // Adiciona um listener para o evento de mudança do checkbox
    naoPossuiNumeroCasaCheckbox.addEventListener('change', toggleCamposEndereco);

    // Máscara dinâmica para o telefone: (11) 98765-4321
    const telefoneInput = document.getElementById('telefone');

    telefoneInput.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é número

        if (value.length > 11) value = value.slice(0, 11); // Máximo 11 dígitos

        if (value.length >= 2 && value.length <= 6) {
            value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
        } else if (value.length > 6) {
            value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
        }

        e.target.value = value;
    });

</script>

<?php
// --- FIM DO BUFFER DE SAÍDA ---
$page_content = ob_get_clean();

// Fecha a conexão com o banco de dados.
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

// Inclui o template principal do painel administrativo.
include '../template_admin.php'; // O template está um nível acima (em 'admin/')
?>
