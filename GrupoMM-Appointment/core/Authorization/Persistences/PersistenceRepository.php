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
 * Um sistema de persistência dos dados de autorização.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Persistences;

use Core\Authorization\Persistences\PersistableInterface;
use Core\Authorization\Persistences\Persistence;
use Core\Cookies\CookieInterface;
use Core\Sessions\SessionInterface;

class PersistenceRepository
  implements PersistenceRepositoryInterface
{
  /**
   * A flag de indicação de sessão única por usuário.
   *
   * @var boolean
   */
  protected $single = false;
  
  /**
   * O manipulador de informações da sessão.
   *
   * @var SessionInterface
   */
  protected $session;
  
  /**
   * O manipulador de informações de cookies.
   *
   * @var CookieInterface
   */
  protected $cookie;
  
  /**
   * O model de acesso às persistências.
   *
   * @var string
   */
  protected $model = 'Core\Authorization\Persistences\Persistence';


  // ============================================[ Criação da classe ]==
  
  /**
   * O construtor de nosso sistema de persistência.
   * 
   * @param SessionInterface $session
   *   O manipulador de dados armazenados na sessão
   * @param CookieInterface $cookie
   *   O manipulador de dados armazenados em cookies
   * @param boolean $single
   *   A flag indicativa de unicidade na autenticação, ou seja, apenas
   * uma máquina pode estar autenticada por vez com uma mesma conta
   */
  public function __construct(
    SessionInterface $session,
    CookieInterface $cookie,
    bool $single = false
  )
  {
    // Armazena o manipulador de sessão
    if (isset($session)) {
      $this->session = $session;
    }
    
    if (isset($cookie)) {
      $this->cookie  = $cookie;
    }
    
    $this->single = $single;
  }
  
  
  // =============[ Implementações da PersistenceRepositoryInterface ]==
  
  /**
   * Recupera um código de persistência na sessão atual
   * 
   * @return mixed|null
   *   Retorna os dados do código de persistência ou nulo se não
   * localizar
   */
  public function getPersistenceCode()
  {
    // Verifica se temos um código de persistência armazenado na sessão
    if ($code = $this->session->get('Security\Persistence')) {
      return $code;
    }
    
    // Verifica se temos um código de persistência armazenado no cookie
    if ($code = $this->cookie->get()) {
      return $code;
    }
    
    return null;
  }
  
  /**
   * Encontra uma persistência pelo código de persistência.
   * 
   * @param mixed $code
   *   O código de persistência contendo o seletor e o validador (uma
   * matriz ou objeto)
   * 
   * @return Persistence
   *   Um objeto de persistência ou falso se não conseguir localizar
   */
  public function findByPersistenceCode($code)
  {
    if (is_object($code)) {
      $selector = $code->selector;
    } else {
      $selector = $code['selector'];
    }

    // O código de persistência contém um seletor e um validador.
    // Localiza a informação de persistência na base de dados pelo
    // seletor
    $persistence = Persistence::where('selector', '=', $selector)
      ->first()
    ;
    
    return $persistence
      ? $persistence
      : false
    ;
  }
  
  /**
   * Encontra um usuário pelo código de persistência.
   * 
   * @param mixed $code
   *   O código de persistência
   * 
   * @return \Core\Authorization\Users\UserInterface|false
   *   Um objeto com os dados do usuário ou falso se não localizar
   */
  public function findUserByPersistenceCode($code)
  {
    // Primeiramente localizamos o objeto de persistência para recuperar
    // os dados do usuário
    $persistence = $this->findByPersistenceCode($code);
    
    // Recuperamos o usuário atrelado ao código de persistência
    return $persistence
      ? $persistence->user
      : false
    ;
  }
  
  /**
   * Adiciona uma nova persistência de usuário à sessão atual e anexa o
   * usuário.
   * 
   * @param PersistableInterface $persistable
   *   Um objeto de persistência
   * @param bool|boolean $remember
   *   O indicativo de que deve memorizar este código na sessão
   *                                           
   * @return bool
   */
  public function persist(
    $persistable,
    bool $remember = false
  ): bool
  {
    if ($this->single) {
      $this->flush($persistable);
    }
    
    // Gera um seletor e um validador para persistir os dados do usuário
    $selector  = $persistable->generateSelectorCode();
    $validator = $persistable->generateValidatorCode();
    $token     = base64_encode(hash('sha384', $validator, true));
    
    // Monta o cookie de lembrança
    $code = [
      'selector' => $selector,
      'validator' => $validator
    ];
    
    // Armazena primeiramente na sessão corrente
    $this->session->set('Security\Persistence', $code);
    
    if ($remember === true) {
      $this->cookie->set($code);
    }
    
    // Gera o token a ser armazenado na base de dados
    $token = base64_encode(hash('sha384', $validator, true));
    
    // Armazena na base de dados
    $persistence = new Persistence();
    $persistence->userid = $persistable->getPersistableId();
    $persistence->selector = $selector;
    $persistence->token = $token;
    
    return $persistence->save();
  }
  
  /**
   * Remove a persistência para a sessão atual.
   * 
   * @return bool
   */
  public function forget(): bool
  {
    // Recupera o código de persistência
    $code = $this->getPersistenceCode();
    
    // Se não tivermos nenhum código de persistência, retorna
    if ($code === null) {
      return false;
    }
    
    // Remove as informações de persistência da sessão atual
    $this->session->delete('Security\Persistence');
    
    // Remove as informações de persistência do cookie atual
    $this->cookie->forget();
    
    return $this->remove($code);
  }
  
  /**
   * Remove o código de persistência fornecido.
   * 
   * @param mixed $code
   *   O código de persistência
   * 
   * @return bool
   */
  public function remove($code): bool
  {
    if (is_object($code)) {
      $selector = $code->selector;
    } else {
      $selector = $code['selector'];
    }

    return Persistence::where('selector', $selector)->delete();
  }
  
  /**
   * Libera persistências para o usuário especificado, independente da
   * sessão em que ela se encontra.
   * 
   * @param PersistableInterface $persistable
   *   O objeto de persistência
   * @param bool|boolean $forget
   *   O indicativo se deve encerrar a sessão atual
   */
  public function flush(
    $persistable,
    bool $forget = true
  ): void
  {
    if ($forget) {
      $this->forget();
    }
    
    foreach ($persistable->{$persistable->getPersistableRelationship()}()->get() as $persistence) {
      // Recupera o código de persistência
      $code = $this->getPersistenceCode();
      
      // Libera códigos de persistência obsoletos
      if ($persistence->selector !== $code['selector']) {
        $persistence->delete();
      }
    }
  }
}
