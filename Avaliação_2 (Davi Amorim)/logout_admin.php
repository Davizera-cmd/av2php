<?php
session_start();

unset($_SESSION['admin_id']);
unset($_SESSION['admin_usuario']);
unset($_SESSION['admin_nome']);

$_SESSION['mensagem_sucesso_global'] = "Logout administrativo realizado com sucesso!";
header("Location: login_admin.php");
exit();
?>
