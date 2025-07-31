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
 * Um manipulador de saída para o console.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Console;

use Core\Debugger\DebuggerInterface;
use Core\Debugger\DebugLevel;
use Core\Helpers\InterpolateTrait;
use Core\Console\Formatters\{Formatter, Ansi};
use Core\Console\Outputters\{Outputter, StandardOutput};
use Exception;
use Psr\Log\LoggerLevel;
use Psr\Log\InvalidArgumentException;

class Console
  implements DebuggerInterface, Outputter
{
  /**
   * O método para interpolar as variáveis num texto
   */
  use InterpolateTrait;

  // Os títulos para as mensagens de depuração
  const LEVEL_NAMES = [
    DebugLevel::EMERGENCY => 'Emergência',
    DebugLevel::ALERT => 'Alerta',
    DebugLevel::CRITICAL => 'Ocorrência Crítica',
    DebugLevel::ERROR => 'Erro',
    DebugLevel::WARNING => 'Aviso',
    DebugLevel::NOTICE => 'Notícia',
    DebugLevel::INFO => 'Informação',
    DebugLevel::DEBUG => 'DEBUG'
  ];

  // Os temas para as mensagens de depuração
  const LEVEL_STYLE = [
    DebugLevel::EMERGENCY => [
      'border' => '<lightWhite><bold><bgLightRed>',
      'title' => '<lightYellow><bold><bgLightRed>',
      'content' => '<white><bgLightRed>'
    ],
    DebugLevel::ALERT => [
      'border' => '<lightWhite><bold><bgYellow>',
      'title' => '<lightWhite><bold><bgYellow>',
      'content' => '<black><bgYellow>'
    ],
    DebugLevel::CRITICAL => [
      'border' => '<lightWhite><bold><bgRed>',
      'title' => '<lightYellow><bold><bgRed>',
      'content' => '<white><bgRed>'
    ],
    DebugLevel::ERROR => [
      'border' => '<white><bold>',
      'title' => '<lightRed><bold>',
      'content' => '<lightRed>'
    ],
    DebugLevel::WARNING => [
      'border' => '<white><bold>',
      'title' => '<lightYellow><bold>',
      'content' => '<yellow>'
    ],
    DebugLevel::NOTICE => [
      'border' => '<darkGray><bold>',
      'title' => '<lightBlue><bold>',
      'content' => '<lightBlue>'
    ],
    DebugLevel::INFO => [
      'border' => '<darkGray>',
      'title' => '<lightCyan><bold>',
      'content' => '<cyan>'
    ],
    DebugLevel::DEBUG => [
      'border' => '<darkGray>',
      'title' => '<lightBlue><bold>',
      'content' => '<white>'
    ]
  ];

  /**
   * O controlador do dispositivo de saída do conteúdo.
   *
   * @var Core\Console\Outputters\Outputter
   */
  protected $outputter;

  /**
   * O construtor de nosso console
   */
  public function __construct()
  {
    $this->outputter = new StandardOutput(new Ansi());
  }


  // ========================================[ Métodos auxiliares ]=====

  /**
   * Obtém a largura da tela.
   */
  protected function getScreenWith(): int
  {
    return exec('tput cols');
  }

  /**
   * Faz a quebra de textos na largura informada.
   *
   * @param string $string
   *   O texto a ser formatado
   * @param int $width
   *   A largura da tela
   *
   * @return array
   *   O texto separado em linhas de acordo com o tamanho esperado
   */
  function mb_wordwrap(string $string, int $width): array
  {
    $len = mb_strlen($string, 'UTF-8');
    
    if ($len <= $width) {
      return array($string);
    }

    $return = [];
    $last_space = FALSE;
    $i = 0;

    do {
      if (mb_substr($string, $i, 1, 'UTF-8') == ' ') {
        $last_space = $i;
      }

      if ($i > $width) {
        $last_space = ($last_space == 0)
          ? $width
          : $last_space
        ;

        $return[] = trim(mb_substr($string, 0, $last_space, 'UTF-8'));
        $string = mb_substr($string, $last_space, $len, 'UTF-8');
        $len = mb_strlen($string, 'UTF-8');
        $i = 0;
      }

      $i++;
    } while ($i < $len);

    $return[] = trim($string);

    return $return;
  }

  /**
   * Imprime uma mensagem na forma de uma caixa.
   *
   * @param int $level
   *   O nível da mensagem
   * @param string $message
   *   A mensagem a ser exibida
   *
   * @return void
   */
  protected function messageBox(int $level, string $message): void
  {
    // Determinamos a largura da tela
    $screenWidth = $this->getScreenWith();

    // Imprimimos o título
    $title = self::LEVEL_NAMES[$level];
    $this->out(''
      . self::LEVEL_STYLE[$level]['border']
      . '+' . str_repeat('-', 4) . '[ '
      . self::LEVEL_STYLE[$level]['title']
      . $title . self::LEVEL_STYLE[$level]['border'] . ' ]'
      . str_repeat('-', ($screenWidth - (10 + mb_strlen($title))))
      . '+'
    );

    // Quebra os textos na largura desejada
    $lines = $this->mb_wordwrap($message, $screenWidth - 4);

    // Imprime cada linha, tomando o cuidado de acrescentar as bordas e
    // os espaços necessários
    foreach ($lines as $line) {
      $this->out(''
        . self::LEVEL_STYLE[$level]['border']
        . '|' . self::LEVEL_STYLE[$level]['content'] . ' ' . $line
        . str_repeat(' ', ($screenWidth - (3 + mb_strlen($line))))
        . self::LEVEL_STYLE[$level]['border']
        . '|'
      );
    }

    // Imprime o fechamento da caixa
    $this->out(''
      . self::LEVEL_STYLE[$level]['border']
      . '+' . str_repeat('-', ($screenWidth - 2))
      . '+'
    );
  }

  /**
   * Imprime uma mensagem na forma de uma linha.
   *
   * @param int $level
   *   O nível da mensagem
   * @param string $message
   *   A mensagem a ser exibida
   *
   * @return void
   */
  protected function messageLine(int $level, string $message): void
  {
    // Imprimimos o título
    $this->inline(''
      . self::LEVEL_STYLE[$level]['border'] . '[ '
      . self::LEVEL_STYLE[$level]['title']
      . self::LEVEL_NAMES[$level]
      . self::LEVEL_STYLE[$level]['border'] . ' ] '
    );

    // Imprime a mensagem
    $this->out(''
      . self::LEVEL_STYLE[$level]['content'] . $message
    );
  }

  // =======================[ Implementações da DebuggerInterface ]=====

  /**
   * Registra um evento quando o sistema está inutilizável. Nesta caso a
   * mensagem de depuração é gerada e o aplicativo é encerrado.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function emergency(string $message, array $context = []): void
  {
    $this->event(DebugLevel::EMERGENCY, $message, $context);
  }

  /**
   * Registra um evento quando uma ação deve ser tomada imediatamente,
   * como, por exemplo: O site inteiro está inativo, ou o banco de dados
   * não está disponível, etc. Isso deve acionar os alertas do SMS e
   * acordá-lo.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function alert(string $message, array $context = []): void
  {
    $this->event(DebugLevel::ALERT, $message, $context);
  }

  /**
   * Registra um evento quando ocorre uma condição crítica.
   * Exemplo: um componente do aplicativo está indisponível, ou ocorreu
   * uma exceção inesperada.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function critical(string $message, array $context = []): void
  {
    $this->event(DebugLevel::CRITICAL, $message, $context);
  }

  /**
   * Registra eventos de erros em tempo de execução, e que não requerem
   * ação imediata, mas geralmente devem ser registrados e monitorados.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function error(string $message, array $context = []): void
  {
    $this->event(DebugLevel::ERROR, $message, $context);
  }

  /**
   * Registra ocorrências excepcionais que não são erros. Exemplo: uso
   * de APIs obsoletas, mau uso de uma API, coisas indesejáveis que não
   * estão necessariamente erradas.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function warning(string $message, array $context = []): void
  {
    $this->event(DebugLevel::WARNING, $message, $context);
  }

  /**
   * Registra eventos normais, mas significativos.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function notice(string $message, array $context = []): void
  {
    $this->event(DebugLevel::NOTICE, $message, $context);
  }

  /**
   * Registra eventos interessantes, como por exemplo: usuário
   * autentica-se, etc.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function info(string $message, array $context = []): void
  {
    $this->event(DebugLevel::INFO, $message, $context);
  }
  
  /**
   * Regista informações detalhadas sobre depuração.
   *
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   */
  public function debug(string $message, array $context = []): void
  {
    $this->event(DebugLevel::DEBUG, $message, $context);
  }

  /**
   * Registra eventos de depuração com um nível arbitrário.
   *
   * @param int $level
   *   O nível do evento
   * @param string $message
   *   A mensagem a ser gravada
   * @param array $context
   *   Os dados de contexto
   * 
   * @return void
   *
   * @throws \Psr\Log\InvalidArgumentException
   */
  protected function event(int $level, string $message,
    array $context = []): void
  {
    // Interpola os valores de contexto na mensagem
    $message = $this->interpolate($message, $context);

    switch ($level) {
      case DebugLevel::DEBUG:
        $this->messageLine($level, $message);

        break;
      
      default:
        $this->messageBox($level, $message);

        break;
    }
  }

  // ===============================[ Implementações da Outputter ]=====

  /**
   * Despeja os valores passados no dispositivo de saída, e ao final
   * acrescenta um caractere de nova linha.
   *
   * @param string $values
   *   O valor ou valores a serem impressos
   */
  public function out(string ...$values): void
  {
    $this->outputter->out(...$values);
  }

  /**
   * Despeja os valores passados no dispositivo de saída.
   *
   * @param string $values
   *   O valor ou valores a serem impressos
   */
  public function inline(string ...$values): void
  {
    $this->outputter->inline(...$values);
  }

  /**
   * Obtém o caractere de nova linha.
   *
   * @return string
   */
  public function newLine(): string
  {
    return PHP_EOL;
  }
}
