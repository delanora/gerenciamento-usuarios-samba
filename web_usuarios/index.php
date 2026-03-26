<?php
/**
 * Sistema de Gerenciamento de Usuários Linux/Samba
 * Página de Login (index.php principal)
 */

session_start();

// Se já estiver logado, redirecionar para app/home.php
if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    header('Location: app/home.php');
    exit;
}

$base_dir = dirname(__DIR__) . '/usuarios';
$credenciais_file = $base_dir . '/usuarios_sistema.txt';

$mensagem = '';
$tipo_mensagem = '';

// Processar formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    
    if (empty($usuario) || empty($senha)) {
        $mensagem = 'Usuário e senha são obrigatórios!';
        $tipo_mensagem = 'erro';
    } else {
        // Carregar credenciais do arquivo
        $credenciais = [];
        if (file_exists($credenciais_file) && is_readable($credenciais_file)) {
            $linhas = @file($credenciais_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($linhas !== false && is_array($linhas)) {
                foreach ($linhas as $linha) {
                    $parts = explode(' | ', $linha);
                    if (count($parts) >= 2) {
                        $credenciais[trim($parts[0])] = trim($parts[1]);
                    }
                }
            }
        }
        
        // Verificar credenciais
        if (isset($credenciais[$usuario])) {
            // Verificar senha (pode ser hash ou texto simples)
            $senha_armazenada = $credenciais[$usuario];
            
            // Se começar com $2y$, é hash bcrypt
            if (strpos($senha_armazenada, '$2y$') === 0) {
                $senha_valida = password_verify($senha, $senha_armazenada);
            } else {
                // Comparação direta (para senha em texto)
                $senha_valida = ($senha === $senha_armazenada);
            }
            
            if ($senha_valida) {
                // Login bem-sucedido
                $_SESSION['logado'] = true;
                $_SESSION['usuario'] = $usuario;
                header('Location: app/home.php');
                exit;
            } else {
                $mensagem = 'Usuário ou senha inválidos!';
                $tipo_mensagem = 'erro';
            }
        } else {
            $mensagem = 'Usuário ou senha inválidos!';
            $tipo_mensagem = 'erro';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gerenciamento de Usuários</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="css/form.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card card">
            <h1>🔐 Login</h1>
            
            <?php if ($mensagem): ?>
                <div class="mensagem <?php echo $tipo_mensagem; ?>">
                    <?php echo htmlspecialchars($mensagem); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="usuario">Usuário:</label>
                    <input type="text" 
                           id="usuario" 
                           name="usuario" 
                           value="<?php echo htmlspecialchars($usuario ?? ''); ?>" 
                           required 
                           autofocus
                           placeholder="Digite seu usuário">
                </div>
                
                <div class="form-group">
                    <label for="senha">Senha:</label>
                    <input type="password" 
                           id="senha" 
                           name="senha" 
                           required 
                           placeholder="Digite sua senha">
                </div>
                
                <button type="submit">Entrar</button>
            </form>
            
            <div class="footer">
                Sistema de Gerenciamento de Usuários
            </div>
        </div>
    </div>
</body>
</html>

