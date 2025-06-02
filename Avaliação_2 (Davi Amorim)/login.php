<?php
session_start();
require_once 'config/conexao.php';

define('MAX_LOGIN_ATTEMPTS', 3);

function redirecionarComMensagemLogin($tipo, $mensagem, $email_tentativa = null) {
    $_SESSION[$tipo] = $mensagem;
    if ($email_tentativa) {
        $_SESSION['login_email_tentativa'] = $email_tentativa;
    }
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
    $senha_fornecida = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha_fornecida)) {
        redirecionarComMensagemLogin('mensagem_erro', 'E-mail e senha são obrigatórios.', $email);
    }

    if (!isset($_SESSION['login_attempts'][$email])) {
        $_SESSION['login_attempts'][$email] = 0;
    }

    if ($_SESSION['login_attempts'][$email] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION['mostrar_mensagem_recuperacao'] = true;
        redirecionarComMensagemLogin('mensagem_erro', 'Você excedeu o número de tentativas de login.', $email);
    }

    $sql = "SELECT id, nome, email, senha, foto FROM clientes WHERE email = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Erro ao preparar statement (login): " . $conn->error);
        redirecionarComMensagemLogin('mensagem_erro', 'Erro no servidor. Tente mais tarde.', $email);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $cliente = $resultado->fetch_assoc();
        if (password_verify($senha_fornecida, $cliente['senha'])) {
            $_SESSION['cliente_id'] = $cliente['id'];
            $_SESSION['cliente_nome'] = $cliente['nome'];
            $_SESSION['cliente_email'] = $cliente['email'];
            $_SESSION['cliente_foto'] = $cliente['foto'];

            unset($_SESSION['login_attempts'][$email]);
            unset($_SESSION['mostrar_mensagem_recuperacao']);

            header("Location: index.php");
            exit();
        } else {
            $_SESSION['login_attempts'][$email]++;
            redirecionarComMensagemLogin('mensagem_erro', 'E-mail ou senha inválidos.', $email);
        }
    } else {
        $_SESSION['login_attempts'][$email]++;
        redirecionarComMensagemLogin('mensagem_erro', 'E-mail ou senha inválidos.', $email);
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Meu E-commerce</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container-header">
            <h1><a href="index.php">Meu E-commerce</a></h1>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="pages/carrinho.php">Carrinho</a></li>
                    <li><a href="login.php" class="ativo">Login</a></li>
                    <li><a href="cadastro.php">Cadastro</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="form-container">
            <h2>Login do Cliente</h2>

            <?php
                if (isset($_SESSION['mensagem_erro'])) {
                    echo '<p class="mensagem-erro">' . htmlspecialchars($_SESSION['mensagem_erro']) . '</p>';
                    unset($_SESSION['mensagem_erro']);
                }
                if (isset($_SESSION['mensagem_sucesso'])) {
                    echo '<p class="mensagem-sucesso">' . htmlspecialchars($_SESSION['mensagem_sucesso']) . '</p>';
                    unset($_SESSION['mensagem_sucesso']);
                }
                if (isset($_SESSION['mostrar_mensagem_recuperacao']) && $_SESSION['mostrar_mensagem_recuperacao']) {
                    echo '<p class="mensagem-info">Muitas tentativas de login malsucedidas. Se esqueceu sua senha, considere recuperá-la (funcionalidade de recuperação não implementada nesta versão).</p>';
                    unset($_SESSION['mostrar_mensagem_recuperacao']);
                }
            ?>

            <form action="login.php" method="POST">
                <div class="form-grupo">
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_SESSION['login_email_tentativa']) ? htmlspecialchars($_SESSION['login_email_tentativa']) : ''; unset($_SESSION['login_email_tentativa']); ?>">
                </div>

                <div class="form-grupo">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" required>
                </div>

                <div class="form-grupo text-center">
                    <button type="submit" class="botao">Entrar</button>
                </div>
            </form>
            <p class="text-center mt-1">Não tem uma conta? <a href="cadastro.php">Cadastre-se aqui</a>.</p>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Meu E-commerce. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html>
