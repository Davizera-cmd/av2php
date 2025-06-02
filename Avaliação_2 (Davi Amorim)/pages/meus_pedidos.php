<?php

session_start();
require_once '../config/conexao.php';


if (!isset($_SESSION['cliente_id'])) {
    $_SESSION['mensagem_erro'] = "Você precisa estar logado para acessar seus pedidos.";
    header("Location: ../login.php");
    exit();
}

$cliente_id = $_SESSION['cliente_id'];
$pedidos_cliente = [];


$sql_pedidos = "SELECT 
                    cc.id AS item_pedido_id,
                    cc.quantidade,
                    cc.preco_unitario,
                    cc.data_compra,
                    p.nome AS nome_produto,
                    p.imagem AS imagem_produto
                FROM 
                    carrinho_compras cc
                JOIN 
                    produtos p ON cc.produto_id = p.id
                WHERE 
                    cc.cliente_id = ?
                ORDER BY 
                    cc.data_compra DESC, p.nome ASC";

$stmt_pedidos = $conn->prepare($sql_pedidos);

if ($stmt_pedidos) {
    $stmt_pedidos->bind_param("i", $cliente_id);
    $stmt_pedidos->execute();
    $resultado = $stmt_pedidos->get_result();
    
    if ($resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {

            $data_compra_formatada = new DateTime($row['data_compra']);
            $row['data_compra_formatada'] = $data_compra_formatada->format('d/m/Y H:i');
            

            $pedidos_cliente[] = $row;
        }
    }
    $stmt_pedidos->close();
} else {
    error_log("Erro ao preparar statement (meus_pedidos): " . $conn->error);
    $_SESSION['mensagem_erro_pagina'] = "Ocorreu um erro ao tentar buscar seus pedidos.";

}
$conn->close();

$nome_pagina = "Meus Pedidos";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Pedidos - Meu E-commerce</title>
    <link rel="stylesheet" href="../assets/css/style.css"> </head>
<body>
    <header>
        <div class="container-header">
            <h1><a href="../index.php">Meu E-commerce</a></h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="carrinho.php">Carrinho</a></li>
                    <?php if (isset($_SESSION['cliente_id'])): ?>
                        <li><a href="meus_dados.php">Meus Dados</a></li>
                        <li><a href="meus_pedidos.php" class="ativo">Meus Pedidos</a></li>
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
        <h2>Histórico de Pedidos</h2>

        <?php
        if (isset($_SESSION['mensagem_sucesso_pagina'])) { // Usando chave diferente para não conflitar com outras
            echo '<p class="mensagem-sucesso">' . htmlspecialchars($_SESSION['mensagem_sucesso_pagina']) . '</p>';
            unset($_SESSION['mensagem_sucesso_pagina']);
        }
        if (isset($_SESSION['mensagem_erro_pagina'])) {
            echo '<p class="mensagem-erro">' . htmlspecialchars($_SESSION['mensagem_erro_pagina']) . '</p>';
            unset($_SESSION['mensagem_erro_pagina']);
        }
        ?>

        <?php if (empty($pedidos_cliente)): ?>
            <p class="mensagem-info">Você ainda não realizou nenhum pedido.</p>
            <p class="text-center"><a href="../index.php" class="botao">Começar a Comprar</a></p>
        <?php else: ?>
            <div class="tabela-responsiva">
                <table class="tabela-carrinho">
                    <thead>
                        <tr>
                            <th colspan="2">Produto</th>
                            <th>Data da Compra</th>
                            <th>Quantidade</th>
                            <th>Preço Unit. (na compra)</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 

                        foreach ($pedidos_cliente as $item_comprado): 
                            $subtotal_item = $item_comprado['quantidade'] * $item_comprado['preco_unitario'];
                        ?>
                            <tr>
                                <td>
                                    <?php
                                    $caminho_imagem_pedido = '../assets/img/' . (!empty($item_comprado['imagem_produto']) && file_exists('../assets/img/' . $item_comprado['imagem_produto']) ? $item_comprado['imagem_produto'] : 'placeholder.png');
                                    ?>
                                    <img src="<?php echo htmlspecialchars($caminho_imagem_pedido); ?>" alt="[Imagem de <?php echo htmlspecialchars($item_comprado['nome_produto']); ?>]" class="produto-imagem-carrinho">
                                </td>
                                <td><?php echo htmlspecialchars($item_comprado['nome_produto']); ?></td>
                                <td><?php echo $item_comprado['data_compra_formatada']; ?></td>
                                <td><?php echo $item_comprado['quantidade']; ?></td>
                                <td>R$ <?php echo number_format($item_comprado['preco_unitario'], 2, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($subtotal_item, 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
