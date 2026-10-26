<?php

use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\ObjectUploader;
use Aws\S3\S3Client;

require_once "./vendor/autoload.php";
include_once "./IP_Keys.php";

class SSH_DATAGRO
{
    private $server;
    private $ip;
    private $keyFile;
    private $S3;
    private $S3_KEY;
    private $S3_SECRET;
    private $SSH;
    private $SFTP;
    private $log = "";
    public function __construct($ip, $keyFile, $server, $S3_KEY, $S3_SECRET)
    {
        // Sets
        $this->__set("ip", $ip);
        $this->__set("keyFile", PublicKeyLoader::load(file_get_contents("./chaves/$keyFile")));
        $this->__set("server", $server);
        $this->__set("S3_KEY", $S3_KEY);
        $this->__set("S3_SECRET", $S3_SECRET);
        $this->__set("S3", new S3Client(['version' => 'latest', 'region' => 'sa-east-1', 'credentials' => ['key' => $this->S3_KEY, 'secret' => $this->S3_SECRET,],]));

        // Exec
        $this->Login();
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }

    public function __get($name)
    {
        return $this->$name;
    }

    private function Login()
    {
        $this->SFTP = new SFTP($this->ip);
        $this->SSH = new SSH2($this->ip);

        if (!$this->SFTP->login("ubuntu", $this->keyFile) || !$this->SSH->login("ubuntu", $this->keyFile)) {
            die(date("(d-m-Y H:i:s): ") . "Erro no login");
        } else {
            echo date("(d-m-Y H:i:s): ") . "Login feito\n";
            $this->log .= date("(d-m-Y H:i:s): ") . "Login feito\n";
            $this->SSH->setTimeout(0);
        }
    }

    // Origem = caminho remoto
    // Destino = caminho local
    public function backup($wp)
    {
        $arq = "arquivos/";
        if (!file_exists($arq))
            mkdir($arq);

        $arq .= strtolower($this->server) . "_backups/";
        if (!file_exists($arq))
            mkdir($arq);

        $this->server = strtolower($this->server);

        echo date("(d-m-Y H:i:s): ") . "Execuntado sudo tar -czf /tmp/{$this->server}.tar.gz $wp\n";
        $this->log .= date("(d-m-Y H:i:s): ") . "Execuntado sudo tar -czf /tmp/{$this->server}.tar.gz $wp\n";
        $resultado = $this->SSH->exec(
            "sudo tar -czf /tmp/{$this->server}.tar.gz $wp 2>&1; echo TAR_EXIT_CODE:\$?"
        );

        preg_match('/TAR_EXIT_CODE:(\d+)/', $resultado, $matches);

        if (!isset($matches[1]) || $matches[1] !== '0') {
            die(date("(d-m-Y H:i:s): ") . "Erro ao executar os backups via SSH: " . $resultado);
        } else {
            echo date("(d-m-Y H:i:s): ") . "sudo tar -czf /tmp/{$this->server}.tar.gz $wp concluído\n";
            $this->log .= date("(d-m-Y H:i:s): ") . "sudo tar -czf /tmp/{$this->server}.tar.gz $wp concluído\n";
            echo date("(d-m-Y H:i:s): ") . "Fazendo download de /tmp/{$this->server}.tar.gz $wp\n";
            $this->log .= date("(d-m-Y H:i:s): ") . "Fazendo download de /tmp/{$this->server}.tar.gz $wp\n";
            $this->SFTP->get(
                "/tmp/{$this->server}.tar.gz",
                "$arq{$this->server}_" . date("y_m_d") . ".tar.gz"
            );
            echo date("(d-m-Y H:i:s): ") . "Download de /tmp/{$this->server}.tar.gz $wp concluído\n";
            $this->log .= date("(d-m-Y H:i:s): ") . "Download de /tmp/{$this->server}.tar.gz $wp concluído\n";
        }

        $arquivos = glob("arquivos/{$this->server}_backups/*");

        foreach ($arquivos as $local) {
            $key = strtolower("{$this->server}/" . str_replace("arquivos/{$this->server}_backups/", "", $local));
            $this->sobeS3($key, $local);
        }

        $this->geraLog($this->server);
    }
    private function sobeS3($remote, $local)
    {
        echo date("(d-m-Y H:i:s): ") . "Enviando $local para o S3\n";
        $arquivoLocal = fopen($local, 'rb');
        $this->log .= date("(d-m-Y H:i:s): ") . "Enviando $local para o S3\n";

        $uploader = new ObjectUploader($this->S3, $_ENV['BUCKET'], $remote, $arquivoLocal);
        do {
            try {
                $result = $uploader->upload();
            } catch (MultipartUploadException $e) {
                rewind($remote);
                $uploader = new MultipartUploader($this->S3, $remote, [
                    'state' => $e->getState(),
                ]);
            }
        } while (!isset($result));

        echo date("(d-m-Y H:i:s): ") . "Envio $local para o S3 concluído\n";
        $this->log .= date("(d-m-Y H:i:s): ") . "Envio $local para o S3 concluído\n";
    }
    private function geraLog($bkp)
    {
        $logPasta = "logs/";
        if (!file_exists($logPasta))
            mkdir($logPasta);

        $logPasta .= date("Y") . "/";
        if (!file_exists($logPasta))
            mkdir($logPasta);

        $logPasta .= date("m") . "/";
        if (!file_exists($logPasta))
            mkdir($logPasta);

        $logPasta .= date("d") . "/";
        if (!file_exists($logPasta))
            mkdir($logPasta);

        $logPasta .= "$bkp/";
        if (!file_exists($logPasta))
            mkdir($logPasta);

        $logPasta .= "$bkp.log";
        echo date("(d-m-Y H:i:s): ") . "Log salvo em $logPasta\n";

        file_put_contents($logPasta, $this->log, FILE_APPEND);
    }

}