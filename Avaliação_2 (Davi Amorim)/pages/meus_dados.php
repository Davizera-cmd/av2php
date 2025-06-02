<?php
session_start();
require_once '../config/conexao.php'; 


if (!isset($_SESSION['cliente_id'])) {
    $_SESSION['mensagem_erro'] = "Você precisa estar logado para acessar seus dados.";
    header("Location: ../login.php");
    exit();
}

$cliente_id = $_SESSION['cliente_id'];
$cliente_dados = null;


$sql_cliente = "SELECT nome, email, telefone, endereco, foto, pdf FROM clientes WHERE id = ?";
$stmt_cliente = $conn->prepare($sql_cliente);

if ($stmt_cliente) {
    $stmt_cliente->bind_param("i", $cliente_id);
    $stmt_cliente->execute();
    $resultado = $stmt_cliente->get_result();
    if ($resultado->num_rows === 1) {
        $cliente_dados = $resultado->fetch_assoc();
    } else {

        $_SESSION['mensagem_erro'] = "Erro ao carregar seus dados. Tente fazer login novamente.";
        unset($_SESSION['cliente_id']); 
        unset($_SESSION['cliente_nome']);
        unset($_SESSION['cliente_email']);
        unset($_SESSION['cliente_foto']);
        header("Location: ../login.php");
        exit();
    }
    $stmt_cliente->close();
} else {
    error_log("Erro ao preparar statement (meus_dados): " . $conn->error);
    $_SESSION['mensagem_erro'] = "Ocorreu um erro no servidor ao tentar buscar seus dados.";
 
}
$conn->close();

$nome_pagina = "Meus Dados";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Dados - Meu E-commerce</title>
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
                        <li><a href="meus_dados.php" class="ativo">Meus Dados</a></li>
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
        <h2>Meus Dados Cadastrais</h2>

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

        <?php if ($cliente_dados): ?>
            <div class="card-pedido" style="max-width: 700px; margin: 20px auto;">
                <div class="info-bloco" style="text-align: center; margin-bottom: 20px;">
                    <?php
                    $caminho_foto_perfil = '../assets/img/' . (!empty($cliente_dados['foto']) && file_exists('../assets/img/' . $cliente_dados['foto']) ? $cliente_dados['foto'] : 'placeholder_perfil.png');
            
                    if (!file_exists($caminho_foto_perfil)) {
                         $caminho_foto_perfil = "https://placehold.co/150x150/F5F5F5/426a6d?text=Perfil"; 
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($caminho_foto_perfil); ?>" alt="Foto de Perfil" class="perfil-foto" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; border: 3px solid var(--laranja);">
                </div>

                <div class="info-bloco">
                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($cliente_dados['nome']); ?></p>
                </div>
                <div class="info-bloco">
                    <p><strong>E-mail:</strong> <?php echo htmlspecialchars($cliente_dados['email']); ?></p>
                </div>
                <div class="info-bloco">
                    <p><strong>Telefone:</strong> <?php echo htmlspecialchars($cliente_dados['telefone'] ?: 'Não informado'); ?></p>
                </div>
                <div class="info-bloco">
                    <p><strong>Endereço:</strong> <?php echo nl2br(htmlspecialchars($cliente_dados['endereco'] ?: 'Não informado')); ?></p>
                </div>
                
                <hr style="margin: 20px 0;">

                <div class="info-bloco">
                    <p><strong>Documento PDF:</strong>
                        <?php if (!empty($cliente_dados['pdf']) && file_exists('../assets/pdfs/' . $cliente_dados['pdf'])): ?>
                            <a href="../assets/pdfs/<?php echo htmlspecialchars($cliente_dados['pdf']); ?>" target="_blank" class="botao botao-secundario" style="margin-left: 10px; padding: 8px 15px; font-size:0.9em;">Visualizar Documento</a>
                        <?php else: ?>
                            <span>Nenhum documento PDF cadastrado ou arquivo não encontrado.</span>
                        <?php endif; ?>
                    </p>
                </div>
                
              
                <div style="margin-top: 30px; text-align:center;">
                    <a href="editar_dados.php" class="botao">Editar Meus Dados</a>
                </div>
              

            </div>
        <?php elseif (!isset($_SESSION['mensagem_erro'])):?>
            <p class="mensagem-info">Não foi possível carregar seus dados.</p>
        <?php endif; ?>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Meu E-commerce. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html>
