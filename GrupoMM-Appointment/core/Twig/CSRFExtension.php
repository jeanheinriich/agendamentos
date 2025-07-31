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
 * Classe responsável por extender o Twig permitindo a inclusão da
 * função 'csrf' (do inglês Cross-site request forgery - Falsificação de
 * solicitação entre sites) que protege o sistema da injeção de código.
 */

namespace Core\Twig;

use Slim\Csrf\Guard;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CSRFExtension
  extends AbstractExtension
{
  /**
   * A engine de controle de CSRF.
   *
   * @var Guard
   */
  protected $csrf;
  
  /**
   * O construtor de nossa extensão.
   * 
   * @param Guard $csrf
   *   A engine de controle de CSRF
   */
  public function __construct(Guard $csrf)
  {
    $this->csrf = $csrf;
  }
  
  /**
   * Recupera as funções para o sistema Twig.
   * 
   * @return array
   */
  public function getFunctions()
  {
    return [
      new TwigFunction('csrf', [$this, 'csrfFields'], [
          'needs_environment' => false,
          'is_safe' => ['html']
        ]),
      new TwigFunction('Token', [$this, 'csrfTokens']),
    ];
  }
  
  /**
   * Recupera o HTML para os campos CSRF.
   * 
   * @return string
   *   Os campos renderizados
   */
  public function csrfFields(): string
  {
    return '
        <input type="hidden" name="' . $this->csrf->getTokenNameKey() .
          '" value="' . $this->csrf->getTokenName() . '">
        <input type="hidden" name="' . $this->csrf->getTokenValueKey() .
          '" value="' . $this->csrf->getTokenValue() . '">'
    ;
  }
  
  /**
   * Recupera os tokens dos campos CSRF.
   * 
   * @return array  Uma matriz com os valores dos tokens CSRF.
   */
  public function csrfTokens(): array
  {
    return [
      'Key' => [ 'name' => $this->csrf->getTokenNameKey(),
                  'value' => $this->csrf->getTokenName() ],
      'Data' => [ 'name' => $this->csrf->getTokenValueKey(),
                  'value' => $this->csrf->getTokenValue() ]
    ];
  }
}
