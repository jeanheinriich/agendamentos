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
 * A classe base para um formatador de mensagens de erros.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Handlers\Formatters;

use Throwable;

abstract class ErrorFormatter
  implements ErrorMessageFormatter
{
  /**
   * A flag indicativa de que os erros devem ser exibidos com detalhes
   * 
   * @var bool
   */
  protected $displayErrorDetails;

  /**
   * O construtor de nosso formatador.
   * 
   * @param bool $displayErrorDetails
   *   A flag indicativa de que os erros devem ser exibidos com detalhes
   */
  public function __construct(bool $displayErrorDetails)
  {
    $this->displayErrorDetails = $displayErrorDetails;
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
   *   Os dados formatados
   */
  abstract public function renderError(
    Throwable $error,
    array $params
  ): string;

  /**
   * Renderiza uma mensagem de erro.
   * 
   * @param string $error
   *   A mensagem de erro
   * @param array $params
   *   Os parâmetros da requisição
   * 
   * @return string
   *   Os dados formatados
   */
  abstract public function renderErrorMessage(
    string $error,
    array $params
  ): string;

  /**
   * Recupera a descrição do erro.
   * 
   * @param Throwable $error
   *   A exceção que ocasionou o erro
   * 
   * @return string
   *   A descrição do erro
   */
  protected function getErrorDescription(
    Throwable $error
  ): string
  {
    return sprintf("Ocorreu um erro interno do tipo '%s' (Código %d): "
      . "%s em %s:%d",
      get_class($error), $error->getCode(),
      $error->getMessage(), $error->getFile(),
      $error->getLine())
    ;
  }
}