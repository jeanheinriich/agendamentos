<?php
/*
 * This file is part of tests of Extension Library.
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
 * Conjunto de testes do gerador de formulários dinâmico.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Tests\Core\Forms;

use Core\Forms\Form;
use Core\Forms\Elements\DateInput;
use Core\Forms\Elements\EmailInput;
use Core\Forms\Elements\HiddenInput;
use Core\Forms\Elements\TextInput;
use Core\Forms\Elements\PasswordInput;
use Core\Forms\Elements\ResetButton;
use Core\Forms\Elements\SubmitButton;
use Core\Forms\Frameworks\Raw;
use Core\Forms\Frameworks\Fomantic;
use PHPUnit\Framework\TestCase;

class FormTest
  extends TestCase
{
  /**
   * Testa a criação de um formulário em branco.
   */
  public function testEmptyForm()
  {
    $raw = new Raw();
    $form = new Form($raw);
    $expected = ''
      . '<form method="POST" action="">'
      . '</form>'
    ;

    $this->assertEquals($form->render(), $expected);
  }

  /**
   * Testa a criação de um formulário em branco usando o método PUT.
   */
  public function testEmptyPUTForm()
  {
    $raw = new Raw();
    $form = new Form($raw);
    $form->put();

    $expected = ''
      . '<form method="POST" action="">'
      .   '<input type="hidden" name="_method" value="PUT" />'
      . '</form>'
    ;

    $this->assertEquals($form->render(), $expected);
  }

  /**
   * Testa a criação de um formulário com conteúdo
   */
  public function testFormWithContent()
  {
    $raw = new Raw();
    $form = new Form($raw);
    $form->put();
    $form->addElement(new DateInput('data'));
    $form->addElement(new EmailInput('email'));
    $id = new HiddenInput('id');
    $id->value(0);
    $form->addElement($id);
    $form->addElement(new TextInput('name'));
    $form->addElement(new PasswordInput('password'));
    $form->addElement(new SubmitButton('Enviar'));

    $expected = ''
      . '<form method="POST" action="">'
      .   '<input type="hidden" name="_method" value="PUT" />'
      .   '<input type="date" name="data" value="" />'
      .   '<input type="email" name="email" value="" />'
      .   '<input type="hidden" name="id" value="0" />'
      .   '<input type="text" name="name" value="" />'
      .   '<input type="password" name="password" value="" />'
      .   '<input type="submit" value="Enviar" />'
      . '</form>'
    ;

    $this->assertEquals($form->render(), $expected);
  }

  /**
   * Testa a criação de um formulário com conteúdo baseado em um
   * framwork.
   */
  public function testFormWithContentBasedOnFramework()
  {
    $fomantic = new Fomantic();
    $form = new Form($fomantic);
    $form->put();
    $data = new DateInput('data');
    $data->label('Data de admissão:');
    $form->addElement($data);
    $email = new EmailInput('email');
    $email->label('Email de contato:');
    $form->addElement($email);
    $id = new HiddenInput('id');
    $id->value(0);
    $form->addElement($id);
    $name = new TextInput('name');
    $name->label('Nome:');
    $form->addElement($name);
    $pwd = new PasswordInput('password');
    $pwd->label('Senha:');
    $form->addElement($pwd);
    $form->addElement(new ResetButton('Limpar'));
    $form->addElement(new SubmitButton('Enviar'));

    $expected = ''
      . '<form class="ui medium form" method="POST" action="">'
      .   '<input type="hidden" name="_method" value="PUT" />'
      .   '<div class="field">'
      .     '<label for="data">Data de admissão:</label>'
      .     '<input type="date" name="data" value="" />'
      .   '</div>'
      .   '<div class="field">'
      .     '<label for="email">Email de contato:</label>'
      .     '<input type="email" name="email" value="" />'
      .   '</div>'
      .   '<input type="hidden" name="id" value="0" />'
      .   '<div class="field">'
      .     '<label for="name">Nome:</label>'
      .     '<input type="text" name="name" value="" />'
      .   '</div>'
      .   '<div class="field">'
      .     '<label for="password">Senha:</label>'
      .     '<input type="password" name="password" value="" />'
      .   '</div>'
      .   '<input type="reset" value="Limpar" class="ui button" />'
      .   '<input type="submit" value="Enviar" class="primary ui button" />'
      . '</form>'
    ;

    $this->assertEquals($form->render(), $expected);
  }
}