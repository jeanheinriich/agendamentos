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
 * Classe responsável pela criação de um sistema de execução de tarefas
 * em modo console.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Middlewares;

use Core\Traits\ExceptionTrait;
use Exception;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionMethod;

class ConsoleMiddleware
  extends Middleware
{
  /**
   * Os métodos para manipulação de erros
   */
  use ExceptionTrait;

  // Os comandos mínimos disponíveis
  protected $commands = [
    '__default' => \App\Commands\HelpCommand::class,
    'Help'      => \App\Commands\HelpCommand::class
  ];

  /**
   * O constructor de nossa classe.
   * 
   * @param ContainerInterface $container
   *   Os containers de nossa aplicação
   * @param array $commands
   *   Uma matriz com os comandos disponíveis
   */
  public function __construct(
    ContainerInterface $container,
    array $commands = []
  )
  {
    parent::__construct($container);

    // Faz o merge das configurações passadas como argumento com as
    // configurações padrão
    if (is_array($commands)) {
      $this->commands = array_merge($this->commands, $commands);
    }
  }

  /**
   * A função executada sempre que o middleware for chamado e intercepta
   * a execução para analisar o comando requisitado.
   * 
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * @param ResponseInterface $response
   *   A resposta HTTP
   * @param callable $next
   *   O próximo middleware
   * 
   * @return ResponseInterface
   */
  public function __invoke(
    ServerRequestInterface $request,
    ResponseInterface $response,
    callable $next
  ): ResponseInterface
  {
    if (PHP_SAPI !== 'cli') {
      // Registra o evento de depuração
      $this->debug("Requisitada a linha de comando por HTTP");

      return $next($request, $response);
    }
    global $argv;

    $this->debug("Iniciando operação em modo console", []);
    
    // Verifica se foram fornecidos os argumentos na linha de comando
    if (count($argv) > 1) {
      // Temos, teoricamente, o comando e os argumentos, então separamos
      // estas informações
      $command = ucfirst(strtolower($argv[1]));
      $args = array_slice($argv, 2);
    } else {
      // Como não foi fornecido o comando, executamos o comando padrão
      $command = '__default';
      $args = [];
    }
    $this->debug("Requisitado o comando '{name}'",
      [ 'name' => $command ]
    );

    // Recupera o executável em linha de comando
    $binaryName = $argv[0];

    // Recuperamos os comandos possíveis
    $availableCommands = $this->commands;
    $commandResponse = "";

    try {
      // Verifica se o comando está definido
      if (array_key_exists($command, $availableCommands)) {
        // O nome da classe é o nome do comando
        $class = $availableCommands[$command];

        // Verifica se a classe para o comando existe
        if (!class_exists($class, true)) {
          $this->error("Comando {name} inválido ou inexistente",
            [ 'name' => $class ]
          );
          $this->runtimeException(
            sprintf("Comando '%s' inválido ou inexistente\n", $class)
          );
        }

        // Instanciamos a classe do comando
        $reflectCommand = new ReflectionClass($class);

        // Verifica se a classe é um comando válido
        if (!$reflectCommand->isSubclassOf('Core\Console\Command')) {
          $this->error("A classe de comando {name} não é parente da "
            . "classe de comandos deste framework",
            [ 'name' => $class ]
          );
          $this->runtimeException(
            sprintf("'%s' não é um comando válido\n", $class)
          );
        }

        // Executamos o comando propriamente dito
        if ($reflectCommand->getConstructor()) {
          $taskConstructMethod = new ReflectionMethod($class,  '__construct');
          $constructParams = $taskConstructMethod->getParameters();

          if (count($constructParams) == 0) {
            // Cria uma nova instância da classe sem argumentos
            $task = $reflectCommand->newInstanceArgs();
          } elseif (count($constructParams) == 1) {
            // Cria uma nova instância da classe e passa o container
            // por referência, se necessário
            if ($constructParams[0]->isPassedByReference()) {
              $task = $reflectCommand->newInstanceArgs([&$this->container]);
            } else {
              $task = $reflectCommand->newInstanceArgs([$this->container]);
            }
          } else {
            $this->error("A classe {name} possui um método __construct "
              . "não suportado",
              [ 'name' => $class ]
            );
            $this->runtimeException(
              sprintf("A classe %s possui um método __construct não "
                . "suportado\n", $class)
            );
          }
        } else {
          $task = $reflectCommand->newInstanceWithoutConstructor();
        }

        $commandResponse = $task->command($args);

        if (is_null($commandResponse)) {
          $commandResponse = "\nConcluído\n";
        }
      } else {
        // Não existe o comando, então retorna o erro
        echo "Comando '{$command}' não existe\n\n";
        
        return $response
          ->withStatus(500)
        ;
      }

      echo "{$commandResponse}\n";

      return $response
        ->withStatus(200)
      ;
    } catch(Exception $exception) {
      // Retorna uma mensagem com o erro ocorrido
      $this->error("Não foi possível executar o comando '{name}'. Erro "
        . "interno: {error}.",
        [ 'name' => $command,
          'error' => $exception->getMessage() ]
      );

      echo "Ops. Desculpe, mas ocorreu um erro interno.\n\n"
        . "A mensagem retornada é:\n" . $exception->getMessage()
        . "\n\n"
      ;
      
      return $response
        ->withStatus(500)
      ;
    }
  }
}
