<?php

session_start();
require_once '../config/conexao.php'; 


if (!isset($_SESSION['cliente_id'])) {
    $_SESSION['mensagem_erro'] = "Você precisa estar logado para finalizar a compra.";
    header("Location: ../login.php");
    exit();
}


if (!defined('NOME_COOKIE_CARRINHO')) {
    define('NOME_COOKIE_CARRINHO', 'meu_ecommerce_carrinho');
}

function obterItensCarrinhoCookieFinalizar() {
    if (isset($_COOKIE[NOME_COOKIE_CARRINHO])) {
        $carrinho = json_decode($_COOKIE[NOME_COOKIE_CARRINHO], true);
        return is_array($carrinho) ? $carrinho : [];
    }
    return [];
}

$itens_carrinho_cookie = obterItensCarrinhoCookieFinalizar();

if (empty($itens_carrinho_cookie)) {
    $_SESSION['mensagem_info'] = "Seu carrinho está vazio. Adicione produtos antes de finalizar a compra.";
    header("Location: carrinho.php"); 
    exit();
}

$cliente_id = $_SESSION['cliente_id'];
$conn->begin_transaction(); 

try {
    $ids_produtos_cookie = array_keys($itens_carrinho_cookie);
    $produtos_para_inserir = [];
    $erro_preco_divergente = false;

    if (!empty($ids_produtos_cookie)) {
        $placeholders = implode(',', array_fill(0, count($ids_produtos_cookie), '?'));
        $tipos = str_repeat('i', count($ids_produtos_cookie));
        
        $sql_produtos_atuais = "SELECT id, preco FROM produtos WHERE id IN ($placeholders)";
        $stmt_produtos_atuais = $conn->prepare($sql_produtos_atuais);
        
        if (!$stmt_produtos_atuais) {
            throw new Exception("Erro ao preparar consulta de produtos: " . $conn->error);
        }
        
        $stmt_produtos_atuais->bind_param($tipos, ...$ids_produtos_cookie);
        $stmt_produtos_atuais->execute();
        $resultado_produtos_atuais = $stmt_produtos_atuais->get_result();
        
        $precos_atuais_db = [];
        while ($row = $resultado_produtos_atuais->fetch_assoc()) {
            $precos_atuais_db[$row['id']] = $row['preco'];
        }
        $stmt_produtos_atuais->close();


        foreach ($itens_carrinho_cookie as $id_produto => $item_cookie) {
            if (!isset($precos_atuais_db[$id_produto])) {

                throw new Exception("O produto '" . htmlspecialchars($item_cookie['nome'] ?? "ID $id_produto") . "' não está mais disponível. Remova-o do carrinho e tente novamente.");
            }
            
            $preco_atual_bd = $precos_atuais_db[$id_produto];

            $produtos_para_inserir[] = [
                'cliente_id' => $cliente_id,
                'produto_id' => $id_produto,
                'quantidade' => $item_cookie['quantidade'],
                'preco_unitario' => $preco_atual_bd 
            ];
        }
    }

    if ($erro_preco_divergente) {
 
        throw new Exception("Os preços de um ou mais itens no seu carrinho foram atualizados. Por favor, revise seu carrinho.");
    }

    if (empty($produtos_para_inserir)) {
        throw new Exception("Nenhum produto válido encontrado no carrinho para finalizar a compra.");
    }

    $sql_insert_item = "INSERT INTO carrinho_compras (cliente_id, produto_id, quantidade, preco_unitario, data_compra) VALUES (?, ?, ?, ?, NOW())";
    $stmt_insert_item = $conn->prepare($sql_insert_item);
    if (!$stmt_insert_item) {
        throw new Exception("Erro ao preparar inserção de item no pedido: " . $conn->error);
    }

    foreach ($produtos_para_inserir as $item_pedido) {
        $stmt_insert_item->bind_param("iiid", 
            $item_pedido['cliente_id'], 
            $item_pedido['produto_id'], 
            $item_pedido['quantidade'], 
            $item_pedido['preco_unitario']
        );
        if (!$stmt_insert_item->execute()) {
            throw new Exception("Erro ao salvar item do pedido: " . $stmt_insert_item->error);
        }
    }
    $stmt_insert_item->close();


    $conn->commit();


    setcookie(NOME_COOKIE_CARRINHO, '', time() - 3600, "/");

    $_SESSION['pedido_finalizado_id'] = $conn->insert_id; 
    $_SESSION['mensagem_sucesso'] = "Compra finalizada com sucesso! Seu pedido foi registrado.";
    header("Location: resumo_pedido.php");
    exit();

} catch (Exception $e) {
    $conn->rollback(); 
    error_log("Erro ao finalizar compra: " . $e->getMessage());
    $_SESSION['mensagem_erro'] = "Falha ao finalizar a compra: " . $e->getMessage();
    header("Location: carrinho.php"); 
    exit();
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>