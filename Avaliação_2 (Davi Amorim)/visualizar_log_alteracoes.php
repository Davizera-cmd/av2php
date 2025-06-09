<?php
// visualizar_log_alteracoes.php
session_start();
require_once 'config/conexao.php';

// Verifica se o administrador está logado
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['mensagem_erro_global'] = "Acesso restrito. Por favor, faça login como administrador.";
    header("Location: login_admin.php");
    exit();
}

// --- PASSO 1: DEFINIÇÃO DOS CAMINHOS DE ARQUIVOS, INCLUINDO HISTÓRICO ---
// Caminhos relativos a este arquivo
if (!defined('PASTA_UPLOADS_IMG')) define('PASTA_UPLOADS_IMG', 'assets/img/');
if (!defined('PASTA_UPLOADS_PDF')) define('PASTA_UPLOADS_PDF', 'assets/pdfs/');
if (!defined('PASTA_HISTORY_IMG')) define('PASTA_HISTORY_IMG', 'assets/img/history/');
if (!defined('PASTA_HISTORY_PDF')) define('PASTA_HISTORY_PDF', 'assets/pdfs/history/');


$logs = [];
$filtro_cliente_id = isset($_GET['cliente_id']) ? filter_var($_GET['cliente_id'], FILTER_VALIDATE_INT) : null;

$sql_logs = "SELECT 
                l.id_log, l.cliente_id, c.nome AS nome_cliente, c.email AS email_cliente, 
                l.campo_alterado, l.valor_antigo, l.valor_novo, l.data_alteracao,
                l.alterado_por_admin_id, a.usuario AS nome_admin
             FROM 
                log_alteracoes_clientes l
             JOIN 
                clientes c ON l.cliente_id = c.id
             LEFT JOIN
                administradores a ON l.alterado_por_admin_id = a.id";

if ($filtro_cliente_id) {
    $sql_logs .= " WHERE l.cliente_id = ?";
}
$sql_logs .= " ORDER BY l.data_alteracao DESC";

$stmt_logs = $conn->prepare($sql_logs);

if ($stmt_logs) {
    if ($filtro_cliente_id) {
        $stmt_logs->bind_param("i", $filtro_cliente_id);
    }
    $stmt_logs->execute();
    $resultado_logs = $stmt_logs->get_result();
    while ($row = $resultado_logs->fetch_assoc()) {
        $dt = new DateTime($row['data_alteracao']);
        $row['data_alteracao_fmt'] = $dt->format('d/m/Y H:i:s');
        $logs[] = $row;
    }
    $stmt_logs->close();
} else {
    error_log("Erro ao preparar statement para buscar logs: " . $conn->error);
    $_SESSION['mensagem_erro_admin_painel'] = "Erro ao carregar o histórico de alterações.";
}
$conn->close();
$nome_pagina_admin = "LogAlteracoes";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de Alterações de Dados de Clientes</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container-header">
            <h1><a href="visualizar_clientes_pedidos.php">Painel Administrativo</a></h1>
            <nav>
                <ul>
                    <li><a href="visualizar_clientes_pedidos.php">Clientes e Pedidos</a></li>
                    <li><a href="visualizar_log_alteracoes.php" class="ativo">Log de Alterações</a></li>
                    <li><a href="index.php">Ver Loja</a></li>
                    <?php if (isset($_SESSION['admin_id'])): ?>
                        <li><a href="logout_admin.php">Logout Admin (<?php echo htmlspecialchars($_SESSION['admin_usuario']); ?>)</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Histórico de Alterações nos Dados dos Clientes</h2>
        
        <form method="GET" action="visualizar_log_alteracoes.php" style="margin-bottom: 20px;">
            <div class="form-grupo" style="max-width: 300px;">
                <label for="cliente_id_filtro">Filtrar por ID do Cliente:</label>
                <input type="number" name="cliente_id" id="cliente_id_filtro" value="<?php echo htmlspecialchars($filtro_cliente_id ?? ''); ?>" placeholder="ID do Cliente">
                <button type="submit" class="botao botao-secundario" style="margin-top:5px; padding: 8px 12px;">Filtrar</button>
                <?php if ($filtro_cliente_id): ?>
                    <a href="visualizar_log_alteracoes.php" style="margin-left:10px; font-size:0.9em;">Limpar Filtro</a>
                <?php endif; ?>
            </div>
        </form>
        
        <?php
        if (isset($_SESSION['mensagem_erro_admin_painel'])) {
            echo '<p class="mensagem-erro">' . htmlspecialchars($_SESSION['mensagem_erro_admin_painel']) . '</p>';
            unset($_SESSION['mensagem_erro_admin_painel']);
        }
        ?>

        <?php if (empty($logs)): ?>
            <p class="mensagem-info">Nenhuma alteração registrada no log<?php echo $filtro_cliente_id ? ' para este cliente' : ''; ?>.</p>
        <?php else: ?>
            <div class="tabela-responsiva">
                <table class="tabela-carrinho"> 
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Cliente (ID)</th>
                            <th>E-mail Cliente</th>
                            <th>Campo Alterado</th>
                            <th>Valor Antigo</th>
                            <th>Valor Novo</th>
                            <th>Alterado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log['data_alteracao_fmt']; ?></td>
                            <td><?php echo htmlspecialchars($log['nome_cliente']); ?> (<?php echo $log['cliente_id']; ?>)</td>
                            <td><?php echo htmlspecialchars($log['email_cliente']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['campo_alterado']))); ?></td>
                            <td>
                                <?php 
                                // --- PASSO 2: LÓGICA DE LINK MODIFICADA PARA O VALOR ANTIGO ---
                                if (in_array($log['campo_alterado'], ['foto', 'pdf']) && !empty($log['valor_antigo'])) {
                                    // Se o campo for 'foto', use a pasta de histórico de imagens. Se for 'pdf', a de PDFs.
                                    $pasta = ($log['campo_alterado'] == 'foto') ? PASTA_HISTORY_IMG : PASTA_HISTORY_PDF;
                                    echo '<a href="' . $pasta . htmlspecialchars($log['valor_antigo']) . '" target="_blank">' . htmlspecialchars($log['valor_antigo']) . '</a>';
                                } else {
                                    echo nl2br(htmlspecialchars($log['valor_antigo']));
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                // A lógica para o VALOR NOVO continua a mesma, apontando para as pastas principais
                                if (in_array($log['campo_alterado'], ['foto', 'pdf']) && !empty($log['valor_novo'])) {
                                    $pasta = ($log['campo_alterado'] == 'foto') ? PASTA_UPLOADS_IMG : PASTA_UPLOADS_PDF;
                                    echo '<a href="' . $pasta . htmlspecialchars($log['valor_novo']) . '" target="_blank">' . htmlspecialchars($log['valor_novo']) . '</a>';
                                } else {
                                    echo nl2br(htmlspecialchars($log['valor_novo']));
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['nome_admin'] ?? 'Cliente'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Meu E-commerce - Painel Administrativo</p>
        </div>
    </footer>
</body>
</html>
