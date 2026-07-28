# 🖥️ Sistema de Gerenciamento de Usuários Linux/Samba

Sistema para administração de usuários Linux e Samba com interface web, fila de pendências e criação automatizada via script bash.

---

## 📋 Índice

1. [Visão Geral](#-visão-geral)
2. [Arquitetura](#-arquitetura)
3. [Pré-requisitos](#-pré-requisitos)
4. [Instalação e Configuração](#-instalação-e-configuração)
5. [Guia de Uso](#-guia-de-uso)
6. [Referência de Arquivos](#-referência-de-arquivos)
7. [Segurança](#-segurança)
8. [Manutenção](#-manutenção)
9. [Solução de Problemas](#-solução-de-problemas)

---

## 🎯 Visão Geral

Este projeto resolve um problema comum em servidores Linux com Samba: **gerenciar criação de usuários** de forma prática e organizada.

### O problema

Em ambientes com muitos usuários (empresas, escolas, etc.), criar manualmente cada usuário no Linux e no Samba consome tempo e é propenso a erros.

### A solução

O sistema oferece:

| Componente | Função |
|------------|--------|
| **Interface Web** | Painel administrativo para cadastrar usuários e acompanhar o status |
| **Script Bash** | Automatiza a criação dos usuários no Linux e Samba |
| **Sistema de Filas** | Usuários pendentes aguardam processamento em lote |

### Fluxo completo

```
┌──────────────┐     ┌──────────────────┐     ┌──────────────────┐
│  Admin faz   │ ──► │  Cadastra novo   │ ──► │  Usuário vai    │
│  login (web) │     │  usuário + setor │     │  p/ fila de     │
└──────────────┘     └──────────────────┘     │  pendentes      │
                                              └────────┬─────────┘
                                                       │
                                                       ▼  (cron ou manual)
                                              ┌──────────────────┐
                                              │  Script bash     │
                                              │  cria usuário:   │
                                              │  ├─ Linux        │
                                              │  ├─ Samba        │
                                              │  ├─ Grupos       │
                                              │  └─ Gera senha   │
                                              └────────┬─────────┘
                                                       │
                                                       ▼
                                              ┌──────────────────┐
                                              │  Usuário vai p/  │
                                              │  lista de        │
                                              │  criados         │
                                              └──────────────────┘
```

---

## 🏗️ Arquitetura

### Camadas do sistema

```
┌─────────────────────────────────────────────────────┐
│                 INTERFACE WEB (PHP)                  │
│  ┌──────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │  Login   │  │  Adicionar   │  │   Listar      │  │
│  │ index.php│  │ usuario.php  │  │  usuarios.php │  │
│  └────┬─────┘  └──────┬───────┘  └───────┬───────┘  │
│       │               │                   │          │
│  ┌────┴─────┐         │                   │          │
│  │ auth.php │◄────────┴───────────────────┘          │
│  └──────────┘    (proteção de autenticação)          │
└─────────────────────────┬───────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────┐
│              ARMAZENAMENTO (Arquivos)                │
│  ┌──────────────────────┐  ┌─────────────────────┐  │
│  │ usuarios_pendentes   │  │  usuarios_criados   │  │
│  │       .txt           │  │       .txt          │  │
│  └──────────────────────┘  └─────────────────────┘  │
│  ┌──────────────────────┐  ┌─────────────────────┐  │
│  │  setores.conf        │  │ usuarios_sistema    │  │
│  │                      │  │      .txt           │  │
│  └──────────────────────┘  └─────────────────────┘  │
└─────────────────────────┬───────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────┐
│             SCRIPT DE CRIAÇÃO (Bash)                 │
│                                                     │
│  ┌──────────────────────────────────────────────┐   │
│  │           cria_usuarios.sh                   │   │
│  │                                              │   │
│  │  1. Lê usuarios_pendentes.txt                │   │
│  │  2. Para cada usuário:                       │   │
│  │     ├─ Extrai setor do login                 │   │
│  │     ├─ Cria no Linux (useradd)               │   │
│  │     ├─ Cria no Samba (smbpasswd)             │   │
│  │     ├─ Adiciona aos grupos                   │   │
│  │     └─ Salva senha gerada                    │   │
│  │  3. Remove processados da fila               │   │
│  └──────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────┘
```

### Estrutura de diretórios

```
/var/www/html/
├── usuarios/                    # 📁 Dados e lógica (raiz do sistema)
│   ├── cria_usuarios.sh         # Script de criação (executado como root)
│   ├── usuarios_pendentes.txt   # Fila de usuários a criar
│   ├── usuarios_criados.txt     # Histórico de usuários criados (+ senhas)
│   ├── usuarios_sistema.txt     # Credenciais do painel admin
│   ├── usuarios_sistema.example.txt  # Exemplo do arquivo de credenciais
│   ├── setores.conf             # Lista de setores/departamentos
│   └── PERMISSOES.md            # Instruções de permissões
│
└── web_usuarios/                # 🌐 Interface web
    ├── index.php                # Página de login
    ├── logout.php               # Logout
    ├── .htaccess                # Configurações Apache
    ├── css/                     # Estilos (tema escuro GitHub)
    └── app/
        ├── auth.php             # Proteção de autenticação
        ├── home.php             # Menu principal
        ├── adicionar_usuario.php # Formulário de cadastro
        └── listar_usuarios.php  # Listagem de pendentes e criados
```

---

## ✅ Pré-requisitos

| Requisito | Versão | Detalhe |
|-----------|--------|---------|
| **Linux** | Qualquer distro | Testado em Debian/Ubuntu |
| **Apache** | 2.4+ | Com mod_rewrite ativado |
| **PHP** | 7.4+ | Com suporte a sessions |
| **Samba** | 4.x | Com `smbpasswd` disponível |
| **Root** | — | Necessário para o script bash |

---

## 🔧 Instalação e Configuração

### 1. Copiar os arquivos para o servidor

```bash
# Copiar para o diretório web (Debian/Ubuntu)
# Em outras distros (RHEL/CentOS), use /var/www/ no lugar
sudo cp -r usuarios/ /var/www/html/
sudo cp -r web_usuarios/ /var/www/html/
```

### 2. Configurar credenciais do painel admin

```bash
cd /var/www/html/usuarios/

# Criar arquivo de credenciais a partir do exemplo
cp usuarios_sistema.example.txt usuarios_sistema.txt

# Editar com as credenciais desejadas
nano usuarios_sistema.txt
```

Formato do arquivo `usuarios_sistema.txt`:
```
admin | minha_senha_aqui
```

> **💡 Dica:** Use `password_hash()` do PHP para gerar hash bcrypt e armazená-lo no arquivo para maior segurança.

### 3. Aplicar permissões corretas

```bash
# Diretório de dados
sudo chown www-data:www-data /var/www/html/usuarios/usuarios_pendentes.txt
sudo chown www-data:www-data /var/www/html/usuarios/usuarios_criados.txt
sudo chmod 664 /var/www/html/usuarios/usuarios_pendentes.txt
sudo chmod 664 /var/www/html/usuarios/usuarios_criados.txt

# Setores (apenas leitura)
sudo chown root:www-data /var/www/html/usuarios/setores.conf
sudo chmod 644 /var/www/html/usuarios/setores.conf

# Script (executável como root)
sudo chown root:root /var/www/html/usuarios/cria_usuarios.sh
sudo chmod 755 /var/www/html/usuarios/cria_usuarios.sh

# Arquivos PHP
sudo chown www-data:www-data /var/www/html/web_usuarios/*.php
sudo chmod 644 /var/www/html/web_usuarios/*.php
```

### 4. Configurar setores

Edite o arquivo `usuarios/setores.conf` e adicione um setor por linha:

```
vendas
ti
rh
financeiro
administrativo
```

### 5. Acessar o painel web

Abra no navegador: `http://SEU_SERVIDOR/web_usuarios/`

> **💡 Nota:** Em distribuições como Debian/Ubuntu, o DocumentRoot padrão do Apache é `/var/www/html/`. Em outras (RHEL/CentOS, Fedora) pode ser `/var/www/`. Ajuste os caminhos conforme sua distro.

---

## 📖 Guia de Uso

### 🌐 Interface Web

#### 🔐 Login
- Acesse `http://seu-servidor/web_usuarios/`
- Use as credenciais definidas em `usuarios_sistema.txt`

#### ➕ Adicionar Usuário
1. No menu principal, clique em **"Adicionar Usuário"**
2. Preencha o **nome** (apenas letras minúsculas, sem acentos)
3. Selecione o **setor** no dropdown
4. O login será gerado automaticamente: `nome.setor` (ex: `joao.vendas`)
5. Clique em "Adicionar Usuário"
6. O usuário entra na fila de pendentes

#### 📋 Listar Usuários
- **⏳ Pendentes**: Usuários aguardando processamento
- **✅ Criados**: Usuários já criados no sistema (com senhas exibidas)

### ⚡ Script Bash (`cria_usuarios.sh`)

#### Execução manual
```bash
sudo /var/www/html/usuarios/cria_usuarios.sh
```

#### Agendamento automático (cron)
```bash
sudo crontab -e
# Adicionar linha:
0 * * * * /var/www/html/usuarios/cria_usuarios.sh >> /var/log/cria_usuarios.log 2>&1
```

Isso executa o script **a cada hora**, no minuto 0.

#### Logs
```bash
# Visualizar logs
tail -f /var/log/cria_usuarios.log
```

### O que o script faz (passo a passo)

1. **Verifica** se está rodando como root (senão, aborta)
2. **Verifica** se o arquivo de pendentes existe
3. **Cria** o grupo padrão `cliente` no Linux se não existir
4. Para **cada usuário** na fila de pendentes:
   - Extrai o setor do login (ex: `joao.vendas` → setor = `vendas`)
   - Valida que o login tem setor (senão, mantém na fila com erro)
   - Verifica se o usuário já existe no Linux
   - **Gera senha** no formato: `{2 primeiras letras}@{4 caracteres aleatórios}` (ex: `jo@a8f3`)
   - **Cria** o usuário no Linux (`useradd -s /bin/false`)
   - **Adiciona** ao Samba com a senha gerada (`smbpasswd -a`)
   - **Habilita** o usuário no Samba
   - **Adiciona** ao grupo padrão `cliente`
   - **Cria** o grupo do setor se não existir
   - **Adiciona** ao grupo do setor
5. **Remove** os processados da fila de pendentes

---

## 📄 Referência de Arquivos

### `usuarios/`

| Arquivo | Função | Formato | Quem escreve | Quem lê |
|---------|--------|---------|--------------|---------|
| `cria_usuarios.sh` | Script de criação | Bash script | Admin | Root (execução) |
| `usuarios_pendentes.txt` | Fila de pendentes | 1 login por linha | PHP (web) | Script bash |
| `usuarios_criados.txt` | Histórico de criados | `login \| senha` | Script bash | PHP (web) |
| `usuarios_sistema.txt` | Credenciais admin | `usuario \| senha_ou_hash` | Admin | PHP (web) |
| `setores.conf` | Lista de setores | 1 setor por linha | Admin | PHP (web) |
| `PERMISSOES.md` | Docs de permissões | Markdown | — | — |

### `web_usuarios/`

| Arquivo | Função | Dependências |
|---------|--------|-------------|
| `index.php` | Login + autenticação | `usuarios_sistema.txt` |
| `logout.php` | Encerra sessão | — |
| `.htaccess` | Regras Apache | mod_rewrite |
| `app/auth.php` | Middleware de proteção | Sessão PHP |
| `app/home.php` | Menu principal | `auth.php` |
| `app/adicionar_usuario.php` | Cadastro de usuário | `auth.php`, `setores.conf`, `usuarios_pendentes.txt` |
| `app/listar_usuarios.php` | Listagem | `auth.php`, `usuarios_pendentes.txt`, `usuarios_criados.txt` |

---

## 🔒 Segurança

### ⚠️ Pontos de atenção

| Item | Risco | Mitigação |
|------|-------|-----------|
| **Senhas em texto claro** | `usuarios_criados.txt` armazena senhas geradas | Restringir acesso ao arquivo (`chmod 660`) |
| **Credenciais admin** | Podem estar em texto plano | Usar hash bcrypt no `usuarios_sistema.txt` |
| **Acesso ao painel** | Qualquer um com credenciais acessa | Usar HTTPS + senha forte |
| **Script como root** | Erro no script pode afetar o sistema | Revisar antes de executar |

### 🔐 Gerando hash bcrypt para o admin

```php
<?php
echo password_hash('minha_senha', PASSWORD_BCRYPT);
?>
```

Cole o hash gerado no `usuarios_sistema.txt`:
```
admin | $2y$10$ExemploDeHashBcryptCom60CaracteresNoTotal
```

### 📋 Checklist de segurança

- [ ] HTTPS configurado no servidor
- [ ] Senha admin forte (ou hash bcrypt)
- [ ] `usuarios_sistema.txt` com permissão 640
- [ ] `usuarios_criados.txt` com permissão 660
- [ ] Acesso ao SSH restrito
- [ ] Logs monitorados

---

## 🔄 Manutenção

### Adicionar novo setor

Edite `usuarios/setores.conf` e adicione uma nova linha. O dropdown no formulário web será atualizado automaticamente.

### Limpar arquivo de criados

Para apagar o histórico (mantendo apenas os logs do script):

```bash
sudo truncate -s 0 /var/www/html/usuarios/usuarios_criados.txt
```

> **⚠️ Cuidado:** Isso não apaga os usuários do Linux/Samba, apenas o registro local.

### Remover usuário do sistema

Para remover um usuário criado acidentalmente:

```bash
sudo pdbedit -x usuario.setor    # Remove do Samba
sudo userdel usuario.setor       # Remove do Linux
sudo rm -rf /home/usuario.setor  # Remove home (se existir)
```

---

## 🔍 Solução de Problemas

### O script não funciona

| Sintoma | Causa | Solução |
|---------|-------|---------|
| `ERRO: Este script precisa ser executado como root` | Sem sudo | Execute com `sudo` |
| `ERRO: Arquivo de pendentes não encontrado` | Caminho errado | Verifique se o diretório é `/var/www/html/usuarios/` |
| `smbpasswd: command not found` | Samba não instalado | `sudo apt install samba` |
| `groupadd: command not found` | Caminho faltando | Use caminho completo: `/usr/sbin/groupadd` |

### O painel web não carrega

| Sintoma | Causa | Solução |
|---------|-------|---------|
| `404 Not Found` | Apache sem PHP | `sudo apt install libapache2-mod-php` |
| Tela em branco | Erro no PHP | Verificar `error_log` do Apache |
| `Usuário ou senha inválidos` | Credenciais erradas | Verificar `usuarios_sistema.txt` |

### Usuário não aparece na lista

1. Verifique se foi adicionado ao `usuarios_pendentes.txt`
2. Verifique permissões do arquivo (`www-data` precisa de escrita)
3. Veja os logs: `tail -f /var/log/cria_usuarios.log`

---

## 📝 Licença

Este é um sistema interno. Use e modifique conforme necessário para seu ambiente.

---

*Documentação gerada em Julho de 2026.*
