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
 * Uma classe responsável pela manipulação das trilhas de navegação.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Breadcrumbs;

class Breadcrumb
  implements BreadcrumbInterface
{
  /**
   * As trilhas de navegação
   * 
   * @var array
   */
  protected $trail = [];

  /**
   * O indicador de erros
   * 
   * @var boolean
   */
  protected $haveErrors = false;
  
  /**
   * Acrescenta um valor na trilha de navegação
   * 
   * @param string $title
   *   O título desta rota
   * @param string $url
   *   O endereço desta rota
   */
  public function push(
    string $title,
    ?string $url = null
  ): void
  {
    $this->trail[] = [
      'title' => $title,
      'url' => $url
    ];
  }
  
  /**
   * Recupera uma matriz com as informações de nossa trilha de navegação
   * 
   * @return array
   *   A matriz contendo às informações para montagem da trilha de
   *   navegação
   */
  public function getTrail(): array
  {
    return $this->trail;
  }

  /**
   * Seta que temos um ou mais erros e a trilha de navegação deve
   * refletir isto, pois iremos exibir uma página de erros.
   */
  public function setHasError(): void
  {
    $this->haveErrors = true;
  }
  
  /**
   * Recupera se temos um ou mais erros no sistema que devem refletir na
   * nossa trilha de navegação, pois iremos exibir uma página de erros.
   * 
   * @return bool
   *   O indicativo se temos erros
   */
  public function haveErrors(): bool
  {
    return $this->haveErrors;
  }
}
