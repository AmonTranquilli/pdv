<?php
session_start(); // Inicia a sessão para armazenar o carrinho
header('Content-Type: application/json'); // Define o cabeçalho para retorno JSON

require_once '../../includes/conexao.php'; // Caminho para a conexão com o banco de dados

$response = ['success' => false, 'message' => ''];

// Garante que o carrinho seja um array no início da sessão
if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

// Obtém o corpo da requisição JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica se a requisição é válida e se a ação foi definida
if ($data === null || !isset($data['action'])) {
    $response['message'] = 'Requisição inválida.';
    echo json_encode($response);
    exit();
}

$action = $data['action'];

switch ($action) {
    case 'add_item':
        // Pega o "pacote" 'item' que contém todos os dados do produto
        $item = $data['item'] ?? null;

        // Validação para garantir que o item e seus dados essenciais existem
        if (!$item || !isset($item['id']) || !isset($item['quantidade'])) {
            $response['message'] = 'Dados do produto inválidos.';
            echo json_encode($response);
            exit();
        }

        // Agora, acessa os dados DENTRO do $item
        $produtoId = filter_var($item['id'], FILTER_VALIDATE_INT);
        $quantidade = filter_var($item['quantidade'], FILTER_VALIDATE_INT);
        $obs = isset($item['observacao']) ? trim($item['observacao']) : '';
        
        // As opções e adicionais estão dentro de 'adicionais' no objeto JS
        $opcoes = isset($item['adicionais']) && is_array($item['adicionais']) ? $item['adicionais'] : [];

        // Busca informações do produto no banco de dados
        $stmt = $conn->prepare("SELECT id, nome, preco, imagem FROM produtos WHERE id = ?");
        $stmt->bind_param("i", $produtoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $produto = $result->fetch_assoc();
        $stmt->close();

        if ($produto) {
            // Usa o ID único enviado pelo JavaScript
            $item_carrinho_id = $item['item_carrinho_id']; 

            // Verifica se um item idêntico já existe
            if (isset($_SESSION['carrinho'][$item_carrinho_id])) {
                // Item existe, apenas atualiza a quantidade
                $_SESSION['carrinho'][$item_carrinho_id]['quantidade'] += $quantidade;
            } else {
                // Novo item, define suas propriedades
                $_SESSION['carrinho'][$item_carrinho_id] = [
    'item_carrinho_id' => $item_carrinho_id,
    'id' => $produto['id'],
    'nome' => $produto['nome'],
    'preco_unitario' => $item['preco_unitario'],
    'quantidade' => $quantidade,
    'obs' => $obs,
    'opcoes' => $opcoes,
    'imagem' => $produto['imagem'],
    'preco' => $item['preco_unitario'] * $quantidade  // <-- aqui já calcula o preço total do item
];

            }
            
            $response['success'] = true;
            $response['message'] = 'Item adicionado ao carrinho!';
            $response['cart'] = array_values($_SESSION['carrinho']);
        } else {
            $response['message'] = 'Produto não encontrado.';
        }
        break;

    case 'remove_item':
        $itemId = $data['itemId'] ?? null;
        if ($itemId && isset($_SESSION['carrinho'][$itemId])) {
            unset($_SESSION['carrinho'][$itemId]);
            $response['success'] = true;
            $response['message'] = 'Item removido do carrinho.';
        } else {
            $response['message'] = 'Item não encontrado no carrinho para remoção.';
        }
        $response['cart'] = array_values($_SESSION['carrinho']);
        break;

    case 'update_quantity':
        $itemId = $data['itemId'] ?? null;
        $quantityChange = filter_var($data['quantity'], FILTER_VALIDATE_INT) ?? 0;
        $actionType = $data['action_type'] ?? ''; // 'increase' ou 'decrease'

        if ($itemId && isset($_SESSION['carrinho'][$itemId]) && $quantityChange > 0) {
            if ($actionType === 'increase') {
                $_SESSION['carrinho'][$itemId]['quantidade'] += $quantityChange;
                // Recalcula o preço total do item no carrinho
                $_SESSION['carrinho'][$itemId]['preco'] = $_SESSION['carrinho'][$itemId]['quantidade'] * $_SESSION['carrinho'][$itemId]['preco_unitario'];
                $response['success'] = true;
                $response['message'] = 'Quantidade do item aumentada.';
            } elseif ($actionType === 'decrease') {
                if ($_SESSION['carrinho'][$itemId]['quantidade'] - $quantityChange > 0) {
                    $_SESSION['carrinho'][$itemId]['quantidade'] -= $quantityChange;
                    // Recalcula o preço total do item no carrinho
                    $_SESSION['carrinho'][$itemId]['preco'] = $_SESSION['carrinho'][$itemId]['quantidade'] * $_SESSION['carrinho'][$itemId]['preco_unitario'];
                    $response['success'] = true;
                    $response['message'] = 'Quantidade do item diminuída.';
                } else {
                    // Se a quantidade for <= 0, remove o item
                    unset($_SESSION['carrinho'][$itemId]);
                    $response['success'] = true;
                    $response['message'] = 'Item removido do carrinho (quantidade zerada).';
                }
            }
        } else {
            $response['message'] = 'Item não encontrado no carrinho para atualização.';
        }
        $response['cart'] = array_values($_SESSION['carrinho']);
        break;

    case 'get_cart':
        $response['success'] = true;
        // Garante que cada item no carrinho tenha a 'imagem' definida.
        // Se a imagem não estiver definida ou estiver vazia, atribui um placeholder.
        foreach ($_SESSION['carrinho'] as $key => $item) {
            if (!isset($item['imagem']) || empty($item['imagem'])) {
                $_SESSION['carrinho'][$key]['imagem'] = 'https://placehold.co/80x80/FF5722/FFFFFF?text=Item';
            }
        }
        $response['cart'] = array_values($_SESSION['carrinho']); // Retorna o carrinho atual
        break;

    case 'clear_cart':
        $_SESSION['carrinho'] = [];
        $response['success'] = true;
        $response['message'] = 'Carrinho limpo.';
        $response['cart'] = [];
        break;

    default:
        $response['message'] = 'Ação desconhecida.';
        break;
}

$conn->close(); // Fecha a conexão com o banco de dados
echo json_encode($response);
?>