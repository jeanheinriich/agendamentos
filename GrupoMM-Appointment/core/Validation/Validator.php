<?php
/*
 * This file is part of Extension Library.
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
 * Classe responsável pelas validação dos campos de um formulário.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 * @author Alexis Wurth <awurth.dev@gmail.com>
 */

namespace Core\Validation;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface as Request;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Rules\AllOf;
use Respect\Validation\Rules\AbstractWrapper;
use Slim\Interfaces\RouteInterface;
use SplFileInfo;

class Validator
  implements ValidatorInterface
{
  /**
   * As mensagens de erro padrão para as regras dadas.
   * 
   * @var array
   */
  protected $defaultMessages;

  /**
   * A lista de erros de validação.
   * 
   * @var array
   */
  protected $errors;

  /**
   * Flag que define se os erros devem ser armazenados em uma matriz
   * associativa com regras de validação como chave ou em uma matriz
   * indexada.
   * 
   * @var bool
   */
  protected $showValidationRules;

  /**
   * Os dados validados.
   * 
   * @var array
   */
  protected $values;

  /**
   * O construtor de nosso validador.
   * 
   * @param bool|boolean $showValidationRules (opcional)
   *   Flag que define se os erros devem ser armazenados em uma matriz
   *   associativa com regras de validação como chave (false) ou em uma
   *   matriz indexada (true)
   * @param array $defaultMessages (opcional)
   *   Uma matriz com as mensagens padrão para cada tipo de validação
   *   possível
   */
  public function __construct(bool $showValidationRules = true,
    array $defaultMessages = [])
  {
    $this->showValidationRules = $showValidationRules;
    $this->defaultMessages = $defaultMessages;
    $this->errors = [];
    $this->values = [];
  }

  /**
   * Determina se não há erros de validação.
   * 
   * @return boolean
   */
  public function isValid(): bool
  {
    return empty($this->errors);
  }

  /**
   * Valida os valores de uma matriz com as regras dadas.
   * 
   * @param array $input
   *   A matriz com os valores a serem validados
   * @param array $rules
   *   A matriz com as regras de validação
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   * @param array $messages (opcional)
   *   As mensagens de erro personalizadas para as regras de validação
   * @param mixed $default (opcional)
   *   O valor padrão a ser atribuído, caso não tenha um valor
   * 
   * @return $this
   *   A instância do validador
   */
  public function array(array $input, array $rules,
    ?string $group = null, array $messages = [], $default = null): ValidatorInterface
  {
    foreach ($rules as $key => $options) {
      $config = new Configuration($options, $key, $group, $default);
      $value = $input[$key] ?? $config->getDefault();

      $this->validateInput($value, $config, $messages);
    }

    return $this;
  }

  /**
   * Valida as propriedades de um objeto com as regras dadas.
   * 
   * @param mixed $object
   *   O objeto cujas propriedades serão validadas
   * @param array $rules
   *   A matriz com as regras de validação
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   * @param array $messages (opcional)
   *   As mensagens de erro personalizadas para as regras de validação
   * @param mixed $default (opcional)
   *   O valor padrão a ser atribuído, caso não tenha um valor
   * 
   * @return $this
   *   A instância do validador
   *
   * @throws InvalidArgumentException
   *   Em caso de argumento inválido
   */
  public function object($object, array $rules, ?string $group = null,
    array $messages = [], $default = null): ValidatorInterface
  {
    if (!is_object($object)) {
      throw new InvalidArgumentException("O primeiro argumento deve "
        . "ser um objeto"
      );
    }

    foreach ($rules as $property => $options) {
      $config = new Configuration($options, $property, $group, $default);
      $value = $this->getPropertyValue(
        $object, $property, $config->getDefault()
      );

      $this->validateInput($value, $config, $messages);
    }

    return $this;
  }

  /**
   * Valida os parâmetros de solicitação com as regras dadas, obtendo os
   * dados da requisição realizada.
   * 
   * @param Request $request
   *   A requisição cujos campos serão validados
   * @param array $rules
   *   A matriz com as regras de validação
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   * @param array $messages (opcional)
   *   As mensagens de erro personalizadas para as regras de validação
   * @param mixed $default (opcional)
   *   O valor padrão a ser atribuído, caso não tenha um valor
   * 
   * @return $this
   *   A instância do validador
   */
  public function request(Request $request, array $rules,
    ?string $group = null, array $messages = [], $default = null): ValidatorInterface
  {
    foreach ($rules as $param => $options) {
      if (is_array($options)) {
        // Este parâmetro indica que nesta parte, temos um conjunto de
        // campos agrupados formando uma matriz. Desta forma, as regras
        // devem ser aplicadas a cada linha de nossa matriz
        // recursivamente
        $arrayOfValues = $this->getRequestParam($request, $param,
          $default
        );

        if (is_array($arrayOfValues)) {
          foreach ($arrayOfValues as $index => $data) {
            // Para cada dado, faremos o processamento, modificando o nome
            // do campo para corresponder corretamente ao seu nome no
            // formulário
            $this->aggregate($options, $group, $messages, $default,
              $param, $index, $data
            );
          }
        }
      } else {
        $config = new Configuration($options, $param, $group, $default);

        $value = $this->getRequestParam($request, $param,
          $config->getDefault()
        );

        $this->validateInput($value, $config, $messages);
      }
    }

    return $this;
  }

  /**
   * Valida um campo com as regras dadas, modificando o nome do campo
   * para corresponder corretamente ao seu nome no formulário.
   * 
   * @param array $rules
   *   A matriz com as regras de validação
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   * @param array $messages (opcional)
   *   As mensagens de erro personalizadas para as regras de validação
   * @param mixed $default (opcional)
   *   O valor padrão a ser atribuído, caso não tenha um valor
   * @param string $aggregateName
   *   O nome do conjunto de dados
   * @param int $aggregateIndex
   *   O índice do valor dentro do conjunto de dados
   * @param mixed $aggregateValues
   *   O valor do conjunto de dados
   * 
   * @return $this
   *   A instância do validador
   */
  protected function aggregate(
    array $rules,
    ?string $group = null,
    array $messages = [],
    $default = null,
    string $aggregateName,
    int $aggregateIndex,
    $aggregateValues
  ): ValidatorInterface
  {
    foreach ($rules as $param => $options) {
      $paramArray = "{$aggregateName}[{$aggregateIndex}][{$param}]";
      if (is_array($options)) {
        // Este parâmetro indica que nesta parte, temos um conjunto de
        // campos agrupados formando uma matriz. Desta forma, as regras
        // devem ser aplicadas a cada linha de nossa matriz
        // recursivamente
        $arrayOfValues = $this->getAggregateValue($aggregateValues,
          $param
        );

        if (is_array($arrayOfValues)) {
          // Somente percorremos os dados se existirem dados
          foreach ($arrayOfValues as $index => $data) {
            // Para cada dado, faremos o processamento, modificando o nome
            // do campo para corresponder corretamente ao seu nome no
            // formulário
            $this->aggregate($options, $group, $messages, $default,
              $paramArray, $index, $data
            );
          }
        }
      } else {
        $config = new Configuration($options, $paramArray, $group,
          $default
        );
        $value = $this->getAggregateValue($aggregateValues, $param);

        $this->validateInput($value, $config, $messages);
      }
    }

    return $this;
  }

  /**
   * Recupera o valor de um campo em um formulário.
   * 
   * @param array $values
   *   A matriz com os campos e seus valores
   * @param string $str
   *   O parâmetro (campo) que se deseja obter
   * 
   * @return mixed
   *   O valor do parâmetro (campo)
   */
  protected function getAggregateValue(array $values, string $str)
  {
    preg_match_all("/\\[(.*?)\\]/", $str, $matches);

    $parm = end($matches[1]);

    if ($parm) {
      return isset($values[$parm])
        ? $values[$parm]
        : null
      ;
    }

    return isset($values[$str])
      ? $values[$str]
      : null
    ;
  }

  /**
   * Valida parâmetros de solicitação, uma matriz ou propriedades de
   * objetos.
   *
   * @param mixed $input
   *   Os valores de entrada
   * @param array $rules
   *   As regras de validação
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   * @param array $messages (opcional)
   *   As mensagens de erro personalizadas para as regras de validação
   * @param mixed $default (opcional)
   *   O valor padrão a ser atribuído, caso não tenha um valor
   * 
   * @return $this
   *   A instância do validador
   *
   * @throws InvalidArgumentException
   *   Em caso de argumento inválido
   */
  public function validate($input, array $rules, ?string $group = null,
    array $messages = [], $default = null): ValidatorInterface
  {
    if ($input instanceof Request) {
      return $this->request($input, $rules, $group, $messages, $default);
    } elseif (is_array($input)) {
      return $this->array($input, $rules, $group, $messages, $default);
    } elseif (is_object($input)) {
      return $this->object($input, $rules, $group, $messages, $default);
    }

    return $this->value($input, $rules, null, $group, $messages);
  }

  /**
   * Valida um único valor com as regras dadas.
   *
   * @param mixed $value
   *   O valor a ser validado
   * @param array $rules
   *   As regras de validação específicas deste valor
   * @param string $key
   *   A chave (nome do campo) (opcional)
   * @param string $group (opcional)
   *   O grupo ao qual pertence este valor
   * @param array $messages (opcional)
   *   As mensagens de erro personalizadas para as regras de validação
   *
   * @return $this
   *   A instância do validador
   */
  public function value($value, array $rules, ?string $key = null,
    ?string $group = null, array $messages = []): ValidatorInterface
  {
    $config = new Configuration($rules, $key, $group);

    $this->validateInput($value, $config, $messages);

    return $this;
  }

  /**
   * Obtém a quantidade de erros existentes.
   *
   * @return int
   *   A quantidade de erros
   */
  public function count(): int
  {
    return count($this->errors);
  }

  /**
   * Adiciona um erro de validação manualmente.
   *
   * @param string $key
   *   A chave (nome do campo)
   * @param string $message
   *   A mensagem de erro
   * @param string $group (opcional)
   *   O grupo ao qual pertence este campo
   * 
   * @return $this
   *   A instância do validador
   */
  public function addError(string $key, string $message,
    ?string $group = null): ValidatorInterface
  {
    if (!empty($group)) {
      $this->errors[$group][$key][] = $message;
    } else {
      $this->errors[$key][] = $message;
    }

    return $this;
  }

  /**
   * Obtém a mensagem padrão para o parâmetro informado.
   *
   * @param string $key
   *   A chave (nome do campo) para o qual se deseja obter a mensagem
   *   padrão
   *
   * @return string
   *   A mensagem padrão
   */
  public function getDefaultMessage(string $key): string
  {
    return $this->defaultMessages[$key] ?? '';
  }

  /**
   * Obtém todas as mensagens padrão.
   *
   * @return array
   *   Uma matriz com as mensagens padrão
   */
  public function getDefaultMessages(): array
  {
    return $this->defaultMessages;
  }

  /**
   * Obtém um erro para o parâmetro informado.
   *
   * @param string $key
   *   A chave (nome do campo)
   * @param mixed $index (opcional)
   *   O índice do valor (para o caso de estarmos lidando com uma matriz
   *   de valores)
   * @param string $group (opcional)
   *   O grupo ao qual este valor faz parte
   *
   * @return string
   *   A mensagem de erro de validação
   */
  public function getError(string $key, $index = null,
    ?string $group = null): string
  {
    if (null === $index) {
      return $this->getFirstError($key, $group);
    }

    if (!empty($group)) {
      return $this->errors[$group][$key][$index] ?? '';
    }

    return $this->errors[$key][$index] ?? '';
  }

  /**
   * Obtém todos os erros.
   *
   * @param string $key (opcional)
   *   O campo desejado
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   *
   * @return array
   *   Uma matriz com os erros de validação
   */
  public function getErrors(?string $key = null,
    ?string $group = null): array
  {
    if (!empty($key)) {
      if (!empty($group)) {
        return $this->errors[$group][$key] ?? [];
      }

      return $this->errors[$key] ?? [];
    }

    return $this->errors;
  }

  /**
   * Obtém todos os erros formatados de forma clara.
   *
   * @param string $key (opcional)
   *   O campo desejado
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   *
   * @return array
   *   Uma matriz com os erros de validação
   */
  public function getFormatedErrors(?string $key = null,
    ?string $group = null): array
  {
    $formatErrors = function(string $key, array $errors) {
      return "Erro de validação em {$key}: "
        . implode('; ', $errors) . ';'
      ;
    };
    $allErrors = $this->getErrors($key, $group);
    $messages = array_map(
      $formatErrors,
      array_keys($allErrors),
      array_values($allErrors)
    );
    $result = [];
    foreach ($messages AS $message) {
      $result[] = $message;
    }

    return $result;
  }

  /**
   * Obtém o primeiro erro para o parâmetro.
   *
   * @param string $key
   *   O campo desejado
   * @param string $group (opcional)
   *   O grupo ao qual este valor faz parte
   *
   * @return string
   *   A mensagem de erro
   */
  public function getFirstError(string $key,
    ?string $group = null): string
  {
    if (!empty($group)) {
      if (isset($this->errors[$group][$key])) {
        $first = array_slice($this->errors[$group][$key], 0, 1);

        return array_shift($first);
      }
    }

    if (isset($this->errors[$key])) {
      $first = array_slice($this->errors[$key], 0, 1);

      return array_shift($first);
    }

    return '';
  }

  /**
   * Obtém um valor validado para o parâmetro informado.
   *
   * @param string $key
   *   O campo desejado
   * @param string $group (opcional)
   *   O grupo ao qual este valor faz parte
   *
   * @return mixed
   *   O valor do campo
   */
  public function getValue(string $key, ?string $group = null)
  {
    if (!empty($group)) {
      return $this->values[$group][$key] ?? null;
    }

    return $this->values[$key] ?? null;
  }

  /**
   * Obtém todos os valores validados.
   *
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   *
   * @return array
   *   Uma matriz com os valores validados
   */
  public function getValues(?string $group = null): array
  {
    if (!empty($group)) {
      return $this->values[$group] ?? [];
    }

    return $this->values;
  }

  /**
   * Lida com os valores antes de recuperar, aplicando filtros se
   * necessário.
   *
   * @param array $rules
   *   As regras de validação
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   */
  public function handleValues(array $rules,
    ?string $group = null): void
  {
    // Corrige valores
    foreach ($rules AS $key => $filter) {
      switch ($filter)
      {
        case 'Boolean':
          if (!empty($group)) {
            $this->values[$group][$key] =
              filter_var($this->values[$group][$key],
                FILTER_VALIDATE_BOOLEAN
              )
            ;
          } else {
            $this->values[$key] =
              filter_var($this->values[$key], FILTER_VALIDATE_BOOLEAN)
            ;
          }
          break;
        default:
          throw new InvalidArgumentException("O filtro {$filter} para "
            . "o campo {$key} é inválido."
          );
      }
    }
  }

  /**
   * Obtém o modo de armazenamento dos erros:
   *   false: os erros são armazenados em uma matriz associativa com
   *          regras de validação como chave
   *   true:  os erros são armazenados em uma matriz indexada
   *
   * @return bool
   */
  public function getShowValidationRules(): bool
  {
    return $this->showValidationRules;
  }

  /**
   * Remove os erros de validação
   *
   * @param string $key (opcional)
   *   O campo desejado (opcional)
   * @param string $group (opcional)
   *   O grupo ao qual estes valores fazem parte
   * 
   * @return $this
   *   A instância do validador
   */
  public function removeErrors(?string $key = null,
    ?string $group = null): ValidatorInterface
  {
    if (!empty($group)) {
      if (!empty($key)) {
        unset($this->errors[$group][$key]);
      } else {
        unset($this->errors[$group]);
      }
    } elseif (!empty($key)) {
      unset($this->errors[$key]);
    }

    return $this;
  }

  /**
   * Define a mensagem de erro padrão para uma regra de validação.
   *
   * @param string $rule
   *   A regra de validação
   * @param string $message
   *   A mensagem de erro padrão
   * 
   * @return $this
   *   A instância do validador
   */
  public function setDefaultMessage(string $rule, string $message): ValidatorInterface
  {
    $this->defaultMessages[$rule] = $message;

    return $this;
  }

  /**
   * Define as mensagens de erro padrão para as diversas regra de
   * validação.
   *
   * @param array $messages
   *   Uma matriz com as mensagens de erro padrão para cada regra
   * 
   * @return $this
   *   A instância do validador
   */
  public function setDefaultMessages(array $messages): ValidatorInterface
  {
    $this->defaultMessages = $messages;

    return $this;
  }

  /**
   * Define os erros de validação.
   *
   * @param array $errors
   *   A matriz com os erros de validação
   * @param string $key (opcional)
   *   A chave (campo) (opcional)
   * @param string $group (opcional)
   *   O grupo ao qual este valor faz parte
   * 
   * @return $this
   *   A instância do validador
   */
  public function setErrors(array $errors, ?string $key = null,
    ?string $group = null): ValidatorInterface
  {
    if (!empty($group)) {
      if (!empty($key)) {
        $this->errors[$group][$key] = $errors;
      } else {
        $this->errors[$group] = $errors;
      }
    } elseif (!empty($key)) {
      $this->errors[$key] = $errors;
    } else {
      $this->errors = $errors;
    }

    return $this;
  }

  /**
   * Define o modo de armazenamento de erros.
   *
   * @param bool $showValidationRules
   *   O modo de armazenamento dos erros:
   *     false: os erros são armazenados em uma matriz associativa com
   *            regras de validação como chave
   *     true:  os erros são armazenados em uma matriz indexada
   */
  public function setShowValidationRules(bool $showValidationRules): ValidatorInterface
  {
    $this->showValidationRules = $showValidationRules;

    return $this;
  }

  /**
   * Acrescenta os níveis numa matriz, se necessário, e coloca o valor
   * no último nível.
   *
   * @param array $keys
   *   A matriz com as chaves (nomes dos campos)
   * @param mixed $value
   *   O valor a ser atribuído
   * @param array $target
   *   A matriz destino
   *
   * @return array
   *   A matriz com o nível adicionado
   */
  protected function addArrayLevels(array $keys, $value, array $target)
  {
    if ($keys) {
      $key = array_shift($keys);
      if (isset($target[$key])) {
        $target[$key] = $this->addArrayLevels($keys, $value,
          $target[$key]
        );
      } else {
        $target[$key] = $this->addArrayLevels($keys, $value, []);
      }
    } else {
      $target = $value;
    }

    return $target;
  }

  /**
   * Define o valor de um parâmetro.
   *
   * @param string $key
   *   A chave (campo)
   * @param mixed $value
   *   O valor a ser atribuído
   * @param string $group (opcional)
   *   O grupo ao qual este valor faz parte
   * 
   * @return $this
   *   A instância do validador
   */
  public function setValue(string $key, $value,
    ?string $group = null): ValidatorInterface
  {
    // Caso a chave informe uma matriz, precisamos converter a chave em
    // uma matriz verdadeira antes de armazenar. Primeiramente,
    // decompomos o nome da chave
    $variableName = strtok($key, '[');
    preg_match_all("/\\[(.*?)\\]/", $key, $matches);
    if (count($matches[1]) > 0) {
      // A variável é uma matriz, então precisamos converter
      if (!empty($group)) {
        if (isset($this->values[$variableName])) {
          $this->values[$group][$variableName] = $this->addArrayLevels(
            $matches[1], $value, $this->values[$group][$variableName]
          );
        } else {
          $this->values[$group][$variableName] = $this->addArrayLevels(
            $matches[1], $value, []
          );
        }
      } else {
        if (isset($this->values[$variableName])) {
          $this->values[$variableName] = $this->addArrayLevels(
            $matches[1], $value, $this->values[$variableName]
          );
        } else {
          $this->values[$variableName] = $this->addArrayLevels(
            $matches[1], $value, []
          );
        }
      }
    } else {
      // Uma associação simples de variável
      if (!empty($group)) {
        $this->values[$group][$variableName] = $value;
      } else {
        $this->values[$variableName] = $value;
      }
    }

    return $this;
  }

  /**
   * Define valores de dados validados.
   *
   * @param array $values
   *   A matriz com os valores a serem atribuídos
   * @param string $group (opcional)
   *   O grupo ao qual estes valores pertencem
   * 
   * @return $this
   *   A instância do validador
   */
  public function setValues(array $values, ?string $group = null): ValidatorInterface
  {
    if (!empty($group)) {
      $this->values[$group] = $values;
    } else {
      $this->values = $values;
    }

    return $this;
  }

  /**
   * Limpa os valores de dados validados.
   *
   * @param string $group (opcional)
   *   O grupo ao qual estes valores pertencem
   * 
   * @return $this
   *   A instância do validador
   */
  public function clearValues(?string $group = null): ValidatorInterface
  {
    if (!empty($group)) {
      $this->values[$group] = [];
    } else {
      $this->values = [];
    }

    return $this;
  }

  /**
   * Obtém o valor de uma propriedade de um objeto.
   *
   * @param Object $object
   *   O objeto de qual queremos obter o valor
   * @param string $propertyName
   *   O nome da propriedade deste objeto para o qual desejamos obter o
   *   valor
   * @param mixed $default (opcional)
   *   O valor padrão caso a propriedade não tenha um valor atribuído
   *
   * @return mixed
   *   O valor da propriedade do objeto
   *
   * @throws InvalidArgumentException
   *   Em caso de algum argumento inválido
   */
  protected function getPropertyValue($object, string $propertyName,
    $default = null)
  {
    if (!is_object($object)) {
      throw new InvalidArgumentException('O primeiro argumento deve ser um objeto');
    }

    if (!property_exists($object, $propertyName)) {
      return $default;
    }

    try {
      $reflectionProperty = new ReflectionProperty($object,
        $propertyName
      );
      $reflectionProperty->setAccessible(true);

      return $reflectionProperty->getValue($object);
    }
    catch (ReflectionException $e) {
      return $default;
    }
  }
  
  /**
   * Obtém o valor de um parâmetro de solicitação do corpo ou da string
   * de consulta (nessa ordem).
   *
   * @param Request $request
   *   A requisição
   * @param string $key
   *   O nome da chave (campo) do qual se deseja obter o valor
   * @param mixed $default (opcional)
   *   O valor padrão caso o campo não tenha um valor atribuído
   *   (opcional)
   *
   * @return mixed
   *   O valor do campo da requisição
   */
  protected function getRequestParam(Request $request, string $key,
    $default = null)
  {
    $postParams = $request->getParsedBody();
    $getParams = $request->getQueryParams();
    $route = $request->getAttribute('route');

    $routeParams = [];
    if ($route instanceof RouteInterface) {
      $routeParams = $route->getArguments();
    }

    $result = $default;

    if (is_array($postParams) && isset($postParams[$key])) {
      $result = $postParams[$key];
    } elseif (is_object($postParams) && property_exists($postParams, $key)) {
      $result = $postParams->$key;
    } elseif (isset($getParams[$key])) {
      $result = $getParams[$key];
    } elseif (isset($routeParams[$key])) {
      $result = $routeParams[$key];
    } elseif (isset($_FILES[$key])) {
      if ($_FILES[$key]['error'] > UPLOAD_ERR_OK) {
        $result = null;
      } else {
        $result = new SplFileInfo($_FILES[$key]["name"]);
      }
    }

    return $result;
  }

  /**
   * Obtém o nome de todas as regras de um grupo de regras.
   *
   * @param AllOf $rules
   *   As regras
   *
   * @return array
   *   A matriz com os nomes das regras
   */
  protected function getRulesNames(AllOf $rules): array
  {
    $rulesNames = [];

    foreach ($rules->getRules() as $rule) {
      try {
        if ($rule instanceof AbstractWrapper) {
          $rulesNames = array_merge($rulesNames,
            $this->getRulesNames($rule->getValidatable())
          );
        } else {
          $rulesNames[] = lcfirst(
            (new ReflectionClass($rule))->getShortName()
          );
        }
      }
      catch(ReflectionException $e) {
      }
    }

    return $rulesNames;
  }

  /**
   * Lida com uma exceção de validação.
   *
   * @param NestedValidationException $error
   *   A exceção resultante do erro de validação
   * @param Configuration $config
   *   As configurações desta validação
   * @param array $messages (opcional)
   *   As mensagens de erro padrão para as regras de validação
   */
  protected function handleValidationException(
    NestedValidationException $error, Configuration $config,
    array $messages = []): void
  {
    if ($config->hasMessage()) {
      $this->setErrors([$config->getMessage()], $config->getKey(),
        $config->getGroup()
      );
    } else {
      $this->storeErrors($error, $config, $messages);
    }
  }

  /**
   * Mescla mensagens padrão, mensagens globais e mensagens individuais.
   *
   * @param array $errors
   *   A matriz com as mensagens de erro a serem mescladas
   *
   * @return array
   *   A matriz mesclada
   */
  protected function mergeMessages(array $errors): array
  {
    $errors = array_filter(call_user_func_array('array_merge', $errors));

    return $this->showValidationRules
      ? $errors
      : array_values($errors)
    ;
  }

  /**
   * Define mensagens de erro após a validação.
   *
   * @param NestedValidationException $error
   *   A exceção resultante do erro de validação
   * @param Configuration $config
   *   As configurações desta validação
   * @param array $messages (opcional)
   *   As mensagens de erro padrão para as regras de validação
   */
  protected function storeErrors(NestedValidationException $error,
    Configuration $config, array $messages = []): void
  {
    $errors = [
      $error->findMessages(
        $this->getRulesNames($config->getValidationRules())
      )
    ];

    // Se as mensagens padrão estiverem definidas
    if (!empty($this->defaultMessages)) {
      $errors[] = $error->findMessages($this->defaultMessages);
    }

    // Se as mensagens individuais forem definidas
    if ($config->hasMessages()) {
      $errors[] = $error->findMessages($config->getMessages());
    }

    // Se as mensagens globais estiverem definidas
    if (!empty($messages)) {
      $errors[] = $error->findMessages($messages);
    }

    $this->setErrors($this->mergeMessages($errors), $config->getKey(),
      $config->getGroup()
    );
  }

  /**
   * Executa a validação de um valor e lida com os erros.
   *
   * @param mixed $input
   *   O campo de entrada
   * @param Configuration $config
   *   As configurações desta validação
   * @param array $messages (opcional)
   *   As mensagens de erro padrão para as regras de validação
   */
  protected function validateInput($input, Configuration $config,
    array $messages = []): void
  {
    try {
      $config->getValidationRules()->assert($input);
    }
    catch(NestedValidationException $error) {
      $this->handleValidationException($error, $config, $messages);
    }

    $this->setValue($config->getKey(), $input, $config->getGroup());
  }
}