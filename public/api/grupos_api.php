<?php
session_start();
header('Content-Type: application/json');
require_once '../../includes/conexao.php';

// Proteção da API
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso não autorizado.']);
    exit();
}

$response = ['sucesso' => false, 'mensagem' => 'Ação desconhecida.'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? null;

// --- AÇÃO PARA CRIAR UM NOVO GRUPO ---
if ($action === 'criar_grupo') {
    $id_produto_pai = $input['id_produto_pai'] ?? null;
    $nome_grupo = trim($input['nome_grupo'] ?? '');
    $tipo_selecao = $input['tipo_selecao'] ?? 'UNICO';
    $min_opcoes = filter_var($input['min_opcoes'], FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $max_opcoes = filter_var($input['max_opcoes'], FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);

    if (empty($nome_grupo) || empty($id_produto_pai) || $max_opcoes < $min_opcoes) {
        $response['mensagem'] = 'Dados inválidos. Verifique o nome e as regras de mínimo/máximo.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO grupos_opcoes (id_produto_pai, nome_grupo, tipo_selecao, min_opcoes, max_opcoes) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issii", $id_produto_pai, $nome_grupo, $tipo_selecao, $min_opcoes, $max_opcoes);
            if ($stmt->execute()) {
                $response['sucesso'] = true;
                $response['mensagem'] = 'Grupo de opções criado com sucesso!';
                $response['novo_grupo_id'] = $conn->insert_id;
            } else {
                $response['mensagem'] = 'Erro ao criar o grupo: ' . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $response['mensagem'] = 'Erro no servidor: ' . $e->getMessage();
        }
    }
}

// --- AÇÃO PARA ATUALIZAR UM GRUPO EXISTENTE ---
elseif ($action === 'update_grupo') {
    $id_grupo = $input['id_grupo'] ?? null;
    $nome_grupo = trim($input['nome_grupo'] ?? '');
    $tipo_selecao = $input['tipo_selecao'] ?? 'UNICO';
    $min_opcoes = filter_var($input['min_opcoes'], FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $max_opcoes = filter_var($input['max_opcoes'], FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);

    if (empty($nome_grupo) || empty($id_grupo) || $max_opcoes < $min_opcoes) {
        $response['mensagem'] = 'Dados inválidos para atualização.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE grupos_opcoes SET nome_grupo = ?, tipo_selecao = ?, min_opcoes = ?, max_opcoes = ? WHERE id = ?");
            $stmt->bind_param("ssiii", $nome_grupo, $tipo_selecao, $min_opcoes, $max_opcoes, $id_grupo);

            if ($stmt->execute()) {
                $response['sucesso'] = true;
                $response['mensagem'] = 'Grupo atualizado com sucesso!';
            } else {
                $response['mensagem'] = 'Erro ao atualizar o grupo: ' . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $response['mensagem'] = 'Erro no servidor: ' . $e->getMessage();
        }
    }
}

// --- AÇÃO PARA APAGAR UM GRUPO E TODOS OS SEUS ITENS ---
elseif ($action === 'delete_grupo') {
    $id_grupo = $input['id_grupo'] ?? null;

    if (empty($id_grupo)) {
        $response['mensagem'] = 'ID do grupo não fornecido.';
    } else {
        $conn->begin_transaction();
        try {
            // 1. Apaga os itens do grupo
            $stmt_itens = $conn->prepare("DELETE FROM itens_grupo WHERE id_grupo_opcao = ?");
            $stmt_itens->bind_param("i", $id_grupo);
            $stmt_itens->execute();
            $stmt_itens->close();
            
            // 2. Apaga o grupo em si
            $stmt_grupo = $conn->prepare("DELETE FROM grupos_opcoes WHERE id = ?");
            $stmt_grupo->bind_param("i", $id_grupo);
            $stmt_grupo->execute();
            $stmt_grupo->close();

            $conn->commit();
            $response['sucesso'] = true;
            $response['mensagem'] = 'Grupo e todos os seus itens foram apagados com sucesso!';

        } catch (Exception $e) {
            $conn->rollback();
            $response['mensagem'] = 'Erro ao apagar o grupo: ' . $e->getMessage();
        }
    }
}

// --- AÇÃO PARA CRIAR UM NOVO ITEM DENTRO DE UM GRUPO ---
elseif ($action === 'criar_item') {
    $id_grupo = $input['id_grupo_opcao'] ?? null;
    $nome_item = trim($input['nome_item'] ?? '');
    $preco_adicional = filter_var($input['preco_adicional'], FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0.0]]);
    $id_produto_vinculado = filter_var($input['id_produto_vinculado'], FILTER_VALIDATE_INT);

    if (empty($nome_item) || empty($id_grupo)) {
        $response['mensagem'] = 'Dados inválidos.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO itens_grupo (id_grupo_opcao, nome_item, preco_adicional, id_produto_vinculado) VALUES (?, ?, ?, ?)");
            $id_prod_vinc_param = $id_produto_vinculado > 0 ? $id_produto_vinculado : NULL;
            $stmt->bind_param("isdi", $id_grupo, $nome_item, $preco_adicional, $id_prod_vinc_param);
            if ($stmt->execute()) {
                $response['sucesso'] = true;
                $response['mensagem'] = 'Item adicionado com sucesso!';
            } else {
                $response['mensagem'] = 'Erro ao adicionar o item: ' . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $response['mensagem'] = 'Erro no servidor: ' . $e->getMessage();
        }
    }
}

// --- AÇÃO PARA APAGAR UM ITEM ESPECÍFICO ---
elseif ($action === 'delete_item') {
    $id_item = $input['id_item'] ?? null;

    if (empty($id_item)) {
        $response['mensagem'] = 'ID do item não fornecido.';
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM itens_grupo WHERE id = ?");
            $stmt->bind_param("i", $id_item);
            if ($stmt->execute()) {
                $response['sucesso'] = true;
                $response['mensagem'] = 'Item apagado com sucesso!';
            } else {
                $response['mensagem'] = 'Erro ao apagar o item: ' . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $response['mensagem'] = 'Erro no servidor: ' . $e->getMessage();
        }
    }
}

// --- AÇÃO PARA BUSCAR PRODUTOS ---
elseif ($action === 'listar_produtos') {
    $termo_busca = $_GET['term'] ?? '';
    $termo_busca = "%" . $termo_busca . "%";
    try {
        $id_produto_pai = filter_input(INPUT_GET, 'id_produto_pai', FILTER_VALIDATE_INT);
        $stmt = $conn->prepare("SELECT id, nome as text FROM produtos WHERE nome LIKE ? AND id != ? AND ativo = 1 LIMIT 10");
        $stmt->bind_param("si", $termo_busca, $id_produto_pai);
        $stmt->execute();
        $result = $stmt->get_result();
        $produtos = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['results' => $produtos]);
        $conn->close();
        exit();
    } catch (Exception $e) {
        $response['mensagem'] = 'Erro no servidor: ' . $e->getMessage();
    }
}

$conn->close();
echo json_encode($response);