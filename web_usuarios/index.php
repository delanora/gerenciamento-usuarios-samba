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
            $senha_armazenada = $credenciais[$usuario];

            // Se começar com $2y$, é hash bcrypt
            if (strpos($senha_armazenada, '$2y$') === 0) {
                $senha_valida = password_verify($senha, $senha_armazenada);
            } else {
                // Comparação direta (para senha em texto)
                $senha_valida = ($senha === $senha_armazenada);
            }

            if ($senha_valida) {
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
        <div class="login-card">
            <div class="login-lock-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#58a6ff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    <circle cx="12" cy="16" r="1.5" fill="#58a6ff"/>
                </svg>
            </div>

            <h1>Acesso ao Sistema</h1>

            <?php if ($mensagem): ?>
                <div class="mensagem <?php echo $tipo_mensagem; ?>">
                    <span class="mensagem-icon">
                        <?php if ($tipo_mensagem === 'sucesso'): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php else: ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <?php endif; ?>
                    </span>
                    <span><?php echo htmlspecialchars($mensagem); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="usuario">Usuário</label>
                    <input type="text"
                           id="usuario"
                           name="usuario"
                           value="<?php echo htmlspecialchars($usuario ?? ''); ?>"
                           required
                           autofocus
                           placeholder="Digite seu usuário">
                </div>

                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password"
                           id="senha"
                           name="senha"
                           required
                           placeholder="Digite sua senha">
                </div>

                <button type="submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Entrar
                </button>
            </form>

            <div class="login-footer">
                <strong>Sistema de Gerenciamento de Usuários</strong><br>
                Linux / Samba
            </div>
        </div>
    </div>
</body>
</html>
