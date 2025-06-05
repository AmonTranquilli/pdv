<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho para a conexão

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php"); // Redireciona para o login
    exit();
}

$mensagem = '';
$sucesso = false;
$cliente_id = null;
$cliente = []; // Para armazenar os dados do cliente que está sendo editado

// Variáveis para pré-preencher o formulário
$nome = '';
$telefone = '';
$endereco = '';
$ncasa = '';
$bairro = '';
// REMOVIDO: $cidade = '';
$cep = ''; // CEP agora pode ser vazio
$complemento = '';
$ponto_referencia = ''; // NOVO: Ponto de Referência
$nao_possui_numero_casa = false;

// 2. Processa o envio do formulário (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cliente_id = filter_var($_POST['id_cliente'], FILTER_VALIDATE_INT);
    $nome = trim($_POST['nome']);
    $telefone = trim($_POST['telefone']);
    $endereco = trim($_POST['endereco']);
    $ncasa_post = isset($_POST['ncasa']) ? trim($_POST['ncasa']) : '';
    $bairro = trim($_POST['bairro']);
    // REMOVIDO: $cidade = trim($_POST['cidade']);
    $cep = trim($_POST['cep']); // CAPTURAR o CEP, mesmo que vazio
    $complemento = trim($_POST['complemento']);
    $ponto_referencia = trim($_POST['ponto_referencia']); // NOVO: Captura Ponto de Referência
    $nao_possui_numero_casa = isset($_POST['nao_possui_numero_casa']) ? true : false;

    // Lógica para definir o valor de ncasa a ser salvo no DB
    $ncasa_param = $nao_possui_numero_casa ? NULL : ($ncasa_post === '' ? NULL : $ncasa_post); // Se 'S/N' ou vazio, salva como NULL
    
    // Validações
    if ($cliente_id === false || $cliente_id <= 0) {
        $mensagem = "ID do cliente inválido para atualização.";
    } elseif (empty($nome) || empty($telefone) || empty($endereco) || empty($bairro)) { // REMOVIDO: empty($cidade)
        $mensagem = "Por favor, preencha todos os campos obrigatórios (Nome, Telefone, Endereço, Bairro).";
    } elseif (!$nao_possui_numero_casa && empty($ncasa_post)) {
        $mensagem = "O campo 'Nº Casa' é obrigatório, a menos que você marque 'Cliente não possui número da casa'.";
    } elseif ($nao_possui_numero_casa && empty($complemento) && empty($ponto_referencia)) { // NOVO: Complemento OU Ponto de Referência é obrigatório
        $mensagem = "Pelo menos um dos campos 'Complemento' ou 'Ponto de Referência' é obrigatório quando o cliente não possui número da casa.";
    } else {
        // Prepara a query de atualização
        // ATUALIZAÇÃO: Removido 'cidade' e adicionado 'ponto_referencia'
        $stmt = $conn->prepare("UPDATE clientes SET nome = ?, telefone = ?, endereco = ?, ncasa = ?, bairro = ?, cep = ?, complemento = ?, ponto_referencia = ? WHERE id = ?");

        if ($stmt === false) {
            $mensagem = "Erro na preparação da consulta: " . $conn->error;
        } else {
            // ATUALIZAÇÃO: "sssssssi" -> 7 strings e 1 inteiro (para o ID)
            // Ordem: nome, telefone, endereco, ncasa, bairro, cep, complemento, ponto_referencia, cliente_id
            $stmt->bind_param("ssssssssi", $nome, $telefone, $endereco, $ncasa_param, $bairro, $cep, $complemento, $ponto_referencia, $cliente_id);

            if ($stmt->execute()) {
                $mensagem = "Cliente atualizado com sucesso!";
                $sucesso = true;
                // Para manter a mensagem visível na página de edição, não redirecionamos imediatamente.
            } else {
                $mensagem = "Erro ao atualizar cliente: " . $stmt->error;
            }
            $stmt->close();
        }
    }
} 
// 3. Carrega os dados do cliente (se um ID foi passado via GET e não foi um POST de erro)
if (isset($_GET['id']) && !empty($_GET['id']) && $sucesso === false) { // Carrega apenas se não houve sucesso no POST
    $cliente_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($cliente_id === false || $cliente_id <= 0) {
        $mensagem = "ID de cliente inválido.";
    } else {
        // ATUALIZAÇÃO: Removido 'cidade' e adicionado 'ponto_referencia' na consulta SELECT
        $stmt_cliente = $conn->prepare("SELECT id, nome, telefone, endereco, ncasa, bairro, cep, complemento, ponto_referencia FROM clientes WHERE id = ?");
        if ($stmt_cliente === false) {
            $mensagem = "Erro na preparação da consulta de cliente: " . $conn->error;
        } else {
            $stmt_cliente->bind_param("i", $cliente_id);
            $stmt_cliente->execute();
            $result_cliente = $stmt_cliente->get_result();

            if ($result_cliente->num_rows === 1) {
                $cliente = $result_cliente->fetch_assoc();
                // Preenche as variáveis para o formulário
                $nome = $cliente['nome'];
                $telefone = $cliente['telefone'];
                $endereco = $cliente['endereco'];
                $ncasa = $cliente['ncasa'];
                $bairro = $cliente['bairro'];
                // REMOVIDO: $cidade = $cliente['cidade'];
                $cep = $cliente['cep']; // CEP
                $complemento = $cliente['complemento'];
                $ponto_referencia = $cliente['ponto_referencia']; // NOVO: Ponto de Referência
                
                // Define o estado do checkbox 'nao_possui_numero_casa'
                $nao_possui_numero_casa = ($ncasa === NULL || $ncasa === 'S/N'); // Se ncasa for NULL ou 'S/N', marca o checkbox

            } else {
                $mensagem = "Cliente não encontrado.";
                $sucesso = false;
                $cliente_id = null; // Zera o ID para não exibir o formulário vazio
            }
            $stmt_cliente->close();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] != 'POST') {
    // Se não é POST e não tem ID via GET, significa que o acesso está incorreto.
    $mensagem = "Acesso inválido. Nenhum ID de cliente fornecido.";
    $sucesso = false;
}

$conn->close(); // Fecha a conexão com o banco de dados

// Define o título da página para o template
$page_title = 'Editar Cliente';

// --- INÍCIO DO BUFFER DE SAÍDA ---
ob_start();
?>

<div class="container">
    <h1 style="text-align: center; margin-bottom: 25px;">Editar Cliente</h1>

    <?php if (!empty($mensagem)) : ?>
        <p class="mensagem <?php echo $sucesso ? 'sucesso' : 'erro'; ?>"><?php echo htmlspecialchars($mensagem); ?></p>
    <?php endif; ?>

    <?php if ($cliente_id !== null && ($sucesso || !empty($cliente))) : // Só exibe o formulário se o ID é válido ou se houve sucesso na edição anterior ?>
        <div class="page-content"> 
            <form action="editar.php?id=<?php echo htmlspecialchars($cliente_id); ?>" method="POST">
                <input type="hidden" name="id_cliente" value="<?php echo htmlspecialchars($cliente_id); ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome do Cliente:</label>
                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="telefone">Telefone:</label>
                        <input type="text" id="telefone" name="telefone" maxlength="15" inputmode="numeric" required pattern="\(\d{2}\)\s?\d{5}-\d{4}" value="<?php echo htmlspecialchars($telefone); ?>" placeholder="(21) 12345-6789">
                        <small>Formato: (21) 12345-6789</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="endereco">Endereço:</label>
                    <input type="text" id="endereco" name="endereco" value="<?php echo htmlspecialchars($endereco); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group checkbox-and-input">
                        <div class="form-check">
                            <input type="checkbox" id="nao_possui_numero_casa" name="nao_possui_numero_casa" value="1" <?php echo $nao_possui_numero_casa ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="nao_possui_numero_casa">Cliente não possui número da casa</label>
                        </div>
                        <div id="ncasa_group">
                            <label for="ncasa">Nº Casa:</label>
                            <input type="text" id="ncasa" name="ncasa" value="<?php echo htmlspecialchars($ncasa ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bairro">Bairro:</label>
                        <input type="text" id="bairro" name="bairro" value="<?php echo htmlspecialchars($bairro); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <!-- REMOVIDO: Campo Cidade -->
                    <!-- <div class="form-group">
                        <label for="cidade">Cidade:</label>
                        <input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($cidade); ?>" required>
                    </div> -->
                    <div class="form-group">
                        <label for="cep">CEP:</label>
                        <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($cep); ?>">
                        <small>(Opcional)</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="complemento">Complemento:</label>
                    <input type="text" id="complemento" name="complemento" value="<?php echo htmlspecialchars($complemento ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="ponto_referencia">Ponto de Referência:</label>
                    <input type="text" id="ponto_referencia" name="ponto_referencia" value="<?php echo htmlspecialchars($ponto_referencia ?? ''); ?>">
                    <small>(Opcional)</small>
                </div>

                <div class="action-buttons" style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Atualizar Cliente</button>
                    <a href="clientes.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Voltar para Clientes</a>
                </div>
            </form>
        </div>
    <?php else : ?>
        <p class="message-error">Não foi possível carregar o cliente para edição. <?php echo htmlspecialchars($mensagem); ?></p>
        <div class="action-buttons" style="text-align: center; margin-top: 30px;">
            <a href="clientes.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Voltar para Clientes</a>
        </div>
    <?php endif; ?>

<script>
    // Script para controlar a visibilidade e obrigatoriedade do campo "Nº Casa" e "Complemento"
    const naoPossuiNumeroCasaCheckbox = document.getElementById('nao_possui_numero_casa');
    const ncasaInput = document.getElementById('ncasa');
    const complementoInput = document.getElementById('complemento');
    const pontoReferenciaInput = document.getElementById('ponto_referencia'); // NOVO: Ponto de Referência

    function toggleCamposEndereco() {
        if (naoPossuiNumeroCasaCheckbox.checked) {
            ncasaInput.value = 'S/N'; // Preenche com "S/N"
            ncasaInput.setAttribute('disabled', 'disabled'); // DESABILITA o campo
            ncasaInput.removeAttribute('required'); // Remove obrigatoriedade
            ncasaInput.style.backgroundColor = '#e0e0e0'; // Muda cor para indicar desabilitado
            ncasaInput.style.cursor = 'not-allowed'; // Muda cursor
            
            // Torna complemento OU ponto de referência obrigatório se não houver número da casa
            // Adiciona um listener para garantir que pelo menos um dos dois é preenchido
            // Isso é mais complexo para fazer apenas com required/removeAttribute, então faremos a validação no PHP
            // Aqui, apenas removemos o required de ncasa. A validação de complemento/ponto_referencia será no PHP.
            complementoInput.removeAttribute('required');
            pontoReferenciaInput.removeAttribute('required');
        } else {
            // Ao desmarcar, verifica se o valor atual é 'S/N' para limpar, caso contrário, mantém o que estava
            if (ncasaInput.value === 'S/N') {
                ncasaInput.value = ''; 
            }
            ncasaInput.removeAttribute('disabled'); // HABILITA o campo
            ncasaInput.setAttribute('required', 'required'); // Torna ncasa obrigatório novamente
            ncasaInput.style.backgroundColor = '#fff'; // Retorna cor normal
            ncasaInput.style.cursor = 'auto'; // Retorna cursor normal
            complementoInput.removeAttribute('required'); // Remove a obrigatoriedade de complemento
            pontoReferenciaInput.removeAttribute('required'); // Remove a obrigatoriedade de ponto de referência
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

// Inclui o template principal do painel administrativo.
include '../template_admin.php'; // O template está um nível acima (em 'admin/')
?>
