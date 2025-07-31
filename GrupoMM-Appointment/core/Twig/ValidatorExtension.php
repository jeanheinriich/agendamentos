<?php
/*
 * This file is part of Slim Framework 3 skeleton application.
 *
 * Copyright (c) 2018 Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 * Portions Copyright (c) 2016-2017 Alexis Wurth <awurth.dev@gmail.com>
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
 * função 'Validator' que recupera as informações sobre a validação dos
 * campos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 * @author Alexis Wurth <awurth.dev@gmail.com>
 */

namespace Core\Twig;

use Core\Validation\ValidatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ValidatorExtension
  extends AbstractExtension
{
  /**
   * A instância do validador.
   *
   * @var ValidatorInterface
   */
  protected $validator;
  
  /**
   * O construtor de nossa extensão.
   * 
   * @param ValidatorInterface $validator
   *   A interface para o sistema de validação
   */
  public function __construct(
    ValidatorInterface $validator
  )
  {
    $this->validator = $validator;
  }
  
  /**
   * Recupera as funções para o sistema Twig.
   * 
   * @return array
   */
  public function getFunctions(): array
  {
    return [
      new TwigFunction('getError', [$this, 'getError']),
      new TwigFunction('getErrors', [$this, 'getErrors']),
      new TwigFunction('hasError', [$this, 'hasError']),
      new TwigFunction('hasErrors', [$this, 'hasErrors']),
      new TwigFunction('cssError', [$this, 'cssError']),
      new TwigFunction('cssErrors', [$this, 'cssErrors']),
      new TwigFunction('cssStep', [$this, 'cssStep']),
      new TwigFunction('getValue', [$this, 'getValue']),
      new TwigFunction('getValues', [$this, 'getValues'])
    ];
  }
  
  /**
   * Obtém o primeiro erro de validação de um campo.
   * 
   * @param string $key
   *   O campo desejado
   * @param int $index
   *   O índice do erro
   * @param string $group
   *   O grupo ao qual pertence o campo
   * 
   * @return string
   *   A descrição do erro associada ao campo
   */
  public function getError(
    string $key,
    ?int $index = null,
    ?string $group = null
  ): string
  {
    return $this->validator->getError($key, $index, $group);
  }
  
  /**
   * Obtém uma matriz com os erros de validação.
   * 
   * @param string $key
   *   O campo desejado
   * @param string $group
   *   O grupo ao qual pertence o campo
   * 
   * @return array
   *   A matriz de erros
   */
  public function getErrors(
    ?string $key = null,
    ?string $group = null
  ): array
  {
    return $this->validator->getErrors($key, $group);
  }
  
  /**
   * Obtém um valor dos dados validados.
   * 
   * @param string $key
   *   O campo desejado
   * @param string $group
   *   O grupo ao qual pertence o campo
   * 
   * @return mixed
   *   O valor do parâmetro
   */
  public function getValue(
    string $key,
    ?string $group = null
  ): mixed
  {
    return $this->validator->getValue($key, $group);
  }
  
  /**
   * Obtém uma matriz dos valores dos dados validados.
   * 
   * @param string $group
   *   O grupo ao qual pertence os campos
   * 
   * @return mixed
   *   Os valores do parâmetros
   */
  public function getValues(
    ?string $group = null
  ): mixed
  {
    return $this->validator->getValues($group);
  }
  
  /**
   * Diz se há erros de validação para um campo específico.
   * 
   * @param string $key
   *   O campo desejado
   * @param string $group
   *   O grupo ao qual pertence o campo
   * 
   * @return bool
   *   O indicador se o campo possui erros
   */
  public function hasError(
    string $key,
    ?string $group = null
  ): bool
  {
    return !empty($this->validator->getErrors($key, $group));
  }
  
  /**
   * Diz se há erros de validação em algum campo.
   * 
   * @return bool
   *   O indicador que um dos campos possuem erros.
   */
  public function hasErrors(): bool
  {
    return !$this->validator->isValid();
  }
  
  /**
   * Acrescenta a classe indicativa de erro no css se há erros de
   * validação para um campo em específico.
   * 
   * @param string $key
   *   O nome do campo desejado
   * @param string $group
   *   O grupo ao qual pertence o campo
   * 
   * @return string
   *   A classe para indicação de erro no campo, se necessário
   */
  public function cssError(
    $key, $group = null
  ): string
  {
    return empty($this->validator->getErrors($key, $group))
      ? ''
      : ' error'
    ;
  }
  
  /**
   * Acrescenta a classe indicativa de erro no css se há erros de
   * validação para pelo menos um dos campos de um formulário.
   * 
   * @param string $key
   *   O nome do campo
   * @param string $group
   *   O grupo ao qual pertence o campo
   * 
   * @return string
   *   A classe para indicação de erro nos campos, se necessário
   */
  public function cssErrors(
    ?string $key = null,
    ?string $group = null
  ): string
  {
    return empty($this->validator->getErrors($key, $group))
      ? ''
      : ' error'
    ;
  }
  
  /**
   * Acrescenta as classes css para um componente html de indicação dos
   * passos de um formulário.
   * 
   * @param string $key
   *   O campo desejado
   * @param string $stepPosition
   *   O grupo ao qual pertence o campo
   * 
   * @return string
   *   A classe css do passo
   */
  public function cssStep(
    string $key,
    string $stepPosition
  ): string
  {
    $value = intval($this->validator->getValue($key));

    if ($value < $stepPosition) {
      return 'disabled step';
    } else {
      if ($value > $stepPosition) {
        return 'completed step';
      } else {
        return 'active step';
      }
    }
  }
}
