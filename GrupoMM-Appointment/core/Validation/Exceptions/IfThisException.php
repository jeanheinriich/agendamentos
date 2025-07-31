<?php
/*
 * Este arquivo é parte do Sistema de ERP do Grupo M&M
 *
 * (c) Grupo M&M
 *
 * Para obter informações completas sobre direitos autorais e licenças,
 * consulte o arquivo LICENSE que foi distribuído com este código-fonte.
 * ---------------------------------------------------------------------
 * Descrição:
 *
 * A exceção em caso de falha na conferência de uma condição.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

class IfThisException
  extends ValidationException
{
  public static $defaultTemplates = [
    self::MODE_DEFAULT => [
      self::STANDARD => 'A validação de dados falhou para {{name}}',
    ],
    self::MODE_NEGATIVE => [
      self::STANDARD => 'A validação de dados falhou para {{name}}',
    ],
  ];
}
