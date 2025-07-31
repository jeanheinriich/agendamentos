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
 * função 'address' que inclui o acesso à agenda de endereços.
 */

namespace Core\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AddressExtension
  extends AbstractExtension
{
  /**
   * As informações de endereços
   *
   * @var array
   */
  protected $addresses = [];
  
  /**
   * O construtor de nossa extensão.
   * 
   * @param array $addresses
   *   Os endereços disponíveis, na seguinte forma:
   *     [
   *       'contact1' => [
   *           'name' => 'Contact name 1',
   *           'email' => 'contact1@mail.com'
   *         ],
   *       'contact2' => [
   *           'name' => 'Contact name 2',
   *           'email' => 'contact2@mail.com',
   *           'number' => '(11) 2345-6789',
   *           'phone' => '551123456789'
   *         ]
   *     ]
   */
  public function __construct(array $addresses)
  {
    $this->addresses = $addresses;
  }
  
  /**
   * Recupera as funções para o sistema Twig.
   * 
   * @return array
   */
  public function getFunctions()
  {
    return [
      new TwigFunction('address', [$this, 'address'])
    ];
  }
  
  /**
   * Recupera as informações de um endereço configurado no sistema.
   * 
   * @param string $name
   *   O nome do contato desejado.
   * 
   * @return array
   *   A informação do endereço solicitado
   */
  public function address(string $name): array
  {
    return $this->addresses[$name];
  }
}
