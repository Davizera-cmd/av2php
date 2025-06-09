<?php
session_start();
require_once '../config/conexao.php';

if (!isset($_SESSION['cliente_id'])) {
    $_SESSION['mensagem_erro'] = "Acesso negado.";
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['mensagem_sucesso'])) {

}


$pedido_id_recente = $_SESSION['pedido_finalizado_id'] ?? null;



$itens_do_pedido_recente = [];
$total_pedido_recente = 0;

$nome_pagina = "Resumo do Pedido";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumo do Pedido - Meu E-commerce</title>
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
        <h2>Resumo do Pedido</h2>

        <?php
        if (isset($_SESSION['mensagem_sucesso'])) {
            echo '<p class="mensagem-sucesso">' . htmlspecialchars($_SESSION['mensagem_sucesso']) . '</p>';
            unset($_SESSION['mensagem_sucesso']);
        } else {
            echo '<p class="mensagem-info">Não há um pedido recente para resumir ou ocorreu um problema.</p>';
        }
        ?>

        <div class="card-pedido" style="margin-top: 20px;">
            <h3>Obrigado pela sua compra!</h3>
            <p>Seu pedido foi processado e em breve você receberá mais informações.</p>
            <p>Você pode acompanhar seus pedidos na seção <a href="meus_pedidos.php">Meus Pedidos</a>.</p>
            
            <div style="margin-top: 30px; text-align:center;">
                <a href="../index.php" class="botao">Continuar Comprando</a>
                <a href="meus_pedidos.php" class="botao botao-secundario" style="margin-left:10px;">Ver Meus Pedidos</a>
            </div>
        </div>

    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Meu E-commerce. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html>
