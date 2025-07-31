<?php
/*
 * This file is part of Slim Framework 3 skeleton application.
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
 * Uma classe abstrata para servir como base para manipuladores de erros
 * do aplicativo.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti (at) gmail (dot) com>
 */

namespace Core\Handlers;

use Carbon\Carbon;
use Core\Handlers\Formatters\TextFormatter;
use Core\Logger\LoggerTrait;
use Core\Traits\ApplicationTrait;
use Core\Traits\ContainerTrait;
use Core\Traits\RouterTrait;
use Slim\Handlers\AbstractHandler as SlimAbstractHandler;
use Psr\Container\ContainerInterface;
use Throwable;

class AbstractHandler
  extends SlimAbstractHandler
{
  /**
   * Os métodos para manipulação do container
   */
  use ContainerTrait;

  /**
   * Os métodos para registro de eventos em Logs
   */
  use LoggerTrait;

  /**
   * Os métodos para manipulação dos aplicativos
   */
  use ApplicationTrait;

  /**
   * Os métodos para manipulação de templates para renderização dos
   * erros
   */
  use TemplateTrait;

  /**
   * Os métodos para manipulação de rotas
   */
  use RouterTrait;

  /**
   * A flag indicativa de que os erros devem ser exibidos com detalhes
   * 
   * @var bool
   */
  protected $displayErrorDetails;

  /**
   * Tipos de conteúdos tratados conhecidos. Esta versão é estendida em
   * relação a versão original
   *
   * @var array
   */
  protected $knownContentTypes = [
      'application/json',
      'application/xml',
      'text/event-stream',
      'text/xml',
      'text/html',
  ];
  
  /**
   * O construtor do nosso manipulador de erros.
   * 
   * @param bool $displayErrorDetails
   *   A flag indicativa de que os erros devem ser reportados com
   *   detalhamento na tela para o usuário
   * @param ContainerInterface $container
   *   O container da aplicação
   */
  public function __construct(bool $displayErrorDetails,
    ContainerInterface $container)
  { 
    $this->displayErrorDetails = $displayErrorDetails;
    $this->container = $container;
    unset($container);
  }

  /**
   * Uma função que recupera o nome da classe sem o espaço de nome.
   * 
   * @return string
   *   O nome da classe
   */
  protected function getNameOfClass(): string
  {
    $path = explode('\\', get_class($this));

    return array_pop($path);
  }

  /**
   * Escreve no log os erros ocorridos. No caso da propriedade
   * displayErrorDetails for 'false', então acrescenta o detalhamento
   * de erros no log.
   * 
   * @param Throwable $error
   *   A exceção/erro que contém a mensagem de erro a ser registrada
   * 
   * @return void
   */
  protected function writeToErrorLog(Throwable $error)
  {
    if ($this->displayErrorDetails) {
      // Não devemos colocar no LOG um detalhamento do erro
      return;
    }
    
    // Determina o nome do Handler
    $handler = $this->getNameOfClass();

    // Registra o evento
    if (PHP_SAPI == 'cli') {
      $partsOfRoute = [
        'CMD'
      ];
    } else {
      $partsOfRoute = array_filter(explode('/', ltrim($_SERVER['REQUEST_URI'], '/')));
    }

    // Renderiza a mensagem de erro
    $formatter = new TextFormatter(true);
    $now = Carbon::now();

    $output = sprintf("[%s][DETAIL][%s][%s] %s", $now->format("Y-m-d H:i:s"),
      strtoupper($partsOfRoute[0]), $handler,
      $formatter->renderErrorMessage($error, []))
    ;

    $this->logError($output);
  }

  /**
   * Determina o nome do arquivo de erro.
   * 
   * @return string
   *   O nome do arquivo
   */
  protected function getErrorFile(): string
  {
    // Recupera as configurações dos armazenamentos de logs
    $logger = $this->container['settings']['logger'];

    // Remove a última parte do caminho e acrescenta o nome do arquivo
    // de erros
    $path = $logger['path'];
    $now = Carbon::now();
    $path = pathinfo($path, PATHINFO_DIRNAME ) . "/"
      . $now->format('Ymd') . ".log";

    return $path;
  }
  
  /**
   * Envolve a função error_log para que isso possa ser facilmente
   * testado.
   * 
   * @param string $message
   *   A mensagem a ser gravada no log
   * 
   * @return void
   */
  protected function logError(string $message)
  {
    // Recupera o arquivo de armazenamento de erros
    $errorFile = $this->getErrorFile();

    error_log($message, 3, $errorFile);
  }
}
