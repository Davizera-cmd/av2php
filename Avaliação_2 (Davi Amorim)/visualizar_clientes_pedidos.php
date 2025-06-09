<?php
session_start(); 


if (!isset($_SESSION['admin_id'])) {
    $_SESSION['mensagem_erro_global'] = "Acesso restrito. Por favor, faça login como administrador.";
    header("Location: login_admin.php");
    exit();
}

require_once 'config/conexao.php'; 

$clientes_com_pedidos = [];

$sql = "SELECT
            c.id AS cliente_id,
            c.nome AS cliente_nome,
            c.email AS cliente_email,
            c.foto AS cliente_foto_nome,
            c.pdf AS cliente_pdf_nome,
            p.nome AS produto_nome,
            p.imagem AS produto_imagem,
            cc.quantidade,
            cc.preco_unitario,
            cc.data_compra
        FROM
            clientes c
        LEFT JOIN 
            carrinho_compras cc ON c.id = cc.cliente_id
        LEFT JOIN 
            produtos p ON cc.produto_id = p.id
        ORDER BY
            c.nome ASC, cc.data_compra DESC, p.nome ASC";

$resultado = $conn->query($sql);

if ($resultado) { 
    while ($linha = $resultado->fetch_assoc()) {
        $cliente_id_atual = $linha['cliente_id'];

        if (!isset($clientes_com_pedidos[$cliente_id_atual])) {
            $clientes_com_pedidos[$cliente_id_atual] = [
                'info' => [
                    'nome' => $linha['cliente_nome'],
                    'email' => $linha['cliente_email'],
                    'foto' => $linha['cliente_foto_nome'],
                    'pdf' => $linha['cliente_pdf_nome']
                ],
                'pedidos' => [] 
            ];
        }

        if ($linha['produto_nome'] !== null) {
            $data_compra_dt = new DateTime($linha['data_compra']);
            $clientes_com_pedidos[$cliente_id_atual]['pedidos'][] = [
                'produto_nome' => $linha['produto_nome'],
                'produto_imagem' => $linha['produto_imagem'],
                'quantidade' => $linha['quantidade'],
                'preco_unitario' => $linha['preco_unitario'],
                'data_compra_formatada' => $data_compra_dt->format('d/m/Y H:i')
            ];
        }
    }
} elseif ($resultado === false) {
    error_log("Erro na query SQL (visualizar_clientes_pedidos): " . $conn->error);
    $_SESSION['mensagem_erro_admin_painel'] = "Erro ao buscar os dados de clientes e pedidos.";
}

$conn->close();
$nome_pagina_admin = "RelatorioPedidos"; 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin: Clientes e Pedidos - Meu E-commerce</title>
    <link rel="stylesheet" href="assets/css/style.css"> 
</head>
<body>
    <header>
        <div class="container-header">
            <h1><a href="visualizar_clientes_pedidos.php">Painel Administrativo</a></h1>
            <nav>
                <ul>
                    <li><a href="visualizar_clientes_pedidos.php" class="ativo">Clientes e Pedidos</a></li>
                    <li><a href="visualizar_log_alteracoes.php">Log de Alterações</a></li>
                    <li><a href="index.php">Ver Loja</a></li>
                    <?php if (isset($_SESSION['admin_id'])): ?>
                        <li><a href="logout_admin.php">Logout Admin (<?php echo htmlspecialchars($_SESSION['admin_usuario']); ?>)</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Relatório de Clientes e Seus Pedidos</h2>

        <?php
        if (isset($_SESSION['mensagem_erro_admin_painel'])) {
            echo '<p class="mensagem-erro">' . htmlspecialchars($_SESSION['mensagem_erro_admin_painel']) . '</p>';
            unset($_SESSION['mensagem_erro_admin_painel']);
        }
        if (isset($_SESSION['mensagem_sucesso_admin_painel'])) {
            echo '<p class="mensagem-sucesso">' . htmlspecialchars($_SESSION['mensagem_sucesso_admin_painel']) . '</p>';
            unset($_SESSION['mensagem_sucesso_admin_painel']);
        }
        ?>

        <?php if (empty($clientes_com_pedidos)): ?>
            <p class="mensagem-info">Nenhum cliente cadastrado ou nenhum pedido encontrado para exibir no relatório.</p>
        <?php else: ?>
            <?php foreach ($clientes_com_pedidos as $cliente_id => $dados_cliente): ?>
                <div class="cliente-pedido-card">
                    <h3>Cliente: <?php echo htmlspecialchars($dados_cliente['info']['nome']); ?> (ID: <?php echo $cliente_id; ?>)</h3>
                    <p><strong>E-mail:</strong> <?php echo htmlspecialchars($dados_cliente['info']['email']); ?></p>
                    
                    <div style="display: flex; align-items: flex-start; gap: 20px; margin-bottom:15px; flex-wrap: wrap;">
                        <div style="text-align: center;">
                            <strong>Foto de Perfil:</strong><br>
                            <?php
                            $caminho_foto_cliente = 'assets/img/' . (!empty($dados_cliente['info']['foto']) && file_exists('assets/img/' . $dados_cliente['info']['foto']) ? $dados_cliente['info']['foto'] : 'placeholder_perfil.png');
                            if (!file_exists($caminho_foto_cliente)) {
                                 $caminho_foto_cliente = "https://placehold.co/100x100/F5F5F5/426a6d?text=Perfil";
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($caminho_foto_cliente); ?>" alt="[Foto de <?php echo htmlspecialchars($dados_cliente['info']['nome']); ?>]" class="perfil-foto" style="width:100px; height:100px; margin-top:5px;">
                        </div>
                        <div>
                            <strong>Documento PDF:</strong><br>
                            <?php if (!empty($dados_cliente['info']['pdf']) && file_exists('assets/pdfs/' . $dados_cliente['info']['pdf'])): ?>
                                <a href="assets/pdfs/<?php echo htmlspecialchars($dados_cliente['info']['pdf']); ?>" target="_blank" class="botao botao-secundario" style="padding: 8px 12px; font-size:0.9em; margin-top:5px; display:inline-block;">Visualizar PDF</a>
                            <?php else: ?>
                                <span style="display:inline-block; margin-top:5px;">Nenhum PDF cadastrado ou arquivo não encontrado.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h4>Itens Comprados por <?php echo htmlspecialchars($dados_cliente['info']['nome']); ?>:</h4>
                    <?php if (empty($dados_cliente['pedidos'])): ?>
                        <p>Este cliente ainda não comprou nenhum item.</p>
                    <?php else: ?>
                        <div class="tabela-responsiva">
                            <table class="tabela-carrinho">
                                <thead>
                                    <tr>
                                        <th colspan="2">Produto</th>
                                        <th>Data da Compra</th>
                                        <th>Qtd.</th>
                                        <th>Preço Unit.</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_gasto_pelo_cliente = 0;
                                    foreach ($dados_cliente['pedidos'] as $item_pedido): 
                                        $subtotal_item_pedido = $item_pedido['quantidade'] * $item_pedido['preco_unitario'];
                                        $total_gasto_pelo_cliente += $subtotal_item_pedido;
                                    ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $caminho_imagem_item_pedido = 'assets/img/' . (!empty($item_pedido['produto_imagem']) && file_exists('assets/img/' . $item_pedido['produto_imagem']) ? $item_pedido['produto_imagem'] : 'placeholder.png');
                                                ?>
                                                <img src="<?php echo htmlspecialchars($caminho_imagem_item_pedido); ?>" alt="[Imagem de <?php echo htmlspecialchars($item_pedido['produto_nome']); ?>]" class="produto-imagem-carrinho">
                                            </td>
                                            <td><?php echo htmlspecialchars($item_pedido['produto_nome']); ?></td>
                                            <td><?php echo $item_pedido['data_compra_formatada']; ?></td>
                                            <td><?php echo $item_pedido['quantidade']; ?></td>
                                            <td>R$ <?php echo number_format($item_pedido['preco_unitario'], 2, ',', '.'); ?></td>
                                            <td>R$ <?php echo number_format($subtotal_item_pedido, 2, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <?php if ($total_gasto_pelo_cliente > 0): ?>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" style="text-align:right; font-weight:bold;">Total Gasto por este Cliente nos Itens Listados:</td>
                                        <td style="font-weight:bold;">R$ <?php echo number_format($total_gasto_pelo_cliente, 2, ',', '.'); ?></td>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                </div> <hr style="margin: 30px 0; border-top: 1px solid #ccc;">
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Meu E-commerce - Painel Administrativo</p>
        </div>
    </footer>
</body>
</html>
