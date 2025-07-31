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
 * O aplicativo Slim que gerencia o sistema em um ambiente servidor Web.
 * 
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App;

use Core\Helpers\GarbageCollector;

class Application
  extends Base
{
  // O construtor
  public function __construct()
  {
    // Carrega as configurações do ambiente em que estamos (Produção ou
    // Desenvolvimento)
    $this->loadEnvironment();

    $configurations = $this->loadConfigurations();

    // Instancia o aplicativo conforme o ambiente
    $this->rootDir = $this->getRootDir();
    parent::__construct($this->loadConfigurations());

    $this->cleanOldSessionFiles();

    $this->configureContainers();
    $this->registerHandlers();
    $this->loadMiddlewares();
    $this->registerControllers();
    $this->loadRoutes();
  }

  /**
   * Recupera o nome de domínio da aplicação
   * @return [type] [description]
   */
  public function getDomainName()
  {
    return $this->environment['domainName'];
  }

  /**
   * Configura os containers da aplicação.
   * 
   * @return void
   */
  protected function configureContainers()
  {
    $container = $this->getContainer();

    require $this->getConfigurationDir() . '/containers/session.php';
    require $this->getConfigurationDir() . '/containers/flash.php';
    require $this->getConfigurationDir() . '/containers/authorization.php';
    require $this->getConfigurationDir() . '/containers/breadcrumb.php';
    require $this->getConfigurationDir() . '/containers/database.php';
    require $this->getConfigurationDir() . '/containers/csrf.php';
    require $this->getConfigurationDir() . '/containers/validator.php';
    require $this->getConfigurationDir() . '/containers/renderer.php';
    require $this->getConfigurationDir() . '/containers/mailer.php';
    require $this->getConfigurationDir() . '/containers/logger.php';
  }

  /**
   * Carrega as configurações do sistema.
   * 
   * @return array  As configurações do sistema
   */
  protected function loadConfigurations()
  {
    $app = $this;

    // Carrega as configurações globais do aplicativo Slim
    $configuration = [
      'settings' => require $this->getConfigurationDir() . '/slim.php'
    ];
    
    // Carrega as configurações de cache para páginas estáticas
    $cache = require $this->getConfigurationDir() . '/cache.php';
    $configuration['settings'] += $cache;

    // Carrega as configurações de conexão com o banco de dados
    $database = require $this->getConfigurationDir() . '/database.php';
    $configuration['settings'] += $database;
    
    // Carrega as configurações de registro de eventos (logs)
    $logger = require $this->getConfigurationDir() . '/logger.php';
    if ($this->environment['developmentMode']) {
      // Altera as configurações para o modo de desenvolvimento dos
      // registros de logs
      $logger['logger']['level'] = \Monolog\Logger::DEBUG;
    }
    $configuration['settings'] += $logger;

    // Carrega as configurações de armazenamento de arquivos
    $storage = require $this->getConfigurationDir() . '/storage.php';
    $configuration['settings'] += $storage;

    // Carrega as configurações de integração
    $integration = require $this->getConfigurationDir() . '/integration.php';
    $configuration['settings'] += $integration;

    // Carrega as configurações de renderização
    $renderer = require $this->getConfigurationDir() . '/renderer.php';
    $configuration['settings'] += $renderer;

    // Carrega as configurações de sessão
    $session = require $this->getConfigurationDir() . '/session.php';
    $configuration['settings'] += $session;

    // Carrega as configurações de cookies
    $cookie = require $this->getConfigurationDir() . '/cookie.php';
    $configuration['settings'] += $cookie;

    // Carrega as configurações de criptografia
    $encryption = require $this->getConfigurationDir() . '/encryption.php';
    $configuration['settings'] += $encryption;
    
    // Carrega as configurações de aplicações
    $applications = require $this->getConfigurationDir() . '/applications.php';
    $configuration['settings'] += $applications;
    
    // Carrega as configurações de agenda de contatos
    $addresses = require $this->getConfigurationDir() . '/addresses.php';
    $configuration['settings'] += $addresses;
    
    // Carrega as configurações de envio de e-mail
    $mailer = require $this->getConfigurationDir() . '/mailer.php';
    $configuration['settings'] += $mailer;

    // Carrega as configurações de contratantes
    $contractor = require $this->getConfigurationDir() . '/contractor.php';
    $configuration['settings'] += $contractor;

    return $configuration;
  }

  /**
   * Limpa arquivos de sessão obsoletos.
   * 
   * @return void
   */
  protected function cleanOldSessionFiles():void
  {
    $app = $this;
    $container = $this->getContainer();

    // Realiza a limpeza de arquivos desnecessários
    $settings = $container->get('settings')['session'];
    if (!is_null($settings['save_path'])) {
      GarbageCollector::dropOldFiles($settings['save_path'], 10);
    }
  }

  /**
   * Carrega as rotas.
   * 
   * @return void
   */
  protected function loadRoutes():void
  {
    $app = $this;
    $container = $this->getContainer();

    require $this->getConfigurationDir() . '/routes.php';
  }

  /**
   * Registra os controladores.
   * 
   * @return void
   */
  protected function registerControllers()
  {
    $app = $this;
    $container = $this->getContainer();

    if (file_exists($this->getConfigurationDir() . '/controllers.php')) {
      $controllers = require $this->getConfigurationDir() . '/controllers.php';

      foreach ($controllers as $key => $class) {
        $container[$key] = function ($container) use ($class, $app) {
          return new $class($container, $app);
        };
      }
    }
  }
}
