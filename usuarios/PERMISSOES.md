# Instruções de Permissões e Configuração

## Permissões Recomendadas

### Arquivos de Dados
```bash
# Arquivos de dados - leitura/escrita para o usuário do servidor web
chown www-data:www-data /var/www/html/usuarios/usuarios_pendentes.txt
chown www-data:www-data /var/www/html/usuarios/usuarios_criados.txt
chmod 664 /var/www/html/usuarios/usuarios_pendentes.txt
chmod 664 /var/www/html/usuarios/usuarios_criados.txt

# Arquivo de setores - apenas leitura
chown root:www-data /var/www/html/usuarios/setores.conf
chmod 644 /var/www/html/usuarios/setores.conf
```

### Script Bash
```bash
# Script deve ser executável e rodar como root
chown root:root /var/www/html/usuarios/cria_usuarios.sh
chmod 755 /var/www/html/usuarios/cria_usuarios.sh
```

### Arquivos PHP
```bash
# Arquivos PHP - permissões padrão
chown www-data:www-data /var/www/html/web_usuarios/*.php
chmod 644 /var/www/html/web_usuarios/*.php
```

## Configuração do Cron (Opcional)

Para executar o script automaticamente a cada hora:

```bash
sudo crontab -e
```

Adicionar linha:
```
0 * * * * /var/www/html/usuarios/cria_usuarios.sh >> /var/log/cria_usuarios.log 2>&1
```

## Execução Manual

Para executar o script manualmente:
```bash
sudo /var/www/html/usuarios/cria_usuarios.sh
```

## Logs

Os logs são salvos em: `/var/log/cria_usuarios.log`

Certifique-se de que o diretório existe e tem permissões adequadas:
```bash
sudo touch /var/log/cria_usuarios.log
sudo chmod 644 /var/log/cria_usuarios.log
```

