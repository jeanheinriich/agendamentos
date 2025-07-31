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
 * função 'breadcrumb' que inclui informações das trilhas de navegação.
 */

namespace Core\Twig;

use Core\Breadcrumbs\Breadcrumb;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BreadcrumbExtension
  extends AbstractExtension
{
  /**
   * O manipulador de breadcrumbs.
   * 
   * @var Breadcrumb
   */
  protected $breadcrumb;
  
  /**
   * O construtor de nossa extensão
   * 
   * @param Breadcrumb $breadcrumb
   *   O manipulador de Breadcrumbs
   */
  public function __construct(Breadcrumb $breadcrumb)
  {
    $this->breadcrumb = $breadcrumb;
  }
  
  /**
   * Recupera as funções para o sistema Twig.
   * 
   * @return array
   */
  public function getFunctions()
  {
    return [
      new TwigFunction('breadcrumb', [$this, 'breadcrumb']),
      new TwigFunction('haveErrors', [$this, 'haveErrors'])
    ];
  }
  
  /**
   * Gera o conteúdo das trilhas de navegação (breadcrumb)
   * 
   * @return array
   */
  public function breadcrumb(): array
  {
    return $this->breadcrumb->getTrail();
  }
  
  /**
   * Sinaliza se estamos em modo de exibição de uma página de erro.
   * 
   * @return bool
   */
  public function haveErrors(): bool
  {
    return $this->breadcrumb->haveErrors();
  }
}
