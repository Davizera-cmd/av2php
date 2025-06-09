<?php
session_start();
require_once 'config/conexao.php';

define('TAMANHO_MAX_FOTO', 2 * 1024 * 1024);
define('TAMANHO_MAX_PDF', 5 * 1024 * 1024);
define('TIPOS_PERMITIDOS_FOTO', ['image/jpeg', 'image/png']);
define('TIPOS_PERMITIDOS_PDF', ['application/pdf']);
define('PASTA_UPLOADS_IMG', 'assets/img/');
define('PASTA_UPLOADS_PDF', 'assets/pdfs/');

function redirecionarComMensagem($tipo, $mensagem) {
    $_SESSION[$tipo] = $mensagem;
    header("Location: cadastro.php");
    exit();
}

function tratarUploadArquivo($arquivo, $pastaDestino, $tiposPermitidos, $tamanhoMaximo, $campoNome) {
    if (isset($arquivo) && $arquivo['error'] === UPLOAD_ERR_OK) {
        if (!in_array($arquivo['type'], $tiposPermitidos)) {
            return ['erro' => "Tipo de arquivo inválido para o campo $campoNome. Tipos permitidos: " . implode(', ', $tiposPermitidos)];
        }
        if ($arquivo['size'] > $tamanhoMaximo) {
            return ['erro' => "O arquivo para o campo $campoNome é muito grande. Tamanho máximo: " . ($tamanhoMaximo / 1024 / 1024) . "MB."];
        }

        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $nomeArquivo = uniqid($campoNome . '_', true) . '.' . $extensao;
        $caminhoCompleto = $pastaDestino . $nomeArquivo;

        if (!is_dir($pastaDestino)) {
            mkdir($pastaDestino, 0775, true);
        }

        if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            return ['sucesso' => $nomeArquivo];
        } else {
            error_log("Erro ao mover arquivo para $caminhoCompleto.");
            return ['erro' => "Erro ao salvar o arquivo $campoNome. Tente novamente."];
        }
    } elseif (isset($arquivo) && $arquivo['error'] !== UPLOAD_ERR_NO_FILE) {
        error_log("Erro de upload para $campoNome: " . $arquivo['error']);
        return ['erro' => "Ocorreu um erro durante o upload do arquivo $campoNome."];
    }
    return ['erro' => "O campo $campoNome é obrigatório."];
}

// Processa o POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');

    if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
        redirecionarComMensagem('mensagem_erro', 'Todos os campos marcados com * são obrigatórios.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirecionarComMensagem('mensagem_erro', 'Formato de e-mail inválido.');
    }
    if (strlen($senha) < 6) {
        redirecionarComMensagem('mensagem_erro', 'A senha deve ter pelo menos 6 caracteres.');
    }
    if ($senha !== $confirmar_senha) {
        redirecionarComMensagem('mensagem_erro', 'As senhas não coincidem.');
    }

    $sql_check_email = "SELECT id FROM clientes WHERE email = ?";
    $stmt_check_email = $conn->prepare($sql_check_email);
    if (!$stmt_check_email) {
        error_log("Erro ao preparar statement (check_email): " . $conn->error);
        redirecionarComMensagem('mensagem_erro', 'Erro no servidor. Tente mais tarde.');
    }
    $stmt_check_email->bind_param("s", $email);
    $stmt_check_email->execute();
    $stmt_check_email->store_result();
    if ($stmt_check_email->num_rows > 0) {
        $stmt_check_email->close();
        redirecionarComMensagem('mensagem_erro', 'Este e-mail já está cadastrado.');
    }
    $stmt_check_email->close();

    $uploadFoto = tratarUploadArquivo($_FILES['foto_perfil'], PASTA_UPLOADS_IMG, TIPOS_PERMITIDOS_FOTO, TAMANHO_MAX_FOTO, 'Foto de Perfil');
    if (isset($uploadFoto['erro'])) {
        redirecionarComMensagem('mensagem_erro', $uploadFoto['erro']);
    }
    $nomeArquivoFoto = $uploadFoto['sucesso'];

    $uploadPdf = tratarUploadArquivo($_FILES['documento_pdf'], PASTA_UPLOADS_PDF, TIPOS_PERMITIDOS_PDF, TAMANHO_MAX_PDF, 'Documento PDF');
    if (isset($uploadPdf['erro'])) {
        if ($nomeArquivoFoto && file_exists(PASTA_UPLOADS_IMG . $nomeArquivoFoto)) {
            unlink(PASTA_UPLOADS_IMG . $nomeArquivoFoto);
        }
        redirecionarComMensagem('mensagem_erro', $uploadPdf['erro']);
    }
    $nomeArquivoPdf = $uploadPdf['sucesso'];

    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    if ($senha_hash === false) {
        error_log("Erro ao gerar hash da senha.");
        if ($nomeArquivoFoto && file_exists(PASTA_UPLOADS_IMG . $nomeArquivoFoto)) unlink(PASTA_UPLOADS_IMG . $nomeArquivoFoto);
        if ($nomeArquivoPdf && file_exists(PASTA_UPLOADS_PDF . $nomeArquivoPdf)) unlink(PASTA_UPLOADS_PDF . $nomeArquivoPdf);
        redirecionarComMensagem('mensagem_erro', 'Erro crítico de segurança. Tente mais tarde.');
    }

    $sql_insert = "INSERT INTO clientes (nome, email, senha, telefone, endereco, foto, pdf) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);

    if (!$stmt_insert) {
        error_log("Erro ao preparar statement (insert_cliente): " . $conn->error);
        if ($nomeArquivoFoto && file_exists(PASTA_UPLOADS_IMG . $nomeArquivoFoto)) unlink(PASTA_UPLOADS_IMG . $nomeArquivoFoto);
        if ($nomeArquivoPdf && file_exists(PASTA_UPLOADS_PDF . $nomeArquivoPdf)) unlink(PASTA_UPLOADS_PDF . $nomeArquivoPdf);
        redirecionarComMensagem('mensagem_erro', 'Erro no servidor ao tentar cadastrar. Tente mais tarde.');
    }

    $stmt_insert->bind_param("sssssss", $nome, $email, $senha_hash, $telefone, $endereco, $nomeArquivoFoto, $nomeArquivoPdf);

    if ($stmt_insert->execute()) {
        $_SESSION['mensagem_sucesso'] = 'Cadastro realizado com sucesso! Você já pode fazer o login.';
        header("Location: login.php");
        exit();
    } else {
        error_log("Erro ao executar statement (insert_cliente): " . $stmt_insert->error);

        if ($nomeArquivoFoto && file_exists(PASTA_UPLOADS_IMG . $nomeArquivoFoto)) unlink(PASTA_UPLOADS_IMG . $nomeArquivoFoto);
        if ($nomeArquivoPdf && file_exists(PASTA_UPLOADS_PDF . $nomeArquivoPdf)) unlink(PASTA_UPLOADS_PDF . $nomeArquivoPdf);
        redirecionarComMensagem('mensagem_erro', 'Não foi possível realizar o cadastro. Por favor, tente novamente.');
    }

    $stmt_insert->close();
    $conn->close();
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Cadastro de Cliente - Meu E-commerce</title>
    <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
    <header>
        <div class="container-header">
            <h1><a href="index.php">Meu E-commerce</a></h1>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="pages/carrinho.php">Carrinho</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="cadastro.php" class="ativo">Cadastro</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="form-container">
            <h2>Cadastro de Novo Cliente</h2>

            <?php
                if (isset($_SESSION['mensagem_erro'])) {
                    echo '<p class="mensagem-erro">' . htmlspecialchars($_SESSION['mensagem_erro']) . '</p>';
                    unset($_SESSION['mensagem_erro']);
                }
                if (isset($_SESSION['mensagem_sucesso'])) {
                    echo '<p class="mensagem-sucesso">' . htmlspecialchars($_SESSION['mensagem_sucesso']) . '</p>';
                    unset($_SESSION['mensagem_sucesso']);
                }
            ?>

            <form action="cadastro.php" method="POST" enctype="multipart/form-data" id="formCadastro">
                <div class="form-grupo">
                    <label for="nome">Nome Completo:</label>
                    <input type="text" id="nome" name="nome" required />
                </div>

                <div class="form-grupo">
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" required />
                </div>

                <div class="form-grupo">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" required minlength="6" />
                </div>

                <div class="form-grupo">
                    <label for="confirmar_senha">Confirmar Senha:</label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" required />
                </div>

                <div class="form-grupo">
                    <label for="telefone">Telefone (opcional):</label>
                    <input type="tel" id="telefone" name="telefone" placeholder="(XX) XXXXX-XXXX" />
                </div>

                <div class="form-grupo">
                    <label for="endereco">Endereço Completo (opcional):</label>
                    <textarea id="endereco" name="endereco" rows="3"></textarea>
                </div>

                <div class="form-grupo">
                    <label for="foto_perfil">Foto de Perfil (JPG, PNG - Obrigatória, máx 2MB):</label>
                    <input type="file" id="foto_perfil" name="foto_perfil" accept="image/jpeg, image/png" required />
                </div>

                <div class="form-grupo">
                    <label for="documento_pdf">Documento Comprovante de Residência PDF (Obrigatório, máx 5MB):</label>
                    <input type="file" id="documento_pdf" name="documento_pdf" accept="application/pdf" required />
                </div>

                <div class="form-grupo text-center">
                    <button type="submit" class="botao">Cadastrar</button>
                </div>
            </form>

            <p class="text-center mt-1">Já tem uma conta? <a href="login.php">Faça login aqui</a>.</p>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Meu E-commerce. Todos os direitos reservados.</p>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>
