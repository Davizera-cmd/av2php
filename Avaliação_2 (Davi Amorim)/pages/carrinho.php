<?php
// pages/carrinho.php
session_start();
require_once '../config/conexao.php'; // Ajuste o caminho para a pasta config

// Verifica se o cliente está logado, senão redireciona para login
if (!isset($_SESSION['cliente_id'])) {
    $_SESSION['mensagem_erro'] = "Você precisa estar logado para acessar o carrinho.";
    header("Location: ../login.php"); // Ajuste o caminho para o login.php na raiz
    exit();
}

// Inclui o helper gerenciar_carrinho.php para ter acesso à função obterCarrinho()
// Não é estritamente necessário incluir o arquivo todo se formos apenas ler o cookie aqui,
// mas se precisássemos de mais lógica dele, seria útil.
// Por ora, vamos redefinir a função obterCarrinho aqui para simplicidade ou usar a constante.
if (!defined('NOME_COOKIE_CARRINHO')) {
    define('NOME_COOKIE_CARRINHO', 'meu_ecommerce_carrinho');
}

function obterItensCarrinhoCookie() {
    if (isset($_COOKIE[NOME_COOKIE_CARRINHO])) {
        $carrinho = json_decode($_COOKIE[NOME_COOKIE_CARRINHO], true);
        return is_array($carrinho) ? $carrinho : [];
    }
    return [];
}

$itens_carrinho = obterItensCarrinhoCookie();
$total_carrinho = 0;
$produtos_no_carrinho_detalhes = [];

if (!empty($itens_carrinho)) {
    // Precisamos buscar os detalhes atuais dos produtos (nome, imagem, preço atual) do banco
    // para garantir que os preços estão atualizados e para exibir imagens.
    // O carrinho no cookie pode ter preços do momento da adição.
    $ids_produtos = array_keys($itens_carrinho);
    if (!empty($ids_produtos)) {
        $placeholders = implode(',', array_fill(0, count($ids_produtos), '?'));
        $tipos = str_repeat('i', count($ids_produtos));
        
        $sql_produtos_carrinho = "SELECT id, nome, preco, imagem FROM produtos WHERE id IN ($placeholders)";
        $stmt_produtos = $conn->prepare($sql_produtos_carrinho);
        
        if ($stmt_produtos) {
            $stmt_produtos->bind_param($tipos, ...$ids_produtos);
            $stmt_produtos->execute();
            $resultado_stmt = $stmt_produtos->get_result();
            
            $produtos_db = [];
            while ($row = $resultado_stmt->fetch_assoc()) {
                $produtos_db[$row['id']] = $row;
            }
            $stmt_produtos->close();

            // Montar array com detalhes completos para exibição
            foreach ($itens_carrinho as $id_produto => $item_cookie) {
                if (isset($produtos_db[$id_produto])) {
                    $produto_atual = $produtos_db[$id_produto];
                    $quantidade_no_carrinho = $item_cookie['quantidade'];
                    $subtotal_item = $produto_atual['preco'] * $quantidade_no_carrinho;
                    $total_carrinho += $subtotal_item;

                    $produtos_no_carrinho_detalhes[$id_produto] = [
                        'id' => $produto_atual['id'],
                        'nome' => $produto_atual['nome'],
                        'preco_unitario' => $produto_atual['preco'], // Preço atual do BD
                        'quantidade' => $quantidade_no_carrinho,
                        'subtotal' => $subtotal_item,
                        'imagem' => $produto_atual['imagem'] ?? 'placeholder.png'
                    ];
                } else {
                    // Produto no cookie não existe mais no BD, idealmente deveria ser removido do cookie.
                    // Por simplicidade, aqui ele apenas não será exibido ou somado.
                    // Poderia adicionar uma lógica para notificar o usuário ou limpar automaticamente.
                }
            }
        } else {
            // Erro ao preparar statement para buscar produtos
            $_SESSION['mensagem_erro'] = "Erro ao carregar detalhes dos produtos do carrinho.";
            error_log("Erro em carrinho.php ao preparar statement: " . $conn->error);
        }
    }
}

$nome_pagina = "Carrinho";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Carrinho - Meu E-commerce</title>
    <link rel="stylesheet" href="../assets/css/style.css"> <!-- Ajuste o caminho para o CSS -->
</head>
<body>
    <header>
        <div class="container-header">
            <h1><a href="../index.php">Meu E-commerce</a></h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="carrinho.php" class="ativo">Carrinho</a></li>
                    <?php if (isset($_SESSION['cliente_id'])): ?>
                        <li><a href="meus_dados.php">Meus Dados</a></li>
                        <li><a href="meus_pedidos.php">Meus Pedidos</a></li>
                        <li><a href="../logout.php">Logout (<?php echo htmlspecialchars(explode(" ", $_SESSION['cliente_nome'])[0]); ?>)</a></li>
                    <?php else: ?>
                        <li><a href="../login.php">Login</a></li>
                        <li><a href="../cadastro.php">Cadastro</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Meu Carrinho de Compras</h2>

        <?php
        if (isset($_SESSION['mensagem_sucesso'])) {
            echo '<p class="mensagem-sucesso">' . htmlspecialchars($_SESSION['mensagem_sucesso']) . '</p>';
            unset($_SESSION['mensagem_sucesso']);
        }
        if (isset($_SESSION['mensagem_erro'])) {
            echo '<p class="mensagem-erro">' . htmlspecialchars($_SESSION['mensagem_erro']) . '</p>';
            unset($_SESSION['mensagem_erro']);
        }
        ?>

        <?php if (empty($produtos_no_carrinho_detalhes)): ?>
            <p class="mensagem-info">Seu carrinho está vazio.</p>
            <p class="text-center"><a href="../index.php" class="botao">Continuar Comprando</a></p>
        <?php else: ?>
            <form action="../gerenciar_carrinho.php" method="post" id="formAtualizarCarrinho">
                <input type="hidden" name="acao" value="atualizar_multiplos"> <!-- Ação para atualizar todos de uma vez, se desejar -->
                <div class="tabela-responsiva">
                    <table class="tabela-carrinho">
                        <thead>
                            <tr>
                                <th colspan="2">Produto</th>
                                <th>Preço Unit.</th>
                                <th>Quantidade</th>
                                <th>Subtotal</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produtos_no_carrinho_detalhes as $id_produto => $item): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $caminho_imagem_item = '../assets/img/' . (!empty($item['imagem']) && file_exists('../assets/img/' . $item['imagem']) ? $item['imagem'] : 'placeholder.png');
                                        ?>
                                        <img src="<?php echo htmlspecialchars($caminho_imagem_item); ?>" alt="[Imagem de <?php echo htmlspecialchars($item['nome']); ?>]" class="produto-imagem-carrinho">
                                    </td>
                                    <td><?php echo htmlspecialchars($item['nome']); ?></td>
                                    <td>R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                                    <td>
                                        <!-- Formulário individual para atualizar quantidade -->
                                        <form action="../gerenciar_carrinho.php" method="post" style="display: inline;">
                                            <input type="hidden" name="acao" value="atualizar">
                                            <input type="hidden" name="id_produto" value="<?php echo $item['id']; ?>">
                                            <input type="number" name="quantidade" value="<?php echo $item['quantidade']; ?>" min="1" max="99" style="width: 60px; text-align:center;">
                                            <button type="submit" class="botao-secundario" style="padding: 5px 8px; font-size:0.8em; margin-left:5px;">Atualizar</button>
                                        </form>
                                    </td>
                                    <td>R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?></td>
                                    <td class="acoes-item">
                                        <form action="../gerenciar_carrinho.php" method="post" style="display: inline;">
                                            <input type="hidden" name="acao" value="remover">
                                            <input type="hidden" name="id_produto" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="botao-perigo">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <div class="total-carrinho">
                <p>Total do Carrinho: <span>R$ <?php echo number_format($total_carrinho, 2, ',', '.'); ?></span></p>
            </div>

            <div class="botoes-carrinho">
                <form action="../gerenciar_carrinho.php" method="post" style="display: inline-block;">
                    <input type="hidden" name="acao" value="limpar">
                    <button type="submit" class="botao botao-perigo">Esvaziar Carrinho</button>
                </form>
                <a href="finalizar_compra.php" class="botao">Finalizar Compra</a>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Meu E-commerce. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html>
