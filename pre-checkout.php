<?php
ob_start(); // INÍCIO DO BUFFER DE SAÍDA - MUITO IMPORTANTE PARA REDIRECIONAMENTOS
session_start();
error_reporting(E_ALL); // Ative para depuração
ini_set('display_errors', 1); // Ative para depuração

require_once 'includes/conexao.php'; // Caminho para a conexão com o banco de dados

$mensagem_erro = '';
$mensagem_sucesso = '';

// Variáveis para pré-preencher o formulário
$nome_cliente = '';
$telefone_cliente_initial = $_POST['telefone_cliente_initial'] ?? ''; // Telefone digitado inicialmente
$endereco_entrega = '';
$numero_entrega = '';
$bairro_entrega = '';
$cep_entrega = '';
$complemento_entrega = '';
$ponto_referencia_entrega = ''; // Ponto de Referência
$nao_possui_numero_casa = false;
$is_new_client = true; // Assume que é um novo cliente por padrão

// Flags de controle de UI
$show_phone_input_step = true; // Sempre inicia na etapa de telefone no PHP
$show_address_fields = false;
$show_confirmation_popup = false;

// Variáveis para o pop-up de confirmação
$popup_telefone = '';
$popup_nome_cliente = '';

// Função para formatar o número de telefone para o padrão de busca/salvamento no DB (APENAS DÍGITOS)
function formatPhoneNumberForDbSearch($rawPhone) {
    return preg_replace('/\D/', '', $rawPhone); // Remove tudo que não é dígito
}

// Função para formatar o número de telefone para exibição (com parênteses e hífen)
function formatPhoneNumberForDisplay($rawPhone) {
    $cleanPhone = preg_replace('/\D/', '', $rawPhone);
    $length = strlen($cleanPhone);

    if ($length === 11) {
        return '(' . substr($cleanPhone, 0, 2) . ') ' . substr($cleanPhone, 2, 5) . '-' . substr($cleanPhone, 7, 4);
    } elseif ($length === 10) {
        return '(' . substr($cleanPhone, 0, 2) . ') ' . substr($cleanPhone, 2, 4) . '-' . substr($cleanPhone, 6, 4);
    }
    return $cleanPhone; // Retorna sem formatação se não for 10 ou 11 dígitos
}


// Lógica principal do POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    // Obtém o telefone do campo oculto (se for submissão da etapa 2) ou do input visível (etapa 1)
    // Use formatPhoneNumberForDbSearch para garantir que o número esteja limpo para a lógica PHP
    $telefone_raw_from_post = $_POST['telefone_cliente_initial_input'] ?? '';
    $telefone_para_busca_ou_salvar = formatPhoneNumberForDbSearch($telefone_raw_from_post);

    error_log("pre-checkout.php: POST recebido. Ação: " . $action);

    if ($action === 'check_phone') {
        // Validação PHP para o comprimento mínimo do telefone
        if (strlen($telefone_para_busca_ou_salvar) < 10) { // Mínimo de 10 dígitos (DDD + 8 ou 9 dígitos)
            $mensagem_erro = "Por favor, digite um número de telefone válido com DDD (mínimo 10 dígitos).";
            error_log("pre-checkout.php: Erro de validação de telefone: " . $mensagem_erro);
        } elseif (!empty($telefone_para_busca_ou_salvar)) {
            $stmt_check_client = $conn->prepare("SELECT id, nome, telefone, endereco, ncasa, bairro, cep, complemento, ponto_referencia FROM clientes WHERE telefone = ?");

            if ($stmt_check_client === false) {
                $mensagem_erro = "Erro na preparação da consulta de cliente: " . $conn->error;
                error_log("pre-checkout.php: Erro na preparação da consulta de cliente: " . $conn->error);
            } else {
                $stmt_check_client->bind_param("s", $telefone_para_busca_ou_salvar);
                
                if ($stmt_check_client->execute()) {
                    $result_check_client = $stmt_check_client->get_result();

                    if ($result_check_client->num_rows > 0) {
                        $cliente_data = $result_check_client->fetch_assoc();
                        $_SESSION['cliente_id'] = $cliente_data['id'];
                        $popup_nome_cliente = htmlspecialchars($cliente_data['nome']);
                        $is_new_client = false;
                        error_log("pre-checkout.php: Cliente existente encontrado. ID: " . $cliente_data['id']);

                        $_SESSION['temp_client_data'] = [
                            'id' => $cliente_data['id'],
                            'nome' => $cliente_data['nome'],
                            'telefone' => formatPhoneNumberForDisplay($cliente_data['telefone']), // Salva formatado para exibição
                            'endereco' => $cliente_data['endereco'],
                            'numero' => $cliente_data['ncasa'] ?? '',
                            'bairro' => $cliente_data['bairro'],
                            'cep' => $cliente_data['cep'] ?? '',
                            'complemento' => $cliente_data['complemento'] ?? '',
                            'ponto_referencia' => $cliente_data['ponto_referencia'] ?? '',
                            'nao_possui_numero_casa' => ($cliente_data['ncasa'] === NULL || $cliente_data['ncasa'] === 'S/N')
                        ];

                    } else {
                        $_SESSION['cliente_id'] = null;
                        $popup_nome_cliente = '';
                        $is_new_client = true;
                        error_log("pre-checkout.php: Novo cliente. Telefone: " . $telefone_para_busca_ou_salvar);

                        $_SESSION['temp_client_data'] = [
                            'id' => null,
                            'nome' => '',
                            'telefone' => formatPhoneNumberForDisplay($telefone_para_busca_ou_salvar), // Salva formatado para exibição
                            'endereco' => '',
                            'numero' => '',
                            'bairro' => '',
                            'cep' => '',
                            'complemento' => '',
                            'ponto_referencia' => '',
                            'nao_possui_numero_casa' => false
                        ];
                    }
                    $popup_telefone = formatPhoneNumberForDisplay($telefone_para_busca_ou_salvar);
                    // Após processar o check_phone, sempre mostra o popup de confirmação
                    $show_confirmation_popup = true;
                    $show_phone_input_step = false;
                    $show_address_fields = false; // Será ativado após a confirmação do popup
                } else {
                    $mensagem_erro = "Erro ao executar consulta de cliente: " . $stmt_check_client->error;
                    error_log("pre-checkout.php: Erro ao executar consulta de cliente: " . $stmt_check_client->error);
                }
                $stmt_check_client->close();
            }
        } else {
            $mensagem_erro = "Por favor, digite um número de telefone válido.";
            error_log("pre-checkout.php: Erro: Telefone vazio.");
        }
    } elseif ($action === 'confirm_phone_and_continue') {
        // Este bloco agora será acionado APENAS se o botão do modal for clicado
        // após o PHP já ter preenchido $_SESSION['temp_client_data']
        if (isset($_SESSION['temp_client_data'])) {
            $client_data_from_session = $_SESSION['temp_client_data'];
            
            $nome_cliente = htmlspecialchars($client_data_from_session['nome']);
            $telefone_cliente_initial = htmlspecialchars($client_data_from_session['telefone']);
            $endereco_entrega = htmlspecialchars($client_data_from_session['endereco']);
            $numero_entrega = htmlspecialchars($client_data_from_session['numero']);
            $bairro_entrega = htmlspecialchars($client_data_from_session['bairro']);
            $cep_entrega = htmlspecialchars($client_data_from_session['cep']);
            $complemento_entrega = htmlspecialchars($client_data_from_session['complemento']);
            $ponto_referencia_entrega = htmlspecialchars($client_data_from_session['ponto_referencia']);
            $nao_possui_numero_casa = $client_data_from_session['nao_possui_numero_casa'];
            $is_new_client = ($client_data_from_session['id'] === null);

            $show_address_fields = true;
            $show_phone_input_step = false;
            $show_confirmation_popup = false; // Esconde o popup
            unset($_SESSION['temp_client_data']); // Limpa os dados temporários após usá-los
            error_log("pre-checkout.php: Confirmar telefone e continuar. Exibindo campos de endereço.");
        } else {
            // Isso não deveria acontecer se o fluxo estiver correto, mas é um fallback
            $mensagem_erro = "Dados do cliente não encontrados na sessão. Por favor, tente novamente.";
            $show_phone_input_step = true;
            error_log("pre-checkout.php: Erro: Dados do cliente não encontrados na sessão para 'confirm_phone_and_continue'.");
        }
    } elseif ($action === 'confirm_address') {
        $nome_cliente = trim($_POST['nome_cliente']);
        $endereco_entrega = trim($_POST['endereco_entrega']);
        $numero_entrega_post = isset($_POST['numero_entrega']) ? trim($_POST['numero_entrega']) : '';
        $bairro_entrega = trim($_POST['bairro_entrega']);
        $cep_entrega = trim($_POST['cep_entrega']);
        $complemento_entrega = trim($_POST['complemento_entrega']);
        $ponto_referencia_entrega = trim($_POST['ponto_referencia_entrega']);
        $nao_possui_numero_casa = isset($_POST['nao_possui_numero_casa_confirm']) ? true : false;

        // Pega o telefone do campo oculto (já formatado para exibição) e limpa para salvar no DB
        $telefone_para_salvar_display = $_POST['telefone_cliente_initial'];
        $telefone_para_salvar_db = formatPhoneNumberForDbSearch($telefone_para_salvar_display);

        error_log("pre-checkout.php: Ação 'confirm_address'. is_new_client_hidden: " . ($_POST['is_new_client_hidden'] ?? 'N/A'));
        error_log("pre-checkout.php: nao_possui_numero_casa: " . ($nao_possui_numero_casa ? 'true' : 'false') . ", numero_entrega_post: '" . $numero_entrega_post . "'");
        error_log("pre-checkout.php: complemento_entrega: '" . $complemento_entrega . "'");


        if (empty($nome_cliente) || empty($endereco_entrega) || empty($bairro_entrega)) {
            $mensagem_erro = "Por favor, preencha todos os campos obrigatórios (Nome, Endereço, Bairro).";
            error_log("pre-checkout.php: Erro de validação: Campos obrigatórios vazios.");
        } elseif (!$nao_possui_numero_casa && empty($numero_entrega_post)) {
            $mensagem_erro = "O campo 'Número da Casa' é obrigatório, a menos que você marque 'Cliente não possui número da casa'.";
            error_log("pre-checkout.php: Erro de validação: Número da casa vazio e checkbox não marcado.");
        } elseif ($nao_possui_numero_casa && empty($complemento_entrega)) {
            $mensagem_erro = "O campo 'Complemento' é obrigatório quando o cliente não possui número da casa.";
            error_log("pre-checkout.php: Erro de validação: Complemento vazio quando 'não possui número da casa' marcado.");
        } else {
            $numero_entrega_param = $nao_possui_numero_casa ? 'S/N' : ($numero_entrega_post === '' ? NULL : $numero_entrega_post);
            error_log("pre-checkout.php: numero_entrega_param final: " . ($numero_entrega_param ?? 'NULL'));

            $is_new_client = ($_POST['is_new_client_hidden'] === '1');

            if ($is_new_client) {
                $stmt_insert_client = $conn->prepare("INSERT INTO clientes (nome, telefone, endereco, ncasa, bairro, cep, complemento, ponto_referencia, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                if ($stmt_insert_client === false) {
                    $mensagem_erro = "Erro na preparação do INSERT de cliente: " . $conn->error;
                    error_log("pre-checkout.php: Erro na preparação do INSERT: " . $conn->error);
                } else {
                    $stmt_insert_client->bind_param("ssssssss", $nome_cliente, $telefone_para_salvar_db, $endereco_entrega, $numero_entrega_param, $bairro_entrega, $cep_entrega, $complemento_entrega, $ponto_referencia_entrega);

                    if ($stmt_insert_client->execute()) {
                        $_SESSION['cliente_id'] = $conn->insert_id;
                        $mensagem_sucesso = "Cliente cadastrado e endereço confirmado!";
                        $_SESSION['checkout_cliente_data'] = [
                            'id' => $_SESSION['cliente_id'],
                            'nome' => $nome_cliente,
                            'telefone' => $telefone_para_salvar_db, // Salva o telefone limpo para o checkout-payment
                            'endereco' => $endereco_entrega,
                            'numero' => $numero_entrega_param,
                            'bairro' => $bairro_entrega,
                            'cep' => $cep_entrega,
                            'complemento' => $complemento_entrega,
                            'ponto_referencia' => $ponto_referencia_entrega
                        ];
                        error_log("pre-checkout.php: Cliente novo cadastrado com sucesso. Redirecionando para checkout-payment.php");
                        ob_end_clean(); // Limpa o buffer antes do redirecionamento
                        header("Location: checkout-payment.php");
                        exit();
                    } else {
                        $mensagem_erro = "Erro ao cadastrar cliente: " . $stmt_insert_client->error;
                        error_log("pre-checkout.php: Erro ao cadastrar cliente: " . $stmt_insert_client->error);
                    }
                    $stmt_insert_client->close();
                }
            } else { // Cliente existente
                $stmt_update_client = $conn->prepare("UPDATE clientes SET nome = ?, endereco = ?, ncasa = ?, bairro = ?, cep = ?, complemento = ?, ponto_referencia = ? WHERE id = ?");
                
                if ($stmt_update_client === false) {
                    $mensagem_erro = "Erro na preparação do UPDATE de cliente: " . $conn->error;
                    error_log("pre-checkout.php: Erro na preparação do UPDATE: " . $conn->error);
                } else {
                    $stmt_update_client->bind_param("sssssssi", $nome_cliente, $endereco_entrega, $numero_entrega_param, $bairro_entrega, $cep_entrega, $complemento_entrega, $ponto_referencia_entrega, $_SESSION['cliente_id']);

                    if ($stmt_update_client->execute()) {
                        $mensagem_sucesso = "Endereço atualizado e confirmado!";
                        $_SESSION['checkout_cliente_data'] = [
                            'id' => $_SESSION['cliente_id'],
                            'nome' => $nome_cliente,
                            'telefone' => $telefone_para_salvar_db, // Salva o telefone limpo para o checkout-payment
                            'endereco' => $endereco_entrega,
                            'numero' => $numero_entrega_param,
                            'bairro' => $bairro_entrega,
                            'cep' => $cep_entrega,
                            'complemento' => $complemento_entrega,
                            'ponto_referencia' => $ponto_referencia_entrega
                        ];
                        error_log("pre-checkout.php: Cliente existente atualizado com sucesso. Redirecionando para checkout-payment.php");
                        ob_end_clean(); // Limpa o buffer antes do redirecionamento
                        header("Location: checkout-payment.php");
                        exit();
                    } else {
                        $mensagem_erro = "Erro ao atualizar endereço: " . $stmt_update_client->error;
                        error_log("pre-checkout.php: Erro ao atualizar endereço: " . $stmt_update_client->error);
                    }
                    $stmt_update_client->close();
                }
            }
        }
        // Se houver erro na validação ou no DB, exibe a etapa de endereço novamente
        if (!empty($mensagem_erro)) {
            $show_address_fields = true;
            $show_phone_input_step = false;
            error_log("pre-checkout.php: Erro detectado, mantendo na etapa de endereço. Mensagem: " . $mensagem_erro);
        }
    }
} else { // Requisição GET
    // No GET, sempre inicia na etapa de telefone.
    // Se houver dados temporários na sessão (vindo de um POST 'check_phone' anterior),
    // eles serão usados para preencher o modal se o JS decidir exibi-lo.
    $show_phone_input_step = true; // Garante que a etapa de telefone seja a padrão no GET
    $show_address_fields = false;
    $show_confirmation_popup = false; // O pop-up só será ativado via JS após a interação do usuário
    error_log("pre-checkout.php: GET recebido. Iniciando na etapa de telefone.");
}

$conn->close();

$js_show_phone_input_step = $show_phone_input_step ? 'true' : 'false';
$js_show_address_fields = $show_address_fields ? 'true' : 'false';
$js_show_confirmation_popup = $show_confirmation_popup ? 'true' : 'false';
$js_is_new_client = $is_new_client ? '1' : '0';

// Ajusta as flags JS para garantir que a etapa correta seja exibida em caso de erro
if (!empty($mensagem_erro) && ($action === 'confirm_address' || $show_address_fields)) {
    $js_show_address_fields = 'true';
    $js_show_phone_input_step = 'false';
    $js_show_confirmation_popup = 'false';
}

// Se não houver erro e nenhuma ação POST, assume que é a primeira carga da página
// e o JS vai preencher o campo de telefone se houver localStorage
if (empty($mensagem_erro) && empty($action) && !$show_confirmation_popup && !$show_address_fields) {
    $js_show_phone_input_step = 'true';
}

// FIM DO BUFFER DE SAÍDA - APENAS AQUI O CONTEÚDO HTML É ENVIADO
ob_end_flush(); 
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Confirmação de Endereço</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="public/css/cardapio.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .checkout-container {
            max-width: 500px;
            margin: 40px auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            flex-grow: 1; /* Permite que o container cresça e ocupe o espaço */
        }

        h1 {
            text-align: center;
            color: #FF5722;
            margin-bottom: 30px;
            font-size: 2em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        input[type="text"],
        input[type="tel"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            box-sizing: border-box; /* Garante que o padding não aumente a largura total */
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        input[type="text"]:focus,
        input[type="tel"]:focus,
        input[type="number"]:focus,
        textarea:focus {
            border-color: #FF5722;
            box-shadow: 0 0 0 3px rgba(255, 87, 34, 0.2);
            outline: none;
        }

        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .form-check input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
            accent-color: #FF5722;
        }

        .form-check-label {
            font-weight: normal;
            color: #333;
            cursor: pointer;
        }

        .btn-primary, .btn-secondary, .btn-edit-phone {
            display: block;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            text-align: center;
            text-decoration: none;
        }

        .btn-primary {
            background-color: #FF5722;
            color: white;
            margin-bottom: 15px;
        }

        .btn-primary:hover {
            background-color: #E64A19;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* Ajustes para o novo botão de edição */
        .btn-edit-phone {
            background-color: var(--primary-color); /* Usando a variável CSS para laranja */
            color: white;
            padding: 10px 18px; /* Aumentado o padding para deixar maior */
            font-size: 1em; /* Aumentado o tamanho da fonte */
            width: auto; /* Ajusta a largura ao conteúdo */
            display: inline-flex; /* Para alinhar o ícone e o texto */
            align-items: center;
            justify-content: center;
            gap: 8px; /* Espaçamento entre ícone e texto */
            margin-left: 10px; /* Espaçamento à esquerda */
            vertical-align: middle; /* Alinha com o input */
        }

        .btn-edit-phone:hover {
            background-color: #E64A19; /* Laranja mais escuro no hover */
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .message-error, .message-success {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }

        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .phone-input-step, .address-input-step {
            display: none; /* Escondido por padrão, JS controla a visibilidade */
        }

        .phone-input-step.active, .address-input-step.active {
            display: block;
        }

        small {
            color: #777;
            font-size: 0.85em;
            margin-top: 5px;
            display: block;
        }

        /* Estilos para o campo de número da casa quando desabilitado */
        input[type="text"][disabled] {
            background-color: #e0e0e0;
            cursor: not-allowed;
        }

        /* Estilos do Modal de Confirmação */
        .confirmation-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center; /* Centraliza horizontalmente */
            align-items: center; /* Centraliza verticalmente */
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .confirmation-modal-overlay.visible {
            opacity: 1;
            visibility: visible;
        }

        .confirmation-modal-content {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 400px; /* Limita a largura máxima do modal */
            width: 90%; /* Ocupa 90% da largura disponível, até o max-width */
            transform: scale(0.9); /* Inicia menor para um efeito de zoom-in */
            transition: transform 0.3s ease;
        }

        .confirmation-modal-overlay.visible .confirmation-modal-content {
            transform: scale(1); /* Volta ao tamanho normal */
        }

        .confirmation-modal-content h2 {
            color: #FF5722;
            margin-bottom: 20px;
            font-size: 1.8em;
        }

        .confirmation-modal-content p {
            font-size: 1.1em;
            margin-bottom: 15px;
            color: #555;
        }

        .confirmation-modal-content p strong {
            color: #333;
        }

        .confirmation-modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-direction: column; /* Empilha botões em telas menores */
        }

        .confirmation-modal-actions .btn-primary,
        .confirmation-modal-actions .btn-secondary {
            width: 100%;
            padding: 12px;
            font-size: 1em;
        }

        /* Novo estilo para o loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8); /* Fundo branco semi-transparente */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1001; /* Acima do modal de confirmação */
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            flex-direction: column; /* Para empilhar texto e spinner */
        }

        .loading-overlay.visible {
            opacity: 1;
            visibility: visible;
        }

        .loading-overlay .spinner {
            border: 4px solid #f3f3f3; /* Light grey */
            border-top: 4px solid #FF5722; /* Primary color */
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }

        .loading-overlay p {
            font-size: 1.1em;
            color: #555;
            font-weight: 600;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Estilo para o grupo de input e botão de edição */
        .phone-display-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px; /* Espaçamento entre o input e o botão */
        }

        .phone-display-group input#telefone_cliente_display {
            flex-grow: 0; /* Impede que o input ocupe todo o espaço */
            width: auto; /* Permite que a largura seja definida pelo conteúdo ou max-width */
            max-width: 180px; /* Largura máxima para o campo de telefone */
        }

        /* Novo estilo para o Modal de Erro */
        .error-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1002; /* Acima de outros modais */
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .error-modal-overlay.visible {
            opacity: 1;
            visibility: visible;
        }

        .error-modal-content {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 400px;
            width: 90%;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .error-modal-overlay.visible .error-modal-content {
            transform: translateY(0);
        }

        .error-modal-content h2 {
            color: #dc3545; /* Vermelho para erro */
            margin-bottom: 20px;
            font-size: 1.8em;
        }

        .error-modal-content p {
            font-size: 1.1em;
            margin-bottom: 25px;
            color: #555;
        }

        .error-modal-content .btn-ok {
            background-color: #dc3545; /* Vermelho para o botão OK */
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .error-modal-content .btn-ok:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        /* Responsividade */
        @media (max-width: 600px) {
            .checkout-container {
                margin: 20px 15px;
                padding: 20px;
            }

            h1 {
                font-size: 1.8em;
                margin-bottom: 20px;
            }

            .btn-primary, .btn-secondary {
                padding: 12px;
                font-size: 1em;
            }
            .confirmation-modal-content, .error-modal-content {
                padding: 20px;
            }
            .confirmation-modal-content h2, .error-modal-content h2 {
                font-size: 1.5em;
            }
            .confirmation-modal-content p, .error-modal-content p {
                font-size: 1em;
            }
            .phone-display-group {
                flex-direction: column; /* Empilha em telas pequenas */
                align-items: stretch; /* Estica os itens */
            }
            .phone-display-group .btn-edit-phone {
                width: 100%; /* Botão ocupa a largura total */
                margin-left: 0; /* Remove margem lateral */
            }
            .phone-display-group input#telefone_cliente_display {
                max-width: 100%; /* Em mobile, ocupa a largura total disponível */
            }
        }
    </style>
</head>
<body>

    <div class="checkout-container">
        <h1>Finalizar Pedido</h1>

        <?php if (!empty($mensagem_erro)): ?>
            <!-- A mensagem de erro agora será exibida via JS no modal de erro -->
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const errorModalOverlay = document.getElementById('errorModalOverlay');
                    const errorModalMessage = document.getElementById('errorModalMessage');
                    errorModalMessage.textContent = '<?= $mensagem_erro ?>';
                    errorModalOverlay.classList.add('visible');
                });
            </script>
        <?php endif; ?>
        <?php if (!empty($mensagem_sucesso)): ?>
            <p class="message-success"><?= $mensagem_sucesso ?></p>
        <?php endif; ?>

        <form id="preCheckoutForm" method="POST" action="pre-checkout.php">
            <input type="hidden" name="action" id="formActionInput" value="">
            <input type="hidden" name="is_new_client_hidden" id="isNewClientHiddenInput" value="<?= $is_new_client ? '1' : '0' ?>">
            <!-- Campo oculto para manter o telefone original ao submeter o formulário de endereço -->
            <input type="hidden" name="telefone_cliente_initial" id="telefoneClienteInitialHidden" value="<?= htmlspecialchars($telefone_cliente_initial) ?>">

            <!-- Etapa 1: Telefone -->
            <div id="phoneInputStep" class="phone-input-step">
                <div class="form-group">
                    <label for="telefone_cliente_initial_input">Seu Telefone:</label>
                    <input type="tel" id="telefone_cliente_initial_input" name="telefone_cliente_initial_input"
                           placeholder="(XX) XXXXX-XXXX" maxlength="15" inputmode="numeric" required
                           value="<?= htmlspecialchars($telefone_cliente_initial) ?>">
                    <small>Digite seu telefone com DDD (mínimo 10 dígitos) para buscar seu cadastro ou iniciar um novo cadastro.</small>
                </div>
                <button type="button" id="btnCheckPhone" class="btn-primary">Continuar</button>
                <!-- Novo botão "Voltar para o Carrinho" -->
                <button type="button" id="btnBackToCart" class="btn-secondary">Voltar para o Carrinho</button>
            </div>

            <!-- Etapa 2: Endereço -->
            <div id="addressInputStep" class="address-input-step">
                <p>Confirme ou preencha seus dados de entrega:</p>
                
                <!-- Telefone exibido com botão de edição -->
                <div class="form-group">
                    <label for="telefone_cliente_display">Telefone:</label>
                    <div class="phone-display-group">
                        <input type="tel" id="telefone_cliente_display"
                               value="<?= htmlspecialchars($telefone_cliente_initial) ?>" readonly>
                        <button type="button" id="btnEditPhoneFromAddress" class="btn-edit-phone">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nome_cliente">Nome Completo:</label>
                    <input type="text" id="nome_cliente" name="nome_cliente" required
                           value="<?= htmlspecialchars($nome_cliente) ?>">
                </div>

                <div class="form-group">
                    <label for="endereco_entrega">Endereço (Rua, Av.):</label>
                    <input type="text" id="endereco_entrega" name="endereco_entrega" required
                           value="<?= htmlspecialchars($endereco_entrega) ?>">
                </div>

                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" id="nao_possui_numero_casa_confirm" name="nao_possui_numero_casa_confirm" value="1"
                               <?= $nao_possui_numero_casa ? 'checked' : '' ?>>
                        <label class="form-check-label" for="nao_possui_numero_casa_confirm">Cliente não possui número da casa</label>
                    </div>
                    <label for="numero_entrega">Número da Casa:</label>
                    <input type="text" id="numero_entrega" name="numero_entrega"
                           value="<?= htmlspecialchars($numero_entrega) ?>"
                           <?= $nao_possui_numero_casa ? 'disabled' : '' ?>>
                </div>

                <div class="form-group">
                    <label for="bairro_entrega">Bairro:</label>
                    <input type="text" id="bairro_entrega" name="bairro_entrega" required
                           value="<?= htmlspecialchars($bairro_entrega) ?>">
                </div>

                <div class="form-group">
                    <label for="cep_entrega">CEP (Opcional):</label>
                    <input type="text" id="cep_entrega" name="cep_entrega"
                           value="<?= htmlspecialchars($cep_entrega) ?>">
                </div>

                <div class="form-group">
                    <label for="complemento_entrega">Complemento:</label>
                    <input type="text" id="complemento_entrega" name="complemento_entrega"
                           value="<?= htmlspecialchars($complemento_entrega) ?>">
                    <small>Ex: Apartamento 101, Bloco B (Obrigatório se não tiver número da casa)</small>
                </div>

                <div class="form-group">
                    <label for="ponto_referencia_entrega">Ponto de Referência (Opcional):</label>
                    <input type="text" id="ponto_referencia_entrega" name="ponto_referencia_entrega"
                           value="<?= htmlspecialchars($ponto_referencia_entrega) ?>">
                    <small>Ex: Próximo à padaria, em frente ao colégio.</small>
                </div>

                <button type="submit" id="btnConfirmAddress" class="btn-primary">Confirmar Endereço e Continuar</button>
                <button type="button" id="btnBackToPhone" class="btn-secondary">Voltar</button>
            </div>
        </form>
    </div>

    <!-- Modal de Confirmação de Telefone -->
    <div id="confirmationModalOverlay" class="confirmation-modal-overlay">
        <div class="confirmation-modal-content">
            <h2>Confirmar Telefone</h2>
            <p>Telefone: <strong><span id="modalTelefone"></span></strong></p>
            <p>Nome: <strong><span id="modalNomeCliente"></span></strong></p>
            <div class="confirmation-modal-actions">
                <button type="button" id="btnConfirmPhone" class="btn-primary">Confirmar</button>
                <button type="button" id="btnEditPhone" class="btn-secondary">Editar Telefone</button>
            </div>
        </div>
    </div>

    <!-- Novo: Overlay de Carregamento -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
        <p>Carregando...</p>
    </div>

    <!-- NOVO: Modal de Erro Genérico -->
    <div id="errorModalOverlay" class="error-modal-overlay">
        <div class="error-modal-content">
            <h2>Erro!</h2>
            <p id="errorModalMessage"></p>
            <button type="button" id="btnErrorModalOk" class="btn-ok">Entendi</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const preCheckoutForm = document.getElementById('preCheckoutForm');
            const formActionInput = document.getElementById('formActionInput');
            const isNewClientHiddenInput = document.getElementById('isNewClientHiddenInput');
            const telefoneClienteInitialHidden = document.getElementById('telefoneClienteInitialHidden');

            const phoneInputStep = document.getElementById('phoneInputStep');
            const addressInputStep = document.getElementById('addressInputStep');
            const btnCheckPhone = document.getElementById('btnCheckPhone');
            const btnBackToPhone = document.getElementById('btnBackToPhone');
            const btnBackToCart = document.getElementById('btnBackToCart');

            const telefoneClienteInitialInput = document.getElementById('telefone_cliente_initial_input');
            const telefoneClienteDisplay = document.getElementById('telefone_cliente_display');
            const btnEditPhoneFromAddress = document.getElementById('btnEditPhoneFromAddress');

            const naoPossuiNumeroCasaConfirm = document.getElementById('nao_possui_numero_casa_confirm');
            const numeroEntregaInput = document.getElementById('numero_entrega');
            const complementoEntregaInput = document.getElementById('complemento_entrega'); // Referência ao campo complemento
            const pontoReferenciaEntregaInput = document.getElementById('ponto_referencia_entrega');

            // Elementos do Modal de Confirmação
            const confirmationModalOverlay = document.getElementById('confirmationModalOverlay');
            const modalTelefone = document.getElementById('modalTelefone');
            const modalNomeCliente = document.getElementById('modalNomeCliente');
            const btnConfirmPhone = document.getElementById('btnConfirmPhone');
            const btnEditPhone = document.getElementById('btnEditPhone');

            // Elemento do Loading Overlay
            const loadingOverlay = document.getElementById('loadingOverlay');

            // Elementos do Novo Modal de Erro
            const errorModalOverlay = document.getElementById('errorModalOverlay');
            const errorModalMessage = document.getElementById('errorModalMessage');
            const btnErrorModalOk = document.getElementById('btnErrorModalOk');

            // NOVO: Referência ao botão de confirmação de endereço
            const btnConfirmAddress = document.getElementById('btnConfirmAddress');


            // Variáveis PHP passadas para o JS (mantidas para o fluxo inicial do PHP)
            const jsShowPhoneInputStep = <?= $js_show_phone_input_step ?>;
            const jsShowAddressFields = <?= $js_show_address_fields ?>;
            const jsShowConfirmationPopup = <?= $js_show_confirmation_popup ?>;
            const jsIsNewClient = <?= $js_is_new_client ?>;
            const jsPopupTelefone = '<?= $popup_telefone ?>';
            const jsPopupNomeCliente = '<?= $popup_nome_cliente ?>';
            const jsTelefoneClienteInitial = '<?= htmlspecialchars($telefone_cliente_initial) ?>';

            // Função para exibir o modal de erro
            function showErrorMessage(message) {
                errorModalMessage.textContent = message;
                errorModalOverlay.classList.add('visible');
            }

            // Evento para fechar o modal de erro
            btnErrorModalOk.addEventListener('click', () => {
                errorModalOverlay.classList.remove('visible');
            });

            // Função para formatar o número de telefone para exibição (igual ao PHP)
            function formatPhoneNumberForDisplay(rawPhone) {
                const cleanPhone = rawPhone.replace(/\D/g, '');
                if (cleanPhone.length === 11) {
                    return `(${cleanPhone.substring(0, 2)}) ${cleanPhone.substring(2, 7)}-${cleanPhone.substring(7, 11)}`;
                } else if (cleanPhone.length === 10) {
                    return `(${cleanPhone.substring(0, 2)}) ${cleanPhone.substring(2, 6)}-${cleanPhone.substring(6, 10)}`;
                }
                return rawPhone;
            }

            // Função para aplicar/remover readonly e disabled nos campos de endereço
            function setAddressFieldsReadonly(isReadonly) {
                const fields = ['nome_cliente', 'endereco_entrega', 'bairro_entrega', 'cep_entrega', 'complemento_entrega', 'ponto_referencia_entrega'];
                fields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        if (isReadonly) {
                            field.setAttribute('readonly', 'readonly');
                            field.style.backgroundColor = '#f0f0f0';
                            field.style.cursor = 'not-allowed';
                        } else {
                            field.removeAttribute('readonly');
                            field.style.backgroundColor = '#fff';
                            field.style.cursor = 'auto';
                        }
                    }
                });

                if (isReadonly) {
                    naoPossuiNumeroCasaConfirm.setAttribute('disabled', 'disabled');
                    numeroEntregaInput.setAttribute('readonly', 'readonly');
                    numeroEntregaInput.style.backgroundColor = '#f0f0f0';
                    numeroEntregaInput.style.cursor = 'not-allowed';
                } else {
                    naoPossuiNumeroCasaConfirm.removeAttribute('disabled');
                }
            }

            // Função para controlar o campo de número da casa e complemento
            function toggleNumeroCasaField() {
                if (naoPossuiNumeroCasaConfirm.checked) {
                    numeroEntregaInput.value = 'S/N';
                    numeroEntregaInput.setAttribute('disabled', 'disabled');
                    numeroEntregaInput.style.backgroundColor = '#e0e0e0';
                    numeroEntregaInput.style.cursor = 'not-allowed';
                    complementoEntregaInput.setAttribute('required', 'required'); // Complemento se torna obrigatório
                } else {
                    if (numeroEntregaInput.value === 'S/N') {
                        numeroEntregaInput.value = '';
                    }
                    numeroEntregaInput.removeAttribute('disabled');
                    numeroEntregaInput.style.backgroundColor = '#fff';
                    numeroEntregaInput.style.cursor = 'auto';
                    complementoEntregaInput.removeAttribute('required'); // Complemento volta a ser opcional
                }
            }

            // Aplica a máscara de telefone
            telefoneClienteInitialInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);
                if (value.length >= 2 && value.length <= 6) {
                    value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
                } else if (value.length > 6) {
                    value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
                }
                e.target.value = value;
            });

            // Evento para o botão "Continuar" (busca telefone)
            btnCheckPhone.addEventListener('click', () => {
                const cleanedPhone = telefoneClienteInitialInput.value.replace(/\D/g, '');
                if (cleanedPhone.length < 10) { // Validação mínima de 10 dígitos
                    showErrorMessage("Por favor, digite um número de telefone válido com DDD (mínimo 10 dígitos).");
                    return; // Impede a submissão
                }

                // Salva o telefone e nome no localStorage ANTES de submeter
                localStorage.setItem('clientPhone', cleanedPhone);
                // O nome do cliente só será conhecido após a resposta do PHP, mas podemos salvar uma string vazia ou um placeholder
                localStorage.setItem('clientName', ''); // Será atualizado pelo PHP se o cliente for encontrado

                telefoneClienteInitialHidden.value = telefoneClienteInitialInput.value; // Mantém o valor formatado para o PHP

                loadingOverlay.classList.add('visible');
                btnCheckPhone.setAttribute('disabled', 'disabled');

                // Simula a submissão do formulário para que o PHP processe
                formActionInput.value = 'check_phone';
                preCheckoutForm.submit();
            });

            // Evento para o botão "Voltar" (volta para a etapa de telefone)
            btnBackToPhone.addEventListener('click', () => {
                phoneInputStep.classList.add('active');
                addressInputStep.classList.remove('active');
                // Limpa os campos de endereço ao voltar
                document.getElementById('nome_cliente').value = '';
                document.getElementById('endereco_entrega').value = '';
                document.getElementById('numero_entrega').value = '';
                document.getElementById('bairro_entrega').value = '';
                document.getElementById('cep_entrega').value = '';
                document.getElementById('complemento_entrega').value = '';
                document.getElementById('ponto_referencia_entrega').value = '';
                naoPossuiNumeroCasaConfirm.checked = false;
                toggleNumeroCasaField();
                // REMOVIDO: localStorage.removeItem('clientPhone');
                // REMOVIDO: localStorage.removeItem('clientName');
            });

            // Evento para o botão "Editar Telefone" no formulário de endereço
            btnEditPhoneFromAddress.addEventListener('click', () => {
                phoneInputStep.classList.add('active');
                addressInputStep.classList.remove('active');
                confirmationModalOverlay.classList.remove('visible');
                // REMOVIDO: localStorage.removeItem('clientPhone');
                // REMOVIDO: localStorage.removeItem('clientName');
            });

            // Evento para o botão "Voltar para o Carrinho"
            btnBackToCart.addEventListener('click', () => {
                window.location.href = 'carrinho.php';
            });

            // Quando o checkbox "Cliente não possui número da casa" muda
            naoPossuiNumeroCasaConfirm.addEventListener('change', toggleNumeroCasaField);

            // Eventos do Modal de Confirmação
            btnConfirmPhone.addEventListener('click', () => {
                // Ao confirmar, o JS simula o POST para 'confirm_phone_and_continue'
                // Pega o telefone LIMPO do localStorage para garantir que o PHP receba o formato correto
                const phoneForConfirmation = localStorage.getItem('clientPhone');
                if (phoneForConfirmation) {
                    telefoneClienteInitialInput.value = phoneForConfirmation; // Define o valor do input para o telefone LIMPO
                } else {
                    // Fallback: se por algum motivo não estiver no localStorage, usa o valor atual do input
                    console.warn("clientPhone não encontrado no localStorage para confirmação. Usando valor atual do input.");
                    telefoneClienteInitialInput.value = telefoneClienteInitialInput.value.replace(/\D/g, ''); // Limpa o valor atual
                }

                formActionInput.value = 'confirm_phone_and_continue';
                confirmationModalOverlay.classList.remove('visible');
                loadingOverlay.classList.add('visible'); // Mostra o loading antes de submeter
                preCheckoutForm.submit();
            });

            btnEditPhone.addEventListener('click', () => {
                confirmationModalOverlay.classList.remove('visible');
                phoneInputStep.classList.add('active');
                addressInputStep.classList.remove('active');
                telefoneClienteInitialInput.value = ''; // Limpa o campo para nova digitação
                // REMOVIDO: localStorage.removeItem('clientPhone');
                // REMOVIDO: localStorage.removeItem('clientName');
            });

            // NOVO: Evento para o botão "Confirmar Endereço e Continuar"
            btnConfirmAddress.addEventListener('click', (event) => {
                // Define a ação antes de permitir a submissão do formulário
                formActionInput.value = 'confirm_address';
                // O formulário será submetido normalmente, não precisa de preCheckoutForm.submit() aqui
            });


            // --- Lógica de inicialização da UI baseada nas flags PHP e localStorage ---
            // Sempre inicia na etapa de telefone
            phoneInputStep.classList.add('active');
            addressInputStep.classList.remove('active');
            confirmationModalOverlay.classList.remove('visible');
            loadingOverlay.classList.remove('visible'); // Esconde o loading inicial
            btnCheckPhone.removeAttribute('disabled'); // Garante que o botão não esteja desabilitado

            // Se o PHP indicou que o pop-up deve ser mostrado (após um POST de check_phone)
            if (jsShowConfirmationPopup) {
                phoneInputStep.classList.remove('active');
                addressInputStep.classList.remove('active');
                confirmationModalOverlay.classList.add('visible');
                modalTelefone.textContent = jsPopupTelefone;
                modalNomeCliente.textContent = jsPopupNomeCliente === '' ? 'Novo Cliente' : jsPopupNomeCliente;
            } else if (jsShowAddressFields) {
                // Se o PHP indicou para mostrar os campos de endereço (após confirm_phone_and_continue)
                phoneInputStep.classList.remove('active');
                addressInputStep.classList.add('active');
                setAddressFieldsReadonly(jsIsNewClient === '0');
                toggleNumeroCasaField();
                telefoneClienteDisplay.value = jsTelefoneClienteInitial;
            } else {
                // Se não há flags PHP ativas para outras etapas, e há dados no localStorage, preenche o campo de telefone
                const savedClientPhone = localStorage.getItem('clientPhone');
                if (savedClientPhone) {
                    telefoneClienteInitialInput.value = formatPhoneNumberForDisplay(savedClientPhone);
                    // O campo hidden também precisa do valor formatado para o PHP processar
                    telefoneClienteInitialHidden.value = formatPhoneNumberForDisplay(savedClientPhone); 
                }
            }
        });
    </script>
</body>
</html>
