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
 * Um manipulador de grupos de usuários.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */


namespace Core\Authorization\Groups;

class GroupsManager
  implements GroupsManagerInterface
{
  // --------------------[ Implementações da GroupsManagerInterface ]---

  /**
   * Localiza um grupo pela ID fornecida.
   * 
   * @param int $groupID
   *   O ID do grupo
   * 
   * @return GroupInterface
   *   Os dados do grupo
   */
  public function findById($groupID): GroupInterface
  {
    return Group::find($groupID);
  }
  
  /**
   * Localiza um grupo pelo seu nome.
   * 
   * @param string $name
   *   O nome do grupo
   * 
   * @return Group
   *   Os dados do grupo
   */
  public function findByName($name): GroupInterface
  {
    return Group::where('name', $name)
      ->first()
    ;
  }
}
