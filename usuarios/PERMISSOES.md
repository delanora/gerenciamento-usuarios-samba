# 🔒 Guia de Permissões, Instalação e Operação

Este documento detalha todas as permissões necessárias para o funcionamento correto e seguro do sistema de gerenciamento de usuários.

---

## 📋 Pré-requisitos de ambiente

Antes de configurar as permissões, certifique-se de que:

1. O Apache (`www-data`) é o usuário do servidor web
2. O PHP tem permissão para ler/escrever nos arquivos de dados
3. O script bash será executado como **root** (via sudo ou cron)

---

## 🔐 Permissões dos Arquivos

### 📁 Localização dos arquivos

```
/var/www/html/
├── usuarios/           # Dados do sistema
└── web_usuarios/       # Interface web

/var/backups/samba/      # Backup smb.conf + arquivo de staging
```

### 🎯 Tabela de permissões

| Arquivo | Proprietário | Grupo | Permissão | Quem escreve | Quem lê |
|---------|-------------|-------|-----------|-------------|---------|
| `usuarios/usuarios_pendentes.txt` | `www-data` | `www-data` | `664` | PHP (web) + Script (root) | PHP (web) + Script (root) |
| `usuarios/usuarios_criados.txt` | `www-data` | `www-data` | `664` | Script (root) | PHP (web) |
| `usuarios/setores.conf` | `root` | `www-data` | `664` | PHP (web) via Gerenciar Setores | PHP (web) |
| `usuarios/usuarios_sistema.txt` | `root` | `www-data` | `640` | Admin (manual) | PHP (web) |
| `usuarios/cria_usuarios.sh` | `root` | `root` | `755` | Admin (manual) | Root (execução) |
| `web_usuarios/*.php` | `www-data` | `www-data` | `644` | Admin (manual) | Apache (execução) |
| `web_usuarios/css/*.css` | `www-data` | `www-data` | `644` | Admin (manual) | Apache (leitura) |
| `/var/backups/samba/` | `www-data` | `www-data` | `775` | PHP (web) — arquivo staging | PHP (web) + Script (root) |

---

## 🛠️ Comandos de configuração

### 1. Criar diretórios (se não existirem)

```bash
sudo mkdir -p /var/www/html/usuarios
sudo mkdir -p /var/www/html/web_usuarios/css
sudo mkdir -p /var/www/html/web_usuarios/app
sudo mkdir -p /var/backups/samba
```

### 2. Copiar arquivos do repositório

```bash
# A partir da raiz do projeto
sudo cp -r usuarios/* /var/www/html/usuarios/
sudo cp -r web_usuarios/* /var/www/html/web_usuarios/
```

### 3. Aplicar permissões

```bash
# ==========================================
# Arquivos de dados
# ==========================================

# Pendentes (PHP escreve, Script lê e escreve)
sudo chown www-data:www-data /var/www/html/usuarios/usuarios_pendentes.txt
sudo chmod 664 /var/www/html/usuarios/usuarios_pendentes.txt

# Criados (Script escreve, PHP lê)
sudo chown www-data:www-data /var/www/html/usuarios/usuarios_criados.txt
sudo chmod 664 /var/www/html/usuarios/usuarios_criados.txt

# ==========================================
# Arquivos de configuração
# ==========================================

# Setores (leitura e escrita - gerenciado via interface web)
sudo chown root:www-data /var/www/html/usuarios/setores.conf
sudo chmod 664 /var/www/html/usuarios/setores.conf

# Credenciais do admin (mais restrito por segurança)
sudo chown root:www-data /var/www/html/usuarios/usuarios_sistema.txt
sudo chmod 640 /var/www/html/usuarios/usuarios_sistema.txt

# ==========================================
# Scripts bash (executáveis como root)
# ==========================================

sudo chown root:root /var/www/html/usuarios/cria_usuarios.sh
sudo chmod 755 /var/www/html/usuarios/cria_usuarios.sh

sudo chown root:root /var/www/html/usuarios/aplicar_compartilhamentos.sh
sudo chmod 755 /var/www/html/usuarios/aplicar_compartilhamentos.sh

# ==========================================
# Arquivos da interface web
# ==========================================

# PHP
sudo chown www-data:www-data /var/www/html/web_usuarios/*.php
sudo chmod 644 /var/www/html/web_usuarios/*.php

# CSS
sudo chown www-data:www-data /var/www/html/web_usuarios/css/*.css
sudo chmod 644 /var/www/html/web_usuarios/css/*.css

# ==========================================
# Log
# ==========================================

# ==========================================
# Diretório de staging / backup (compartilhamentos Samba)
# ==========================================

# O PHP (www-data) precisa escrever o arquivo de staging aqui
sudo mkdir -p /var/backups/samba
sudo chown www-data:www-data /var/backups/samba
sudo chmod 775 /var/backups/samba

# ==========================================
# Log
# ==========================================

sudo touch /var/log/cria_usuarios.log
sudo chmod 644 /var/log/cria_usuarios.log
```

---

## ⚡ Script Bash (`cria_usuarios.sh`)

### Execução manual

```bash
sudo /var/www/html/usuarios/cria_usuarios.sh
```

### Agendamento automático (cron)

Para executar automaticamente a cada hora:

```bash
sudo crontab -e
```

Adicione a linha:
```cron
0 * * * * /var/www/html/usuarios/cria_usuarios.sh >> /var/log/cria_usuarios.log 2>&1
```

**Explicação do cron:**
| Campo | Valor | Significado |
|-------|-------|-------------|
| Minuto | `0` | No minuto 0 |
| Hora | `*` | Toda hora |
| Dia do mês | `*` | Todo dia |
| Mês | `*` | Todo mês |
| Dia da semana | `*` | Todos os dias |

### Logs

```bash
# Ver logs em tempo real
sudo tail -f /var/log/cria_usuarios.log

# Ver últimas 50 linhas
sudo tail -50 /var/log/cria_usuarios.log

# Buscar erros no log
grep -i "erro\|error\|fail" /var/log/cria_usuarios.log
```

---

## 🧪 Verificação de permissões

Após configurar, verifique se está tudo correto:

```bash
# Verificar proprietários e permissões
ls -la /var/www/html/usuarios/
find /var/www/html/web_usuarios -name "*.php" -exec ls -la {} +

# Verificar se o www-data consegue ler os arquivos
sudo -u www-data cat /var/www/html/usuarios/usuarios_pendentes.txt
sudo -u www-data cat /var/www/html/usuarios/setores.conf

# Verificar se o script é executável
ls -la /var/www/html/usuarios/cria_usuarios.sh

# Verificar permissão do diretório de staging
ls -la /var/backups/samba/
sudo -u www-data touch /var/backups/samba/.test_write && echo "✅ www-data pode escrever" && rm /var/backups/samba/.test_write

# Verificar sudo para o script de compartilhamentos
SAIDA_SUDO=$(sudo -u www-data sudo -n /var/www/html/usuarios/aplicar_compartilhamentos.sh 2>&1)
if echo "$SAIDA_SUDO" | grep -qi "password\|terminal"; then
  echo "❌ Sudo com problemas — veja seção de configuração de sudo abaixo"
  echo "   Saída: $SAIDA_SUDO"
else
  echo "✅ Sudo funcionando (staging pode estar ausente — comportamento normal)"
fi
```

---

## ⚠️ Problemas comuns de permissão

### ❌ "Erro ao salvar usuário" no painel web

**Causa:** `www-data` não tem permissão de escrita em `usuarios_pendentes.txt`

**Solução:**
```bash
sudo chown www-data:www-data /var/www/html/usuarios/usuarios_pendentes.txt
sudo chmod 664 /var/www/html/usuarios/usuarios_pendentes.txt
```

### ❌ Script não funciona: "Permission denied"

**Causa:** Script não é executável ou não está rodando como root

**Solução:**
```bash
sudo chmod 755 /var/www/html/usuarios/cria_usuarios.sh
sudo /var/www/html/usuarios/cria_usuarios.sh  # sempre com sudo!
```

### ❌ "Arquivo de pendentes não encontrado" no script

**Causa:** O script espera que os arquivos estejam em `/var/www/html/usuarios/`

**Solução:** Certifique-se de que copiou os arquivos para o local correto:
```bash
ls -la /var/www/html/usuarios/usuarios_pendentes.txt
```

### ❌ "Erro ao escrever arquivo de staging. Verifique permissões."

**Causa:** O diretório `/var/backups/samba/` não existe ou o `www-data` não tem permissão de escrita.

**Solução:**
```bash
sudo mkdir -p /var/backups/samba
sudo chown www-data:www-data /var/backups/samba
sudo chmod 775 /var/backups/samba
```

### ❌ "sudo: a terminal is required to read the password" no gerenciador de compartilhamentos

**Causa:** O `www-data` não tem permissão de sudo sem senha para executar o script `aplicar_compartilhamentos.sh`.

**Solução:** Configure o sudo (veja seção abaixo).

---

## 🔒 Boas práticas de segurança

1. **Sempre use HTTPS** em produção
2. **Mantenha `usuarios_sistema.txt` com permissão 640** para evitar leitura por outros usuários
3. **Use hash bcrypt** para a senha do admin (veja README.md principal)
4. **Nunca deixe `usuarios_criados.txt`** com permissão de leitura global (contém senhas)
5. **Monitore os logs** regularmente
6. **Revise o agendamento cron** para não sobrecarregar o servidor

---

## 🤝 Configuração de Sudo para o Gerenciador de Compartilhamentos

O gerenciador de compartilhamentos Samba precisa editar `/etc/samba/smb.conf` e reiniciar o serviço, o que requer privilégios root.

### 1. Criar o arquivo de regras no sudoers.d (recomendado)

Em vez de editar o `/etc/sudoers` diretamente, crie um arquivo separado em `/etc/sudoers.d/`:

```bash
sudo visudo -f /etc/sudoers.d/samba-gerenciamento
```

Adicione o conteúdo:
```
# Permitir que o Apache (www-data) execute o script de compartilhamentos Samba sem senha
www-data ALL=(root) NOPASSWD: /var/www/html/usuarios/aplicar_compartilhamentos.sh
```

> ⚠️ **Importante:** O arquivo em `/etc/sudoers.d/` deve ter permissão `440` e pertencer a `root:root`. O `visudo -f` já cuida disso.

### 2. Ou editar o /etc/sudoers diretamente

```bash
sudo visudo
```

Adicione no final:
```
www-data ALL=(root) NOPASSWD: /var/www/html/usuarios/aplicar_compartilhamentos.sh
```

### 3. Verificar configuração

Teste se o sudo está funcionando corretamente:

```bash
# Teste 1: Verificar se a regra existe
sudo -l -U www-data | grep aplicar_compartilhamentos

# Teste 2: Tentar executar o script como www-data (deve rodar sem pedir senha)
sudo -u www-data sudo -n /var/www/html/usuarios/aplicar_compartilhamentos.sh
```

**Esperado:** O script deve executar sem pedir senha. Se o staging não existir, ele mostrará "ERRO: Arquivo de staging não encontrado" — isso significa que o sudo está OK.

### 4. Diagnóstico de problemas

Se o teste acima pedir senha ou mostrar "a terminal is required":

```bash
# Verificar se o arquivo sudoers existe e tem permissão correta
ls -la /etc/sudoers.d/samba-gerenciamento
# Deve mostrar: -r--r----- root root

# Verificar sintaxe do arquivo
sudo visudo -c -f /etc/sudoers.d/samba-gerenciamento

# Verificar se o caminho do script está correto
ls -la /var/www/html/usuarios/aplicar_compartilhamentos.sh

# Forçar recriação do arquivo (se necessário)
sudo rm -f /etc/sudoers.d/samba-gerenciamento
sudo visudo -f /etc/sudoers.d/samba-gerenciamento
```

---

## 📝 Notas sobre o Samba

### Scripts do sistema

| Script | Função | Permissão |
|--------|--------|-----------|
| `cria_usuarios.sh` | Cria usuários Linux/Samba | Executado como root (cron ou manual) |
| `aplicar_compartilhamentos.sh` | Aplica config de compartilhamentos Samba | Executado via sudo pelo PHP |

### Comandos Samba utilizados

| Comando | Função |
|---------|--------|
| `smbpasswd -s -a usuario` | Adiciona/atualiza usuário no Samba |
| `smbpasswd -e usuario` | Habilita usuário no Samba |
| `systemctl restart smbd` | Reinicia o serviço Samba |
| `testparm` | Valida a configuração do smb.conf |

Certifique-se de que o Samba está instalado e configurado:

```bash
# Verificar instalação
which smbpasswd

# Verificar status do serviço
sudo systemctl status smbd
```

---

*Documentação atualizada em Julho de 2026.*
