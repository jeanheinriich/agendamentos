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
 * A classe que fornece uma maneira simples de impedir que o usuário
 * use uma senha fácil de adivinhar ou que seja simples de ser quebrada
 * por ataque de força bruta.
 * 
 * Ele fornece regras de validação de senha simples, porém bastante
 * eficazes. A biblioteca introduz a tradução de senhas codificadas pelo
 * alfabeto leet, convertendo a senha fornecida em uma lista de
 * possíveis alterações simples, como alterar a letra a por @ ou 4. Esta
 * lista é então comparada com senhas comuns baseadas em pesquisas feitas
 * sobre as mais recentes violações de banco de dados de senhas (stratfor,
 * sony, phpbb, rockyou, myspace).
 * 
 * Além disso, ele valida o tamanho e o uso de vários conjuntos de
 * caracteres (letras maiúsculas, minúsculas, caracteres numéricos e
 * caracteres especiais). Por último, verifica também o uso de palavras
 * de uso no ambiente onde está inserido (como o nome da empresa).
 * 
 * --------------------------------------------------------------------
 * Controle de Modificações
 * 
 * 2018-09-20 - Emerson Cavalcanti
 *   - Versão inicial
 * ---------------------------------------------------------------------
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Passwords;

use Exception;

class PasswordStrengthInspector
{
  /**
   * As mensagens de erro identificáveis durante nossa análise.
   *
   * @var array
   */
  private $lang = array(
    'length' => 'A senha deve ter entre %s e %s caracteres',
    'upper' => 'A senha deve conter pelo menos um caractere maiúsculo',
    'lower' => 'A senha deve conter pelo menos um caractere minúsculo',
    'alpha' => 'A senha deve conter pelo menos um caractere alfabético',
    'numeric' => 'A senha deve conter pelo menos um caractere numérico',
    'special' => 'A senha deve conter pelo menos um símbolo',
    'environ' => 'A senha usa informações identificáveis e é simples de ser adivinhada',
    'common' => 'A senha é muito comum e pode ser facilmente quebrada',
    'onlynumeric' => 'A senha não deve ser totalmente numérica'
  );
  
  /**
   * As opções de análise.
   *
   * @var array
   */
  private $options = [
    'tests' => [
      'length',
      'upper',
      'lower',
      'numeric',
      'special',
      'environ',
      'common',
      'onlynumeric'
    ],
    'maxlen-guessable-test' => 24
  ];
  
  /**
   * Determina um tamanho máximo para nossa senha.
   *
   * @var int|null
   */
  private $maxLength = null;
  
  /**
   * O tamanho mínimo de nossa senha.
   *
   * @var integer
   */
  private $minLength = 8;
  
  /**
   * Determina as palavras comuns no ambiente em que estamos (como o
   * nome da empresa) quer permitiriam facilitar a descoberta da senha.
   *
   * @var array
   */
  private $environ = [];
  
  /**
   * O arquivo contendo um dicionario de senhas comuns.
   *
   * @var string
   */
  private $dict;
  
  /**
   * Os erros determinados durante a verificação.
   *
   * @var array
   */
  private $errors = [];
  
  /**
   * A nossa senha original.
   *
   * @var string
   */
  private $original;
  
  // As variações das senhas usando a tradução de caracteres do alfabeto
  // leet
  private $pass = [];
  
  // ============================================[ Criação da classe ]==
  
  /**
   * O construtor do analisador de complexidade de senha.
   * 
   * @param int $maxLength
   *   O comprimento máximo da senha
   * @param array $options
   *   A matriz com as opções
   * @param array $environ
   *   A matriz com as palavras de uso no ambiente onde se está inserido
   * @param string $dict
   *   O arquivo do dicionário de senhas
   */
  public function __construct(
    int $maxLength = 25,
    array $options = [],
    array $environ = [],
    ?string $dict = null
  )
  {
    $this->maxLength = $maxLength;
    $this->environ = $environ;
    
    $this->dict = (isset($dict))
      ? $dict
      : __DIR__ . DIRECTORY_SEPARATOR . 'StupidPass.default.dict'
    ;
    
    // Verifica se temos opções definidas
    if (count($options) > 0) {
      // Fazemos o merge das opções padrão do sistema com as opções
      // fornecidas
      $this->options = array_merge($this->options, $options);
    }
  }
  
  /**
   * Valida uma senha com base na configuração no construtor. Use
   * $this->getErrors() para recuperar uma matriz com os erros.
   * 
   * @param string $password
   *   A senha a ser analisada
   * 
   * @return bool
   */
  public function validate(string $password): bool
  {
    $this->errors = null;
    $this->original = $password;
    
    // Determina quais as verificações básicas precisam ser realizadas
    // em função de nossas configurações
    if ( in_array('length', $this->options['tests']) ) {
      $this->length();
    }
    if ( in_array('upper', $this->options['tests']) ) {
      $this->upper();
    }
    if ( in_array('lower', $this->options['tests']) ) {
      $this->lower();
    }
    if ( in_array('alpha', $this->options['tests']) ) {
      $this->alpha();
    }
    if ( in_array('numeric', $this->options['tests']) ) {
      $this->numeric();
    }
    if ( in_array('special', $this->options['tests']) ) {
      $this->special();
    }
    if ( in_array('onlynumeric', $this->options['tests']) ) {
      $this->onlynumeric();
    }
    
    // Verifica se a senha está dentro de um limite de verificações
    if (strlen($password) <= $this->options['maxlen-guessable-test']) {
      // Extrapola o texto digitado para permitir verificações de senhas
      // simples modificadas pelo uso do 'Alfabeto leet'
      $this->extrapolate();
      
      // Determina quais as verificações avançadas precisam ser realizadas
      // em função de nossas configurações
      if ( in_array('environ', $this->options['tests']) ) {
        $this->environmental();
      }
      if ( in_array('common', $this->options['tests']) ) {
        $this->common();
      }
    }
    
    $this->pass = null;
    
    return (empty($this->errors));
  }
  
  /**
   * Recupera uma matriz enumerando todos os erros existentes em nossa
   * análise.
   * 
   * @return array
   *   A matriz com os erros
   */
  public function getErrors(): array
  {
    return $this->errors;
  }
  
  /* =========================================[ Testes Básicos ]=====
   * Os testes simples de verificação de nossa senha.
   * ================================================================ */
  
  /**
   * Teste de verificação de comprimento.
   * 
   * @return void
   */
  private function length(): void
  {
    $passwordLength = strlen($this->original);
    
    if ( ($passwordLength < $this->minLength)
         OR ($passwordLength > $this->maxLength) ) {
      $err = sprintf($this->lang['length'], $this->minLength,
        $this->maxLength
      );
      $this->errors[] = $err;
    }
  }
  
  /**
   * Teste de verificação de presença de caracteres maiúsculos.
   * 
   * @return void
   */
  private function upper(): void
  {
    if (!preg_match('/[A-Z]+/', $this->original)) {
      $this->errors[] = $this->lang['upper'];
    }
  }
  
  /**
   * Teste de verificação de presença de caracteres minúsculos.
   * 
   * @return void
   */
  private function lower(): void
  {
    if (!preg_match('/[a-z]+/', $this->original)) {
      $this->errors[] = $this->lang['lower'];
    }
  }
  
  /**
   * Teste de verificação de presença de caracteres alfabéticos.
   * 
   * @return void
   */
  private function alpha(): void
  {
    if (!preg_match('/[a-z]+/', strtolower($this->original))) {
      $this->errors[] = $this->lang['alpha'];
    }
  }
  
  /**
   * Teste de verificação de presença de caracteres numéricos.
   * 
   * @return void
   */
  private function numeric(): void
  {
    if (!preg_match('/[0-9]+/', $this->original)) {
      $this->errors[] = $this->lang['numeric'];
    }
  }
  
  /**
   * Teste de verificação de presença de apenas caracteres numéricos.
   * 
   * @return void
   */
  private function onlynumeric(): void
  {
    if (preg_match('/^[0-9]*$/', $this->original)) {
      $this->errors[] = $this->lang['onlynumeric'];
    }
  }
  
  /**
   * Teste de verificação de presença de símbolos.
   * 
   * @return void
   */
  private function special(): void
  {
    if (!preg_match('/[\W_]/', $this->original)) {
      $this->errors[] = $this->lang['special'];
    }
  }

  
  /* =======================================[ Testes Avançados ]=====
   * Os testes de verificação avançados de nossa senha.
   * ================================================================ */
  
  /**
   * Teste de verificação de presença de palavras de uso no ambiente
   * onde se está inserido.
   * 
   * @return void
   */
  private function environmental(): void
  {
    foreach ($this->environ as $env) {
      foreach ($this->pass as $pass) {
        if (preg_match("/$env/i", $pass) == 1) {
          $this->errors[] = $this->lang['environ'];
          return;
        }
      }
    }
  }
  
  /**
   * Teste de verificação de presença de senhas comuns.
   * 
   * @return void
   */
  private function common(): void
  {
    $fp = fopen($this->dict, 'r');
    {
      if (!$fp) {
        throw new Exception("Não foi possível abrir o arquivo de "
          . "dicionário de senhas comuns: {$this->dict}"
        );
      }
    }
    
    while (($buf = fgets($fp, 1024)) !== false) {
      $buf = rtrim($buf);
      foreach ($this->pass as $pass) {
        if ($pass == $buf) {
          $this->errors[] = $this->lang['common'];
          return;
        }
      }
    }
  }
  
  /**
   * Extrapola o texto digitado para permitir verificações de senhas
   * simples modificadas pelo uso do 'Alfabeto leet'.
   * 
   * @return void
   */
  private function extrapolate(): void
  {
    // Alfabeto leet
    // Atenção: não coloque muita coisa aqui, tem um impacto exponencial
    // no desempenho.
    $leet = [
      '@' => ['a', 'o'],
      '4' => ['a'],
      '8' => ['b'],
      '3' => ['e'],
      '1' => ['i', 'l'],
      '!' => ['i', 'l', '1'],
      '0' => ['o'],
      '$' => ['s', '5'],
      '5' => ['s'],
      '6' => ['b', 'd'],
      '7' => ['t']
    ];
    
    $map = array();
    $pass_array = str_split(strtolower($this->original));
    foreach ($pass_array as $i => $char) {
      $map[$i][] = $char;
      foreach ($leet as $pattern => $replace) {
        if ($char === (string)$pattern) {
          for ($j = 0, $c = count($replace); $j < $c; $j++) {
            $map[$i][] = $replace[$j];
          }
        }
      }
    }

    $this->pass = $this->expand($map);
  }
  
  /**
   * Expande nossa senha para todas as senhas possíveis recursivamente.
   * 
   * @param array &$map
   *   A matriz com as possíveis senhas traduzidas através do alfabeto
   * leet
   * @param array $old
   *   A matriz anterior
   * @param integer $index
   *   A posição dentro da matriz
   * 
   * @return array
   *   As possíveis senhas expandidas
   */
  private function expand(&$map, $old = array(), $index = 0): array
  {
    $new = array();
    foreach ($map[$index] as $char) {
      $count = count($old);
      if ($count == 0) {
        $new[] = $char;
      } else {
        for ($i = 0; $i < $count; $i++) {
          $new[] = @$old[$i] . $char;
        }
      }
    }

    unset($old);

    $result = ($index == count($map) - 1)
      ? $new
      : $this->expand($map, $new, $index + 1)
    ;

    return $result;
  }
}
