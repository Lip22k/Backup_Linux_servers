# Backup de Servidores Linux

Rotina automatizada em PHP para backup de diretórios de servidores Linux remotos.
Usa **SSH** para compactar os arquivos, **SFTP** para baixá-los e envia o resultado para um bucket **S3**.

## Como funciona

1. Conecta ao servidor via SSH (phpseclib)
2. Gera o `.tar.gz` do diretório alvo em `/tmp` com `sudo tar`
3. Valida o código de saída do `tar`
4. Baixa o arquivo via SFTP para `arquivos/<servidor>_backups/`
5. Envia o backup para o bucket S3
6. Remove o arquivo temporário do servidor remoto
7. Registra o log com data e hora de cada etapa

## Requisitos

- PHP 8.0+
- Composer
- [phpseclib 3](https://phpseclib.com/)
- [AWS SDK for PHP](https://aws.amazon.com/sdk-for-php/)
- Usuário SSH com permissão de `sudo tar` no servidor remoto
- Espaço livre em `/tmp` no servidor e em disco no ambiente local

## Instalação

    git clone <url-do-repositorio>
    cd <repositorio>
    composer install

## Configuração

Configure as credenciais de acesso aos servidores (host, usuário, chave ou senha)
e as credenciais do S3 (bucket, região, chave de acesso).

> Nunca versione credenciais. Use variáveis de ambiente ou um arquivo `.env`
> listado no `.gitignore`.

## Uso

    php Backup.php <SERVIDOR> <NOME_SITE> <CAMINHO_REMOTO>

Exemplo:

    php Backup.php EXPERIENCE CONFERENCES /var/www/html/conferences

Os backups são salvos localmente em:

    arquivos/<servidor>_backups/<servidor>_<aa_mm_dd>.tar.gz

## Observações

- Backups grandes podem levar bastante tempo. Os timeouts de SSH e SFTP
  são desativados (`setTimeout(0)`) para não interromper o `tar` e o download.
- O arquivo temporário em `/tmp` do servidor é removido após o download.

## Licença

Defina a licença do projeto (ex.: MIT).
