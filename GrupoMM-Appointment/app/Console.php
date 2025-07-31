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
 * O aplicativo Slim que gerencia o sistema em um ambiente de linha de
 * comando, permitindo a execução de comandos no console para executar
 * tarefas que independam da interface Web.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App;

class Console
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

    $this->configureContainers();
    $this->registerHandlers();
    $this->loadMiddlewares();
  }

  /**
   * Configura os containers da aplicação.
   * 
   * @return void
   */
  protected function configureContainers()
  {
    $container = $this->getContainer();

    require $this->getConfigurationDir() . '/containers/database.php';
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
      'settings' => require $this->getConfigurationDir() . '/console.php',
      'commands' => require $this->getConfigurationDir() . '/commands.php'
    ];

    // Carrega as configurações de conexão com o banco de dados
    $database = require $this->getConfigurationDir() . '/database.php';
    $configuration['settings'] += $database;
    
    // Carrega as configurações de registro de eventos (logs)
    $logger = require $this->getConfigurationDir() . '/logger.php';
    
    // Se estiver em modo de desenvolvimento, modificamos o nível dos
    // registros
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
    
    // Carrega as configurações de envio de e-mail
    $mailer = require $this->getConfigurationDir() . '/mailer.php';
    $configuration['settings'] += $mailer;

    // Carrega as configurações de contratantes
    $contractor = require $this->getConfigurationDir() . '/contractor.php';
    $configuration['settings'] += $contractor;

    return $configuration;
  }
}
