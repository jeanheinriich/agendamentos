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
 * Um formatador de mensagens de erro no formato texto.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Handlers\Formatters;

use Core\Console\Formatters\{Formatter, Ansi, PlainText};
use Throwable;

class TextFormatter
  extends ErrorFormatter
{
  /**
   * O formatador de cores em modo console.
   * 
   * @var Core\Console\Formatters\Formatter
   */
  private $formatter;

  /**
   * A flag que habilita o uso de cores.
   * 
   * @var bool
   */
  private $enableColors = true;

  /**
   * O construtor de nosso formatador.
   * 
   * @param bool $displayErrorDetails
   *   A flag indicativa de que os erros devem ser exibidos com detalhes
   */
  public function __construct(bool $displayErrorDetails)
  {
    parent::__construct($displayErrorDetails);

    // Acrescenta formatador de cores
    $this->formatter = ($this->enableColors)
      ? new Ansi()
      : new PlainText()
    ;
  }

  /**
   * Renderiza um erro.
   * 
   * @param Throwable $error
   *   A exceção/erro que contém a mensagem
   * @param array $params
   *   Os parâmetros da requisição
   * 
   * @return string
   *   A mensagem de erro formatada
   */
  public function renderError(Throwable $error, array $params): string
  {
    $text = "<white><bold>"
      . $this->getErrorDescription($error) . "<reset>" . PHP_EOL
    ;

    // Determina se precisamos exibir o detalhamento do erro
    if ($this->displayErrorDetails) {
      $text .= PHP_EOL;
      $text .= "<lightWhite>Detalhamento<reset>" . PHP_EOL;
      $text .= "<black>" . str_repeat('-', 40) . "<reset>" . PHP_EOL;
      $text .= $this->renderTextError($error);
      
      while ($error = $error->getPrevious()) {
        $text = "<lightYellow>Exceção anterior<reset>" . PHP_EOL;
        $this->renderTextError($error);
      }
    } else {
      $text .= "<lightWhite>Desculpe-nos pelo inconveniente "
        . "temporário.<reset>" . PHP_EOL
      ;
    }
    
    return $this->formatter->format($text);
  }

  /**
   * Renderiza uma mensagem de erro.
   * 
   * @param string $error
   *   A mensagem de erro
   * @param array $params
   *   Os parâmetros da requisição
   * 
   * @return string
   *   A mensagem de erro formatada
   */
  public function renderErrorMessage(string $error,
    array $params): string
  {
    $text = "<white><bold>{$error}<reset>" . PHP_EOL;
    
    return $text;
  }

  /**
   * Renderiza uma exceção no formato texto.
   * 
   * @param Throwable $error
   *   A exceção que ocasionou o erro
   * 
   * @return string
   *   A exceção no formato texto
   */
  protected function renderTextError(Throwable $error): string
  {
    $text = "<lightBlue>    Tipo: <lightWhite>" . get_class($error)
      . "<reset>" . PHP_EOL
    ;
    
    if (($code = $error->getCode())) {
      $text .= "<lightBlue>  Código: <lightWhite>{$code}<reset>"
        . PHP_EOL
      ;
    }
    
    if (($message = $error->getMessage())) {
      $text .= "<lightBlue>Mensagem: <lightWhite>" .
        htmlentities($message) . "<reset>" . PHP_EOL
      ;
    }
    
    if (($file = $error->getFile())) {
      $text .= "<lightBlue> Arquivo: <lightWhite>{$file}<reset>"
        . PHP_EOL
      ;
    }
    
    if (($line = $error->getLine())) {
      $text .= "<lightBlue>   Linha: <lightWhite>{$line}<reset>"
        . PHP_EOL
      ;
    }

    $text .= "<black>" . str_repeat('-', 40) . "<reset>" . PHP_EOL;
    
    if (($trace = $error->getTraceAsString())) {
      $text .= PHP_EOL;
      $text .= "<lightYellow>Rastreamento:<reset>" . PHP_EOL;
      $text .= "<white>{$trace}<reset>" . PHP_EOL;
      $text .= "<black>" . str_repeat('-', 40) . "<reset>" . PHP_EOL;
      $text .= PHP_EOL;
    }
    
    return $text;
  }
}