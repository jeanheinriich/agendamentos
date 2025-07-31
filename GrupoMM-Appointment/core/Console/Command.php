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
 * Classe responsável pela criação de um controlador de tarefa para
 * execução em linha de comando.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

declare(strict_types = 1);

namespace Core\Console;

use Core\Console\Console;
use Core\Logger\LoggerTrait;
use Core\Traits\ContainerTrait;
use Core\Traits\ExceptionTrait;
use Psr\Container\ContainerInterface;

abstract class Command
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
   * O nome do aplicativo de console
   * 
   * @var Core\Application
   */
  public $application;

  /**
   * O controlador do dispositivo de saída do conteúdo.
   *
   * @var Core\Console\Console
   */
  protected $console;

  /**
   * O construtor do nosso comando.
   * 
   * @param ContainerInterface $container
   *   A estrutura que contém os containers da aplicação
   * @param Core\Application $app
   *   O acesso à aplicação
   */
  public function __construct(ContainerInterface $container)
  {
    $this->container = $container;
    unset($container);

    global $argv;

    // Determina o nome do aplicativo sendo executado
    $partsOfName = array_filter( explode('/',  $argv[0]) );
    $this->application = array_pop($partsOfName);

    if (ob_get_level()) {
      // Retira o buffer de escrita
      ob_end_flush();
      ob_implicit_flush();
    }

    $this->console = new Console();
  }

  /**
   * Executa o comando atual.
   *
   * @param string[] $args
   *   Os argumentos da linha de comando
   */
  abstract protected function command(array $args);
}