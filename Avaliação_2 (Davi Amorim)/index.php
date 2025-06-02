<?php
session_start();
require_once 'config/conexao.php';

// Buscar produtos do banco de dados
$sql_produtos = "SELECT id, nome, preco, imagem FROM produtos ORDER BY nome ASC";
$resultado_produtos = $conn->query($sql_produtos);
$produtos = [];
if ($resultado_produtos && $resultado_produtos->num_rows > 0) {
    while ($row = $resultado_produtos->fetch_assoc()) {
        $produtos[] = $row;
    }
}

$nome_pagina = "Home"; 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo ao Meu E-commerce</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container-header">
            <h1><a href="index.php">Meu E-commerce</a></h1>
            <nav>
                <ul>
                    <li><a href="index.php" class="<?php echo ($nome_pagina == 'Home') ? 'ativo' : ''; ?>">Home</a></li>
                    <li><a href="pages/carrinho.php">Carrinho</a></li>
                    <?php if (isset($_SESSION['cliente_id'])): ?>
                        <li><a href="pages/meus_dados.php">Meus Dados</a></li>
                        <li><a href="pages/meus_pedidos.php">Meus Pedidos</a></li>
                        <li><a href="logout.php">Logout (<?php echo htmlspecialchars(explode(" ", $_SESSION['cliente_nome'])[0]); ?>)</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="cadastro.php">Cadastro</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="banner">
            <h2>Nossos Produtos em Destaque!</h2>
            <p>Confira as melhores ofertas e novidades.</p>
        </div>

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

        <section class="secao-produtos">
            <h2>Todos os Produtos</h2>
            <?php if (!empty($produtos)): ?>
                <div class="produtos">
                    <?php foreach ($produtos as $produto): ?>
                        <div class="produto">
                            <?php
                            // Define um caminho padrão para imagem caso não haja uma específica
                            $caminho_imagem = 'assets/img/' . (!empty($produto['imagem']) && file_exists('assets/img/' . $produto['imagem']) ? $produto['imagem'] : 'placeholder.png');
                            // Se 'placeholder.png' não existir, você pode querer um tratamento diferente ou garantir que ele exista.
                            // Para este exemplo, vamos assumir que você pode criar um assets/img/placeholder.png
                            if (!file_exists($caminho_imagem)) {
                                // Fallback para um placeholder online se o local não existir, ou uma cor de fundo
                                // $caminho_imagem = "https://placehold.co/300x200/F5F5F5/426a6d?text=Produto";
                                // Para evitar dependência externa, é melhor ter um placeholder local.
                                // Se nem o placeholder local existir, pode-se optar por não mostrar a tag <img> ou usar um estilo.
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($caminho_imagem); ?>" alt="[Imagem de <?php echo htmlspecialchars($produto['nome']); ?>]" class="produto-imagem">
                            <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>
                            <p class="preco">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></p>
                            
                            <form action="gerenciar_carrinho.php" method="post" class="form-add-carrinho">
                                <input type="hidden" name="acao" value="adicionar">
                                <input type="hidden" name="id_produto" value="<?php echo $produto['id']; ?>">
                                <div class="form-grupo-inline">
                                    <label for="quantidade_<?php echo $produto['id']; ?>">Qtd:</label>
                                    <input type="number" name="quantidade" id="quantidade_<?php echo $produto['id']; ?>" value="1" min="1" max="99">
                                </div>
                                <button type="submit" class="botao-comprar">Adicionar ao Carrinho</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="mensagem-info">Nenhum produto encontrado no momento.</p>
            <?php endif; ?>
        </section>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Meu E-commerce. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html>
