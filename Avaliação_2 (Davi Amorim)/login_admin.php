<?php
// login_admin.php
session_start();
require_once 'config/conexao.php';

// Se o admin já estiver logado, redireciona para o painel
if (isset($_SESSION['admin_id'])) {
    header("Location: visualizar_clientes_pedidos.php");
    exit();
}

$erro_login = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario_admin = trim($_POST['usuario_admin'] ?? '');
    $senha_admin = $_POST['senha_admin'] ?? '';

    if (empty($usuario_admin) || empty($senha_admin)) {
        $erro_login = 'Usuário e senha são obrigatórios.';
    } else {
        $sql = "SELECT id, usuario, senha, nome_completo FROM administradores WHERE usuario = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            error_log("Erro ao preparar statement (login_admin): " . $conn->error);
            $erro_login = 'Erro no servidor. Tente mais tarde.';
        } else {
            $stmt->bind_param("s", $usuario_admin);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($resultado->num_rows === 1) {
                $admin = $resultado->fetch_assoc();
                if (password_verify($senha_admin, $admin['senha'])) {
                    // Login bem-sucedido
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_usuario'] = $admin['usuario'];
                    $_SESSION['admin_nome'] = $admin['nome_completo'];
                    
                    header("Location: visualizar_clientes_pedidos.php");
                    exit();
                } else {
                    $erro_login = 'Usuário ou senha inválidos.';
                }
            } else {
                $erro_login = 'Usuário ou senha inválidos.';
            }
            $stmt->close();
        }
    }
    $conn->close();
}
$nome_pagina = "LoginAdmin";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrador - Meu E-commerce</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container-header">
            <h1><a href="index.php">Meu E-commerce - Admin Login</a></h1>
            <nav>
                <ul>
                    <li><a href="index.php">Voltar para Loja</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="form-container" style="max-width: 450px;">
            <h2>Acesso Administrativo</h2>

            <?php if (!empty($erro_login)): ?>
                <p class="mensagem-erro"><?php echo htmlspecialchars($erro_login); ?></p>
            <?php endif; ?>
            
            <?php
            // Mensagem de logout do admin
            if (isset($_SESSION['mensagem_sucesso_global'])) {
                echo '<p class="mensagem-sucesso">' . htmlspecialchars($_SESSION['mensagem_sucesso_global']) . '</p>';
                unset($_SESSION['mensagem_sucesso_global']);
            }
            ?>


            <form action="login_admin.php" method="POST">
                <div class="form-grupo">
                    <label for="usuario_admin">Usuário:</label>
                    <input type="text" id="usuario_admin" name="usuario_admin" required 
                           value="<?php echo isset($usuario_admin) ? htmlspecialchars($usuario_admin) : ''; ?>">
                </div>

                <div class="form-grupo">
                    <label for="senha_admin">Senha:</label>
                    <input type="password" id="senha_admin" name="senha_admin" required>
                </div>

                <div class="form-grupo text-center">
                    <button type="submit" class="botao">Entrar</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Meu E-commerce - Painel Administrativo</p>
        </div>
    </footer>
</body>
</html>
