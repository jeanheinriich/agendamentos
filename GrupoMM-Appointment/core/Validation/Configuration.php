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
 * Classe responsável pelas configurações do sistema de validação dos
 * campos de um formulário.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 * @author Alexis Wurth <awurth.dev@gmail.com>
 */

namespace Core\Validation;

use InvalidArgumentException;
use Respect\Validation\Rules\AllOf;

class Configuration
{
  /**
   * O valor padrão para parâmetros de solicitação inexistentes,
   * propriedades de objeto ou chaves de matriz.
   * 
   * @var mixed
   */
  protected $default;
  
  /**
   * O grupo a ser usado para armazenamento de erros e valores.
   * 
   * @var string
   */
  protected $group;
  
  /**
   * A chave a ser usada para armazenamento de erros e valores.
   * 
   * @var string
   */
  protected $key;
  
  /**
   * A mensagem de erro.
   * 
   * @var string
   */
  protected $message;
  
  /**
   * As mensagens de erro individuais por regra.
   * 
   * @var array
   */
  protected $messages = [
    'alpha'        => '{{name}} deve conter apenas letras',
    'alnum'        => '{{name}} deve conter apenas letras e dígitos',
    'arrayType'    => '{{name}} deve ser uma matriz',
    'between'      => '{{name}} deve estar entre {{minValue}} e {{maxValue}}',
    'date'         => '{{name}} deve ser uma data válida. Veja o exemplo: {{format}}',
    'domain'       => '{{name}} deve ser um domínio válido',
    'email'        => '{{name}} deve ser um e-mail válido',
    'extension'    => '{{name}} deve possuir extensão {{extension}}',
    'floatVal'     => '{{name}} precisa ser um valor válido',
    'imei'         => '{{name}} deve ser um IMEI válido',
    'in'           => '{{name}} deve ser uma das opções {{haystack}}',
    'intVal'       => '{{name}} deve ser um número inteiro',
    'length'       => '{{name}} deve possuir entre {{minValue}} e {{maxValue}} caracteres',
    'max'          => '{{name}} deve ser menor ou igual a {{interval}}',
    'min'          => '{{name}} deve ser maior ou igual a {{interval}}',
    'notBlank'     => '{{name}} não pode estar em branco',
    'notEmpty'     => '{{name}} precisa ser preenchido(a)',
    'noWhitespace' => '{{name}} não pode conter espaços',
    'oneOf'        => '{{name}} precisa ser válido',
    'phone'        => '{{name}} deve ser um número de telefone válido',
    'postalCode'   => '{{name}} deve ser um CEP válido'
  ];
  
  /**
   * As regras de validação.
   * 
   * @var AllOf
   */
  protected $rules;
  
  /**
   * O construtor de nossa classe.
   * 
   * @param AllOf|array $options
   *   As opções de configuração        
   * @param string $key (opcional)
   *   O nome da chave (campo)
   * @param string $group (opcional)
   *   O grupo ao qual pertence
   * @param mixed $default (opcional)
   *   O valor padrão a ser atribuído, caso não tenha um valor
   */
  public function __construct($options, ?string $key = null,
    ?string $group = null, $default = null)
  {
    $this->key = $key;
    $this->group = $group;
    $this->default = $default;
    
    if ($options instanceof AllOf) {
      // As opções passadas estão implementadas em uma regra de
      // validação do Respect/Validation
      $this->rules = $options;
    } else {
      // As opções passadas estão implementadas na forma de uma matriz
      // de opções
      $this->setOptions($options);
    }
    
    $this->validateOptions();
  }
  
  /**
   * Obtém o valor padrão para parâmetros de solicitação inexistentes,
   * propriedades de objeto ou chaves de matriz.
   * 
   * @return mixed
   */
  public function getDefault()
  {
    return $this->default;
  }
  
  /**
   * Define o valor padrão para parâmetros de solicitação inexistentes,
   * propriedades de objeto ou chaves de matriz.
   * 
   * @param mixed $default
   *   O valor padrão
   */
  public function setDefault($default)
  {
    $this->default = $default;
  }
  
  /**
   * Obtém o grupo a ser usado para armazenamento de erros e valores.
   * 
   * @return string|null
   *   O nome do grupo
   */
  public function getGroup(): ?string
  {
    return $this->group;
  }
  
  /**
   * Define o grupo a ser usado para armazenamento de erros e valores.
   * 
   * @param string $group
   *   O nome do grupo
   */
  public function setGroup(string $group)
  {
    $this->group = $group;
  }
  
  /**
   * Obtém a chave a ser usada para armazenamento de erros e valores.
   * 
   * @return string|null
   *   O nome da chave (campo)
   */
  public function getKey(): ?string
  {
    return $this->key;
  }
  
  /**
   * Define a chave a ser usada para armazenamento de erros e valores.
   * 
   * @param string $key
   *   O nome da chave (campo)
   */
  public function setKey(string $key)
  {
    $this->key = $key;
  }
  
  /**
   * Obtém a mensagem de erro.
   * 
   * @return string
   *   A mensagem de erro
   */
  public function getMessage(): string
  {
    return $this->message;
  }
  
  /**
   * Define a mensagem de erro.
   * 
   * @param string $message
   *   A mensagem de erro
   */
  public function setMessage(string $message)
  {
    $this->message = $message;
  }
  
  /**
   * Obtém as mensagens de erro individuais por regra.
   * 
   * @return array
   *   As mensagens de erro
   */
  public function getMessages(): array
  {
    return $this->messages;
  }
  
  /**
   * Define as mensagens de erro individuais por regra.
   * 
   * @param array $messages
   *   As mensagens de erro
   */
  public function setMessages(array $messages)
  {
    $this->messages = $messages;
  }
  
  /**
   * Obtém as regras de validação no padrão do Respect/Validation.
   * 
   * @return AllOf
   *   As regras de validação
   */
  public function getValidationRules(): AllOf
  {
    return $this->rules;
  }
  
  /**
   * Define as regras de validação.
   * 
   * @param AllOf $rules
   *   As regras de validação no padrão do Respect/Validation
   */
  public function setValidationRules(AllOf $rules)
  {
    $this->rules = $rules;
  }
  
  /**
   * Obtém se um grupo está definido
   * 
   * @return boolean
   */
  public function hasGroup(): bool
  {
    return !empty($this->group);
  }
  
  /**
   * Ontém se uma chave foi definida.
   * 
   * @return boolean
   */
  public function hasKey(): bool
  {
    return !empty($this->key);
  }
  
  /**
   * Informa se uma única mensagem foi definida.
   * 
   * @return boolean
   */
  public function hasMessage(): bool
  {
    return !empty($this->message);
  }
  
  /**
   * Informa se as mensagens de regras individuais foram definidas.
   * 
   * @return boolean
   */
  public function hasMessages(): bool
  {
    return !empty($this->messages);
  }
  
  /**
   * Define opções de configuração à partir de uma matriz.
   * 
   * @param array $options
   *   A matriz com as opções de configuração
   */
  public function setOptions(array $options)
  {
    $availableOptions = [
      'default',
      'group',
      'key',
      'message',
      'messages',
      'name',
      'rules'
    ];

    foreach ($availableOptions as $option) {
      if (isset($options[$option])) {
        $this->$option = $options[$option];
      }
    }
  }
  
  /**
   * Verifica se todas as opções obrigatórias estão definidas e válidas.
   *
   * @throws InvalidArgumentException
   */
  public function validateOptions(): void
  {
    if (!$this->rules instanceof AllOf) {
      throw new InvalidArgumentException("As regras de validação estão "
        . "ausentes ou são inválidas"
      );
    }
    
    if (!$this->hasKey()) {
      throw new InvalidArgumentException("Uma chave deve ser definida");
    }

    if ($this->hasMessage() && !is_string($this->message)) {
      throw new InvalidArgumentException(
        sprintf("Mensagem personalizada esperada deveria ser do tipo "
          . "string, porém foi fornecido %s", gettype($this->message))
      );
    }
  }
}
