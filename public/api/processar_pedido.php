<?php
session_start();
header('Content-Type: application/json');

require_once '../../includes/conexao.php';

function enviarNotificacaoWhatsApp($telefone_destino, $dados_para_template)
{
    $url = 'http://localhost:3000/enviar-notificacao';
    $dados_post = [
        'telefone_destino' => $telefone_destino,
        'mensagem' => $dados_para_template
    ];
    $payload = json_encode($dados_post);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    @curl_exec($ch);
    curl_close($ch);
}

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data === null) {
    $response['message'] = 'Dados da requisição inválidos.';
    echo json_encode($response);
    exit();
}

$cliente_data = $data['cliente_data'] ?? [];
$carrinho = $data['carrinho'] ?? [];
$total_pedido = $data['total_pedido'] ?? 0;
$forma_pagamento = $data['forma_pagamento'] ?? 'dinheiro';
$troco_para = $data['troco_para'] ?? null;
$troco = $data['troco'] ?? null;
$observacoes_pedido = $data['observacoes_pedido'] ?? '';

if (empty($cliente_data) || empty($carrinho) || $total_pedido <= 0) {
    $response['message'] = 'Dados essenciais do pedido estão faltando.';
    echo json_encode($response);
    exit();
}

$conn->begin_transaction();

try {
    // Insere o Pedido Principal
    $stmt_pedido = $conn->prepare("INSERT INTO pedidos (id_cliente, nome_cliente, telefone_cliente, endereco_entrega, numero_entrega, bairro_entrega, complemento_entrega, referencia_entrega, data_pedido, total_pedido, forma_pagamento, troco_para, troco, observacoes_pedido, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, 'pendente')");
    $id_cliente = $cliente_data['id'] ?? null;
    $stmt_pedido->bind_param("isssssssdssss", $id_cliente, $cliente_data['nome'], $cliente_data['telefone'], $cliente_data['endereco'], $cliente_data['numero'], $cliente_data['bairro'], $cliente_data['complemento'], $cliente_data['ponto_referencia'], $total_pedido, $forma_pagamento, $troco_para, $troco, $observacoes_pedido);
    if (!$stmt_pedido->execute()) {
        throw new Exception("Erro ao inserir pedido principal: " . $stmt_pedido->error);
    }
    $pedido_id = $conn->insert_id;
    $stmt_pedido->close();

    // Insere Itens e Atualiza Estoque
    $stmt_item = $conn->prepare("INSERT INTO itens_pedido (id_pedido, id_produto, nome_produto, quantidade, preco_unitario, observacao_item, detalhes_opcoes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_update_estoque = $conn->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND controla_estoque = 1");

    foreach ($carrinho as $item) {
        // 1. Prepara um texto com todos os detalhes do item
        $detalhes_texto_final = '';
        $detalhes_array = [];
        if (!empty($item['opcoes']) && is_array($item['opcoes'])) {
            foreach ($item['opcoes'] as $opcao) {
                $detalhes_array[] = '+ ' . htmlspecialchars($opcao['nome']);
            }
        }
        // Junta todos os detalhes com uma quebra de linha HTML para exibir depois
        $detalhes_texto_final = implode('<br>', $detalhes_array);

        // 2. Insere o item no banco de dados, agora com os detalhes na nova coluna
        $stmt_item->bind_param("iisidss", $pedido_id, $item['id'], $item['nome'], $item['quantidade'], $item['preco_unitario'], $item['obs'], $detalhes_texto_final);
        if (!$stmt_item->execute()) {
            throw new Exception("Erro ao inserir item do pedido: " . $stmt_item->error);
        }

        // 3. Atualiza o estoque do produto principal (lógica mantida)
        $stmt_check_estoque = $conn->prepare("SELECT estoque, controla_estoque FROM produtos WHERE id = ?");
        $stmt_check_estoque->bind_param("i", $item['id']);
        $stmt_check_estoque->execute();
        $produto_info = $stmt_check_estoque->get_result()->fetch_assoc();
        $stmt_check_estoque->close();

        if ($produto_info && $produto_info['controla_estoque'] == 1) {
            if ($produto_info['estoque'] < $item['quantidade']) {
                throw new Exception("Estoque insuficiente para: " . $item['nome']);
            }
            $stmt_update_estoque->bind_param("ii", $item['quantidade'], $item['id']);
            if (!$stmt_update_estoque->execute()) {
                throw new Exception("Erro ao atualizar estoque para: " . $item['nome']);
            }
        }
        // >>> SUBSTITUA PELO CÓDIGO ABAIXO <<<

        // --- NOVA VERIFICAÇÃO DE ESTOQUE INTELIGENTE ---
        if ($produto_info && $produto_info['controla_estoque'] == 1) {
            $estoque_anterior = (int)$produto_info['estoque'];
            $estoque_novo = $estoque_anterior - $item['quantidade'];

            $mensagem_notificacao = ''; // Inicia a mensagem como vazia

            // Defina aqui seus níveis de alerta. Pode adicionar mais se quiser.
            $nivel_alerta_critico = 5;
            $nivel_alerta_zerado = 0;

            // 1. Alerta quando o estoque ACABA
            if ($estoque_novo <= $nivel_alerta_zerado && $estoque_anterior > $nivel_alerta_zerado) {
                $mensagem_notificacao = "O estoque de '" . $item['nome'] . "' acabou e o produto foi desativado!";

                // Opcional, mas recomendado: desativa o produto automaticamente
                $stmt_desativar = $conn->prepare("UPDATE produtos SET ativo = 0 WHERE id = ?");
                $stmt_desativar->bind_param("i", $item['id']);
                $stmt_desativar->execute();
                $stmt_desativar->close();
            }
            // 2. Alerta quando entra no nível CRÍTICO (ex: cruza a barreira de 5 unidades)
            elseif ($estoque_novo <= $nivel_alerta_critico && $estoque_anterior > $nivel_alerta_critico) {
                $mensagem_notificacao = "Atenção: Estoque de '" . $item['nome'] . "' está crítico! Restam apenas " . $estoque_novo . " unidades.";
            }

            // 3. Se uma mensagem de notificação foi definida, insere ela no banco de dados
            if (!empty($mensagem_notificacao)) {
                $link_notificacao = "/pdv/admin/produtos/produtos.php"; // Link para a listagem de produtos
                $stmt_notificacao = $conn->prepare(
                    "INSERT INTO notificacoes (tipo, mensagem, link) VALUES ('estoque_baixo', ?, ?)"
                );
                $stmt_notificacao->bind_param("ss", $mensagem_notificacao, $link_notificacao);
                $stmt_notificacao->execute();
                $stmt_notificacao->close();
            }
        }
        // --- FIM DA NOVA VERIFICAÇÃO ---
    }
    $stmt_item->close();
    $stmt_update_estoque->close();
    // --- FIM DA SEÇÃO CORRIGIDA ---

    $conn->commit();
    // --- CÓDIGO DO BOT (COM A CORREÇÃO FINAL) ---
    $itens_params = [];
    foreach ($carrinho as $item) {
        if (count($itens_params) < 5) {
            $item_formatado = "➡ " . htmlspecialchars($item['quantidade']) . "x " . htmlspecialchars($item['nome']);
            if (!empty($item['adicionais']) && is_array($item['adicionais'])) {
                foreach ($item['adicionais'] as $adicional) {
                    $item_formatado .= "\n  - " . htmlspecialchars($adicional['nome']);
                }
            }
            $itens_params[] = $item_formatado;
        }
    }
    while (count($itens_params) < 5) {
        $itens_params[] = "\u{200B}"; // CORREÇÃO FINAL: Caractere Invisível
    }

    $taxa_entrega_valor = 0.00;
    $sqlConfig = $conn->prepare("SELECT taxa_entrega FROM configuracoes_loja WHERE id = 1");
    if ($sqlConfig) {
        $sqlConfig->execute();
        $resultConfig = $sqlConfig->get_result();
        if ($config = $resultConfig->fetch_assoc()) {
            $taxa_entrega_valor = (float)$config['taxa_entrega'];
        }
        $sqlConfig->close();
    }

    $endereco_completo = htmlspecialchars($cliente_data['endereco']) . ", " . htmlspecialchars($cliente_data['numero'] ?? 'S/N') . ", " . htmlspecialchars($cliente_data['bairro']);
    if (!empty($cliente_data['complemento'])) {
        $endereco_completo .= " (" . htmlspecialchars($cliente_data['complemento']) . ")";
    }

    $dados_para_bot = [ /* O array com os 6 ou 10 parâmetros, dependendo do seu template atual */]; // PREENCHA ESTE ARRAY CONFORME O TEMPLATE ESCOLHIDO
    // Exemplo para o template simplificado de 6 variáveis:
    // CÓDIGO CORRIGIDO
    $dados_para_bot = [
        "template_name"     => "confirmacao_com_botao", // <-- ADICIONE ESTA LINHA (use o nome exato do seu template)
        "nome_cliente"      => explode(' ', $cliente_data['nome'])[0],
        "id_pedido"         => strval($pedido_id),
        "forma_pagamento"   => ucfirst($forma_pagamento),
        "taxa_entrega"      => number_format($taxa_entrega_valor, 2, ',', '.'),
        "endereco_completo" => $endereco_completo,
        "total_pedido"      => number_format($total_pedido, 2, ',', '.')
    ];

    $telefone_cliente_formatado = "55" . preg_replace('/\D/', '', $cliente_data['telefone']);
    enviarNotificacaoWhatsApp($telefone_cliente_formatado, $dados_para_bot);

    unset($_SESSION['carrinho'], $_SESSION['checkout_cliente_data']);
    $response['success'] = true;
    $response['message'] = 'Pedido finalizado com sucesso!';
    $response['pedido_id'] = $pedido_id;
} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Erro ao finalizar pedido: ' . $e->getMessage();
    error_log("Erro no processamento do pedido: " . $e->getMessage());
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
    echo json_encode($response);
}
