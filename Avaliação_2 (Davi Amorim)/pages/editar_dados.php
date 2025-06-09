<?php
session_start();
require_once '../config/conexao.php';

// --- DEFINIÇÕES DE UPLOAD E HISTÓRICO ---
if (!defined('TAMANHO_MAX_FOTO')) define('TAMANHO_MAX_FOTO', 2 * 1024 * 1024);
if (!defined('TAMANHO_MAX_PDF')) define('TAMANHO_MAX_PDF', 5 * 1024 * 1024);
if (!defined('TIPOS_PERMITIDOS_FOTO')) define('TIPOS_PERMITIDOS_FOTO', ['image/jpeg', 'image/png']);
if (!defined('TIPOS_PERMITIDOS_PDF')) define('TIPOS_PERMITIDOS_PDF', ['application/pdf']);
if (!defined('PASTA_UPLOADS_IMG')) define('PASTA_UPLOADS_IMG', '../assets/img/');
if (!defined('PASTA_UPLOADS_PDF')) define('PASTA_UPLOADS_PDF', '../assets/pdfs/');
// Novas constantes para as pastas de histórico
if (!defined('PASTA_HISTORY_IMG')) define('PASTA_HISTORY_IMG', '../assets/img/history/');
if (!defined('PASTA_HISTORY_PDF')) define('PASTA_HISTORY_PDF', '../assets/pdfs/history/');

if (!isset($_SESSION['cliente_id'])) {
    $_SESSION['mensagem_erro'] = "Você precisa estar logado para editar seus dados.";
    header("Location: ../login.php");
    exit();
}

$cliente_id = $_SESSION['cliente_id'];
$cliente_atual = null;
$mensagem_erro_form = '';
$mensagem_sucesso_form = '';

// Busca os dados atuais do cliente
$sql_cliente_atual = "SELECT nome, email, telefone, endereco, foto, pdf FROM clientes WHERE id = ?";
$stmt_atual = $conn->prepare($sql_cliente_atual);
if ($stmt_atual) {
    $stmt_atual->bind_param("i", $cliente_id);
    $stmt_atual->execute();
    $resultado_atual = $stmt_atual->get_result();
    if ($resultado_atual->num_rows === 1) {
        $cliente_atual = $resultado_atual->fetch_assoc();
    } else {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['mensagem_erro'] = "Erro ao carregar seus dados. Faça login novamente.";
        header("Location: ../login.php");
        exit();
    }
    $stmt_atual->close();
} else {
    error_log("Erro ao preparar statement (buscar cliente para edicao): " . $conn->error);
    $mensagem_erro_form = "Não foi possível carregar seus dados para edição. Tente mais tarde.";
}

// --- FUNÇÃO DE UPLOAD MODIFICADA PARA MOVER ARQUIVOS ANTIGOS ---
function tratarUploadArquivoEdicao($arquivo, $pastaDestino, $pastaHistory, $tiposPermitidos, $tamanhoMaximo, $campoNome, $clienteId, $arquivoAntigo = null) {
    if (isset($arquivo) && $arquivo['error'] === UPLOAD_ERR_OK && $arquivo['size'] > 0) {
        // Validações
        if (!in_array($arquivo['type'], $tiposPermitidos)) {
            return ['erro' => "Tipo de arquivo inválido para $campoNome."];
        }
        if ($arquivo['size'] > $tamanhoMaximo) {
            return ['erro' => "O arquivo para $campoNome é muito grande."];
        }

        // Processamento do novo arquivo
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $nomeNovoArquivo = uniqid(str_replace(' ', '_', $campoNome) . '_edit_', true) . '.' . $extensao;
        $caminhoCompletoNovo = $pastaDestino . $nomeNovoArquivo;
        if (!is_dir($pastaDestino)) {
            mkdir($pastaDestino, 0775, true);
        }

        // Tenta mover o novo arquivo
        if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompletoNovo)) {
            $nomeArquivoHistoricoFinal = $arquivoAntigo; // Por padrão, o valor antigo é o nome original

            // Se o upload do novo arquivo for bem-sucedido, move o antigo para o histórico
            if ($arquivoAntigo && file_exists($pastaDestino . $arquivoAntigo)) {
                if (!is_dir($pastaHistory)) {
                    mkdir($pastaHistory, 0775, true);
                }
                $nomeArquivoHistorico = time() . '_' . $clienteId . '_' . basename($arquivoAntigo);
                $caminhoCompletoHistorico = $pastaHistory . $nomeArquivoHistorico;
                
                if (rename($pastaDestino . $arquivoAntigo, $caminhoCompletoHistorico)) {
                    $nomeArquivoHistoricoFinal = $nomeArquivoHistorico;
                } else {
                    error_log("Falha ao mover arquivo antigo '$arquivoAntigo' para o histórico '$caminhoCompletoHistorico'.");
                }
            }
            return ['sucesso' => $nomeNovoArquivo, 'arquivo_historico' => $nomeArquivoHistoricoFinal];
        } else {
            error_log("Falha ao mover arquivo para $caminhoCompletoNovo. Permissões?");
            return ['erro' => "Erro ao salvar o novo arquivo $campoNome."];
        }
    } elseif (isset($arquivo) && $arquivo['error'] !== UPLOAD_ERR_NO_FILE && $arquivo['error'] !== UPLOAD_ERR_OK) {
        error_log("Erro de upload para $campoNome (código ".$arquivo['error'].")");
        return ['erro' => "Ocorreu um erro no upload do arquivo $campoNome (Código: " . $arquivo['error'] . ")."];
    }
    return ['sucesso' => $arquivoAntigo, 'nenhum_novo_arquivo' => true];
}


// Processamento do formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['salvar_dados'])) {
    if (!$cliente_atual) { 
        $mensagem_erro_form = "Não foi possível carregar os dados atuais para edição. Tente recarregar a página.";
    } else {
        $nome_novo = trim($_POST['nome'] ?? '');
        $email_novo = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $telefone_novo = trim($_POST['telefone'] ?? '');
        $endereco_novo = trim($_POST['endereco'] ?? '');
        
        $senha_nova = $_POST['senha_nova'] ?? '';
        $confirmar_senha_nova = $_POST['confirmar_senha_nova'] ?? '';

        $erros_validacao = [];
        $alteracoes_para_log = []; 

        if (empty($nome_novo)) $erros_validacao[] = "O nome é obrigatório.";
        if (!filter_var($email_novo, FILTER_VALIDATE_EMAIL)) $erros_validacao[] = "Formato de e-mail inválido.";

        // --- CORREÇÃO: Inicialização da variável $alterar_senha ---
        $alterar_senha = false;
        $senha_hash_nova = null; 
        if (!empty($senha_nova) || !empty($confirmar_senha_nova)) {
            if (empty($senha_nova) || strlen($senha_nova) < 6) {
                $erros_validacao[] = "A nova senha deve ter pelo menos 6 caracteres.";
            }
            if ($senha_nova !== $confirmar_senha_nova) {
                $erros_validacao[] = "As novas senhas não coincidem.";
            }
            
            $erros_relacionados_a_senha = array_filter($erros_validacao, function($erro) {
                return strpos(strtolower($erro), 'senha') !== false;
            });

            if (empty($erros_relacionados_a_senha)) {
                 $temp_hash = password_hash($senha_nova, PASSWORD_DEFAULT);
                 if ($temp_hash === false) {
                     $erros_validacao[] = "Erro crítico ao processar a nova senha.";
                 } else {
                     $senha_hash_nova = $temp_hash;
                     $alterar_senha = true; 
                 }
            }
        }
        
        if ($email_novo !== $cliente_atual['email']) {
            $sql_check_email_edit = "SELECT id FROM clientes WHERE email = ? AND id != ?";
            $stmt_check_email_edit = $conn->prepare($sql_check_email_edit);
            if ($stmt_check_email_edit) {
                $stmt_check_email_edit->bind_param("si", $email_novo, $cliente_id);
                $stmt_check_email_edit->execute();
                $stmt_check_email_edit->store_result();
                if ($stmt_check_email_edit->num_rows > 0) {
                    $erros_validacao[] = "Este e-mail já está em uso por outro cliente.";
                }
                $stmt_check_email_edit->close();
            } else {
                 $erros_validacao[] = "Erro ao verificar e-mail. Tente novamente.";
                 error_log("Erro ao preparar (check_email_edit): " . $conn->error);
            }
        }

        // Processamento de upload de arquivos
        $nomeArquivoFotoAntigo = $cliente_atual['foto'];
        $nomeArquivoFotoParaSalvar = $nomeArquivoFotoAntigo; 
        $uploadFotoNova = tratarUploadArquivoEdicao($_FILES['foto_perfil_nova'], PASTA_UPLOADS_IMG, PASTA_HISTORY_IMG, TIPOS_PERMITIDOS_FOTO, TAMANHO_MAX_FOTO, 'Foto_de_Perfil', $cliente_id, $nomeArquivoFotoAntigo);
        if (isset($uploadFotoNova['erro'])) {
            $erros_validacao[] = $uploadFotoNova['erro'];
        } elseif (!isset($uploadFotoNova['nenhum_novo_arquivo'])) {
            $nomeArquivoFotoParaSalvar = $uploadFotoNova['sucesso']; 
        }

        $nomeArquivoPdfAntigo = $cliente_atual['pdf'];
        $nomeArquivoPdfParaSalvar = $nomeArquivoPdfAntigo; 
        $uploadPdfNovo = tratarUploadArquivoEdicao($_FILES['documento_pdf_novo'], PASTA_UPLOADS_PDF, PASTA_HISTORY_PDF, TIPOS_PERMITIDOS_PDF, TAMANHO_MAX_PDF, 'Documento_PDF', $cliente_id, $nomeArquivoPdfAntigo);
        if (isset($uploadPdfNovo['erro'])) {
            $erros_validacao[] = $uploadPdfNovo['erro'];
        } elseif (!isset($uploadPdfNovo['nenhum_novo_arquivo'])) {
            $nomeArquivoPdfParaSalvar = $uploadPdfNovo['sucesso']; 
        }
        
        // Se todas as validações passaram
        if (empty($erros_validacao)) {
            // Preparar logs para campos de texto
            if ($nome_novo !== $cliente_atual['nome']) $alteracoes_para_log[] = ['campo' => 'nome', 'antigo' => $cliente_atual['nome'], 'novo' => $nome_novo];
            if ($email_novo !== $cliente_atual['email']) $alteracoes_para_log[] = ['campo' => 'email', 'antigo' => $cliente_atual['email'], 'novo' => $email_novo];
            if ($telefone_novo !== $cliente_atual['telefone']) $alteracoes_para_log[] = ['campo' => 'telefone', 'antigo' => $cliente_atual['telefone'], 'novo' => $telefone_novo];
            if ($endereco_novo !== $cliente_atual['endereco']) $alteracoes_para_log[] = ['campo' => 'endereco', 'antigo' => $cliente_atual['endereco'], 'novo' => $endereco_novo];

            // Preparar logs para arquivos
            if ($nomeArquivoFotoParaSalvar !== $nomeArquivoFotoAntigo) { 
                $valor_antigo_log = isset($uploadFotoNova['arquivo_historico']) ? $uploadFotoNova['arquivo_historico'] : $nomeArquivoFotoAntigo;
                $alteracoes_para_log[] = ['campo' => 'foto', 'antigo' => $valor_antigo_log, 'novo' => $nomeArquivoFotoParaSalvar];
            }
            if ($nomeArquivoPdfParaSalvar !== $nomeArquivoPdfAntigo) {
                $valor_antigo_log = isset($uploadPdfNovo['arquivo_historico']) ? $uploadPdfNovo['arquivo_historico'] : $nomeArquivoPdfAntigo;
                $alteracoes_para_log[] = ['campo' => 'pdf', 'antigo' => $valor_antigo_log, 'novo' => $nomeArquivoPdfParaSalvar];
            }
            if ($alterar_senha) {
                $alteracoes_para_log[] = ['campo' => 'senha', 'antigo' => '[SENHA OCULTA]', 'novo' => '[SENHA ALTERADA]'];
            }
       
            // Inserir logs no banco
            if (!empty($alteracoes_para_log)) {
                $sql_log = "INSERT INTO log_alteracoes_clientes (cliente_id, campo_alterado, valor_antigo, valor_novo) VALUES (?, ?, ?, ?)";
                $stmt_log = $conn->prepare($sql_log);
                if ($stmt_log) {
                    foreach ($alteracoes_para_log as $log_entry) {
                        $stmt_log->bind_param("isss", $cliente_id, $log_entry['campo'], $log_entry['antigo'], $log_entry['novo']);
                        if (!$stmt_log->execute()) {
                            error_log("Erro ao inserir log de alteração para cliente_id $cliente_id, campo ".$log_entry['campo'].": " . $stmt_log->error);
                        }
                    }
                    $stmt_log->close();
                } else {
                    error_log("Erro ao preparar statement para log (cliente_id $cliente_id): " . $conn->error);
                }
            }

            // Atualizar os dados do cliente no banco
            $campos_sql_update = "nome = ?, email = ?, telefone = ?, endereco = ?, foto = ?, pdf = ?";
            $params_sql_update = [$nome_novo, $email_novo, $telefone_novo, $endereco_novo, $nomeArquivoFotoParaSalvar, $nomeArquivoPdfParaSalvar];
            $types_sql_update = "ssssss";

            if ($alterar_senha && $senha_hash_nova) { 
                $campos_sql_update .= ", senha = ?";
                $params_sql_update[] = $senha_hash_nova;
                $types_sql_update .= "s";
            }
            $params_sql_update[] = $cliente_id; 
            $types_sql_update .= "i";

            $sql_update_cliente = "UPDATE clientes SET $campos_sql_update WHERE id = ?";
            $stmt_update_cliente = $conn->prepare($sql_update_cliente);

            if ($stmt_update_cliente) {
                $stmt_update_cliente->bind_param($types_sql_update, ...$params_sql_update);
                if ($stmt_update_cliente->execute()) {
                    $mensagem_sucesso_form = "Seus dados foram atualizados com sucesso!";
                    // Atualiza os dados na sessão e na variável local para exibição imediata
                    $_SESSION['cliente_nome'] = $nome_novo;
                    $_SESSION['cliente_email'] = $email_novo;
                    $_SESSION['cliente_foto'] = $nomeArquivoFotoParaSalvar; 
                    
                    $cliente_atual['nome'] = $nome_novo;
                    $cliente_atual['email'] = $email_novo;
                    $cliente_atual['telefone'] = $telefone_novo;
                    $cliente_atual['endereco'] = $endereco_novo;
                    $cliente_atual['foto'] = $nomeArquivoFotoParaSalvar;
                    $cliente_atual['pdf'] = $nomeArquivoPdfParaSalvar;
                } else {
                    $mensagem_erro_form = "Erro ao atualizar seus dados no banco: " . $stmt_update_cliente->error;
                    error_log("Erro ao executar UPDATE cliente (ID $cliente_id): " . $stmt_update_cliente->error);
                }
                $stmt_update_cliente->close();
            } else {
                 $mensagem_erro_form = "Erro ao preparar a atualização dos seus dados: " . $conn->error;
                 error_log("Erro ao preparar UPDATE cliente (ID $cliente_id): " . $conn->error);
            }
        } else {
            $mensagem_erro_form = implode("<br>", $erros_validacao);
        }
    }
}
if (isset($conn)) {
    $conn->close();
}
$nome_pagina = "EditarDados"; 
?>
<!-- O HTML abaixo permanece o mesmo -->
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Meus Dados - Meu E-commerce</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container-header">
            <h1><a href="../index.php">Meu E-commerce</a></h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="carrinho.php">Carrinho</a></li>
                    <?php if (isset($_SESSION['cliente_id'])): ?>
                        <li><a href="meus_dados.php" class="<?php echo ($nome_pagina == 'EditarDados' || $nome_pagina == 'MeusDados') ? 'ativo' : ''; ?>">Meus Dados</a></li>
                        <li><a href="meus_pedidos.php">Meus Pedidos</a></li>
                        <li><a href="../logout.php">Logout (<?php echo htmlspecialchars(explode(" ", $_SESSION['cliente_nome'])[0]); ?>)</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="form-container" style="max-width: 650px;">
            <h2>Editar Meus Dados Cadastrais</h2>

            <?php if (!empty($mensagem_erro_form)): ?>
                <p class="mensagem-erro"><?php echo $mensagem_erro_form; ?></p>
            <?php endif; ?>
            <?php if (!empty($mensagem_sucesso_form)): ?>
                <p class="mensagem-sucesso"><?php echo htmlspecialchars($mensagem_sucesso_form); ?></p>
            <?php endif; ?>

            <?php if ($cliente_atual): ?>
            <form action="editar_dados.php" method="POST" enctype="multipart/form-data">
                <div class="form-grupo">
                    <label for="nome">Nome Completo:</label>
                    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($cliente_atual['nome']); ?>" required>
                </div>

                <div class="form-grupo">
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($cliente_atual['email']); ?>" required>
                </div>

                <div class="form-grupo">
                    <label for="telefone">Telefone (opcional):</label>
                    <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($cliente_atual['telefone'] ?? ''); ?>" placeholder="(XX) XXXXX-XXXX">
                </div>

                <div class="form-grupo">
                    <label for="endereco">Endereço Completo (opcional):</label>
                    <textarea id="endereco" name="endereco" rows="3"><?php echo htmlspecialchars($cliente_atual['endereco'] ?? ''); ?></textarea>
                </div>

                <hr style="margin: 25px 0;">
                <p style="font-size:0.9em; color:#555;">Deixe os campos de senha em branco se não desejar alterá-la.</p>
                <div class="form-grupo">
                    <label for="senha_nova">Nova Senha (mín. 6 caracteres):</label>
                    <input type="password" id="senha_nova" name="senha_nova" minlength="6">
                </div>
                <div class="form-grupo">
                    <label for="confirmar_senha_nova">Confirmar Nova Senha:</label>
                    <input type="password" id="confirmar_senha_nova" name="confirmar_senha_nova">
                </div>
                <hr style="margin: 25px 0;">

                <div class="form-grupo">
                    <label for="foto_perfil_nova">Alterar Foto de Perfil (JPG, PNG - máx 2MB):</label>
                    <?php if (!empty($cliente_atual['foto'])): ?>
                        <p style="font-size:0.9em;">Foto atual: 
                            <a href="<?php echo PASTA_UPLOADS_IMG . htmlspecialchars($cliente_atual['foto']); ?>" target="_blank">
                                <?php echo htmlspecialchars($cliente_atual['foto']); ?>
                            </a>
                            <img src="<?php echo PASTA_UPLOADS_IMG . htmlspecialchars($cliente_atual['foto']); ?>" alt="Foto atual" style="max-width: 50px; max-height: 50px; vertical-align: middle; margin-left: 10px; border-radius: 4px;">
                        </p>
                    <?php endif; ?>
                    <input type="file" id="foto_perfil_nova" name="foto_perfil_nova" accept="image/jpeg, image/png">
                </div>

                <div class="form-grupo">
                    <label for="documento_pdf_novo">Alterar Documento PDF (máx 5MB):</label>
                    <?php if (!empty($cliente_atual['pdf'])): ?>
                        <p style="font-size:0.9em;">PDF atual: 
                            <a href="<?php echo PASTA_UPLOADS_PDF . htmlspecialchars($cliente_atual['pdf']); ?>" target="_blank">
                                <?php echo htmlspecialchars($cliente_atual['pdf']); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    <input type="file" id="documento_pdf_novo" name="documento_pdf_novo" accept="application/pdf">
                </div>

                <div class="form-grupo text-center">
                    <button type="submit" name="salvar_dados" class="botao">Salvar Alterações</button>
                    <a href="meus_dados.php" class="botao botao-secundario" style="margin-left:10px;">Cancelar</a>
                </div>
            </form>
            <?php else: ?>
                <p class="mensagem-info">Não foi possível carregar seus dados para edição no momento. Por favor, tente recarregar a página ou contate o suporte se o problema persistir.</p>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Meu E-commerce. Todos os direitos reservados.</p>
        </div>
    </footer>
    <script src="../assets/js/script.js"></script>
</body>
</html>
