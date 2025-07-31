<?php
/*
 * This file is part of Extension Library.
 *
 * (c) Emerson Cavalcanti
 *
 * LICENSE: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the 
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject
 * to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * A base de um aplicativo Slim que gerencia o sistema.
 * 
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App;

use Slim\App;
use Exception;

class Base
  extends App
{
  /**
   * As configurações do ambiente (Produção ou Desenvolvimento).
   * 
   * @var array
   */
  protected $environment;

  /**
   * O diretório raiz.
   * 
   * @var string
   */
  protected $rootDir;

  /**
   * O diretório público.
   * 
   * @var string
   */
  protected $publicDir;

  /**
   * Carrega as informações de ambiente em que nos encontramos.
   * 
   * @return void
   */
  protected function loadEnvironment()
  {
    $filename = 'config/application.ini';
    $config = @parse_ini_file($filename, true, INI_SCANNER_TYPED);
    
    if ($config == false) {
      throw new Exception('Arquivo de configurações do ambiente ' .
        $filename . ' ausente.'
      );
    }

    if (array_key_exists('Application', $config)) {
      // Carrega as configurações do aplicativo do arquivo INI
      $this->environment = $config[ 'Application' ];
    } else {
      // Define as configurações padrões
      $this->environment = [
        'developmentMode' => true,
        'domainName' => 'erp.test'
      ];
    }
  }

  /**
   * Retorna qual o modo de operação (Linha de comando ou Web).
   * 
   * @return string
   *   O modo de operação
   */
  public function getSAPI()
  {
    switch (PHP_SAPI) {
      case 'cli':
        // Estamos no modo console (linha de comando)
        $sapi = 'cmd';

        break;
      default:
        $sapi = 'app';

        break;
    }

    return $sapi;
  }

  /**
   * Determina se o modo de execução do aplicativo é em console.
   *
   * @return bool
   */
  public function isConsole():bool
  {
    return php_sapi_name() == 'cli';
  }

  /**
   * Determina se o ambiente de execução do aplicativo é o de
   * desenvolvimento.
   *
   * @return bool
   */
  public function isDevelopment():bool
  {
    return ($this->getEnvironmentName() === 'dev');
  }

  /**
   * Retorna o nome do ambiente em que nos encontramos (Desenvolvimento
   * ou Produção).
   * 
   * @return string
   *   O nome do ambiente
   */
  public function getEnvironmentName()
  {
    if ($this->environment['developmentMode']) {
      return 'dev';
    }

    return 'prod';
  }

  /**
   * Recupera o diretório raiz.
   * 
   * @return string
   *   O diretório raiz
   */
  public function getRootDir()
  {
    // Se o diretório raiz estiver vazio, atribuí o diretório atual
    if (null === $this->rootDir) {
      $this->rootDir = dirname(__DIR__);
    }

    return $this->rootDir;
  }

  /**
   * Define o caminho para o diretório público (local onde está
   * hospedada a página inicial ou script).
   *
   * @return void
   */
  public function setPublicDir($path)
  {
    $path = basename($path);

    if (!empty($path)) {
      $this->publicDir = $path;
    }
  }

  /**
   * Recupera o caminho para o diretório público.
   * 
   * @return string
   *   O caminho para a pasta pública
   */
  public function getPublicDir()
  {
    return $this->getRootDir()
      . '/' . $this->publicDir
    ;
  }

  /**
   * Recupera o caminho para armazenamento das informações de cache.
   * 
   * @return string
   *   O nome do caminho
   */
  public function getCacheDir()
  {
    return $this->getRootDir()
      . '/var/cache/'
    ;
  }

  /**
   * Recupera as configurações do diretório de configurações.
   * 
   * @return string
   *   O diretório onde são armazenados os arquivos de configuração
   */
  public function getConfigurationDir()
  {
    return $this->getRootDir()
      . '/app/config'
    ;
  }

  /**
   * Recupera as configurações do diretório de cache de rotas.
   * 
   * @return string
   *   O diretório para armazenamento de cache de rotas
   */
  public function getRouteCacheDir()
  {
    return $this->getRootDir()
      . '/var/router/cache.'
      . $this->getEnvironmentName()
      . '.php'
    ;
  }

  /**
   * Recupera as configurações do diretório de Logs.
   * 
   * @return string
   *   O diretório para armazenamento de logs
   */
  public function getLogDir()
  {
    return $this->getRootDir()
      . '/var/log'
    ;
  }

  /**
   * Recupera as configurações do diretório de armazenamento de dados de
   * sessão.
   * 
   * @return string
   *   O diretório para armazenamento de dados de sessão
   */
  public function getSessionDir()
  {
    return $this->getRootDir()
      . '/var/session'
    ;
  }

  /**
   * Recupera as configurações do diretório de armazenamento.
   * 
   * @return string
   *   O diretório para armazenamento de arquivos
   */
  public function getStorageDir()
  {
    return $this->getRootDir()
      . '/var/storage'
    ;
  }

  /**
   * Carrega os Middlewares da aplicação.
   * 
   * @return void
   */
  protected function loadMiddlewares()
  {
    $app = $this;
    $container = $this->getContainer();

    require $this->getConfigurationDir()
      . '/middlewares.php'
    ;
  }

  /**
   * Registra os manipuladores do sistema.
   * 
   * @return void
   */
  protected function registerHandlers()
  {
    $container = $this->getContainer();
    $app = $this;

    require $this->getConfigurationDir()
      . '/handlers.php'
    ;
  }
}