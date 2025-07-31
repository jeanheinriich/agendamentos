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
 * Uma classe para erros de acesso negado.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Exceptions;

use Exception;

class TemplateNotFoundException
  extends Exception
  implements ExceptionInterface
{
  /**
   * A mensagem de exceção.
   * 
   * @var string
   */
  protected $message = "Não foi possível encontrar o arquivo de "
    . "template %spara renderizar."
  ;

  /**
   * O código definido para esta exceção.
   * 
   * @var integer
   */
  protected $code;

  /**
   * O nome de arquivo de origem da exceção.
   * 
   * @var integer
   */
  protected $file;

  /**
   * Linha do arquivo de origem da exceção.
   * 
   * @var integer
   */
  protected $line;

  /**
   * As informações de rastreamento do erro.
   * 
   * @var array
   */
  private $trace;

  /**
   * Criar uma nova exceção.
   * 
   * @param string $template
   *   O nome do arquivo de template
   * @param int|integer $code
   *   O código de erro
   * 
   * @param Exception|null $previous
   *   A exceção anterior (caso exista)
   */
  public function __construct(string $template = null, int $code = 0,
    Exception $previous = null)
  {
    if (!$template) {
      $message = sprintf($this->message, "desconhecido");
    } else {
      $message = sprintf($this->message, '"' . $template . '" ');
    }

    $code = 404;

    parent::__construct($message, $code, $previous);
  }

  /**
   * Converte esta exceção para texto.
   * 
   * @return string
   *   Uma string que descreve a exceção
   */
  public function __toString()
  {
    return get_class($this)
      . " '{$this->message}' in {$this->file}({$this->line})\n"
      . "{$this->getTraceAsString()}"
    ;
  }
}
