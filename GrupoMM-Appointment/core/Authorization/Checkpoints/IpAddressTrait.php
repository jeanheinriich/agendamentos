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
 * Essa é uma trait (característica) simples de inclusão de métodos para
 * lidar com o endereço IP da origem de uma requisição que outras
 * classes podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Checkpoints;

trait IpAddressTrait
{
  /**
   * Recupera o endereço IP da conexão.
   * 
   * @return string
   *   O endereço IP da conexão
   */
  public function getIpAddress()
  {
    if ( !empty($_SERVER['HTTP_CLIENT_IP']) ) {
      // IP de uma internet compartilhada
      $address = $_SERVER['HTTP_CLIENT_IP'];
    } elseif ( !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
      // IP através de um proxy
      $address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
      // IP do usuário
      $address = $_SERVER['REMOTE_ADDR'];
    }

    return $address;
  }
}