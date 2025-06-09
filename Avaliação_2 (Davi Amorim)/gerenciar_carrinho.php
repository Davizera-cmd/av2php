<?php

session_start();
require_once 'config/conexao.php'; 

define('NOME_COOKIE_CARRINHO', 'meu_ecommerce_carrinho');

define('TEMPO_VIDA_COOKIE', time() + (86400 * 30));


function obterCarrinho() {
    if (isset($_COOKIE[NOME_COOKIE_CARRINHO])) {
        $carrinho = json_decode($_COOKIE[NOME_COOKIE_CARRINHO], true);
   
        return is_array($carrinho) ? $carrinho : [];
    }
    return [];
}

function salvarCarrinho($carrinho) {
    $jsonCarrinho = json_encode($carrinho);
    setcookie(NOME_COOKIE_CARRINHO, $jsonCarrinho, TEMPO_VIDA_COOKIE, "/");
}


function redirecionarParaAnterior($fallbackUrl = 'index.php', $mensagemTipo = null, $mensagem = null) {
    if ($mensagemTipo && $mensagem) {
        $_SESSION[$mensagemTipo] = $mensagem;
    }
    $urlRedirecionamento = $_SERVER['HTTP_REFERER'] ?? $fallbackUrl;
    header("Location: " . $urlRedirecionamento);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    $carrinho = obterCarrinho();

    switch ($acao) {
        case 'adicionar':
            if (isset($_POST['id_produto']) && isset($_POST['quantidade'])) {
                $id_produto = filter_var($_POST['id_produto'], FILTER_VALIDATE_INT);
                $quantidade = filter_var($_POST['quantidade'], FILTER_VALIDATE_INT);

                if ($id_produto && $quantidade && $quantidade > 0) {
            
                    $sql_check_produto = "SELECT id, nome, preco FROM produtos WHERE id = ?";
                    $stmt_check = $conn->prepare($sql_check_produto);
                    if ($stmt_check) {
                        $stmt_check->bind_param("i", $id_produto);
                        $stmt_check->execute();
                        $resultado_produto = $stmt_check->get_result();

                        if ($resultado_produto->num_rows > 0) {
            
                            if (isset($carrinho[$id_produto])) {
                                $carrinho[$id_produto]['quantidade'] += $quantidade;
                            } else {
                                $produto_db = $resultado_produto->fetch_assoc();
                                $carrinho[$id_produto] = [
                                    'id' => $id_produto, 
                                    'nome' => $produto_db['nome'],
                                    'preco' => $produto_db['preco'], 
                                    'quantidade' => $quantidade
                                ];
                            }
                            salvarCarrinho($carrinho);
                            redirecionarParaAnterior('index.php', 'mensagem_sucesso', htmlspecialchars($produto_db['nome']) . ' adicionado ao carrinho!');
                        } else {
                            redirecionarParaAnterior('index.php', 'mensagem_erro', 'Produto não encontrado.');
                        }
                        $stmt_check->close();
                    } else {
                         error_log("Erro ao preparar statement (check_produto_carrinho): " . $conn->error);
                         redirecionarParaAnterior('index.php', 'mensagem_erro', 'Erro ao processar o produto.');
                    }
                } else {
                    redirecionarParaAnterior('index.php', 'mensagem_erro', 'Dados inválidos para adicionar ao carrinho.');
                }
            } else {
                redirecionarParaAnterior('index.php', 'mensagem_erro', 'Informações do produto ausentes.');
            }
            break;

        case 'remover':
            if (isset($_POST['id_produto'])) {
                $id_produto = filter_var($_POST['id_produto'], FILTER_VALIDATE_INT);
                if ($id_produto && isset($carrinho[$id_produto])) {
                    $nome_produto_removido = $carrinho[$id_produto]['nome'] ?? 'Produto';
                    unset($carrinho[$id_produto]);
                    salvarCarrinho($carrinho);
                    redirecionarParaAnterior('pages/carrinho.php', 'mensagem_sucesso', htmlspecialchars($nome_produto_removido) . ' removido do carrinho.');
                } else {
                    redirecionarParaAnterior('pages/carrinho.php', 'mensagem_erro', 'Produto não encontrado no carrinho para remoção.');
                }
            } else {
                 redirecionarParaAnterior('pages/carrinho.php', 'mensagem_erro', 'ID do produto ausente para remoção.');
            }
            break;

        case 'atualizar':
            if (isset($_POST['id_produto']) && isset($_POST['quantidade'])) {
                $id_produto = filter_var($_POST['id_produto'], FILTER_VALIDATE_INT);
                $quantidade = filter_var($_POST['quantidade'], FILTER_VALIDATE_INT);

                if ($id_produto && isset($carrinho[$id_produto])) {
                    if ($quantidade > 0) {
                        $carrinho[$id_produto]['quantidade'] = $quantidade;
                        salvarCarrinho($carrinho);
                        redirecionarParaAnterior('pages/carrinho.php', 'mensagem_sucesso', 'Quantidade atualizada no carrinho.');
                    } elseif ($quantidade <= 0) { // Se a quantidade for 0 ou menos, remove o item
                        unset($carrinho[$id_produto]);
                        salvarCarrinho($carrinho);
                        redirecionarParaAnterior('pages/carrinho.php', 'mensagem_sucesso', 'Produto removido do carrinho.');
                    }
                } else {
                     redirecionarParaAnterior('pages/carrinho.php', 'mensagem_erro', 'Produto não encontrado no carrinho para atualização.');
                }
            } else {
                redirecionarParaAnterior('pages/carrinho.php', 'mensagem_erro', 'Dados inválidos para atualizar o carrinho.');
            }
            break;

        case 'limpar':
            $carrinho = [];
            salvarCarrinho($carrinho);

            redirecionarParaAnterior('pages/carrinho.php', 'mensagem_sucesso', 'Carrinho esvaziado com sucesso.');
            break;

        default:
            redirecionarParaAnterior('index.php', 'mensagem_erro', 'Ação desconhecida no carrinho.');
            break;
    }
} else {
    header("Location: index.php");
    exit();
}

$conn->close(); 
?>
