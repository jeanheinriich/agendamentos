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
 * Um formatador de mensagens de erro no formato HTML.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Handlers\Formatters;

use Throwable;

class HTMLFormatter
  extends ErrorFormatter
{
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
    $html = '<p>' . $this->getErrorDescription($error) . '</p>';

    // Determina se precisamos exibir o detalhamento do erro
    if ($this->displayErrorDetails) {
      $html .= '<h2>Detalhamento</h2>';
      $html .= $this->renderHtmlError($error);
      
      while ($error = $error->getPrevious()) {
        $html .= '<h2>Exceção anterior</h2>';
        $html .= $this->renderHtmlError($error);
      }
    } else {
      $html .= '<p>Desculpe-nos pelo inconveniente temporário.</p>';
    }
    
    return $html;
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
    $html = '<p>' . $error . '</p>';
    
    return $html;
  }

  /**
   * Renderiza uma exceção no formato HTML.
   * 
   * @param  Throwable $error  A exceção que ocasionou o erro
   * 
   * @return string
   */
  protected function renderHtmlError(Throwable $error): string
  {
    $html = sprintf('<div><strong>Tipo:</strong> %s</div>',
      get_class($error))
    ;
    
    if (($code = $error->getCode())) {
      $html .= sprintf('<div><strong>Código:</strong> %s</div>', $code);
    }
    
    if (($message = $error->getMessage())) {
      $html .= sprintf('<div><strong>Mensagem:</strong> %s</div>',
        htmlentities($message))
      ;
    }
    
    if (($file = $error->getFile())) {
      $html .= sprintf('<div><strong>Arquivo:</strong> %s</div>',
        $file)
      ;
    }
    
    if (($line = $error->getLine())) {
      $html .= sprintf('<div><strong>Linha:</strong> %s</div>', $line);
    }
    
    if (($trace = $error->getTraceAsString())) {
      $html .= '<br><div class="ui attached segment">'
        . '<h2 class="label">Rastreamento</h2>'
        . '<i data-content="Copiar" class="copy link icon"></i>'
        . '</div>'
      ;
      $html .= sprintf('<div class="ui attached segment">'
        . '<code>%s</code></div>', htmlentities($trace))
      ;
    }
    
    return $html;
  }
}