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
 * O controlador do gerenciamento dos contratantes do sistema. Um
 * contratante pode ser uma pessoa física e/ou jurídica.
 */

/**
 * @todo Acrescentar a coordenada geográfica e as informações de conta
 * na edição do contratante.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Cadastre;

use App\Models\ActionPerProfile;
use App\Models\BillingType;
use App\Models\ContractType;
use App\Models\ContractTypeCharge;
use App\Models\DocumentType;
use App\Models\Entity as Contractor;
use App\Models\EntityType;
use App\Models\Gender;
use App\Models\InstallmentType;
use App\Models\Mailing;
use App\Models\MailingAddress;
use App\Models\MailingProfile;
use App\Models\MaritalStatus;
use App\Models\Phone;
use App\Models\PhoneType;
use App\Models\Subsidiary;
use App\Providers\StateRegistration;
use Core\Controllers\Controller;
use Core\Controllers\HandleFileTrait;
use Core\Controllers\QueryTrait;
use Core\Exceptions\UploadFileException;
use Core\Helpers\FormatterTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Mpdf\Mpdf;
use Respect\Validation\Validator as V;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class ContractorsController
extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Os métodos para manipular o recebimento de arquivos.
   */
  use HandleFileTrait;

  /**
   * As funções de formatação especiais
   */
  use FormatterTrait;

  /**
   * A logomarca vazia, usada para preencher o espaço.
   *
   * @var string
   */
  protected $emptyLogo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgA'
    . 'AAMEAAADACAYAAAC9Hgc5AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAABsQAAAbE'
    . 'BYZgoDgAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAACAASUR'
    . 'BVHic7Z15fFTV+f8/z7mThbCKQAIGCJmwKNalIQmI1iiQmQlCtZraWqGQhGjdl9p'
    . 'Wf61N1bYu/VqX1gWSQLHa1lg3JJME1LTKlkBdcc2EsCeAyp5t7nl+f8wEZiYzmTs'
    . 'z984k6vv1csmdc889ydzn3nOe8zyfh/At+pCZGTfSNDzNJIWZCRlMnCyYUpgxigg'
    . 'jAIxkkALwUAACgAJgiEcPX3n8/0EA7WC0MtFuIm4h5t0S1CqYdxGL1qSD1NzYaO+'
    . 'I4m/4tYViPYD+R6kYmbPOrLByDrE8lwlnA3Q6gHFw3djRQgXQDOBjgD9m0LuKpC1'
    . '7Nk//HCiVURxHv+dbIwhCWm5uYsex+GwGXUjABUyYAWBQrMfVC4cBbAHjLQbeQgd'
    . 'vaH2/9lisB9WX+dYI/JA8Le9MEpQPcD5A0wEkxHpMEdAFYBMDdkWges/GmncAcKw'
    . 'H1Zf41ggAoKBASd5+6EICXQEgH8D4WA/JQFrA+Ldg+Y89m9esx7cG8c02gpQsy1Q'
    . 'QFoCxAIQxsR5PDNhJxC8RROWeTdVvx3owseIbZwSnZc86VWXTIhCWAJgc6/H0IbY'
    . 'y8Jyiqn/fs2XtjlgPJpp8Y4xgzLQ5M1WFriWmKwAkxno8fRgnAZUk8Kc9G2v+F+v'
    . 'BRIOvuRGUiuSs9XOJ6E4AM2I9mv4GE62DlA+0NtS+hq/x2uFraQRTpxbEHxh06Go'
    . 'C3QHGlFiP52vAR0z8fyOODv371q2VnbEejN58zYygVKRkb7wc4D8CMMd6NF9DdhD'
    . 'x7/eOG1qOyko11oPRi6+NESRnWWaD8BAB58R6LN8APgKotKW++gV8DaZJ/d4IRmf'
    . 'lfY+JHgSQE+uxfNMgYD2AX+2tr3kr1mOJhH5rBCOz8lMUUh8EcDX68e/xNeE1ZvX'
    . 'm1oa1TbEeSDhEM+BLJ0pFSnbCAkH8CoDp+NYA+gKTiKhk0GkT41IGTNzw5ZeN/Wq'
    . '90K9uoNHZc6YxxFIA58Z6LN8SkA+ZxJLWTfaNsR6IVvqHEeTmmlKOJ9wO4B4A8bE'
    . 'ezrcERRLxXwZ+ofyiP+Q89HkjGD199uksTSsBnhbrsXxLyLwvgYX76mvei/VAeqM'
    . 'vrwkoOcdyM5iex9c7qvPrTDIBiwedlnHw6G5HfawHE4g++SYYMXP+YFNnx3IQLo/'
    . '1WL5FHwh4JS7etHjH26u/Ct46uvQ5I0jJsZ0Bli/i2wjPryPbFJY/2N2w5t1YD8S'
    . 'TPmUEKdnWAoAr0LfTF0PhCICdDNpFxHvA2AeA2RWpecS3MQODCTABIDBGunMcUgG'
    . 'MhXdSfn/mODMXtjbU/ivWA+mmrxgBjc6x/oGZf4m+M6ZQ2A5QA4g/ZvBHzPSxk8S'
    . '2LzfZD+t1gREz5w8Wzs4JJOXpJGgqmE53OwvS9LpGFGEiemDvpun/ry+IAsT8hps'
    . '6tSD+wMAjFQT+SazHohFm4D1iWitIruti08b9DVUtsRrMqJxZyQTTdGKcB8ZsEM6'
    . 'BS9Kl70N4MXFAx0+a6+raYzuMGHJK5uyhCYrpRYAvjuU4NHAUhNXMeBUmXtu6vnZ'
    . 'frAcUiJRzbSM5Xp0lmOYxcAn6+DSKidbFCZ6/a0PNl7EaQ8yMIHXGJac51a4qAGf'
    . 'Fagy9w20M8SKIKwcM6KiJ9dMqHDIybAlHh8vZAH4I4HIAA2M8JP8QPhFO1RKrtM6'
    . 'YGEFydt4EAr2Jvun/30CM5e1Sff6rLWsPxXowejFi5vzBirO9gJgWAzg/1uPxw3a'
    . 'piln7ttgd0b5w1I1gTObscVJR6gBMiPa1e6ETwCtM4uH+FPMSLqOyLWcT03VEfDW'
    . 'ApFiPx4MWljyndXPth9G8aFSNoA++AY4C+KvKyiOxXNzGiuTz8kbBSTcRcCP6ztp'
    . 'hHxgXtzTUbI3WBaNmBCnTrWmQXIe+YQBHGHjMBOefd9e//kWsBxNrxp0/95TOTvV'
    . 'mBt9MwLBYjwfAXlXB9/ZvqGmMxsWiYgTuBJj1iP0UyAnipehUSlvese+P8Vj6HKd'
    . 'lzzpVknI3M/0MQFyMh7NDqOoF0VgsG24E7jigOhC+a/S1grBaqPzzPVtqP4nxOPo'
    . '8I3PmTDSxeIiB78dyHAR8zl1iptEPLGONIDMzLkUZsQqAxdDr9AZjDwh3ttTXrIz'
    . 'WJXNzS017Bu4YpwLjmUWaAMYzYwKA8QCfCmAoCBsdAw//2Ee1gcy2wqcAZIBpNwO'
    . '7iLAHhEZVqp80V6/Yjigmtidn511CoMcR213pBm7ni4xU1jbSCCgl27oc4J8aeI3'
    . 'ekAx6VCa1372/ru6okRdKn1cyTqjO6czIBigHwHfRu9dlgymOLZ++WuEVP5RhK7y'
    . 'LQb/v5byjDGwl4k0M2hTnpA2f1pZt0+N3CMSYzHlJUnT9DsS3Ikah9wx+qbX+vCu'
    . 'MCrEwzAhSsiy/A+Fuo/oPwjZiXrS3ofa/RnQ+Zl5JUqLqtAF0KTFmA0gJ4fTN3KX'
    . 'Mblq71GsPwmwt+hEIzyH076QZ4Fom1KLTtNa3X70YM23OTCnE3xAjPScG/bm1vvo'
    . '2I/o2xAiScyzfJ8ZLRvUfhApnXMItB9a92iNKMxJSLUXDEwRfAtBlAFkAHhBqHwy'
    . '82ykxa1dNuVeIQLq18HwiWoPINVJVABuZUClYPtdoX67rXHpkbu4g0ZbwMDGW6Nm'
    . 'vVoj56r0Ntc/q3q/eHZ6WnT9JhVoPYKjeffcOtzHoptb6mjJDui8tFeMaWocCwIC'
    . 'urmFOYgUmGkpSHcSgJGIaBIGhzDQU4LEgjIVrHXA6gEQG/isT1O83v7zioGe3aXk'
    . 'lUxRFfRvAqTqPuAPAS8woa5o+9k2U6jeVGJ2V9xMmegrRD3lvFwIz9RYK1tUIRsy'
    . 'cP9jU1bERwBl69quBj0CioGWT/aMoXzc4BQVKetvA9FP2xjdv2bK0y/Mjc97isVD'
    . 'EOrjyBYykiQmPDTieUL617gld1keu3G/lBUT9u6am+Hhlmp4ZanoaAaVkWSqjnxL'
    . 'JVZ2k/Fin2H1CFL0vZlvhQoBuBJCJ6EwdvyLip8H8sB5TpeE5tiHxkP8Ew6bH4DR'
    . 'DsLdsmnGJXgtl3f7wyVnWm4j4Ub360wIxHt2bNuT2SMVh02eXDKU450KVZGVz1Yq'
    . 'oh0+kzysZB1W9jJguA/h8GO+FOULAn2WX8nDEC+mCAiW5+fCfiHCLTmPTBvEdLZt'
    . 'q/6RLV3p04i57tBnRK34hmenW1obqxyLsh8zWooUs8FtIcWVT9bKGQA0nWAsnC4h'
    . '7INj1N2NIgA8x01EiPsagYyDaJyS2OROd7/rO/bUy6ZKFp0nV9FMG/RTApPB+Lc1'
    . '8wcS/bsoetzTSNUNydt6NBHoE0Uvo6VBYTtcjX1kfI8i2PAHgZ3r0pYEuYl4cqZd'
    . 'ggrX4LEH8VwAziOjyxqqyV3prn5lZEndwlLodwOheO2Y8k9iecJ2/uXfapYuGhWA'
    . 'clG4tnEmgEhB+BOPCGJyS+cxt1RWfRtpRcrblKgJWIFohF4zlLQ01hZF2o9d0iJK'
    . 'z8n5IRA/B2EVeO4gub9lUXRVuB2flLRh43JRwBzPfCSCeiG5orCr7q2ebMfNKkgY'
    . '41esc9nKv122Greh3jIB7H3uY+Kamqop/+36Qm1tq2jVg530M/ALAZ8y8lojWqgl'
    . 'qnRajSJ9XMo5U5+3MVET6J8b83mEv/7VenaXkWPPB/G8YOCtg8G7B4ld7G6qfhQ5'
    . 'rOF0XY6kzCgY41cM3Afg19HefdTL48tb62tfC7SDduiQLQv6LuDuQj/7gsJf9P88'
    . '2LgNwrgLoIoI8s9G+/ITHadIlC09T1TgHvOsadwH8WGJbYqm/p/+E/KLxgvEsgJl'
    . '+hqQC2AjgnwT5r2CL1UnzSkZIp3ojA7cCGKzx1+6NDwlJ0xrtj5+QSjwrb8HA4yL'
    . 'urMbqig3hdjomZ06eZPEK9DeETiJ+qsuU+Gs994EM8Ui4Uyf/CP1k07tY0BWtG6t'
    . 'fDfN8Ss8vuokYD+Kkluk/Hfbyq+DxJMnNLTXtHLDzVcDt7WCUOarLvTaGzPlFT4F'
    . 'xjatXWksq3dJYs8xv7Ht6fuHlxLQMwCkaxqiC6E2wfCaxLfHF3lyZU2YtPLUrwXQ'
    . '3mK6DS6IlHJxMPKOpqmKz58EMW2EFgxYy48FT9iu/9XXramV0lsXKhJehXyF0w+T'
    . 'fDXXLjc7K+54kejTC6jESoB+11FdXhnPypHklI1Sn+je4inR38w4haYbnExAA0q2'
    . 'FfyGi6z0OdTilMmF7zdK93QfSrIvSFBLlksT926rK1vi75tTc6wZ1DGh/jEGLwxk'
    . 'zgKNMWGki/r/PVlcE/NInWAsnC0H3glEQ6gUIuKfRXv5bz2Pp+UsuIZarPA5tZpN'
    . 'yedOqpWGFM6fk5F0Bpn8hssXyh8x0S2tD9esR9NErUfBNl4qU7A1XA3gIwKhQzyb'
    . 'CrXs31TwSzpUzrIUzmOh5uASsujnKJDKbqpZ95tnWbCv6uXuMviPoMWXq9Zq2onk'
    . 'MPAZ9Ii8lgaskK/f05rky2wptAD0JzQlL9L/EQYdmbK08WYTPFRaCD9Fz4d8iCFd'
    . '+XlUeVhzW6GzLtQw8GcapXzHhd60DOv6KujpnONfWStRie9LOyR3WEZdQyoTroNl'
    . '7wA+11Nf+IpzrmW1FlwL0XI8YH8YiR3X53zwPpVuLf0DElfD/xNrjGHR4XLC9iAn'
    . 'WwslE9BgBeeGMNwgMcK2U4vfbasr8lkYaM68kKcmp3s3Az9H7PsNRyTzN1xuUYSv'
    . '+OwfWfnICdL3DXrY0nMGnZFvuB/BLjc2dYDytkPO30cr6i3qAmzu26GEAc3ttyHi'
    . '1pWHGZeHsCpqtxTeB+M/wvamZnndUl13pecjlKsVGH2NRCXgDhOfbVbzoG/Dmicv'
    . 'bFPdrZroNUaidwOAXJMs7mqtXNPv7PMNaOAMCFczkt3QtEV/ZWFXxvNc5+cXfZ+a'
    . 'Xg12agHt9p1AaoZRsyz/hkn7pjTcl0S37NlW/H8Y1wiZmukPJWZbZRHgU/mNPPu1'
    . 'Q1ZyQJU9KS0X6xp0PEcFfyG0Tof3cRvuzJ8Ir3K7QBvcYVDD+y4KfF8z/DhpWUFo'
    . 'qzJt2XA3Q/Qi2d6A71EbgxxLaEu7zt4B2rUk6nmbgKs/jTHiiqarcc82D8ZaS0Sb'
    . 'hfBcgTVNVZjzcVF1+e6gjHpmbO0g5Fr8eRN/x8/FOAL+OZuKTJzGrT3Bsj6MpZcC'
    . 'k8s4BfASu2mPdXoRDQtKs/Vtqd4fYJaUnmf9CwE1+PpOCcFmj/W9e64BR5nMXA+w'
    . 'kiPu7RPzPmu1Ln/7q83c2f9n47vFgF5uKMwY649Q1AEaEOE49iANwvjNOXXBqxnd'
    . '3fdn4jlfg4P7mhs4vG995cfjEc7cBwupu/37noMM/PPzRRyfn16WlYsSegy8Bfm9'
    . 'MvxBhxqkTM4d92fi/mlAGfLy5uTNp9KRaEvwTnEw4OkbgexKTOq/ate71LaH0pyc'
    . 'x1yIFgDGZ80awqeM3zFQiiefv21Tr1+vSC5SeX/QXYlzn57NmBu5tspdXRDLGzMy'
    . 'SOF93YXp+0cPEuDWSfvWAwS84RULJjtVP9oisNM8tzoTkv0HiUkdNuZd6g9lWdB8'
    . 'AzYt+Twj0SKO9LOTffXS25QIG1wD0GpmUO/aur9oezvX1pE8YQTcp59pGhpVUXVC'
    . 'gmI8POZ2cMl6YyLUDK0yHEzva2t6vfSbs3NS0SxcNU9rFpUz0AwIuNDn59E/XVOw'
    . '58bl1UZpCSiP6RsWfHVLS1X4XzqWlwjc2yGwrvhjgWkQw9nANIezv2SD6lBH0BSb'
    . 'ML04WnWwFcBkIVnht9vR0l5qtRS+DYqvK4IFKwJ+G7lN+09sml2vn2/Q/reuA3uF'
    . '7HfaKWKXR6sI33ggmzykc41SQA6LpAC6GK0k+0ObOgY5Bh8ftqqxs6z7gfqIatpE'
    . 'TFoS3nKpypecmXzdTCwri244O+S8BObpdj3mxo7pihW79RZlvmhG4IjOJphMwnV0'
    . '3QmrQs7y7uMbXX262Fb8PsObFZXSgpxz2Mn+RvWS2FT4EUMgenl7oIqa8xuqyOh3'
    . '7jBr90ggm5BeNV5h+z5DHmMgpuGfpI0nc4BvRabYV3ghQpDkIHzns5WfCI+YoI7+'
    . 'omBnLIuxXR+ilYfvElb1Nicy2oofg2ljTCd5HEJmN9rJd+vUZHfqdEeTmlpp2Ju7'
    . '4D4jO67WhEDMdq5et7/5x3NyfnRInOz+HDgntxMLaWL3shIswtaBgQMLRITsQG3e'
    . 'pN0zPD9svrtYQ+EYZtqK/sr55IBsTBx2+0DMcoz/QP8r6eLBzwI67gxoA401PAwC'
    . 'AOO66EzopOjCpXh6RXZWVbX3hTUDAc2PbU3/Sw5VrLZplthXd4NOcG3PG3gCQnht'
    . 'U09uPDulNPKxP0q+MwDx3yXkA3RWsHYO8kmEmzSsZAWYdn3hkSc9f5LUGiFP5LwD'
    . 'CCjvWB17amDN2QV1dqVew2cT8ou+B8AqAx8zWYu+NxNJSOWyfKAbRWh0HcpvLWdB'
    . '/CGgEo8/LHz8qx9pnSilNzb1uEKR8BsH92h83VZfZPQ84u9Q7oXOSD7HwerJ+uqZ'
    . 'iD5he0vMaWmHCEw57xbW+ewHplsLZkqnanY1GIH4kPb/YK7x7y5alXWq8swDAxzo'
    . 'NRwD8t/TZJVHWnQpMSo7tjDGZs8cF+jzwm0CV1wrm/6VkW54ckzkv5nPdtqSOewC'
    . 'kB2/Jf4bHojXt0kXDiFCi/4jEgknzSrz/LgpFVW0DAMC4zx0P5JVmOCG/eA4J8ap'
    . 'PYCAR8zKztfAyz7bNL684SFDmEaBX8bxUilcf0Kmv8AcxwzI8Jdv6GFi+JxXhOx0'
    . '8QSAjIGa+Cq6n7rWq0vl5SrbllxkZNr2yhEIiPb9wGrHfmCBf9ncMOvJ3zwNKh1I'
    . 'CQ5TSeIB0Or2My7F62Xoi1Ot/Lb84GXSdo7r8N74fZNgK8wTjlQBSkQpIPJtuLfS'
    . 'qW9ZoX+qQTAugl+4SoyRm06LcXNPonLwSp4pPAL4RgImBq1BQ4HcW4dcIRmflXQD'
    . 'gxOvDXb3k/mPD5QeuqvNRpLRUENNT0LC9z4xnPDeycnNLTQCu7+WUiGDQ9VMLCnz'
    . 'Dp/9i1PU8OALw/CZ7WY9kFbO18DIGBTIANzyAiF4y5y32EkVoqi6rIuBBncZIAD/'
    . 'm/g6iRnJ23sUpxxO2MNPTAEaeHAydlrLzyAX+zvFrBCxwpd/jwESAnx+dY1mTPC3'
    . 'vTF1GHYT0TTsXwaXQFhRS6DnPn3ck7bbCw5gNYEzHkaFeMfIJAw//C4BhAl5M2Ea'
    . 'Q0x32CrvvZ2Zb4UK4Mum0JLiPIJN4wdeIU9vG/hrAOp2GO3VH0o5infrqlZEzLBk'
    . 'p2ZbnCfQ6ApQFJuk/aainERQUKGDqVUqRGbNJ0DvJWdanjVwvTM29bhAB92ls3uh'
    . 'YXeYVjkuQ4eb4aoZJek3TtlZWdjLzU4ZcjPAWOeOmeypgdJORX3QnQCsQQuI9M7L'
    . 'bjw32Simtqyt1mlRaAFdRw4ghpnuMXCQPz7ENScnOe1BRsRXoPddaAlf4m9L3MII'
    . 'xOw7OApCs4fomIi6RSuenyTmWm5Gbq/trr31A563QmLBCRF5iXBm2xSPBmKf3mPx'
    . 'cOWti/mKvOByS8U8C0LP4N4P4sWGtyixH7VP7PD+YWlAQn2ErrGDGHxDO5ifTTem'
    . '2Qq8n5Ke1ZduINadDBmMkxal36NSXB6UiOSevMJ7lZwDdAQ1ZfQQMO3YK98ho7GE'
    . 'EUoofhTia4cR4JKUt4YOUaXm6CbOmzy4ZSmDt+paseqUMMosfIEpKaFIKr3WH60a'
    . 'l5wO1Dw3eRyxsjqqKm303wSbNKxnRfmzI2ghULQAABHoyPX+Jl+RjY3X5kwC/EUm'
    . '/Hlw3Zl6JbvWSU7KsWSnZG94mpnJoe2CfgAX3eDB6G0FBgQLCpWGNjDEFgqqSsy0'
    . 'vjcq0RV7NxOS8hYHhGlt/7DtFoGiqYxOunDC/ONn7EIelkOHdL61VSZ7tGaLRTYZ'
    . 'l8XedqloPht/FXogMJqkuR2mp5/3ATMrP4KpzECmnJHaqF0XayYiZc8YkZ1mfBvF'
    . 'GADPC6oRhhc8b08sIUrYdzoQ2oaiAEHCpUOTHo3PyHj0lc3ZYc8Ex80qSiOhG7Rd'
    . 'lr0y0VEvRcAZyw7l2mMSLLngtABvt5e8g/AXmQSbc4shOtfhTyTbbCheyEG+dVNL'
    . 'TAaLzzBt3egmNuWRp6P906Pw/TUMOV4d79tSpBfHJOZabTV3iYyIuQWSRDiljptm'
    . '8dLC8OmOFZkXQuSdxzHRTgqJ8kpyTV4gQ56pJqlyMEOJ8SMIrnj9B0BxEvQ4vX5e'
    . 'ZWeJ1TWKEvHlG4NcI9J2mqvJHfXeAz8pbMDDDVlgB0N/Qe2HA8CDcn5a/yKv+Wpt'
    . 'J/B5AcwS9tpic8qpw5fOTs/LmfTHwyMfEeATAkAjGcQJVSK9pu5cRELNeRtBNCjG'
    . 'VJ2dZtT/VS0sFM98cwjWc0mn6j+cBAhuh/ROMMYdGSa8Ms8bBh1+E9htoLxNf0Wi'
    . 'vmOcvHDnduiTrmBK/JdL5fxCGKRAPex7Ys2rpcabw8pABdAnClZ4pqaGQkmO5noh'
    . 'eBVhDpIB2CPBvBGm5uYkA9x6dGSaC+B2tbdM37LgYwMQQum/wLTTBwJwQztcNBnt'
    . 'vzFVWqgwEc5e2g+ghUxxPDqRobbYV/4ZIrgMwWcfh+ofpxxnWJV51p5uqyv8BUMh'
    . '1wojo1nCV69wYVX5reto5ucO6fzhhBG3H488DKOSKjBo4PPzYkE1aGwsR2pOOGXW'
    . 'eP0/OK54A42uABSJ3grXYa6PGKeKXMuAv2Z9BqFQET3VUlf3Ct6Yx4BIG2zlg5wa'
    . 'A70EUp3dM8s8+IQYM4pDeBkz4u6/kfVruokSzrVhzHFfLgI63AOhWm8wDU0dc4om'
    . 'QjhNGIFz1ePWHsHbrVm1JFmmXLhrGwA9C6V4I9qpU0iUwPZTz9UYI9pJ92bH6ya8'
    . 'IeM6nWR0TZzuqyn/oT3A3M7MkLt1W9EtB3ABgmpHjDcDp6T474Y6q8moAWuXaN3c'
    . 'OPNzjZjcNUP4K8KNply4a5u+kHtTVORkUdi2K3mAP75I4eZDCczkFvRiv1tpWdJo'
    . 'uQ4ia9qrEe54/k5AxNQJmXO37JZMUj8IVmLaOgPkOe/lFvpLo3WRYl1gOjlI/IOB'
    . '+REHWMRBCyLt9A86ISEtk6B4CXeYZwwUA6dbiXzFQCCDR1C40u68J6LWCUNgQn9j'
    . 'g7DYCAuFsAy7Fqok1u8aI+YrQuqe2bYOPNPocOze0PvSFgIGmdsVrStdYs2wrk5j'
    . 'isJef32gvX+XvvAxbcarZWrSSSVYjGnP/IDDTlPRjQ7xiyBqryl4F8GEvpx0VJOb'
    . '6Luwz8ou/T8QnMs4kUTBN0hN0qM5auIqZ6E1md5SDAIDR5+WPQ4T7A/4g4P0D69Z'
    . 'o8gy440tCnJLxR76uN2JMDa0P/WHC9T4bT/CVgu8m7dJFwzJsRfcz+HMQFkRnhNo'
    . 'Q4N/4rg0Y+HOA5ioBV31etcxrepphWTKVmVfCY9ZBQO7k+YWaKu249WiNEOhNOu1'
    . 'Y3Jk4MTCn/K4BFwEz3tbalkzOixHi65/AXn+cyXMKx4Swy2wUe8BYkbHpi14Xshm'
    . '2GxPMtuLbTR2Kg12y5dGq/KkZZppiPjLEKyit3aT8E0CPOmtMuN33LTdhfnEyC1m'
    . 'Fnv79+K5OodmDR4xIPEwBkaTkAG4jYBgyFQKBtde9IhGyb5+ZvBaVqolDca3qBgP'
    . 'HCHiOWFgdgw6Pc1SX3+dbBaebzMySOLOtcAnj+GcA/6kPGG3vEF3r+eOeVUuPA/B'
    . 'KXHJnt3ltDKblLkoUXfwSAoSyC5KXaB6DgN+aDJEiIc8F3GG3BD7HiDLurIgQwgY'
    . '4ZO8Ugb0U1hiksVKLLnwFwlowvzYgSI0xr5NGqfUEiqR8VZTh76Vfsmhi02srPj9'
    . 'xhNSlxIo7XZGXOqp7yDCSaYBSzr3E9zDIqnUETqmsU0j/ZQExTQaMfRO0tmysbtb'
    . 'ScPKcwjEAMkK9AAvylm9nMjKBBgD2AvSUJMobtk9JdlSV/9Bhr1ip1QAAQIDqDBy'
    . 'fERCc3gv9pqoVHxChnkDPOgYduQ4+KZnm/OIHfGsj+GH0pLmFmnaC9zdUtcCIRCV'
    . 'yOSBMU6cWxH+Bw7rfPMTaFzOqQv7KmwZFSvJadBNhjM5vtL0grmPgvwqJ/3y+uuy'
    . 'EIsO2Xk7KzCyJO5Qs8yXLq30l053sfFQh5Ub0DSVrTRBhcWZmiVclSwmxoCnntEa'
    . 'UlnnFN2XkF93JzJryByTTTABaq1G+C0Dz20Mjo4fn2IaY9iceHSsM0B+SRL250nz'
    . 'acjyBPoLrbaB5cayQ6jUdkuCRFL6oXgsD7xPwLhPeA8TmQB6dQGRYlkyFkAsOQl0'
    . 'ERjKBYJKdmwGc8K83V69oNluLXutDStZaSDk00mkDcKKEblPVss/gs41lthWXMLN'
    . '28S3GDADPaGz9HvQ3AiRAnWgSJjlOJ30Bb0hqNoIme8WzAJ5FQYEy6fjg8VJVJjL'
    . 'JiQBNAng8XFUvT3P/tzs9rrPRvvyA1yVBgVI9GcCXRLyfIXYxsB3MOwjcTODtapz'
    . 'yybZXy1pD/yXdLkCSl4FQwJA9clsJuCEzs+Rhr4QYoscA7k9GACaxCB5G4IurUCI'
    . '/gRAihjlALrD/tvQeGXCjSqbJJjCPM0SSlOC3wHWvVFaqn7lej00A/JYDmjJr4ak'
    . 'y0TS0S0UcfOaizPSQAD8BiMMqy68E8GUH8GVvhfdCJTOzJO5wsjqDwTZmuowhg21'
    . 'spR4aKa+Eh0fFYS97IyO/6D1mY7xyxsD56bNLhvoGKwKu+sdg+S+EPsU7E66bL+j'
    . 'dLUh+xqz/fUqM0SYwjTPCBpSuBIf+vQKfvL7yCwB+S3s2VZfpH2dSWioyNuw+HQr'
    . 'nMnPeQagXgTE4lAcHE26Fr1tR8uMgKtN5tAZCVSIuoUfetDm/yAqWLyC8EI+h6fN'
    . 'KxmopFq4I2uY0Yt+YMNJEhHEGzIaO79my6kDwZn2PCfOLk+HEWYKRDeIZ2LTzPBY'
    . '4JbI3MX/XPLfwIsfqije7j7QPPvJcwtEh96MvKFkHhR53+e+jxwAAIABJREFU5KT'
    . 'e4pvkAwBgrIBXNZ8Qe3bKDABBjWDXhpovU7IthwDoq1zBGGWSoHG6z7Uo+C/VTYa'
    . '1+GomLgPQRGAHgxwAO5iFgyEdSYOPbNNb6vusvAUDj4i4VIIwE0kzCBnEdDoDZ6G'
    . 'LT+YK6/hnIYnbAJwwgl2VlW1mW9HTCLNwXpRgIrqnsaqsFD1UjtwNgPciKmDOUrN'
    . 'nklxFGPWdQhJGmgDWoW6VN8yk2QiYMAGuJ8npDDq9e2REDAKh/egQabYV7WHggAD'
    . '2M/EBsDjA4ANN9vJ7PPuakF88R7jybgeDeRiIhoJ5GBOGkWtnNoWBlGPAQJc7jOE'
    . 'SStP1fvf/e4LyJ1gLJ3tWkjc5+QmnSZtcSAzoZHCho6r82d4aCaINzOFn8jFp39t'
    . 'hV71jvY1glIkM0OkU4BCmQjI1yPxaAEglIJUBV111MIRLPNbLCIj5XnTX4iIAYIC'
    . '8e49hVRIhBN0K4EQYwqdrKvaY8wv/DaYfx25YPSHgSxZ8RZPH9K2b3NxSk6f8u1S'
    . '5gSJwsBNhTAgDO6b7pIVxigCgKZovFDgE4alwY2fYJTfuBYHDLtcaFRg/9ZVmYeD'
    . 'hQM1jRKNTVWY6/BiA2Vp4x47EnV45BcRxDRFeT/v3LxG0yHqoMBAvYIRiM7FmIyB'
    . 'Qj5tZIwm+Opqsn7S4USSKLm+ZeHdyzcYYjccLAtaoCWpWc+3STzyPu/OcnwTRg0T'
    . 'kpQvrFhrzDl8J7aqhhPDrbgQAEgQiWNkHglmEINhEYUuHHO8Y6vUHJJAR+ag6I29'
    . 'Iy13kHTYdhjSLzjCIHxu6T5nb/PIKrzDpyfMLB+9M2vkywNcCAPmr0kkRFPhgDsE'
    . 'IqC14m5CJFzBgmizAIQxWhm0E1Om9qGJGP3DL0igxQPEKLhu2X/l3ZE/TiDgE4Af'
    . '+ZB4zbCVmZxdtAuOEficDw90Bjycg5s8RNhSCU4CNeBPECxjgGGEKRbov/GWVgEz'
    . 'z6gnYHm5f0YSA2+Dx8NmyZWkXQT4R9XEQ3oPENIe9/GXfz8xzCy8C1HoAp/t+5jS'
    . 'JKZ4/S8Knvm20w9qFnMm46VDPDZAIIWYj9vZ64HavnvyZ2ZBdagOYmmFd4uVWbJf'
    . '0FIyZ8/qDAV56XFHOc9SUN/p+mJFfVAxJNQGdFsxekjaCI3qLhRJqYYQnWzGkigh'
    . 'zKK+4SCAvIxDCtI0NycnWH3ddgxPxUbtqyr80W4ueA8Hoohb7mURhU9Wy13w/cNV'
    . 'jHvwIc5Aab0Spnj+qEq0ifDep9hubkGiAGXQKAPoXXqbeSgXpB/vI86W2jdkOwBm'
    . 'geV/D6iuHDgXd0iyGQODXVFLP8mcAaXklUxKODtkIUFBxLGbpVTOC4pxhJ7wwoHn'
    . '9SKy/EwdAh4ABr2CiEAYbwTyP4D0dcm3ikF6lSI1GELNXRUXH6vIPAehVE8CTgwB'
    . 'd02ivmOdP5Tojv2iBoqgN0BjaTCCvxPn4Nhm2a5pCMAIO5b7SToeAf4nAiJChWez'
    . 'hCC41IbWgwOutQ+BIN2+iCC/yLWVE0NldynjFKZUzHPaypb4fpRYUDDDnFz7KjJU'
    . 'IZb+IvDdYjw5vi+RBpr0slDHTbGOMgCBCkA/hSIzAlHh0qFcsiaT+ZAQYjHh1kee'
    . 'BRnv5a0Ak3pYT7AH4p47q8ku31yzd6/then7htISjQ7aASUtpXF+8jGBXZWU7whT'
    . 'IkgTNyUxs4HRI/1ADZs1PFQZFlkBN3pUtiUR/MgIQ4wbfCjFgisRd6gTxY6Y4nuK'
    . 'wV6z0/bBb55SY1sOP+1MT1CPgjxHCtMYLiX3BG524brjRBb3RKRjUI1MoYkh72VR'
    . 'i7i1nPSgS7KU9OqxFvI/IpljRJiN9w04vvXxTvFyOsH4HfkMyZTqqKm72p3I9MX9'
    . 'xzsFR6rtundPwVa7Z7wZrWAt6CuFNQK5UW705JkBSuyVqR3MZIQI1R3IhYu+yTO5'
    . 'dTyMWl4ZBgr2KmHz6asURAlWE0MXnxPihw14xa1t1WQ+Vj7TcRYkZtqL7JYt1AM6'
    . 'IdLzwG2VAYbnbiXsV7vAl8lp4PjCwWxAorATzIIwYMXO+puhUFiH9EfyRarYU+Wg'
    . 'WUYAUkL4K5U2cW+w1NRFCPo7g8+z9TLhl2D5lamN1eaW/Bua5hReZksQ7bqlHfWR'
    . 'e/Cb7clh9kxCaQi5SZxQMAJAStGGo1wftFOAQ5mQhoEhnmpZ2HUmHHYjQt8+Kt3o'
    . 'dwVcMpM9Dqiq9qtx8trqiCYwe/nw3Rwl4gLuUiU1V5Y/6xvwAwKRLFp5mthathKT'
    . 'XmWmKv07CH63fQLawjECYVE27/E7n4XQYkg7CuwTARrwJAJaapkRuHftPgjbsBWL'
    . 'yqk3rlgY3QsnYMIjopz0qvyvs6y49DqKH4jq70hrt5b/yp/wwtaAgPj2/6GZVjfv'
    . 'YrXJthJSIv/VKOHvGTf7WLv7w3RjVCwZ2CbDSbETnpIYgjsvQXNMsALN8pb6Z6R8'
    . 'R9hltBgmTWuh5wLG64k0ivAegA+ClTqlkOKrKfuFW3OhBuqVwdsexwe+5Kz3qniz'
    . 'lgdeN687rCNnYiEhzQpEQpPt6AAAEsFMoAoYEnZGP67LXtojYCBLULuFVbI6k+iw'
    . 'MCA40Eibc5FsdRoKLZRyNd9grrvHn7wcAs61optlW+DoJWqP71MfvONlrg+v4sSG'
    . 'jA7UN1AWAX/rWNAtyhiF1JySru8XuTfbdCCn+XzOaa21JHXZ5fQVgHbXLdwJkiKS'
    . '3gaRlHBk81/NAU1XF5kDqeBm24unptqIaAG8DdLG/NkZATF6JNwpTKEagAnyNw17'
    . '+YCjX7E3hOgI6Wgc6d7jzCUirKKpmGMjwLJPZGwMGH6kPUOExlCvOnTJroVcBcGK'
    . 'pVeeyL+AE6CUJxe/T3pMJ1uKzzPlFzzN4fURyJ+HC7KUmwmCNXhtqY+IrHfaKZaF'
    . 'cbniObQjC3djrbTTAJ6irc3YvZkKXTNRwjbb4eE0VcLZWVnYSWHNVmwDEdyaYvKq'
    . 'qHI8z/YP6ft7xVma60+Tk8Q572Q+aqpcFfCum5y/6jjm/6HlB/C4YBYiVeIbwTl4'
    . 'i1qQYcYBZ5vmr1RwME9QcGCEaDZdodHd9gvd6bx4eBAqh/ChFvMFFTD/z/NldVSW'
    . 'kp050oN0gfoyZL3DYy89sqi67P1jV93Rr8Q+IlfdievO7IZbeb4Kgsin0AZuUzKb'
    . 'qirAedEKSMRVJ3Xq5AgCI6N3eW4fN97Q2FCRqdbjeWea5S87z6lfpehxADz96DDg'
    . 'ExjMEzB/blprmqKq4OZSbQpD6FvrG78EJbQN8xNUo4C40A7XcJS7QojcaEEJ22Of'
    . '2hpQn3wROk2qUEVyUlpurKaLUXfUwck8Vq14x+p+9tnI3gBcj7jc8HAA9Tiysapu'
    . 'a4qguX9hoL1/lKV7lS5p1UZrZWnRvuq3Y663WaF++H31jE7CpZ2UePwoUruNLx7W'
    . 'NnetvP0MzLm+ZMTW2hVoPuGuWHVi3Zk9ydt5uAp2m83WSjrfFXwBgjZbGBLzg3t4'
    . 'PH6YfZthKftNoX3rSoATug0QBDJhX+qAC2MjAKhK01rG6bIuWk6YWFMR3HB1iYcI'
    . 'CMH4AQCHwnqkFBeWeOqwsUUYClxo1eE0wef1OY+aVJMGp+m5kdQB0k8NevtT3qTb'
    . 'eUjI6TqjTAtVz9mV086GZTHRq8JahQk37Nr3eCnjWliWKdGHqF8HaC7RJFiEvmvy'
    . 'gSHbe6nnAsbr8QzC9oEPfvhwH4S0ADxLz901xfIrDXn5+k738AS0GkG5dkpWeX/R'
    . 'w+9Ehuxh41T3f794nGNN2bIhXcfOmmvIq6JNrEAFys+dPiV08Fd4Pl+1MfL6/JJ5'
    . '065Isk1DrJXFQD1g3TGRIMRMCnxA8OxH5x8zrCHSl/1Miwgrgdi0Nm6qXbTbbiho'
    . 'RRhE/T4hosTnv2ntc6mguWNBviPlyRBZEtpfAWyTobTCvEzSwobHKf6nWQKTllUw'
    . 'xmeSPmPkqQE7sLQBZADcDeM7jEDPhSfeOcGwg4WXcRDLn5E/8BtT4Hzd5/N27Sc8'
    . 'vuoqYywDsbKqq0PSGBAAC5hmRdC39GYEilbelMGSD9YzkrNnprQ1rtexFMFzenAe'
    . 'CNQxCEouuWwDc1X2gqWrZZ+b8ojIwrglyrgTQDMLHYHxMzJ+wonzc4ZSfhFvxJsO'
    . 'yZCoULmDmSwA1kzV+q8zIzrAVT2+0l534wgS3L2ck3gtjwyIC0WWKk14uXCbkEIM'
    . 'JeLAxZ9xdvjUMcnNLTbsG7LyPGb90pxyUQ2PuQUqWZSoDhtSmVgSdKC98wgj2TBj'
    . '0fsr2w1/CgOLSBOUnAO7V0jaxLeGJ9gEdYwmcJkGp5CpiMRDAAIRQ9Z0I16dduuh'
    . 'BT1nBLoq/M447ZwDoAuE4Me1g8G6AdxGJHaoqm7lDftpct0Kzlqo/MmyLR0pWZpP'
    . 'gPDCsDJkSroYEg2+Gh1Zpo/3Zw2Zb8QrAOwchOtD6T18t94obImA8sbA1Vi+r8a1'
    . 'hMGleyYidXTufB3CR+5DTKRXNG5gEuoyNEd84sGfjjHe7FW9OJkJUVqrIsawBQ/8'
    . 'pEWEhgPug4Qng9jwE/IKn5l436MhAjhNq21ATxSssZMBdaXE03ut67lKqutcJS7U'
    . 'UDU8UmMnA+QBmMXAuEQudvr/LM2zFqe7IWAAAK87HSVWuh/ELfV96ODi6KP77O6q'
    . 'f7KEBm2ErOld1qi+BcCIbjIDnA8U/+YOJDXECELAGOPnG8s4GklQNYiPWBRmjsvJ'
    . 'm7GuoXR9pRx7uudiI7xYUKOnHBp4BmKYRIxvg8wGcwcbdkHGS+Tp4Tu1eW/G52RU'
    . 'zZAt8mv4wyR7FFD1rNHdjthUuZOBJAJ46syxJvV/rtUZlW84GtAdhhgIzee1JeRm'
    . 'BClGtQHWXb9EXQbQAQMRGEE2mzFp4qpoQN1UFnwGJM4noHBzFuQCSjK9tcxIilKQ'
    . 'WFNzrzr1ww48DFE0jONCUPe5/ve1UpM8uGUpx6tOAn9kE49Um+4oPtF6MmK4DGfI'
    . '3ZpPJ5PVG63Gzp2Rb6gFkGXDxrxKTOsY019VFNN/WG3PetaMQp2aQyukS0kwk0gF'
    . 'OB3gSQLqXsgofLvEJPKOM/MKPohE67boaVjiqyhcH+niCpShbCPwDgP/kFyFmOlY'
    . 'v0/QQHDFz/mBTV8duGLP4b2ipr/HagfaXHP0CjDGCUzra4hcC6OE/DgCl24qqCRh'
    . 'PhEMMOgzwV5A4BOJDBOqx6yo9pkjEGER0UlFBjaM/+4YkZ9iKnmV0XQXpqgJFXiV'
    . '1Yxqe4we6CUAZTg6QmemvAB6PwrU/IFYf8vtRaanIqN/5S2bcA//3EwC8rtUAAMD'
    . 'U2bnAV+BLR3rsRfUYNIMrCXQ/DLgLmOl2oLTMc1HSW3MCPQDw6y6Xovu7J9e//L0'
    . 'oyecHzzbCyWcCmAePw05Sb1dYyUO/KKOKM9MthbOaairWdh9IbEtY0T6g4z7oXdb'
    . 'UDQPHBOH3Q1vFn7ZsKesRtzR5TuEYZ/2uvzFjtr/z3Ugm/lVI1yVeYtQjSEjqIUP'
    . 'fYzHXWl+7TYd0x0BMGp29UfMOoMNe9gaBAyWbhwZjrtlWfJvnIZcuJ12JfiLiKwR'
    . 'u9vzZ5SRgQwqCE/g1yeqZjVXlf/SXyJ+eX3i500Tvgbk3AwCAZ9wlqTQxOivvewS'
    . 'cE/KAtcD8wZ7N1T123P17NIj+acggADD4jlDaO1XTHdBNOZv/6Btl6rCXvRHqmGI'
    . 'Fg+ZOsBZO9jwmiR6HvkbcCHB+o71iXnP1imZ/DdItRXPJFYYS7A16nEC/DuXiTFQ'
    . 'aSvtQIMK//B33awQqi2dg3NNxxpgc6/laG7uKyNGfdLp2HKT816R5JV5fXpO94hE'
    . 'Af9PpGkZCgoSXNMu2qvLtBLyiQ9/Hieh3hKQzHfaKXnWbhOBre/u8GyJ6yHN/Ixj'
    . 'JWdZZOLmxpjeSXMLDPfBrBPsbqloAVBs0GEiW94XSvmPQofsA6JUCmqo61ZU++p8'
    . 'Ytk9ZAgSq296X6KlkrUqKSMmawS9AlVMaq8pKG+29x0KlWRelsTbXbHOSs8P/Yjo'
    . 'ARPzbUNqHSO2ehtqd/j4IuMFDTMuNGw9dmJxj0bw22FVZ2UYsroN+znlb+qadXq/'
    . 'pLVuWdrWZlCvA3Nf3Mgb7SrNsqyl7C+GIFTCvJ8jcJntFgUuYIDgKiRsQPAiRCXz'
    . 'N+7XPaM4bT8m2zAVwgdb2IcMIeD8HNIK9cv8qAJq3uEOFGA8gM1OzKGxj9bIaAp7'
    . 'S7fpAaUZ+kVdppD2rlh5X4kzfB/ChXtcxAibc4CvNQq61gUboA2L80FFdMbPRvvw'
    . '//lpkWAtnTLpkoVd+yZh5JUkECrhX4MFfG+0VoWQKEoDfhdA+VL4Y9JUIOGUMvNW'
    . '/ZUsXSL+bzg+TU5QRwSI6vThuUn5OxBGp1XlAzHgqw1bsFbP/2aqlB7pE/PcAaPZ'
    . 'oxID0jGNDL/E8MLRV+SeCl4H9FOCfOnJSzwmkXTppbmG6Ob/oeQh68rPXVnrlPSd'
    . '1qdcELOZ3ks8T2xLuDP4rnGR0lvUnMChEAgCIeWljoz3gNK/XeBcJ59NAKOVYQ+a'
    . '3p2TO1uzj3rNq6XEJLNBxTAqDn8mwFud6Htyx+smv1AR1DhHqdbqO7rBkL3ep243'
    . '5dIDmOwG6Zmzb2DMd9oqVvuHOgCswMSO/uFSVtBWMAinpLnhMP9NyFyUy4edBhtV'
    . 'OwJU90y8DkzrDMpyJ/09r+zDoEkLptd5Dr0bgTj/z61bSiRHxiggpd8Dlcw6ruko'
    . 'gEpn4FfPcYq8nUfPLKw7KTiUPRGsDnRhTCBdNsBSe7X1IfQqAZ1hKMzGuJyRNdNj'
    . 'LlvrLbc7MLIkz24puaB/QsY2ZfwtXuHpdU3WZV5SQSFKuAYKpSvBNjfbykPaYnE7'
    . '8CYBh4SkEVO7eZO/VQxU08lECD8PQiopUkpydF5J6msNethQMPTeJhkCyPcOy2Es'
    . 'nqWnt0kNjj6faADJyWhg2ivB+GDTal+8nVyba+0RYOLZt7MTG6vIn/Hp8CgqUjPy'
    . 'iBQdHqR/DFXrR7TaWTN77JpPnFw4mRu9THMKKUEW1UqZbc0FYFMo5oSIFPxasjab'
    . 'd6ZRs66sAzwveMmyanXEJZx1Y96omhWIAyLDdmMA4/ib0VSI4xMyX+JNCycgvupM'
    . 'Z90IvjX99aCfIcW4lCgCupH3P5Hw/UIat6BIQ7mXumVvBhCeaqsq99iLMtuLfA3y'
    . 'Xb9uTPeIt4qQ5wdyrnmRk2BKODpfvwABlOY+BvdFSXz0rWCttMfCSQ/Lrh0Gaqav'
    . 'jD6Gc0Gh/vCOus2uejgtlABhKRDXm/KIe4gCNVeV/lEQ2APv9nBcrEhnKQs8DvRl'
    . 'AuqVwttlW1MDAq/4MAECrk+K9XMfmvMVjAdzqp203n3WouDQUAwCAY8Pl3TDUAAA'
    . 'w3aOlmSYjaNlcU0+sTTYlAq5LybJdGMoJn7y+8gunlDbo68pNAuOVjPyiBb4fbKs'
    . 'qW0Og7/aRvYRmIr59oNoRbKpGGdbC+WZbUQMJWoNevDDE9HPfJBlW6GEgYHH2vZC'
    . 'YG2rudUqO5aKIpXWCwahrabD7df/6ojlYLyXLmgXiTaGcEwYtThXfPbClJqSbeoK'
    . 'l8Gwh6HUAeuvTPOjIGXtnD29KQYGSfnTIz8nl2zairGggOglcC6aVqe1jX+pNxAu'
    . 'lpSJj0865DNwNbQrhLzvs5Zd5HsiwLrEwyUCRAwdIitzGmmUh6diOzMpPUUh9Bwa'
    . 'UXvJC0EUtG6vrtDQN6YZOybK8AMLlYQ1KO2+2JHXkoa4upNgltyGshd5h0Yxqdio'
    . '/8qeiNsFafJYieGWAqYWO0AfEvFzEKc98tmrpAS1npFuK5pIIWO7JlxbFpHzHs+8'
    . 'x80qSBjjVD+A/SeYgAReH6glCbq4p5XjCGzByZxgAMdbsbajRrNYdUl6sQspdMF4'
    . 'P86LRx+I1zeU82VZT8Z6UPBuApptEMwQrxakbJuYv6RHeu6267P3U42OnMeEW6F8'
    . '2dieIH4OgaQ572VmN1eV/DmIAXg+0Uw4otQC01IhmgAt9+x7gVB+AXwPgfRA0O2Q'
    . 'DAJByPOH3MNgAAEhiEdJUK+SpTXJ23lMECmmnNwyYmb/f2lCrSarPE7OlKAMCdkQ'
    . 'o4OWHLiL6Q2N26j3+Nptc8oLyIQZfhfCnjDtB/BKYnnfYy9cjiGs6M7Mk7uAodS6'
    . 'AJSDsdFSVe0V3ZuQX/pGZgiS08L0Oe8XdnkfSrUWziLAGPX+P7ZLZsq26ImQVvOT'
    . 'p1vkk+WU/feoKgcv31tcWB2/peU6IjMmcN0IqnZ/CAH0iH74CifNbNtk/CvXE8Za'
    . 'S0SahrgZwrt6DImCN4uRFgaTUzXOLzgTjbrekohaaQPwaS1Q2VVesg4Y9mQzb4jM'
    . 'AsZCBRQCS3Yc7TE5O9xzXpLmF6aqkzxHgjU/AmsZBh22orDxRKjbVUjQ8QeBdAGN'
    . '9mn9IIFsoodHduNeTbwAYFOq5IXLUqWJSqGvKsKwyJct6HYi115sKn1Yh6UJ/2UD'
    . 'BmDy/cLCzi/4JIN+AcR0iov/XOPDQU543kCcTLMUXkOD7qKc8/XEAdQDskKh21JQ'
    . '3arlg2qWLhikdph8CvBDATL+NiB5yVJX9wvNQhq2oloE5flo7ukR8lo83iMy2ohe'
    . 'BHqK/du5SfhyOuvRp2fmTVKhvAxgZ6rmhwkR3tW6q/mOo54X5aioVKdkbNsKYhHx'
    . 'fdpJJuWDv+qrtwZv2gNJtRb8g4A8wRhfoHUHyZ59XLd8UqEGGbfGFTOImMJoIvMb'
    . 'ZJv+rWeGuoEBJPzz4IiJaCKIrenFVdnOkS8SP97yx063F+US82qfdF5J5pu+0xmw'
    . 'tvANE3rXEiB9zZI+71d8UMBgjMi2jTQrWA0gL9dww+LBFPfBdbNkS8po17PlZ8vS'
    . '8HJK0DtHYQSV8wgpf2Lq+NqzC425PyTMATtF5ZACgAvQEd4nfRKTD301pqZhYv/N'
    . '8FbiSmK8IVfaFgV812cs947HIbCvaihMbU9QGQbN91R/MtuKLAa4GTih0fEFERY1'
    . 'VZWFlrQ3PsQ2JZ1kHA6akfpBCyu/t2bxmXfCmPQn76di6sXYTgIgymjTDmEJOWpM'
    . '6wxLWOqSppnw1gc4yIBiug4EPCDyQ4tWwwzemFhTEm23FF5ttRY+YN+3aIRn/IcZ'
    . '14egeEXCtT64BM52In3ES4ce+BjA5r3gCwP/CSQOoU5Sus8M1gDGZ85LipXwF0TE'
    . 'AAHg6XAMAIlypj8mclySVzvegvycmEA0KnLbd9a/7LWatATLbipcw+GFyifxqpQv'
    . 'ATgJ/BNBWBn8EIbaSHPBhqOECvpyVt2DgcVPcr5npx8BJ3c5IIKJLPW9gl8/fuY2'
    . 'B25rsFc96th0392enxHPHemaa4pJY4bsbBx55NNBaJxinZM4emqAor8GlyxoNtne'
    . 'o6tlfbVkb9ls4YndVynRrLiS/juiJw251xsm8A+vW9FrorjcmWAsnC6JynFxgtgP'
    . 'YQcB2ZmwH0Q4ibiZguxPYPv742N297s7qxOS84gmqwEwmzHRrnJ6OMKabBKxptJd'
    . '7bRZNmF+c7Cs+llpQMCDh6JBaAOcD9B8mKmmqWvZZuONPnWEZ7lRRBSAnaGN9kBA'
    . '0S+vOcCB08dmmZFkfAnGwhAs9aVYVzNm/oUaTZ8UvpaViYsOuyW1Obg237kBvTLp'
    . 'k4WmqGpcFovPAPI6ZnpftzqpQZN8zbDcmELWfLqWcAsKZIEwCYwwTxhBjNAJL1bN'
    . 'kPr03f35mZkncwWT1JQbOIKY7HfayiPJGRmRaRpsE14AoQP0yQ3iwpb4m4hgkfTY'
    . 'uMjPjUpQRbyF6TwAAaJFEln2bqt+P4jX9knbpomGiXZwJQZkEzATjfAD+qrwfJ/A'
    . 'bDFS2mUwvuEvMhs24uT87JREdp3SpiBOKGARQAjvVJCHEUWeC8xPP2gw+ULq16E8'
    . 'g7JNt6qOR1mNwu0FrEB0vUDfvD/pSZPeWNqkV3Xbv3H+I/yG0uXakfClBl+yrr94'
    . 'QjYtNnl84uKtTmUKQZxDhdHZNV85Fz40lLRwC41UCVrGM+4/DT4kjo8iwFae2Sz6'
    . 'uxxtwdJbFyoTnYIznLRBHQSInnI1Uf+i6hT06x7KAAwgcGUgHmG5raajuNY80VNK'
    . 'si9KEMF0kGGdJ8BkETAEwTs9reMAAtgL0JjPq4rs6//PJ6yvDXfxHC0rJtvwCwO8'
    . 'R5UQjYlqwt6H677r1p1dH3aRkWx4HcEPQhjpDwHOynUta36/VrHXTG1MLCuLbjg2'
    . '5giRfD6Lzgp+hKwxgGwjvsuT3icQnzNTUydJhxPolVJLPyhtIibQc0BwaohvEeHR'
    . 'vQ80tuvapZ2cAgMzMuGTTyDeJ2f/WvrFsFSpfsWdLrZ7ZZu6QafljCbqSGBP07Ds'
    . 'MOgA6APAXAA6DqB2Sj0GgE5IYJA8C4oAgqnQXSNeV5Bn53yGn89koL4ABAASs36s'
    . 'eyA1nVzhIv/ozItMyWlG4wYDi4BrgNgL+39768x7VKAEfEhMsRdlEuJII3wdg1rv'
    . '/CGhiwiuC5SuNg46+Ha6fPzClIjlnw43EeADRTSTqplmSc3p3AW49MSysNXla3pk'
    . 'k6G0YpJ2vgQ0KlEW766vC9nsHY0J+0Xgh+SKALgbRxQBH0eh5H4j+A6a1BPF6o71'
    . 'H8XjdSM7Om0DAcoBCSn/VkSOsKDNbN1RpLvcUCobGdqdMy7NB0KsIXMHEaI4x012'
    . 'tA9ufCDVTLRzGW0pGxxGfxSTPJuBst/doDE6GO4fDEQYcADcS0ycgbGGT8r+mVUt'
    . '36DTswBQUKCk7Dl8L19M/ml4/T5yC5Nw9m9aEIusYEobXJBqdbb2GwbHW7Xkfgm6'
    . 'OdGcxXKYWFMS3HxqUjDjTWHaqSYKQBFACQAlMrgqPxPwVAEgh2oRUv5BC+aJTlQd'
    . 'itRBOybJdyCQfMaxghjaYQUta66vLjbxIVApzJWdb7iKXKy3WvAZBN7ZsrG6O9UD'
    . '6Kqfl2FJVln8AcDViXriNftFSXx2SvHs4RMW/e2y3463BqRMTEb2gqkBMAvM1A0+'
    . 'beOrA1PHvHtu9TRd36teB1BmW4QNOM9/FzP8AKBM32wV2AAAFi0lEQVSxNgDGPS0'
    . 'NNVF5cEb1F03JsvwFhOuDt4wKx0F4khV+MNw8ha8DLgkU520AXQtjSqaGDDMeaW2'
    . 'o6U3wS1eibe00OifvEWZdBXUj5RiA5ULSX8JJ4+yvjD4vfzxU523MWAJQsIy16MH'
    . '0p5aG6l8gitXSY/LKS8my/A6Eu4O3jC5MtI4Yj7Yktb8UDW9S1MnNNSUfi7cJoiI'
    . 'G5iJ2Xju/MPF9rZtqfxPt68Zs3peSY7kbbGh1kkhoJuA5KfkfrZtr+3TVGi2MzJk'
    . 'zUWHlJwAvhnHxTxHCpS31tTG5H2K6+EnJsi4C8dMA4mM5jl5h/oCJ/mmC8oKRG29'
    . '6MyrbcjYBcwk8D6AcxHqhGxgngW7YW18dqMCI4cT8DzNm2pyZUoiXEAVJDh1oAbA'
    . 'GoFXx8craHW+v/iroGVHCndh+AYBL4JrqhBPeHW2OAvhRS32NrxpGVIm5EQDAmGn'
    . 'WyVLwa4herrIeOAnYCvBmgDYDcvPwY8Pe37q119oA+pCba0o5ljAZhEwizmSmmXD'
    . 'lNUQrxVUPWoj4kr2barfEeiB9wgiAE8p2LyH2ewmR0AnmTwGqbGmouVfvzpOz8n4'
    . 'liH7kDsfou1PI4GwRqvqDPVvWGh/6oYE+8+TYs2XVgUFfitkAnon1WCIgHkTfIdF'
    . 'DdU4XiGgmA2ejHxsAAc8JNf57fcUAgD7mInPniy5MybasBfAEYhe01VfpM2/uMHA'
    . 'C+PXe+pqQCjVGgz7zJvCkpb5mpVB5GgO6J4VEA+Z+fbMaADVJ5gtb+qABAH3UCAB'
    . 'gz5baTwYkdcwgl3pa1HYPdcKYvyv13e+rFyoTO9sz9zXU9oUSV37pU9MhX5rr6to'
    . 'B3JycY3mDGOXQvxyTMRj1JuhHbxgGDgK4vrW+5rlYjyUY/eLJ0rqp5hWFxDkEhKW'
    . 'NGXWI+83NagQMqo1T4s7sDwYA9PE3gSfuquSXJmfnXUKgxxFdoacQIUOMgEGC+vb'
    . 'MsAXAL1vrq59BP5rC9os3gSet9bWvCTV+KkC/A2D8xlR4GGIEgvvsG0Yy09IOVZ3'
    . 'SUl+zEv3IAIB+aAQAsGfLquMt9dWlCpTvAPRGrMfjB2NuVtEH1wSM/0FiRmtD9TW'
    . 'RKEPHkn5pBN3srq/6rKW+ejYxX03A57EeTzdMBk2H+tbC2EHMV7c0zMhq2VxTH+v'
    . 'BREK/NgI3vLeh9tm9SR1ngFAIoDnWA+rD05aIYfBuAl3boh44fW9D7bNGaDtFm6+'
    . 'DEbioq3O2bKpZ3qIemATgpwC2xWoobNzObiy/ry8A/CpOGTpxb33103qrwMWSfuM'
    . 'd0syWLV0twMq03Nzn244llhDx7Yh6IolBbwIGxWBC5GDC42jjMr10XvsaXz8jcOP'
    . 'eaHsMKP1Lctb6uULQTcyYHetx9RdOpJqOH/yi/pKOfYuvrRGcpFS2NmAVgFUpWdY'
    . 'sFryEGD+CocoKxiyMo8AhAioFy7/url/jitvq10tebXwDjOAkLQ3VDQAaRubm3ib'
    . 'a4n8oQD9mRi70/jsYNWkxJnZIJcKbzHiG2/nfLV/TKU9vfKOMoJv9dXVHAVQAqBh'
    . '3/txTOjud8+DS2s9DP47VDwEVQD0T/gWF/9HyDdZdAr6hRuCJO094JYCVaefkDut'
    . 'ISJjHEleAMAvh5jMY58+PpN9jAN4k4lVdTlp1YEvNXr0G1d/5xhuBJ83v1h2EK7P'
    . 'tGRQUKKN3HDpHgs4nxkwAF6O/RLG6OArGZiasIxZrWga2rftaainpwLdGEIjKSnU'
    . 'vsAWufx4FSsWonI1nEssLiXEeiM6Aq46Zn+mTYQvjQP1KAhySeBNJsUEwbdgzqO2'
    . 'Db296bfRXL0bfIDfXdNrxpHQJOVWCTxfAVHcS/NGW+hrd84xH51jWgDGSgU8B+ph'
    . 'ZfsxEnyQldXzqdgl/Sxj8f+5eoQLlJvS0AAAAAElFTkSuQmCC'
  ;

  /**
   * Recupera as regras de validação.
   *
   * @param bool $addition
   *   Se a validação é para um formulário de adição
   *
   * @return array
   */
  protected function getValidationRules(
    bool $addition = false
  ): array
  {
    $validationRules = [
      'entityid' => V::notBlank()
        ->intVal()
        ->setName('ID do Contratante'),
      'name' => V::notBlank()
        ->length(2, 100)
        ->setName('Contratante'),
      'tradingname' => V::optional(
            V::notBlank()
              ->length(2, 100)
          )
        ->setName('Nome fantasia/apelido'),
      'entitytypeid' => V::notBlank()
        ->intVal()
        ->setName('Tipo de contratante'),
      'juridicalperson' => V::boolVal()
        ->setName('Tipo jurídico da entidade'),
      'logonormal' => V::optional(
            V::oneOf(
              V::extension('png'),
              V::extension('jpg'),
              V::extension('jpeg')
            )
          )
        ->setName('Logomarca para fundo claro'),
      'logoinverted' => V::optional(
            V::oneOf(
              V::extension('png'),
              V::extension('jpg'),
              V::extension('jpeg')
            )
          )
        ->setName('Logomarca para fundo escuro'),
      'stckey' => V::optional(
            V::notEmpty()
              ->exactly(32)
          )
        ->setName('Chave de acesso'),
      'subsidiaries' => [
        'subsidiaryid' => V::intVal()
          ->setName('ID da unidade/filial'),
        'headoffice' => V::boolVal()
          ->setName('Indicador de matriz/titular'),
        'name' => V::notEmpty()
          ->length(2, 100)
          ->setName('Nome da unidade/filial'),
        'regionaldocumenttype' => V::notEmpty()
          ->intVal()
          ->setName('Tipo de documento'),
        'regionaldocumentnumber' => V::optional(
              V::notEmpty()
                ->length(1, 20)
            )
          ->setName('Número do documento'),
        'regionaldocumentstate' => V::oneOf(
              V::not(V::notEmpty()),
              V::notEmpty()->oneState()
            )
          ->setName('UF'),
        'nationalregister' => V::oneOf(
              V::notEmpty()->cpf(),
              V::notEmpty()->cnpj()
            )
          ->setName('CPF/CNPJ'),
        'birthday' => V::optional(
              V::notEmpty()
                ->date('d/m/Y')
            )
          ->setName('Data de nascimento'),
        'maritalstatusid' => V::optional(
              V::notEmpty()
                ->intVal()
            )
          ->setName('Estado civil'),
        'genderid' => V::optional(
              V::notEmpty()
                ->intVal()
            )
          ->setName('Sexo'),
        'address' => V::notEmpty()
          ->length(2, 100)
          ->setName('Endereço'),
        'streetnumber' => V::optional(
              V::notEmpty()
                ->length(1, 10)
            )
          ->setName('Nº'),
        'complement' => V::optional(
              V::notEmpty()
                ->length(2, 30)
            )
          ->setName('Complemento'),
        'district' => V::optional(
              V::notEmpty()
                ->length(2, 50)
            )
          ->setName('Bairro'),
        'postalcode' => V::notEmpty()
          ->postalCode('BR')
          ->setName('CEP'),
        'cityname' => V::notEmpty()
          ->length(2, 50)
          ->setName('Cidade'),
        'cityid' => V::notEmpty()
          ->intVal()
          ->setName('ID da cidade'),
        'state' => V::notBlank()
          ->oneState()
          ->setName('UF'),
        'personname' => V::optional(
              V::notEmpty()
                ->length(2, 50)
            )
          ->setName('Contato'),
        'department' => V::optional(
              V::notEmpty()
                ->length(2, 50)
            )
          ->setName('Departamento'),
        'blocked' => V::boolVal()
          ->setName('Bloquear esta unidade/filial'),
        'phones' => [
          'phoneid' => V::intVal()
            ->setName('ID do telefone'),
          'phonenumber' => V::notBlank()
            ->length(14, 20)
            ->setName('Telefone'),
          'phonetypeid' => V::notBlank()
            ->intval()
            ->setName('Tipo de telefone')
        ],
        'emails' => [
          'mailingid' => V::intVal()
            ->setName('ID do e-mail'),
          'email' => V::optional(
                V::notEmpty()
                  ->length(2, 100)
                  ->email()
              )
            ->setName('E-Mail')
        ],
        'contacts' => [
          'mailingaddressid' => V::intVal()
            ->setName('ID do contato'),
          'name' => V::notBlank()
            ->length(2, 50)
            ->setName('Nome do contato'),
          'attribute' => V::optional(
                V::notBlank()
                  ->length(2, 50)
              )
            ->setName('Departamento/Observação'),
          'mailingprofileid' => V::notBlank()
            ->intval()
            ->setName('Perfil'),
          'email' => V::optional(
                V::notEmpty()
                  ->length(2, 100)
                  ->email()
              )
            ->setName('E-Mail'),
          'phonenumber' => V::optional(
                V::notBlank()
                  ->length(14, 20)
              )
            ->setName('Telefone'),
          'phonetypeid' => V::notBlank()
            ->intval()
            ->setName('Tipo de telefone')
        ]
      ],
      'note' => V::optional(
            V::notBlank()
          )
        ->setName('Observação')
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['entityid']);
      unset($validationRules['subsidiaries']['subsidiaryid']);
      unset($validationRules['subsidiaries']['phones']['phoneid']);
      unset($validationRules['subsidiaries']['emails']['mailingid']);
      unset($validationRules['subsidiaries']['contacts']['mailingaddressid']);
      // Retira pois inicialmente não temos as informações dos perfis de
      // contato, já que estes somente serão criados depois
      unset($validationRules['subsidiaries']['contacts']['mailingprofileid']);
    } else {
      // Ajusta as regras para edição
      $validationRules['entitytypename'] = V::notBlank()
        ->setName('Tipo de contratante')
      ;
      $validationRules['blocked'] = V::boolVal()
        ->setName('Bloquear este contratante e todas suas '
            . 'unidades/filiais'
          )
      ;
    }

    return $validationRules;
  }

  /**
   * Recupera as informações de tipos de entidades.
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de entidades
   *
   * @return Collection
   *   A matriz com as informações de tipos de entidades
   */
  protected function getEntitiesTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de entidades
      $entityTypes = EntityType::orderBy('entitytypeid')
        ->get([
            'entitytypeid as id',
            'name',
            'juridicalperson'
          ])
      ;

      if ( $entityTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de entidade "
          . "cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "entidades. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "entidades"
      );
    }

    return $entityTypes;
  }

  /**
   * Recupera as informações de tipos de documentos.
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de documentos
   *
   * @return Collection
   *   A matriz com as informações de tipos de documentos
   */
  protected function getDocumentTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de documentos
      $documentTypes = DocumentType::orderBy('documenttypeid')
        ->get([
            'documenttypeid as id',
            'name',
            'juridicalperson'
          ])
      ;

      if ($documentTypes->isEmpty()) {
        throw new Exception("Não temos nenhum tipo de documento "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "documentos. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "documentos"
      );
    }

    return $documentTypes;
  }

  /**
   * Recupera as informações de gêneros.
   *
   * @throws RuntimeException
   *   Em caso de não termos gêneros
   *
   * @return Collection
   *   A matriz com as informações de gêneros
   */
  protected function getGenders(): Collection
  {
    try {
      // Recupera as informações de gêneros
      $genders = Gender::orderBy('genderid')
        ->get([
            'genderid as id',
            'name'
          ])
      ;

      if ( $genders->isEmpty() ) {
        throw new Exception("Não temos nenhum gênero cadastrado");
      }
    } catch (Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível obter as informações de gêneros. "
        . "Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os gêneros");
    }

    return $genders;
  }

  /**
   * Recupera as informações de estados civis.
   *
   * @throws RuntimeException
   *   Em caso de não termos estados civis
   *
   * @return Collection
   *   A matriz com as informações de estados civis
   */
  protected function getMaritalStatus(): Collection
  {
    try {
      // Recupera as informações de estados civis
      $maritalstatus = MaritalStatus::orderBy('name')
        ->get([
            'maritalstatusid as id',
            'name'
          ])
      ;

      if ( $maritalstatus->isEmpty() ) {
        throw new Exception("Não temos nenhum estado civil cadastrado");
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de estados "
        . "civis. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os estados "
        . "civis"
      );
    }

    return $maritalstatus;
  }

  /**
   * Recupera as informações de tipos de telefones.
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de telefones
   *
   * @return Collection
   *   A matriz com as informações de tipos de telefones
   */
  protected function getPhoneTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de telefones
      $phoneTypes = PhoneType::orderBy('phonetypeid')
        ->get([
            'phonetypeid as id',
            'name'
          ])
      ;

      if ( $phoneTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de telefone "
          . "cadastrado"
        );
      }
    } catch (Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "telefones. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "telefones"
      );
    }

    return $phoneTypes;
  }

  /**
   * Recupera as informações de perfis de notificação.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter os perfis de
   *   notificação disponíveis
   *
   * @throws RuntimeException
   *   Em caso de não termos perfis de notificação
   *
   * @return Collection
   *   A matriz com as informações de perfis de notificação
   */
  protected function getMailingProfiles(
    int $contractorID
  ): Collection
  {
    try {
      // Recupera as informações de perfis de notificação
      $mailingProfiles = MailingProfile::orderBy('name')
        ->where('contractorid', $contractorID)
        ->get([
            'mailingprofileid as id',
            'name'
          ])
      ;

      if ( $mailingProfiles->isEmpty() ) {
        throw new Exception("Não temos nenhum perfil de notificação "
          . "cadastrado"
        );
      }
    } catch (Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível obter as informações de perfis de "
        . "notificação. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os perfis de "
        . "notificação"
      );
    }

    return $mailingProfiles;
  }

  /**
   * Recupera o perfil de notificação padrão.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter o perfil de
   *   notificação padrão
   *
   * @return int
   *   O ID do perfil de notificação padrão
   */
  protected function getDefaultMailingProfile(
    int $contractorID
  ): int
  {
    $defaultMailingProfileID = 0;
    try {
      // Recupera as informações de perfis de notificação
      $defaultMailingProfile = MailingProfile::leftJoin(
            'actionsperprofiles',
            function ($join) {
              $join->on('mailingprofiles.mailingprofileid', '=',
                'actionsperprofiles.mailingprofileid'
              );
              $join->on('mailingprofiles.contractorid', '=',
                'actionsperprofiles.contractorid'
              );
            }
          )
        ->where('mailingprofiles.contractorid', $contractorID)
        ->selectRaw('mailingprofiles.mailingprofileid as id')
        ->groupBy('mailingprofiles.mailingprofileid')
        ->havingRaw("count(actionsperprofiles.*) = 0")
        ->get()
      ;

      // Determina o perfil padrão
      if ( $defaultMailingProfile->isNotEmpty() ) {
        $defaultMailingProfileID = $defaultMailingProfile->first()->id;
      }
    } catch (Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível obter as informações do perfil de "
        . "notificação padrão. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      return 0;
    }

    return $defaultMailingProfileID;
  }

  /**
   * Recupera as informações de números de telefones de uma
   * unidade/filial/titular/associado.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste contratante para o qual desejamos
   *   obter os dados de telefones disponíveis
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de números de telefones
   */
  protected function getPhones(
    int $contractorID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de números de telefones
    return Phone::join('phonetypes',
          'phones.phonetypeid', '=', 'phonetypes.phonetypeid'
        )
      ->where('entityid', $contractorID)
      ->where('subsidiaryid', $subsidiaryID)
      ->get([
          'phones.entityid',
          'phones.subsidiaryid',
          'phones.phoneid',
          'phones.phonetypeid',
          'phonetypes.name as phonetypename',
          'phones.phonenumber'
        ])
    ;
  }

  /**
   * Recupera as informações de e-mails de uma unidade/filial/titular/afiliado.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste contratante para o qual desejamos
   *   obter os dados de e-mails disponíveis
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de e-mails
   */
  protected function getEmails(
    int $contractorID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de dados de e-mail
    return Mailing::where('entityid', $contractorID)
      ->where('subsidiaryid', $subsidiaryID)
      ->get([
          'entityid',
          'subsidiaryid',
          'mailingid',
          'email'
        ])
    ;
  }

  /**
   * Recupera as informações de contatos adicionais de uma
   * unidade/filial/titular/associado.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste contratante para o qual desejamos
   *   obter os dados de contato disponíveis
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de dados de contatos adicionais
   */
  protected function getContacts(
    int $contractorID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de contatos adicionais
    return MailingAddress::join('phonetypes',
          'mailingaddresses.phonetypeid', '=', 'phonetypes.phonetypeid'
        )
      ->join('mailingprofiles',
          function ($join) use ($contractorID) {
            $join
              ->on('mailingaddresses.mailingprofileid', '=',
                  'mailingprofiles.mailingprofileid'
                )
              ->where('mailingprofiles.contractorid', '=',
                  $contractorID
                )
            ;
          }
        )
      ->where('entityid', $contractorID)
      ->where('subsidiaryid', $subsidiaryID)
      ->get([
          'mailingaddresses.entityid',
          'mailingaddresses.subsidiaryid',
          'mailingaddresses.mailingaddressid',
          'mailingaddresses.name',
          'mailingaddresses.attribute',
          'mailingaddresses.mailingprofileid',
          'mailingprofiles.name as mailingprofilename',
          'mailingaddresses.email',
          'mailingaddresses.phonetypeid',
          'phonetypes.name as phonetypename',
          'mailingaddresses.phonenumber'
        ])
    ;
  }

  /**
   * Exibe a página inicial do gerenciamento de contratantes.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function show(
    Request $request,
    Response $response
  ): Response
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Contratantes',
      $this->path('ADM\Cadastre\Contractors')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de contratantes.");

    // Recupera os dados da sessão
    $contractor = $this->session->get(
      'contractor',
      [
        'searchField' => 'name',
        'searchValue' => ''
      ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'adm/cadastre/contractors/contractors.twig',
      ['contractor' => $contractor]
    );
  }

  /**
   * Recupera a relação dos contratantes em formato JSON.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function get(
    Request $request,
    Response $response
  ): Response
  {
    $this->debug("Acesso à relação de contratantes.");

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do Datatables
    
    // O número da requisição sequencial
    $draw = $postParams['draw'];

    // A posição inicial (0 = início) da paginação
    $start = $postParams['start'];

    // O comprimento de cada página
    $length = $postParams['length'];

    // Lida com as informações adicionais de filtragem

    // O campo de pesquisa selecionado
    if (array_key_exists('searchField', $postParams)) {
      $searchField = $postParams['searchField'];
      $searchValue = trim($postParams['searchValue']);
    } else {
      $searchField = 'name';
      $searchValue = '';
    }

    // Seta os valores da última pesquisa na sessão
    $this->session->set('contractor',
      [
        'searchField' => $searchField,
        'searchValue' => $searchValue,
        'displayStart' => $start
      ]
    );

    // Corrige o escape dos campos
    $searchValue = addslashes($searchValue);

    try
    {
      // Realiza a consulta
      $sql = "SELECT E.entityID as id,
                     E.subsidiaryID,
                     E.juridicalperson,
                     E.headOffice,  
                     E.type,  
                     E.level,  
                     E.activeRelationship AS active,
                     E.name,
                     E.tradingname,
                     E.blocked,
                     E.cityname,
                     E.nationalregister,
                     E.blockedlevel,
                     0 as delete,
                     E.createdat,
                     E.updatedat,
                     E.fullcount
                FROM erp.getEntitiesData(0, 0, 'contractor',
                  '{$searchValue}', '{$searchField}', NULL,
                  0, 0, {$start}, {$length}) as E;"
      ;
      $contractors = $this->DB->select($sql);

      if (count($contractors) > 0) {
        $rowCount = $contractors[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $contractors
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos contratantes cadastrados.";
        } else {
          switch ($searchField) {
            case 'subsidiaryname':
              $fieldLabel = 'nome da unidade/filial';

              break;
            case 'nationalregister':
              $fieldLabel = 'CPF/CNPJ da unidade/filial';

              break;
            case 'name':
              $fieldLabel = 'nome';

              break;
            case 'tradingname':
              $fieldLabel = 'apelido/nome fantasia';

              break;
            default:
              $fieldLabel = 'conteúdo';

              break;
          }

          // Define a mensagem de erro
          $error = "Não temos contratantes cadastrados cujo "
            . "{$fieldLabel} contém <i>{$searchValue}</i>."
          ;
        }
      }
    } catch (QueryException $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [
          'module' => 'contratantes',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "contratantes. Erro interno no banco de dados."
      ;
    } catch (Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [
          'module' => 'contratantes',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "contratantes. Erro interno."
      ;
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'draw' => $draw,
          'recordsTotal' => 0,
          'recordsFiltered' => 0,
          'data' => [],
          'error' => $error
        ])
    ;
  }

  /**
   * Exibe um formulário para adição de um contratante, quando
   * solicitado, e confirma os dados enviados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function add(
    Request $request,
    Response $response
  ): Response
  {
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de entidades
      $entityTypes = $this->getEntitiesTypes();

      // Recupera as informações de tipos de documentos
      $documentTypes = $this->getDocumentTypes();

      // Recupera as informações de gêneros
      $genders = $this->getGenders();

      // Recupera as informações de estados civis
      $maritalStatus = $this->getMaritalStatus();

      // Recupera as informações de tipos de telefones
      $phoneTypes = $this->getPhoneTypes();

      // Recupera o local de armazenamento das imagens
      $logoDirectory = $this->container['settings']['storage']['images'];
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        ['routeName' => 'ERP\Cadastre\Contractors']
      );

      // Redireciona para a página de gerenciamento de clientes
      return $this->redirect($response, 'ERP\Cadastre\Contractors');
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à adição de contratante.");

      // Monta uma matriz para validação dos tipos de documentos
      $regionalDocumentTypes = [];
      foreach ($documentTypes as $documentType) {
        $regionalDocumentTypes[$documentType->id] =
          $documentType->name;
      }

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        // Recupera os dados do contratante
        $contractorData = $this->validator->getValues();

        // Verifica se as inscrições estaduais são válidas. Inicialmente
        // considera todas válidas e, durante a análise, se alguma não
        // for válida, então registra qual delas está incorreta
        $allHasValid = true;
        foreach ($contractorData['subsidiaries']
          as $subsidiaryNumber => $subsidiary) {
          // Recupera o tipo de documento
          $documentTypeID = $subsidiary['regionaldocumenttype'];

          // Se o tipo de documento for 'Inscrição Estadual' precisa
          // verificar se o valor informado é válido
          if (
            $regionalDocumentTypes[$documentTypeID]
            === 'Inscrição Estadual'
          ) {
            try {
              // Verifica se a UF foi informada
              if (
                ( strtolower($subsidiary['regionaldocumentnumber'])
                  !== 'isento' )
                && (empty($subsidiary['regionaldocumentstate']))
              ) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro neste campo
                $this->validator->setErrors(
                  [
                    'regionaldocumentstate' =>
                    'UF precisa ser preenchido(a)'
                  ],
                  "subsidiaries[{$subsidiaryNumber}][regionaldocumentstate]"
                );
              } else {
                // Verifica se a inscrição estadual é válida
                if (!(StateRegistration::isValid(
                  $subsidiary['regionaldocumentnumber'],
                  $subsidiary['regionaldocumentstate']
                ))) {
                  // Invalida o formulário
                  $allHasValid = false;

                  // Seta o erro neste campo
                  $this->validator->setErrors(
                    [
                      'stateRegistration' =>
                      'A inscrição estadual não é válida'
                    ],
                    "subsidiaries[{$subsidiaryNumber}][regionaldocumentnumber]"
                  );
                }
              }
            } catch (InvalidArgumentException $exception) {
              // Ocorreu uma exceção, então invalida o formulário
              $allHasValid = false;

              // Seta o erro neste campo
              $this->validator->setErrors(
                [
                  'state' => $exception->getMessage()
                ],
                "subsidiaries[{$subsidiaryNumber}][regionaldocumentnumber]"
              );
            }
          }

          if (array_key_exists('contacts', $subsidiary)) {
            // Verifica se os contatos adicionais contém ao menos uma
            // informação de contato, seja o telefone ou o e-mail
            foreach ($subsidiary['contacts']
              as $contactNumber => $contactData) {
              // Verifica se foi fornecido um telefone ou e-mail
              if (
                empty($contactData['email']) &&
                empty($contactData['phonenumber'])
              ) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro nestes campos
                $this->validator->setErrors(
                  [
                    'email' => "Informe um e-mail ou telefone para "
                      . "contato"
                  ],
                  "subsidiaries[{$subsidiaryNumber}][contacts][{$contactNumber}][email]"
                );
                $this->validator->setErrors(
                  [
                    'phonenumber' => "Informe um e-mail ou telefone "
                      . "para contato"
                  ],
                  "subsidiaries[{$subsidiaryNumber}][contacts][{$contactNumber}][phonenumber]"
                );
              }
            }
          }
        }

        if ($allHasValid) {
          try {
            // Primeiro, verifica se não temos um contratante com o
            // mesmo nome (razão social no caso de pessoa jurídica)
            if (Contractor::where("contractor", "true")
              ->whereRaw("public.unaccented(name) ILIKE "
                  . "public.unaccented('{$contractorData['name']}')"
                )
              ->count() === 0
            ) {
              // Agora verifica se não temos outra unidade/filial com o
              // mesmo cpf e/ou cnpj dos informados neste contratante
              $save = true;
              foreach ($contractorData['subsidiaries'] as $subsidiary) {
                if (Contractor::join("subsidiaries",
                      "entities.entityid", '=', "subsidiaries.entityid"
                    )
                  ->where("entities.contractor", "true")
                  ->where("subsidiaries.nationalregister",
                      $subsidiary['nationalregister']
                    )
                  ->count() !== 0
                ) {
                  $save = false;

                  // Alerta sobre a existência de outra unidade/filial
                  // de contratante com o mesmo CPF/CNPJ
                  if (strlen($subsidiary['nationalregister']) === 14) {
                    $person = 'o titular';
                    $documentName = 'CPF';
                  } else {
                    $person = 'a unidade/filial';
                    $documentName = 'CNPJ';
                  }

                  // Registra o erro
                  $this->debug("Não foi possível inserir as "
                    . "informações d{person} '{subsidiaryName}' do "
                    . "contratante '{name}'. Já existe outr{person} "
                    . "com o {document} {nationalregister}.",
                    [
                      'subsidiaryName'  => $subsidiary['name'],
                      'name'  => $contractorData['name'],
                      'person' => $person,
                      'document'  => $documentName,
                      'nationalregister' => $subsidiary['nationalregister']
                    ]
                  );

                  // Alerta o usuário
                  $this->flashNow("error", "Já existe outr{person} com "
                    . "o mesmo <b>{document}</b> "
                    . "<i>'{nationalregister}'</i>.",
                    [
                      'person' => $person,
                      'document'  => $documentName,
                      'nationalregister' => $subsidiary['nationalregister']
                    ]
                  );

                  break;
                }
              }

              // Verificamos se devemos proceder a gravação dos dados
              if ($save) {
                // Primeiramente lida com as logomarcas do contratante

                // Recupera os arquivos das logomarcas, se enviados
                $uploadedFiles = $request->getUploadedFiles();

                // Lida com a logomarca para fundo claro
                $uploadedFile = $uploadedFiles['logonormal'];

                if ($this->fileHasBeenTransferred($uploadedFile)) {
                  // Move o arquivo para a pasta de armazenamento e
                  // armazena o nome do arquivo
                  $contractorData['logonormal'] =
                    $this->moveFile($logoDirectory, $uploadedFile)
                  ;
                } else {
                  // Não foi enviado nenhum arquivo com uma imagem da
                  // logomarca para uso em locais com fundo claro, então
                  // coloca uma imagem vazia
                  $contractorData['logonormal'] =
                    $this->writeBase64Image(
                      $logoDirectory,
                      $this->emptyLogo
                    )
                  ;
                }

                // Lida com a logomarca para fundo escuro
                $uploadedFile = $uploadedFiles['logoinverted'];

                if ($this->fileHasBeenTransferred($uploadedFile)) {
                  // Move o arquivo para a pasta de armazenamento e
                  // armazena o nome do arquivo
                  $contractorData['logoinverted'] =
                    $this->moveFile($logoDirectory, $uploadedFile)
                  ;
                } else {
                  // Não foi enviado nenhum arquivo com uma imagem da
                  // logomarca para uso em locais com fundo escuro,
                  // então coloca uma imagem vazia
                  $contractorData['logoinverted'] =
                    $this->writeBase64Image(
                      $logoDirectory,
                      $this->emptyLogo
                    )
                  ;
                }

                // Grava o novo contratante

                // Separamos as informações das unidades/filiais do
                // restante dos dados do contratante
                $subsidiariesData = $contractorData['subsidiaries'];
                unset($contractorData['subsidiaries']);

                // Iniciamos a transação
                $this->DB->beginTransaction();

                // Incluímos um novo contratante
                $contractor = new Contractor();
                $contractor->fill($contractorData);
                // Indicamos que é um contratante
                $contractor->contractor = true;
                // Adicionamos as demais informações
                $contractor->createdbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $contractor->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $contractor->save();
                $contractorID = $contractor->entityid;

                // Incluímos os perfis de envio de notificações
                // 1. Administrativo
                $mailingprofile = new MailingProfile();
                $mailingprofile->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Administrativo',
                  'description' => 'Recebe notificações de '
                    . 'atendimentos agendados e executados'
                ]);
                $mailingprofile->save();
                $mailingProfileID = $mailingprofile->mailingprofileid;
                $events = [1, 2];
                foreach ($events as $eventID) {
                  // Adiciona cada evento de sistema na relação de
                  // eventos de sistema registrado deste perfil
                  $actionPerProfile = new ActionPerProfile();
                  $actionPerProfile->fill([
                    'contractorid' => $contractorID,
                    'mailingprofileid' => $mailingProfileID,
                    'systemactionid' => $eventID
                  ]);
                  $actionPerProfile->save();
                }
                // 2. Comercial
                $mailingprofile = new MailingProfile();
                $mailingprofile->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Comercial',
                  'description' => 'Não recebe notificações. É '
                    . 'utilizado apenas para relacionamento comercial.'
                ]);
                $mailingprofile->save();
                $defaultMailingProfileID = $mailingprofile->mailingprofileid;
                // 3. Controladoria
                $mailingprofile = new MailingProfile();
                $mailingprofile->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Controladoria',
                  'description' => 'Recebe todas as notificações para '
                    . 'fins de acompanhamento.'
                ]);
                $mailingprofile->save();
                $mailingProfileID = $mailingprofile->mailingprofileid;
                $events = [1, 2, 3, 4];
                foreach ($events as $eventID) {
                  // Adiciona cada evento de sistema na relação de
                  // eventos de sistema registrado deste perfil
                  $actionPerProfile = new ActionPerProfile();
                  $actionPerProfile->fill([
                    'contractorid' => $contractorID,
                    'mailingprofileid' => $mailingProfileID,
                    'systemactionid' => $eventID
                  ]);
                  $actionPerProfile->save();
                }
                // 4. Financeiro
                $mailingprofile = new MailingProfile();
                $mailingprofile->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Financeiro',
                  'description' => 'Recebe apenas notificações de '
                    . 'cunho financeiro.'
                ]);
                $mailingprofile->save();
                $mailingProfileID = $mailingprofile->mailingprofileid;
                $events = [3, 4];
                foreach ($events as $eventID) {
                  // Adiciona cada evento de sistema na relação de
                  // eventos de sistema registrado deste perfil
                  $actionPerProfile = new ActionPerProfile();
                  $actionPerProfile->fill([
                    'contractorid' => $contractorID,
                    'mailingprofileid' => $mailingProfileID,
                    'systemactionid' => $eventID
                  ]);
                  $actionPerProfile->save();
                }
                // 4. Técnico
                $mailingprofile = new MailingProfile();
                $mailingprofile->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Técnico',
                  'description' => 'Recebe apenas notificações de '
                    . 'cunho técnico.'
                ]);
                $mailingprofile->save();
                $mailingProfileID = $mailingprofile->mailingprofileid;
                $events = [2];
                foreach ($events as $eventID) {
                  // Adiciona cada evento de sistema na relação de
                  // eventos de sistema registrado deste perfil
                  $actionPerProfile = new ActionPerProfile();
                  $actionPerProfile->fill([
                    'contractorid' => $contractorID,
                    'mailingprofileid' => $mailingProfileID,
                    'systemactionid' => $eventID
                  ]);
                  $actionPerProfile->save();
                }
                // 5. Emergência
                $mailingprofile = new MailingProfile();
                $mailingprofile->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Emergência',
                  'description' => 'Recebe apenas ligações da central '
                    . 'de monitoramento.'
                ]);
                $mailingprofile->save();
                $mailingProfileID = $mailingprofile->mailingprofileid;
                $events = [5];
                foreach ($events as $eventID) {
                  // Adiciona cada evento de sistema na relação de
                  // eventos de sistema registrado deste perfil
                  $actionPerProfile = new ActionPerProfile();
                  $actionPerProfile->fill([
                    'contractorid' => $contractorID,
                    'mailingprofileid' => $mailingProfileID,
                    'systemactionid' => $eventID
                  ]);
                  $actionPerProfile->save();
                }

                // Incluímos todas unidades/filiais deste contratante
                foreach ($subsidiariesData as $subsidiaryData) {
                  // Separamos as informações dos dados de telefones dos
                  // demais dados desta unidade/filial
                  $phonesData = $subsidiaryData['phones'];
                  unset($subsidiaryData['phones']);

                  // Separamos as informações dos dados de e-mails dos
                  // demais dados desta unidade/filial
                  $emailsData = $subsidiaryData['emails'];
                  unset($subsidiaryData['emails']);

                  // Separamos as informações dos dados de contatos
                  // adicionais dos demais dados desta unidade/filial
                  $contactsData =
                    $subsidiaryData['contacts']
                  ;
                  unset($subsidiaryData['contacts']);

                  // Sempre mantém a UF do documento em maiúscula
                  $subsidiaryData['regionaldocumentstate'] =
                    strtoupper($subsidiaryData['regionaldocumentstate'])
                  ;

                  // Incluímos a nova unidade/filial
                  $subsidiary = new Subsidiary();
                  $subsidiary->fill($subsidiaryData);
                  $subsidiary->entityid = $contractorID;
                  $subsidiary->createdbyuserid =
                    $this->authorization->getUser()->userid
                  ;
                  $subsidiary->updatedbyuserid =
                    $this->authorization->getUser()->userid
                  ;
                  $subsidiary->save();
                  $subsidiaryID = $subsidiary->subsidiaryid;

                  // Incluímos os dados de telefones para esta
                  // unidade/filial
                  foreach ($phonesData as $phoneData) {
                    // Retiramos o campo de ID do telefone, pois os
                    // dados tratam de um novo registro
                    unset($phoneData['phoneid']);

                    // Incluímos um novo telefone desta unidade/filial
                    $phone = new Phone();
                    $phone->fill($phoneData);
                    $phone->entityid = $contractorID;
                    $phone->subsidiaryid = $subsidiaryID;
                    $phone->save();
                  }

                  // Incluímos os dados de emails para esta
                  // unidade/filial
                  foreach ($emailsData as $emailData) {
                    // Retiramos o campo de ID do e-mail, pois os dados
                    // tratam de um novo registro
                    unset($emailData['mailingid']);

                    // Como podemos não ter um endereço de e-mail, então
                    // ignora caso ele não tenha sido fornecido
                    if (trim($emailData['email']) !== '') {
                      // Incluímos um novo e-mail desta unidade/filial
                      $mailing = new Mailing();
                      $mailing->fill($emailData);
                      $mailing->entityid     = $contractorID;
                      $mailing->subsidiaryid = $subsidiaryID;
                      $mailing->save();
                    }
                  }

                  // Incluímos os dados de contatos adicionais para esta
                  // unidade/filial
                  foreach ($contactsData as $contactData) {
                    // Retiramos o campo de ID do contato, pois os dados
                    // tratam de um novo registro
                    unset($contactData['mailingaddressid']);

                    // Incluímos um novo contato desta unidade/filial
                    $mailingAddress = new MailingAddress();
                    $mailingAddress->fill($contactData);
                    $mailingAddress->entityid = $contractorID;
                    $mailingAddress->subsidiaryid = $subsidiaryID;
                    $mailingAddress->mailingprofileid =
                      $defaultMailingProfileID
                    ;
                    $mailingAddress->createdbyuserid =
                      $this->authorization->getUser()->userid
                    ;
                    $mailingAddress->updatedbyuserid =
                      $this->authorization->getUser()->userid
                    ;
                    $mailingAddress->save();
                  }
                }

                // Para finalizar, renomeia os arquivos, de forma que os
                // mesmos tenham como nome a UUID deste contratante.
                // Recupera a UUID deste contratante
                $newContractor = Contractor::where('entityid', $contractorID)
                  ->first()
                ;
                $UUID = $newContractor->entityuuid;
                $currentUserID =
                  $this->authorization->getUser()->userid
                ;

                // Agora renomeia os arquivos
                $contractorData['logonormal'] = $this->renameFile(
                  $logoDirectory,
                  $contractorData['logonormal'],
                  'Logo',
                  $UUID,
                  'N'
                );
                $contractorData['logoinverted'] = $this->renameFile(
                  $logoDirectory,
                  $contractorData['logoinverted'],
                  'Logo',
                  $UUID,
                  'I'
                );

                // Acrescenta um tipo de parcelamento padrão
                $installmentType = new InstallmentType();
                $installmentType->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Até 12 vezes sem juros',
                  'minimuminstallmentvalue' => 50.00,
                  'maxnumberofinstallments' => 12,
                  'interestrate' => 0.000,
                  'interestfrom' => 0,
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $installmentType->save();

                // Acrescenta um conjunto padrão de tipos de cobranças
                $billingTypes = [];
                // 1. Adesão
                $billingType = new BillingType();
                $billingType->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Adesão',
                  'description' => 'A taxa de adesão é um valor cobrado para cobrir os custos relacionados ao início da relação com o cliente.',
                  'rateperequipment' => true,
                  'inattendance' => false,
                  'preapproved' => false,
                  'billingmomentid' => '{2}',
                  'installmenttypeid' => 1,
                  'executiontime' => '00:00:00',
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $billingType->save();
                $billingTypes[1] = $billingType->billingtypeid;
                // 2. Serviço de instalação
                $billingType = new BillingType();
                $billingType->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Serviço de instalação',
                  'description' => 'Serviço em que o equipamento de rastreamento é instalado, bem como acessórios e outros dispositivos acoplados, tais como botão de pânico, sirene, etc. Está incluso a fiação para conexão do equipamento.',
                  'rateperequipment' => true,
                  'inattendance' => true,
                  'preapproved' => true,
                  'billingmomentid' => '{1,2}',
                  'installmenttypeid' => 1,
                  'executiontime' => '01:00:00',
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $billingType->save();
                $billingTypes[2] = $billingType->billingtypeid;
                // 3. Serviço de reinstalação
                $billingType = new BillingType();
                $billingType->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Serviço de reinstalação',
                  'description' => 'Serviço em que o equipamento de rastreamento é reinstalado, incluíndo acessórios nele acoplados, garantindo seu pleno funcionamento.',
                  'rateperequipment' => true,
                  'inattendance' => true,
                  'preapproved' => false,
                  'billingmomentid' => '{1}',
                  'installmenttypeid' => 1,
                  'executiontime' => '01:00:00',
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $billingType->save();
                $billingTypes[3] = $billingType->billingtypeid;
                // 4. Serviço de manutenção
                $billingType = new BillingType();
                $billingType->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Serviço de manutenção',
                  'description' => 'Serviço que realiza a manutenção do equipamento e acessórios nele acoplados, garantindo seu pleno funcionamento.',
                  'rateperequipment' => true,
                  'inattendance' => true,
                  'preapproved' => false,
                  'billingmomentid' => '{1}',
                  'installmenttypeid' => null,
                  'executiontime' => '01:00:00',
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $billingType->save();
                $billingTypes[4] = $billingType->billingtypeid;
                // 5. Transferência de equipamento
                $billingType = new BillingType();
                $billingType->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Transferência de equipamento',
                  'description' => 'Serviço que realiza a transferência do equipamento e acessórios de um veículo para outro.',
                  'rateperequipment' => true,
                  'inattendance' => true,
                  'preapproved' => false,
                  'billingmomentid' => '{1}',
                  'installmenttypeid' => 1,
                  'executiontime' => '01:00:00',
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $billingType->save();
                $billingTypes[5] = $billingType->billingtypeid;
                // 6. Retirada de equipamento
                $billingType = new BillingType();
                $billingType->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Retirada de equipamento',
                  'description' => 'Serviço de retirada do equipamento (e acessórios, se necessário).',
                  'rateperequipment' => true,
                  'inattendance' => true,
                  'preapproved' => true,
                  'billingmomentid' => '{1,3,4}',
                  'installmenttypeid' => null,
                  'executiontime' => '00:30:00',
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                // 7. Acessório
                $billingType = new BillingType();
                $billingType->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Acessório',
                  'description' => 'Cobrança de acessório acoplado ao equipamento de rastreamento.',
                  'rateperequipment' => true,
                  'inattendance' => false,
                  'preapproved' => false,
                  'billingmomentid' => '{5}',
                  'installmenttypeid' => null,
                  'executiontime' => '00:00:00',
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $billingType->save();
                $billingTypes[7] = $billingType->billingtypeid;
                // 8. Técnico fixo
                $billingType = new BillingType();
                $billingType->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Técnico fixo',
                  'description' => 'Técnico especializado disponibilizado pela contratada para permanecer nas dependências do cliente para executar quaisquer serviços técnicos necessários nos equipamentos de rastreamento da contratada, tais como instalação, manutenção, transferência de equipamento, dentre outras.',
                  'rateperequipment' => false,
                  'inattendance' => false,
                  'preapproved' => false,
                  'billingmomentid' => '{5}',
                  'installmenttypeid' => null,
                  'executiontime' => '00:00:00',
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $billingType->save();
                $billingTypes[8] = $billingType->billingtypeid;

                // TODO: Precisa rever para acrescentar os planos padrão
                //       de uma nova instalação
                // Adiciona um modelo de contrato padrão
                $contractType = new ContractType();
                $contractType->fill([
                  'contractorid' => $contractorID,
                  'name' => 'Contrato padrão',
                  'banktariff' => 0.00,
                  'banktariffforreissuing' => 0.00,
                  'finetype' => 1,
                  'finevalue' => 0.00,
                  'interesttype' => 1,
                  'interestvalue' => 0.00,
                  'active' => true,
                  'allowextendingdeadline' => true,
                  'prorata' => true,
                  'duedateonlyinworkingdays' => true,
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $contractType->save();
                $contractTypeID = $contractType->contracttypeid;

                // Incluímos todos os valores cobrados neste tipo de
                // contrato
                // 1. Taxa de adesão
                $charge = new ContractTypeCharge();
                $charge->fill([
                  'contractorid' => $contractorID,
                  'contracttypeid' => $contractTypeID,
                  'name' => 'Taxa de adesão',
                  'billingtypeid' => $billingTypes[1],
                  'chargetype' => 1,
                  'chargevalue' => 80.000,
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $charge->save();
                // 2. Aluguel de equipamento
                $charge = new ContractTypeCharge();
                $charge->fill([
                  'contractorid' => $contractorID,
                  'contracttypeid' => $contractTypeID,
                  'name' => 'Aluguel de equipamento',
                  'billingtypeid' => $billingTypes[2],
                  'chargetype' => 1,
                  'chargevalue' => 60.000,
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $charge->save();
                // 3. Fidelidade
                $charge = new ContractTypeCharge();
                $charge->fill([
                  'contractorid' => $contractorID,
                  'contracttypeid' => $contractTypeID,
                  'name' => 'Fidelidade',
                  'billingtypeid' => $billingTypes[3],
                  'chargetype' => 2,
                  'chargevalue' => 8.334,
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $charge->save();
                // 4. Taxa de instalação
                $charge = new ContractTypeCharge();
                $charge->fill([
                  'contractorid' => $contractorID,
                  'contracttypeid' => $contractTypeID,
                  'name' => 'Taxa de instalação',
                  'billingtypeid' => $billingTypes[4],
                  'chargetype' => 1,
                  'chargevalue' => 50.000,
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $charge->save();
                // 5. Taxa de reinstalação
                $charge = new ContractTypeCharge();
                $charge->fill([
                  'contractorid' => $contractorID,
                  'contracttypeid' => $contractTypeID,
                  'name' => 'Taxa de reinstalação',
                  'billingtypeid' => $billingTypes[5],
                  'chargetype' => 1,
                  'chargevalue' => 50.000,
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $charge->save();
                // 6. Taxa de manutenção
                $charge = new ContractTypeCharge();
                $charge->fill([
                  'contractorid' => $contractorID,
                  'contracttypeid' => $contractTypeID,
                  'name' => 'Taxa de manutenção',
                  'billingtypeid' => $billingTypes[5],
                  'chargetype' => 1,
                  'chargevalue' => 50.000,
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $charge->save();
                // 7. Transferência de equipamento
                $charge = new ContractTypeCharge();
                $charge->fill([
                  'contractorid' => $contractorID,
                  'contracttypeid' => $contractTypeID,
                  'name' => 'Transferência de equipamento',
                  'billingtypeid' => $billingTypes[5],
                  'chargetype' => 1,
                  'chargevalue' => 50.000,
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $charge->save();
                // 8. Retirada de equipamento
                $charge = new ContractTypeCharge();
                $charge->fill([
                  'contractorid' => $contractorID,
                  'contracttypeid' => $contractTypeID,
                  'name' => 'Retirada de equipamento',
                  'billingtypeid' => $billingTypes[6],
                  'chargetype' => 1,
                  'chargevalue' => 0.000,
                  'createdbyuserid' => $currentUserID,
                  'updatedbyuserid' => $currentUserID
                ]);
                $charge->save();

                // Efetiva a transação
                $this->DB->commit();

                // Registra o sucesso
                $this->info("Cadastrado o contratante '{name}' com "
                  . "sucesso.",
                  ['name'  => $contractorData['name']]
                );

                // Alerta o usuário
                $this->flash("success", "O contratante <i>'{name}'</i> "
                  . "foi cadastrado com sucesso.",
                  ['name'  => $contractorData['name']]
                );

                // Registra o evento
                $this->debug("Redirecionando para {routeName}",
                  ['routeName' => 'ADM\Cadastre\Contractors']
                );

                // Redireciona para a página de gerenciamento de
                // contratantes
                return $this->redirect($response,
                  'ADM\Cadastre\Contractors'
                );
              }
            } else {
              // Registra o erro
              $this->debug("Não foi possível inserir as informações do "
                . "contratante '{name}'. Já existe outro contatante "
                . "com o mesmo nome.",
                ['name'  => $contractorData['name']]
              );

              // Apaga os arquivos enviados/criados
              $this->deleteFile(
                $logoDirectory,
                $contractorData['logonormal']
              );
              $this->deleteFile(
                $logoDirectory,
                $contractorData['logoinverted']
              );

              // Adiciona logomarcas vazias novamente
              $this->validator->setValue(
                'logonormal',
                $this->emptyLogo
              );
              $this->validator->setValue(
                'logoinverted',
                $this->emptyLogo
              );

              // Alerta o usuário
              $this->flashNow("error", "Já existe um contratante com o "
                . "nome <i>'{name}'</i>.",
                ['name' => $contractorData['name']]
              );
            }
          }
          catch (UploadFileException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível inserir as informações do "
              . "contratante '{name}'. {error}",
              [
                'name'  => $contractorData['name'],
                'error' => $exception->getMessage()
              ]
            );

            // Apaga os arquivos enviados/criados
            $this->deleteFile(
              $logoDirectory,
              $contractorData['logonormal']
            );
            $this->deleteFile(
              $logoDirectory,
              $contractorData['logoinverted']
            );

            // Adiciona logomarcas vazias novamente
            $this->validator->setValue(
              'logonormal',
              $this->emptyLogo
            );
            $this->validator->setValue(
              'logoinverted',
              $this->emptyLogo
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível inserir as "
              . "informações do contratante. Erro interno {error}"
            );
          }
          catch(QueryException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível inserir as informações do "
              . "contratante '{name}'. Erro interno no banco de dados: "
              . "{error}",
              [
                'name'  => $contractorData['name'],
                'error' => $exception->getMessage()
              ]
            );

            // Apaga os arquivos enviados/criados
            $this->deleteFile(
              $logoDirectory,
              $contractorData['logonormal']
            );
            $this->deleteFile(
              $logoDirectory,
              $contractorData['logoinverted']
            );

            // Adiciona logomarcas vazias novamente
            $this->validator->setValue(
              'logonormal',
              $this->emptyLogo
            );
            $this->validator->setValue(
              'logoinverted',
              $this->emptyLogo
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível inserir as "
              . "informações do contratante. Erro interno no banco de "
              . "dados."
            );
          }
          catch(Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível inserir as informações do "
              . "contratante '{name}'. Erro interno: {error}",
              [
                'name'  => $contractorData['name'],
                'error' => $exception->getMessage()
              ]
            );

            // Apaga os arquivos enviados/criados
            $this->deleteFile(
              $logoDirectory,
              $contractorData['logonormal']
            );
            $this->deleteFile(
              $logoDirectory,
              $contractorData['logoinverted']
            );

            // Adiciona logomarcas vazias novamente
            $this->validator->setValue(
              'logonormal',
              $this->emptyLogo
            );
            $this->validator->setValue(
              'logoinverted',
              $this->emptyLogo
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível inserir as "
              . "informações do contratante. Erro interno."
            );
          }
        } else {
          // Adiciona logomarcas vazias novamente
          $this->validator->setValue('logonormal', $this->emptyLogo);
          $this->validator->setValue('logoinverted', $this->emptyLogo);
        }
      } else {
        $this->debug(
          'Os dados do contratante são INVÁLIDOS'
        );
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }

        // Adiciona logomarcas vazias novamente
        $this->validator->setValue('logonormal', $this->emptyLogo);
        $this->validator->setValue('logoinverted', $this->emptyLogo);
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      $this->validator->setValues([
        'entityid' => 0,
        'entitytypeid' => $entityTypes[0]->toArray()['id'],
        'juridicalperson' => $entityTypes[0]
          ->toArray()['juridicalperson'],
        'logonormal' => $this->emptyLogo,
        'logoinverted' => $this->emptyLogo,
        'subsidiaries' => [
          0 => [
            'subsidiaryid' => 0,
            'headoffice' => true,
            'name' => 'Matriz',
            'regionaldocumenttype' => 4,
            'genderid' => 1,
            'maritalstatusid' => 1,
            'cityid' => 0,
            'cityname' => '',
            'state' => '',
            'phones' => [[
              'phoneid' => 0,
              'phonenumber' => '',
              'phonetypeid' => 1
            ]],
            'emails' => [[
              'mailingid' => 0,
              'email' => ''
            ]],
            'contacts' => [
            ]
          ]
        ]
      ]);
    }

    // Exibe um formulário para adição de um contratante

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Contratantes',
      $this->path('ADM\Cadastre\Contractors')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Cadastre\Contractors\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de contratante.");

    return $this->render($request, $response,
      'adm/cadastre/contractors/contractor.twig',
      [
        'formMethod' => 'POST',
        'entityTypes' => $entityTypes,
        'documentTypes' => $documentTypes,
        'genders' => $genders,
        'maritalStatus' => $maritalStatus,
        'phoneTypes' => $phoneTypes
      ]
    );
  }

  /**
   * Exibe um formulário para edição de um contratante, quando
   * solicitado, e confirma os dados enviados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function edit(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    try {
      // Recupera o ID do contratante
      $contractorID = $args['contractorID'];

      // Recupera as informações de tipos de documentos
      $documentTypes = $this->getDocumentTypes();

      // Recupera as informações de gêneros
      $genders = $this->getGenders();

      // Recupera as informações de estados civis
      $maritalStatus = $this->getMaritalStatus();

      // Recupera as informações de tipos de telefones
      $phoneTypes = $this->getPhoneTypes();

      // Recupera as informações de perfis de notificação
      $mailingProfiles = $this->getMailingProfiles($contractorID);

      // Recupera um perfil padrão para os novos contatos
      $defaultMailingProfileID = $this->getDefaultMailingProfile(
        $contractorID
      );

      // Recupera o local de armazenamento das imagens
      $logoDirectory = $this->container['settings']['storage']['images'];
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        ['routeName' => 'ERP\Cadastre\Contractors']
      );

      // Redireciona para a página de gerenciamento de contratantes
      return $this->redirect($response, 'ERP\Cadastre\Contractors');
    }

    try
    {
      // Recupera as informações do contratante
      $contractor = Contractor::join("entitiestypes",
            "entities.entitytypeid", '=', "entitiestypes.entitytypeid"
          )
        ->join("users as createduser", "entities.createdbyuserid",
            '=', "createduser.userid"
          )
        ->join("users as updateduser", "entities.updatedbyuserid",
            '=',"updateduser.userid"
          )
        ->where("entities.contractor", "true")
        ->where("entities.entityid", '=', $contractorID)
        ->get([
            'entitiestypes.name as entitytypename',
            'entitiestypes.juridicalperson',
            'entities.*',
            'createduser.name as createdbyusername',
            'updateduser.name as updatedbyusername'
          ])
      ;

      if ( $contractor->isEmpty() ) {
        throw new ModelNotFoundException(
          "Não temos nenhum contratante com o código {$contractorID} "
            . "cadastrado"
        );
      }
      $contractor = $contractor
        ->first()
        ->toArray()
      ;

      // Adiciona as imagens das logomarcas
      $searchText = $logoDirectory . DIRECTORY_SEPARATOR
        . "Logo_" . $contractor['entityuuid'] . "_?.*"
      ;
      $files = glob($searchText);
      if (count($files) > 0) {
        // Processa cada arquivo individualmente
        foreach ($files as $imageFile) {
          // Codifica o conteúdo do arquivo em Base64
          $imageDataBase64 = $this->readBase64Image($imageFile);

          // Em função do sufixo presente no nome do arquivo, associa o
          // conteúdo da imagem ao respectivo campo
          switch ($this->getImageSuffix($imageFile)) {
            case 'N':
              $contractor['logonormal'] = $imageDataBase64;

              break;
            case 'I':
              $contractor['logoinverted'] = $imageDataBase64;

              break;
          }
        }
      } else {
        // O arquivo não foi localizado, então retorna arquivos vazios

        // Determina informações do arquivo
        $resourcesDir = $this->app->getPublicDir()
          . DIRECTORY_SEPARATOR . 'resources'
        ;
        $imageFile = $resourcesDir . DIRECTORY_SEPARATOR
          . "unknown.png"
        ;
        $imageData = file_get_contents($imageFile);
        $mimeType = pathinfo($imageFile, PATHINFO_EXTENSION);
        $imageDataBase64 = 'data:image/' . $mimeType . ';base64,'
          . base64_encode($imageData)
        ;
        $contractor['logonormal'] = $imageDataBase64;
        $contractor['logoinverted'] = $imageDataBase64;
      }

      // Agora recupera as informações das unidades filiais
      $contractor['subsidiaries'] = Subsidiary::join("cities",
            "subsidiaries.cityid", '=', "cities.cityid"
          )
        ->join("documenttypes", "subsidiaries.regionaldocumenttype",
            '=', "documenttypes.documenttypeid"
          )
        ->where("entityid", $contractorID)
        ->orderBy("subsidiaryid")
        ->get([
            'subsidiaries.*',
            'documenttypes.name as regionaldocumenttypename',
            'cities.name as cityname',
            'cities.state as state'
          ])
        ->toArray()
      ;

      // Por último, para cada unidade/filial, recupera as informações
      // de telefones, e-mails e contatos adicionais
      foreach ($contractor['subsidiaries'] as $row => $subsidiary) {
        // Telefones
        $phones = $this
          ->getPhones(
              $contractorID,
              $subsidiary['subsidiaryid']
            )
        ;
        if ($phones->isEmpty()) {
          // Criamos os dados de telefone em branco
          $contractor['subsidiaries'][$row]['phones'] = [
            [
              'phoneid' => 0,
              'phonetypeid' => 1,
              'phonenumber' => ''
            ]
          ];
        } else {
          $contractor['subsidiaries'][$row]['phones'] =
            $phones->toArray()
          ;
        }

        // E-mails
        $emails = $this
          ->getEmails(
              $contractorID,
              $subsidiary['subsidiaryid']
            )
        ;
        if ( $emails->isEmpty() ) {
          // Criamos os dados de e-mail em branco
          $contractor['subsidiaries'][$row]['emails'] = [
            [
              'mailingid' => 0,
              'email' => ''
            ]
          ];
        } else {
          $contractor['subsidiaries'][$row]['emails'] =
            $emails->toArray()
          ;
        }

        // Contatos adicionais
        $contacts = $this
          ->getContacts(
              $contractorID,
              $subsidiary['subsidiaryid']
            )
        ;
        if ( !$contacts->isEmpty() ) {
          $contractor['subsidiaries'][$row]['contacts'] =
            $contacts->toArray()
          ;
        }
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o contratante código "
        . "{contractorID}.",
        ['contractorID' => $contractorID]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este "
        . "contratante."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        ['routeName' => 'ADM\Cadastre\Contractors']
      );

      // Redireciona para a página de gerenciamento de contratantes
      return $this->redirect($response, 'ADM\Cadastre\Contractors');
    }

    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados

      // TODO: Precisa analisar uma forma de remover arquivos enviados
      // em caso de erro no formulário

      // Registra o acesso
      $this->debug("Processando à edição do contratante '{name}'.",
        ['name' => $contractor['name']]
      );

      // Monta uma matriz para validação dos tipos de documentos
      $regionalDocumentTypes = [];
      foreach ($documentTypes as $documentType) {
        $regionalDocumentTypes[$documentType->id] =
          $documentType->name
        ;
      }

      // Valida os dados
      $this->validator->validate(
        $request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do contratante são VÁLIDOS');

        // Recupera os dados modificados do contratante
        $contractorData = $this->validator->getValues();

        // Verifica se as inscrições estaduais são válidas. Inicialmente
        // considera todas válidas e, durante a análise, se alguma não
        // for válida, então registra qual delas está incorreta
        $allHasValid = true;
        foreach ($contractorData['subsidiaries']
          as $subsidiaryNumber => $subsidiary) {
          // Recupera o tipo de documento
          $documentTypeID = $subsidiary['regionaldocumenttype'];

          // Se o tipo de documento for 'Inscrição Estadual' precisa
          // verificar se o valor informado é válido
          if (
            $regionalDocumentTypes[$documentTypeID]
            === 'Inscrição Estadual'
          ) {
            try {
              // Verifica se a UF foi informada
              if ((strtolower($subsidiary['regionaldocumentnumber'])
                  !== 'isento')
                && (empty($subsidiary['regionaldocumentstate']))
              ) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro neste campo
                $this->validator->setErrors(
                  [
                    'regionaldocumentstate' => 'UF precisa ser '
                      . 'preenchido(a)'
                  ],
                  "subsidiaries[{$subsidiaryNumber}][regionaldocumentstate]"
                );
              } else {
                // Verifica se a inscrição estadual é válida
                if (!(StateRegistration::isValid(
                  $subsidiary['regionaldocumentnumber'],
                  $subsidiary['regionaldocumentstate']
                ))) {
                  // Invalida o formulário
                  $allHasValid = false;

                  // Seta o erro neste campo
                  $this->validator->setErrors(
                    [
                      'stateRegistration' =>
                      'A inscrição estadual não é válida'
                    ],
                    "subsidiaries[{$subsidiaryNumber}][regionaldocumentnumber]"
                  );
                }
              }
            } catch (InvalidArgumentException $exception) {
              // Ocorreu uma exceção, então invalida o formulário
              $allHasValid = false;

              // Seta o erro neste campo
              $this->validator->setErrors(
                [
                  'state' => $exception->getMessage()
                ],
                "subsidiaries[{$subsidiaryNumber}][regionaldocumentnumber]"
              );
            }
          }

          if (array_key_exists('contacts', $subsidiary)) {
            // Verifica se os contatos adicionais contém ao menos uma
            // informação de contato, seja o telefone ou o e-mail
            foreach ($subsidiary['contacts']
              as $contactNumber => $contactData) {
              // Verifica se foi fornecido um telefone ou e-mail
              if (
                empty($contactData['email']) &&
                empty($contactData['phonenumber'])
              ) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro nestes campos
                $this->validator->setErrors(
                  [
                    'email' => "Informe um e-mail ou telefone para "
                      . "contato"
                  ],
                  "subsidiaries[{$subsidiaryNumber}][contacts][{$contactNumber}][email]"
                );
                $this->validator->setErrors(
                  [
                    'phonenumber' => "Informe um e-mail ou telefone para "
                      . "contato"
                  ],
                  "subsidiaries[{$subsidiaryNumber}][contacts][{$contactNumber}][phonenumber]"
                );
              }
            }
          }
        }

        if ($allHasValid) {
          try {
            // Recupera a ID do contratante
            $UUID = $contractor['entityuuid'];

            // Primeiramente lida com as logomarcas do contratante

            // Recupera os arquivos das logomarcas, se enviados
            $uploadedFiles = $request->getUploadedFiles();

            // Lida com a logomarca para fundo claro
            $uploadedFile = $uploadedFiles['logonormal'];

            if ($this->fileHasBeenTransferred($uploadedFile)) {
              // Move o arquivo para a pasta de armazenamento e
              // armazena o nome do arquivo
              $contractorData['logonormal'] =
                $this->moveFile($logoDirectory, $uploadedFile)
              ;
            } else {
              // Não foi enviado nenhum arquivo com uma imagem da
              // logomarca para uso em locais com fundo claro, então
              // mantém a imagem original
              $contractorData['logonormal'] =
                $this->writeBase64Image(
                  $logoDirectory,
                  $contractor['logonormal']
                )
              ;
            }

            // Lida com a logomarca para fundo escuro
            $uploadedFile = $uploadedFiles['logoinverted'];

            if ($this->fileHasBeenTransferred($uploadedFile)) {
              // Move o arquivo para a pasta de armazenamento e armazena
              // o nome do arquivo
              $contractorData['logoinverted'] =
                $this->moveFile($logoDirectory, $uploadedFile)
              ;
            } else {
              // Não foi enviado nenhum arquivo com uma imagem da
              // logomarca para uso em locais com fundo escuro, então
              // mantém a imagem original
              $contractorData['logoinverted'] =
                $this->writeBase64Image(
                  $logoDirectory,
                  $contractor['logoinverted']
                )
              ;
            }

            // Iniciamos a transação
            $this->DB->beginTransaction();

            // Separamos as informações das unidades/filiais do
            // restante dos dados do contratante
            $subsidiariesData = $contractorData['subsidiaries'];
            unset($contractorData['subsidiaries']);

            // Não permite modificar o tipo de entidade nem a
            // informação de que o mesmo é contratante
            unset($contractorData['entitytypeid']);
            unset($contractorData['contractor']);

            // ================================[ Unidades/Filiais ]=====
            // Recupera as informações das unidades/filiais e separa os
            // dados para as operações de inserção, atualização e
            // remoção.
            // =========================================================

            // -------------------------------[ Pré-processamento ]-----

            // Analisa as unidades/filiais informadas, de forma a
            // separar quais unidades precisam ser adicionadas,
            // removidas e atualizadas

            // Matrizes que armazenarão os dados das unidades/filiais a
            // serem adicionados, atualizados e removidos
            $newSubsidiaries = [];
            $updSubsidiaries = [];
            $delSubsidiaries = [];

            // Os IDs das unidades/filiais mantidos para permitir
            // determinar as unidades/filiais a serem removidas
            $heldSubsidiaries = [];

            // Determina quais unidades serão mantidas (e atualizadas)
            // e as que precisam ser adicionadas (novas)
            foreach ($subsidiariesData as $subsidiary) {
              if (empty($subsidiary['subsidiaryid'])) {
                // Unidade/filial nova
                $newSubsidiaries[] = $subsidiary;
              } else {
                // Unidade/filial existente
                $heldSubsidiaries[] = $subsidiary['subsidiaryid'];
                $updSubsidiaries[]  = $subsidiary;
              }
            }

            // Recupera as unidades/filiais armazenadas atualmente
            $subsidiaries = Subsidiary::where("entityid", $contractorID)
              ->get([
                  'subsidiaryid'
                ])
              ->toArray()
            ;
            $oldSubsidiaries = [];
            foreach ($subsidiaries as $subsidiary) {
              $oldSubsidiaries[] = $subsidiary['subsidiaryid'];
            }

            // Verifica quais as unidades/filiais estavam na base de
            // dados e precisam ser removidas
            $delSubsidiaries = array_diff(
              $oldSubsidiaries,
              $heldSubsidiaries
            );

            // ----------------------------------------[ Gravação ]-----

            // Grava as informações do contratante
            $contractorChanged = Contractor::findOrFail($contractorID);
            $contractorChanged->fill($contractorData);
            $contractorChanged->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $contractorChanged->save();

            // Primeiro apagamos as unidades/filiais removidas pelo
            // usuário durante a edição
            foreach ($delSubsidiaries as $subsidiaryID) {
              // Apaga cada unidade/filial e seus respectivos contatos
              $subsidiary = Subsidiary::findOrFail($subsidiaryID);
              $subsidiary->deleteCascade();
            }

            // Agora inserimos as novas unidades/filiais
            foreach ($newSubsidiaries as $subsidiaryData) {
              // Separamos as informações dos dados de telefones dos
              // demais dados desta unidade/filial
              $phonesData = $subsidiaryData['phones'];
              unset($subsidiaryData['phones']);

              // Separamos as informações dos dados de emails dos demais
              // dados desta unidade/filial
              $emailsData = $subsidiaryData['emails'];
              unset($subsidiaryData['emails']);

              // Separamos as informações dos dados de contatos
              // adicionais dos demais dados desta unidade/filial
              $contactsData = $subsidiaryData['contacts'];
              unset($subsidiaryData['contacts']);

              // Sempre mantém a UF do documento em maiúscula
              $subsidiaryData['regionaldocumentstate'] = strtoupper(
                $subsidiaryData['regionaldocumentstate']
              );

              // Retiramos o campo de ID da unidade/filial, pois os
              // dados tratam de um novo registro
              unset($subsidiaryData['subsidiaryid']);

              // Incluímos a nova unidade/filial
              $subsidiary = new Subsidiary();
              if ($contractor['entitytypeid'] == 2) {
                unset($subsidiaryData['personname']);
                unset($subsidiaryData['department']);
              } else {
                unset($subsidiaryData['birthday']);
                unset($subsidiaryData['age']);
                unset($subsidiaryData['maritalstatusid']);
                unset($subsidiaryData['genderid']);
              }
              $subsidiary->fill($subsidiaryData);
              $subsidiary->entityid = $contractorID;
              $subsidiary->createdbyuserid =
                $this->authorization->getUser()->userid
              ;
              $subsidiary->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $subsidiary->save();
              $subsidiaryID = $subsidiary->subsidiaryid;

              // Incluímos os dados de telefones para esta
              // unidade/filial
              foreach ($phonesData as $phoneData) {
                // Retiramos o campo de ID do telefone, pois os dados
                // tratam de um novo registro
                unset($phoneData['phoneid']);

                // Incluímos um novo telefone desta unidade/filial
                $phone = new Phone();
                $phone->fill($phoneData);
                $phone->entityid     = $contractorID;
                $phone->subsidiaryid = $subsidiaryID;
                $phone->save();
              }

              // Incluímos os dados de emails para esta
              // unidade/filial
              foreach ($emailsData as $emailData) {
                // Retiramos o campo de ID do e-mail, pois os dados
                // tratam de um novo registro
                unset($emailData['mailingid']);

                // Como podemos não ter um endereço de e-mail, então
                // ignora caso ele não tenha sido fornecido
                if (trim($emailData['email']) !== '') {
                  // Incluímos um novo e-mail desta unidade/filial
                  $mailing = new Mailing();
                  $mailing->fill($emailData);
                  $mailing->entityid     = $contractorID;
                  $mailing->subsidiaryid = $subsidiaryID;
                  $mailing->save();
                }
              }

              // Incluímos os dados de contatos adicionais para esta
              // unidade/filial
              foreach ($contactsData as $contactData) {
                // Retiramos o campo de ID do contato, pois os dados
                // tratam de um novo registro
                unset($contactData['mailingaddressid']);

                // Incluímos um novo contato desta unidade/filial
                $mailingAddress = new MailingAddress();
                $mailingAddress->fill($contactData);
                $mailingAddress->entityid        = $contractorID;
                $mailingAddress->subsidiaryid    = $subsidiaryID;
                $mailingAddress->createdbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $mailingAddress->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $mailingAddress->save();
              }
            }

            // Por último, modificamos as unidades/filiais mantidas
            foreach ($updSubsidiaries as $subsidiaryData) {
              // Retiramos o campo de ID da unidade/filial
              $subsidiaryID = $subsidiaryData['subsidiaryid'];
              unset($subsidiaryData['subsidiaryid']);
              unset($subsidiaryData['entityid']);

              // Sempre mantém a UF do documento em maiúscula
              $subsidiaryData['regionaldocumentstate'] =
                strtoupper($subsidiaryData['regionaldocumentstate'])
              ;

              // Separamos as informações dos dados de telefones dos
              // demais dados desta unidade/filial
              $phonesData = $subsidiaryData['phones'];
              unset($subsidiaryData['phones']);

              // Separamos as informações dos dados de emails dos demais
              // dados desta unidade/filial
              $emailsData = $subsidiaryData['emails'];
              unset($subsidiaryData['emails']);

              // Separamos as informações dos dados de contatos
              // adicionais dos demais dados desta unidade/filial
              $contactsData = $subsidiaryData['contacts'];
              unset($subsidiaryData['contacts']);

              // Grava as alterações dos dados da unidade/filial
              $subsidiary = Subsidiary::findOrFail($subsidiaryID);
              if ($contractor['entitytypeid'] == 2) {
                unset($subsidiaryData['personname']);
                unset($subsidiaryData['department']);
              } else {
                unset($subsidiaryData['birthday']);
                unset($subsidiaryData['age']);
                unset($subsidiaryData['maritalstatusid']);
                unset($subsidiaryData['genderid']);
              }
              $subsidiary->fill($subsidiaryData);
              $subsidiary->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $subsidiary->save();

              // =====================================[ Telefones ]=====
              // Recupera as informações de telefones desta unidade e
              // separa os dados para as operações de inserção,
              // atualização e remoção dos mesmos.
              // =======================================================

              // -----------------------------[ Pré-processamento ]-----

              // Matrizes que armazenarão os dados dos telefones a serem
              // adicionados, atualizados e removidos
              $newPhones = [];
              $updPhones = [];
              $delPhones = [];

              // Os IDs dos telefones mantidos para permitir determinar
              // àqueles a serem removidos
              $heldPhones = [];

              // Determina quais telefones serão mantidos (e atualizados)
              // e os que precisam ser adicionados (novos)
              foreach ($phonesData as $phoneData) {
                if (empty($phoneData['phoneid'])) {
                  // Telefone novo
                  unset($phoneData['phoneid']);
                  $newPhones[] = $phoneData;
                } else {
                  // Telefone existente
                  $heldPhones[] = $phoneData['phoneid'];
                  $updPhones[]  = $phoneData;
                }
              }

              // Recupera os telefones armazenados atualmente
              $currentPhones = Phone::where('subsidiaryid', $subsidiaryID)
                ->get(['phoneid'])
                ->toArray()
              ;
              $actPhones = [];
              foreach ($currentPhones as $phoneData) {
                $actPhones[] = $phoneData['phoneid'];
              }

              // Verifica quais os telefones estavam na base de dados e
              // precisam ser removidos
              $delPhones = array_diff($actPhones, $heldPhones);

              // --------------------------------------[ Gravação ]-----

              // Primeiro apagamos os telefones removidos pelo usuário
              // durante a edição
              foreach ($delPhones as $phoneID) {
                // Apaga cada telefone
                $phone = Phone::findOrFail($phoneID);
                $phone->delete();
              }

              // Agora inserimos os novos telefones
              foreach ($newPhones as $phoneData) {
                // Incluímos um novo telefone nesta unidade/filial
                unset($phoneData['phoneid']);
                $phone = new Phone();
                $phone->fill($phoneData);
                $phone->entityid     = $contractorID;
                $phone->subsidiaryid = $subsidiaryID;
                $phone->save();
              }

              // Por último, modificamos os telefones mantidos
              foreach ($updPhones as $phoneData) {
                // Retira a ID do contato
                $phoneID = $phoneData['phoneid'];
                unset($phoneData['phoneid']);

                // Por segurança, nunca permite modificar qual a ID da
                // entidade mãe
                unset($phoneData['entityid']);
                unset($phoneData['subsidiaryid']);

                // Grava as informações do telefone
                $phone = Phone::findOrFail($phoneID);
                $phone->fill($phoneData);
                $phone->save();
              }

              // =======================================[ E-mails ]=====
              // Recupera as informações de e-mails desta unidade e
              // separa os dados para as operações de inserção,
              // atualização e remoção dos mesmos.
              // =======================================================

              // -----------------------------[ Pré-processamento ]-----

              // Matrizes que armazenarão os dados dos e-mails a serem
              // adicionados, atualizados e removidos
              $newEmails = [];
              $updEmails = [];
              $delEmails = [];

              // Os IDs dos e-mails mantidos para permitir determinar
              // àqueles a serem removidos
              $heldEmails = [];

              // Determina quais e-mails serão mantidos (e atualizados)
              // e os que precisam ser adicionados (novos)
              foreach ($emailsData as $emailData) {
                // Ignora se o e-mail não contiver conteúdo
                if (trim($emailData['email']) === '') {
                  continue;
                }

                if (empty($emailData['mailingid'])) {
                  // E-mail novo
                  unset($emailData['mailingid']);
                  $newEmails[] = $emailData;
                } else {
                  // E-mail existente
                  $heldEmails[] = $emailData['mailingid'];
                  $updEmails[]  = $emailData;
                }
              }

              // Recupera os e-mails armazenados atualmente
              $currentEmails = Mailing::where('subsidiaryid', $subsidiaryID)
                ->get(['mailingid'])
                ->toArray()
              ;
              $actEmails = [];
              foreach ($currentEmails as $emailData) {
                $actEmails[] = $emailData['mailingid'];
              }

              // Verifica quais os e-mails estavam na base de dados e
              // precisam ser removidos
              $delEmails = array_diff($actEmails, $heldEmails);

              // --------------------------------------[ Gravação ]-----

              // Primeiro apagamos os e-mails removidos pelo usuário
              // durante a edição
              foreach ($delEmails as $emailID) {
                // Apaga cada e-mail
                $mailing = Mailing::findOrFail($emailID);
                $mailing->delete();
              }

              // Agora inserimos os novos e-mails
              foreach ($newEmails as $emailData) {
                // Incluímos um novo e-mail nesta unidade/filial
                unset($emailData['mailingid']);
                $mailing = new Mailing();
                $mailing->fill($emailData);
                $mailing->entityid     = $contractorID;
                $mailing->subsidiaryid = $subsidiaryID;
                $mailing->save();
              }

              // Por último, modificamos os e-mails mantidos
              foreach ($updEmails as $emailData) {
                // Retira a ID do contato
                $emailID = $emailData['mailingid'];
                unset($emailData['mailingid']);

                // Por segurança, nunca permite modificar qual a ID da
                // entidade mãe
                unset($emailData['entityid']);
                unset($emailData['subsidiaryid']);

                // Grava as informações do e-mail
                $mailing = Mailing::findOrFail($emailID);
                $mailing->fill($emailData);
                $mailing->save();
              }

              // ===========================[ Contatos Adicionais ]=====
              // Recupera as informações de contatos adicionais desta
              // unidade e separa-os para as operações de inserção,
              // atualização e remoção dos mesmos.
              // =======================================================

              // -----------------------------[ Pré-processamento ]-----

              // Matrizes que armazenarão os dados dos contatos
              // adicionais a serem adicionados, atualizados e removidos
              $newContacts = [];
              $updContacts = [];
              $delContacts = [];

              // Os IDs dos contatos mantidos para permitir determinar
              // àqueles a serem removidos
              $heldContacts = [];

              // Determina quais contatos serão mantidos (e atualizadas)
              // e àqueles que precisam ser adicionados (novos)
              foreach ($contactsData as $contactData) {
                if (empty($contactData['mailingaddressid'])) {
                  // Contato novo
                  unset($contactData['mailingaddressid']);
                  $newContacts[] = $contactData;
                } else {
                  // Contato existente
                  $heldContacts[] = $contactData['mailingaddressid'];
                  $updContacts[]  = $contactData;
                }
              }

              // Recupera os contatos armazenados atualmente
              $currentContacts = MailingAddress::where('subsidiaryid', $subsidiaryID)
                ->get(['mailingaddressid'])
                ->toArray()
              ;
              $actContacts = [];
              foreach ($currentContacts as $contactData) {
                $actContacts[] = $contactData['mailingaddressid'];
              }

              // Verifica quais os contatos estavam na base de dados e
              // precisam ser removidos
              $delContacts = array_diff($actContacts, $heldContacts);

              // --------------------------------------[ Gravação ]-----

              // Primeiro apagamos os contatos removidos pelo usuário
              // durante a edição
              foreach ($delContacts as $mailingAddressID) {
                // Apaga cada contato
                $mailingAddress =
                  MailingAddress::findOrFail($mailingAddressID)
                ;
                $mailingAddress->delete();
              }

              // Agora inserimos os novos contatos
              foreach ($newContacts as $contactData) {
                // Incluímos um novo contato nesta unidade/filial
                unset($contactData['mailingaddressid']);
                $mailingAddress = new MailingAddress();
                $mailingAddress->fill($contactData);
                $mailingAddress->entityid     = $contractorID;
                $mailingAddress->subsidiaryid = $subsidiaryID;
                $mailingAddress->createdbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $mailingAddress->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $mailingAddress->save();
              }

              // Por último, modificamos os contatos mantidos
              foreach ($updContacts as $contactData) {
                // Retira a ID do contato
                $mailingAddressID = $contactData['mailingaddressid'];
                unset($contactData['mailingaddressid']);

                // Por segurança, nunca permite modificar qual a ID da
                // entidade mãe
                unset($contactData['entityid']);
                unset($contactData['subsidiaryid']);

                // Grava as informações do contato
                $mailingAddress = MailingAddress::findOrFail(
                  $mailingAddressID
                );
                $mailingAddress->fill($contactData);
                $mailingAddress->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $mailingAddress->save();
              }
            }

            // ======================================[ Logomarcas ]=====
            // Para finalizar, lida com os arquivos de logomarca
            // =========================================================

            // Localiza uma logomarca normal já existente
            $searchText = $logoDirectory . DIRECTORY_SEPARATOR
              . "Logo_{$UUID}_N.*"
            ;
            $files = glob($searchText);
            if (count($files) > 0) {
              // Substituímos o arquivo existente
              foreach ($files as $originalFilename) {
                // Recuperamos o nome do arquivo original, excluíndo o
                // diretório
                $path_parts       = pathinfo($originalFilename);
                $originalFilename = $path_parts['basename'];

                // Substitui o arquivo de imagem pelo novo arquivo
                $contractorData['logonormal'] =
                  $this->replaceFile(
                    $logoDirectory,
                    $originalFilename,
                    $contractorData['logonormal']
                  )
                ;
              }
            } else {
              // Renomeia o arquivo
              $contractorData['logonormal'] =
                $this->renameFile(
                  $logoDirectory,
                  $contractorData['logonormal'],
                  'Logo',
                  $UUID,
                  'N'
                )
              ;
            }

            // Localiza uma logomarca invertida já existente
            $searchText = $logoDirectory . DIRECTORY_SEPARATOR
              . "Logo_{$UUID}_I.*"
            ;
            $files = glob($searchText);
            if (count($files) > 0) {
              foreach ($files as $originalFilename) {
                // Recuperamos o nome do arquivo original, excluíndo o
                // diretório
                $path_parts       = pathinfo($originalFilename);
                $originalFilename = $path_parts['basename'];

                // Substitui o arquivo de imagem pelo novo arquivo
                $contractorData['logoinverted'] =
                  $this->replaceFile(
                    $logoDirectory,
                    $originalFilename,
                    $contractorData['logoinverted']
                  )
                ;
              }
            } else {
              // Renomeia o arquivo
              $contractorData['logoinverted'] =
                $this->renameFile(
                  $logoDirectory,
                  $contractorData['logoinverted'],
                  'Logo',
                  $UUID,
                  'I'
                )
              ;
            }

            // =========================================================

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("O contratante '{name}' foi modificado com "
              . "sucesso.",
              [ 'name' => $contractorData['name'] ]
            );

            // Alerta o usuário
            $this->flash("success", "O contratante <i>'{name}'</i> foi "
              . "modificado com sucesso.",
              [ 'name' => $contractorData['name'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Cadastre\Contractors' ]
            );

            // Redireciona para a página de gerenciamento de
            // contratantes
            return $this->redirect($response,
              'ADM\Cadastre\Contractors'
            );
          }
          catch(UploadFileException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "contratante '{name}'. {error}",
              [
                'name'  => $contractorData['name'],
                'error' => $exception->getMessage()
              ]
            );

            // Adiciona as logomarcas originais novamente
            $this->validator->setValue(
              'logonormal',
              $contractor['logonormal']
            );
            $this->validator->setValue(
              'logoinverted',
              $contractor['logoinverted']
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do contratante. {error}",
              ['error' => $exception->getMessage()]
            );
          }
          catch(QueryException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "contratante '{name}'. Erro interno no banco de dados: "
              . "{error}",
              [
                'name'  => $contractorData['name'],
                'error' => $exception->getMessage()
              ]
            );

            // Adiciona as logomarcas originais novamente
            $this->validator->setValue(
              'logonormal',
              $contractor['logonormal']
            );
            $this->validator->setValue(
              'logoinverted',
              $contractor['logoinverted']
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do contratante. Erro interno no banco de "
              . "dados."
            );
          }
          catch (Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "contratante '{name}'. Erro interno: {error}",
              [
                'name'  => $contractorData['name'],
                'error' => $exception->getMessage()
              ]
            );

            // Adiciona as logomarcas originais novamente
            $this->validator->setValue(
              'logonormal',
              $contractor['logonormal']
            );
            $this->validator->setValue(
              'logoinverted',
              $contractor['logoinverted']
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do contratante. Erro interno."
            );
          }
        } else {
          // Adiciona as logomarcas originais novamente
          $this->validator->setValue(
            'logonormal',
            $contractor['logonormal']
          );
          $this->validator->setValue(
            'logoinverted',
            $contractor['logoinverted']
          );
        }
      } else {
        $this->debug('Os dados do contratante são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }

        // Adiciona as logomarcas originais novamente
        $this->validator->setValue(
          'logonormal',
          $contractor['logonormal']
        );
        $this->validator->setValue(
          'logoinverted',
          $contractor['logoinverted']
        );
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($contractor);
    }

    // Exibe um formulário para edição de um contratante

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Contratantes',
      $this->path('ADM\Cadastre\Contractors')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Cadastre\Contractors\Edit', [
        'contractorID' => $contractorID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à edição do contratante '{name}'.",
      ['name' => $contractor['name']]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'adm/cadastre/contractors/contractor.twig',
      [
        'formMethod' => 'PUT',
        'documentTypes' => $documentTypes,
        'genders' => $genders,
        'maritalStatus' => $maritalStatus,
        'phoneTypes' => $phoneTypes,
        'mailingProfiles' => $mailingProfiles,
        'defaultMailingProfileID' => $defaultMailingProfileID
      ]
    );
  }

  /**
   * Remove o contratante.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function delete(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando à remoção de contratante.");

    // Recupera o ID
    $contractorID = $args['contractorID'];

    try {
      // Recupera as informações do contratante
      $contractor = Contractor::findOrFail($contractorID);

      // Recupera o local de armazenamento das logomarcas
      $logoDirectory =
        $this->container['settings']['storage']['images']
      ;

      // Agora apaga o contratante

      // Iniciamos a transação
      $this->DB->beginTransaction();

      // TODO: Não devemos mais apagar, mas sim marcar como excluído os
      //       registros, facilitando o processamento
      // Remove o contratante e suas unidades/filiais e dados
      // recursivamente
      $contractor->deleteCascade($logoDirectory);

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O contratante '{name}' foi removido com sucesso.",
        ['name' => $contractor->name]
      );

      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o contratante {$contractor->name}",
            'data' => "Delete"
          ])
      ;
    } catch (ModelNotFoundException $exception) {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o contratante código "
        . "{contractorID} para remoção.",
        ['contractorID' => $contractorID]
      );

      $message = "Não foi possível localizar o contratante para "
        . "remoção."
      ;
    } catch (QueryException $exception) {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "contratante '{name}'. Erro interno no banco de dados: "
        . "{error}",
        [
          'name'  => $contractor->name,
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível remover o contratante. Erro interno "
        . "no banco de dados."
      ;
    } catch (Exception $exception) {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "contratante '{name}'. Erro interno: {error}",
        [
          'name'  => $contractor->name,
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível remover o contratante. Erro "
        . "interno."
      ;
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null
        ])
    ;
  }

  /**
   * Alterna o estado do bloqueio de um contratante e/ou de uma
   * unidade/filial deste contratante.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function toggleBlocked(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando à mudança do estado de bloqueio de "
      . "contratante."
    );

    // Recupera o ID
    $contractorID = $args['contractorID'];
    $subsidiaryID = $args['subsidiaryID'];

    try {
      // Recupera as informações do contratante
      if (is_null($subsidiaryID)) {
        // Desbloqueia o contratante
        $contractor = Contractor::findOrFail($contractorID);
        $action     = $contractor->blocked
          ? "desbloqueado"
          : "bloqueado"
        ;
        $contractor->blocked = !$contractor->blocked;
        $contractor->updatedbyuserid = $this
          ->authorization
          ->getUser()
          ->userid
        ;
        $contractor->save();

        $message = "O contratante '{$contractor->name}' foi {$action} "
          . "com sucesso."
        ;
      } else {
        // Desbloqueia a unidade/filial
        $contractor = Contractor::findOrFail($contractorID);
        $subsidiary = Subsidiary::findOrFail($subsidiaryID);
        $action     = $subsidiary->blocked
          ? "desbloqueada"
          : "bloqueada"
        ;
        $subsidiary->blocked = !$subsidiary->blocked;
        $subsidiary->updatedbyuserid =
          $this->authorization->getUser()->userid
        ;
        $subsidiary->save();

        $message = "A unidade/filial '{$subsidiary->name}' do "
          . "contratante '{$contractor->name}' foi {$action} com "
          . "sucesso."
        ;
      }

      // Registra o sucesso
      $this->info($message);

      // Informa que a mudança foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => $message,
            'data' => "Delete"
          ])
      ;
    } catch (ModelNotFoundException $exception) {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível localizar o contratante código "
          . "{contractorID} para alternar o estado do bloqueio.",
          ['contractorID' => $contractorID]
        );

        $message = "Não foi possível localizar o contratante para "
          . "alternar o estado do bloqueio."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível localizar a unidade/filial "
          . "código {subsidiaryID} do contratante código "
          . "{contractorID} para alternar o estado do bloqueio.",
          [
            'contractorID' => $contractorID,
            'subsidiaryID' => $subsidiaryID
          ]
        );

        $message = "Não foi possível localizar a unidade/filial do "
          . "contratante para alternar o estado do bloqueio."
        ;
      }
    } catch (QueryException $exception) {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "do contratante '{name}'. Erro interno no banco de dados: "
          . "{error}.",
          [
            'name'  => $contractor->name,
            'error' => $exception->getMessage()
          ]
        );

        $message = "Não foi possível alternar o estado do bloqueio do "
          . "contratante. Erro interno no banco de dados."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "da unidade/filial {subsidiaryName} do contratante '{name}'"
          . ". Erro interno no banco de dados: {error}.",
          [
            'subsidiaryName'  => $subsidiary->name,
            'name'  => $contractor->name,
            'error' => $exception->getMessage()
          ]
        );

        $message = "Não foi possível alternar o estado do bloqueio da "
          . "unidade/filial do contratante. Erro interno no banco de "
          . "dados."
        ;
      }
    } catch (Exception $exception) {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "do contratante '{name}'. Erro interno: {error}.",
          [
            'name'  => $contractor->name,
            'error' => $exception->getMessage()
          ]
        );

        $message = "Não foi possível alternar o estado do bloqueio do "
          . "contratante. Erro interno."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "da unidade/filial {subsidiaryName} do contratante "
          . "'{name}'. Erro interno: {error}.",
          [
            'subsidiaryName'  => $subsidiary->name,
            'name'  => $contractor->name,
            'error' => $exception->getMessage()
          ]
        );

        $message = "Não foi possível alternar o estado do bloqueio da "
          . "unidade/filial do contratante. Erro interno no banco de "
          . "dados."
        ;
      }
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null
        ])
    ;
  }

  /**
   * Gera um PDF para impressão das informações de um contratante.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function getPDF(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando à geração de PDF com as informações "
      . "cadastrais de um contratante."
    );

    // Recupera as informações do contratante
    $contractorID = $args['contractorID'];
    $contractor = Contractor::join("entitiestypes",
          "entities.entitytypeid", '=', "entitiestypes.entitytypeid"
        )
      ->join("users as createduser", "entities.createdbyuserid", '=',
          "createduser.userid"
        )
      ->join("users as updateduser", "entities.updatedbyuserid", '=',
          "updateduser.userid"
        )
      ->where("entities.contractor", "true")
      ->where("entities.entityid", $contractorID)
      ->get([
          'entitiestypes.name as entitytypename',
          'entitiestypes.juridicalperson',
          'entities.*',
          'createduser.name as createdbyusername',
          'updateduser.name as updatedbyusername'
        ])
      ->first()
      ->toArray()
    ;

    // Adiciona as imagens das logomarcas

    // Recupera o local de armazenamento das imagens
    $logoDirectory = $this->container['settings']['storage']['images'];
    $searchText    = $logoDirectory . DIRECTORY_SEPARATOR
      . "Logo_{$contractor['entityuuid']}_?.*"
    ;

    $files = glob($searchText);
    if (count($files) > 0) {
      // Processa cada arquivo individualmente
      foreach ($files as $imageFile) {
        // Codifica o conteúdo do arquivo em Base64
        $imageDataBase64 = $this->readBase64Image($imageFile);

        // Em função do sufixo presente no nome do arquivo, associa o
        // conteúdo da imagem ao respectivo campo
        switch ($this->getImageSuffix($imageFile)) {
          case 'N':
            $contractor['logonormal'] = $imageDataBase64;

            break;
          case 'I':
            $contractor['logoinverted'] = $imageDataBase64;

            break;
        }
      }
    } else {
      // O arquivo não foi localizado, então retorna arquivos vazios

      // Determina informações do arquivo
      $resourcesDir = $this->app->getPublicDir()
        . DIRECTORY_SEPARATOR . 'resources';
      $imageFile = $resourcesDir . DIRECTORY_SEPARATOR . "unknown.png";
      $imageData = file_get_contents($imageFile);
      $mimeType = pathinfo($imageFile, PATHINFO_EXTENSION);
      $imageDataBase64 = 'data:image/' . $mimeType . ';base64,'
        . base64_encode($imageData);
      $contractor['logonormal'] = $imageDataBase64;
      $contractor['logoinverted'] = $imageDataBase64;
    }

    // Agora recupera as informações das suas unidades/filiais
    $subsidiaryQry = Subsidiary::join("cities",
          "subsidiaries.cityid", '=', "cities.cityid"
        )
      ->join("documenttypes", "subsidiaries.regionaldocumenttype",
          '=', "documenttypes.documenttypeid"
        )
      ->leftJoin("maritalstatus", "subsidiaries.maritalstatusid",
          '=', "maritalstatus.maritalstatusid"
        )
      ->leftJoin("genders", "subsidiaries.genderid",
          '=', "genders.genderid"
        )
      ->where("entityid", $contractorID)
    ;

    if (array_key_exists('subsidiaryID', $args)) {
      // Recupera apenas a unidade/filial informada
      $subsidiaryID = $args['subsidiaryID'];
      $subsidiaryQry
        ->where('subsidiaryid', $subsidiaryID)
      ;
    }

    $contractor['subsidiaries'] = $subsidiaryQry
      ->orderBy('headoffice', 'DESC')
      ->orderBy('name', 'ASC')
      ->get([
          'subsidiaries.*',
          'documenttypes.name as regionaldocumentname',
          'maritalstatus.name as maritalstatusname',
          'genders.name as gendername',
          'cities.name as cityname',
          'cities.state as state'
        ])
      ->toArray()
    ;

    // Para cada unidade/filial, recupera as informações de telefones,
    // e-mails e contatos adicionais
    foreach ($contractor['subsidiaries'] as $row => $subsidiary) {
      // Telefones
      $phones = $this
        ->getPhones(
            $contractorID,
            $subsidiary['subsidiaryid']
          )
      ;

      if ($phones->isEmpty()) {
        // Criamos os dados de telefone em branco
        $contractor['subsidiaries'][$row]['phones'] = [
          [
            'phoneid' => 0,
            'phonetypeid' => 1,
            'phonenumber' => ''
          ]
        ];
      } else {
        $contractor['subsidiaries'][$row]['phones'] =
          $phones->toArray()
        ;
      }

      // E-mails
      $emails = $this
        ->getEmails(
            $contractorID,
            $subsidiary['subsidiaryid']
          )
      ;

      if ($emails->isEmpty()) {
        // Criamos os dados de e-mail em branco
        $contractor['subsidiaries'][$row]['emails'] = [
          [
            'mailingid' => 0,
            'email' => ''
          ]
        ];
      } else {
        $contractor['subsidiaries'][$row]['emails'] =
          $emails->toArray()
        ;
      }

      // Contatos adicionais
      $contacts = $this
        ->getContacts(
            $contractorID,
            $subsidiary['subsidiaryid']
          )
      ;
      if (!$contacts->isEmpty()) {
        $contractor['subsidiaries'][$row]['contacts'] =
          $contacts->toArray()
        ;
      }
    }

    // Renderiza a página para poder converter em PDF
    $title = "Dados cadastrais de contratante";
    $PDFFileName = "Contractor_ID_{$contractorID}.pdf";
    $page = $this
      ->renderPDF(
          'adm/cadastre/contractors/PDFcontractor.twig',
          ['contractor' => $contractor]
        )
    ;
    $header = $this->renderPDFHeader(
      $title,
      'assets/icons/erp/erp.svg'
    );
    $footer = $this->renderPDFFooter();

    // Cria um novo mPDF e define a página no tamanho A4 com orientação
    // portrait
    $mpdf = new Mpdf($this->generatePDFConfig('A4', 'Portrait'));

    // Permite a conversão (opcional)
    $mpdf->allow_charset_conversion = true;

    // Permite a compressão
    $mpdf->SetCompression(true);

    // Define os metadados do documento
    $mpdf->SetTitle($title);
    $mpdf->SetAuthor($this->authorization->getUser()->name);
    $mpdf->SetSubject('Controle de contratantes');
    $mpdf->SetCreator('TrackerERP');

    // Define os cabeçalhos e rodapés
    $mpdf->SetHTMLHeader($header);
    $mpdf->SetHTMLFooter($footer);

    // Seta modo tela cheia
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->showImageErrors = false;
    $mpdf->debug = false;

    // Inclui o conteúdo
    $mpdf->WriteHTML($page);

    // Envia o PDF para o browser no modo Inline
    $stream = fopen('php://memory', 'r+');
    ob_start();
    $mpdf->Output($PDFFileName, 'I');
    $pdfData = ob_get_contents();
    ob_end_clean();
    fwrite($stream, $pdfData);
    rewind($stream);

    // Registra o acesso
    $this->info("Acesso ao PDF com as informações cadastrais do "
      . "contratante '{name}'.",
      ['name' => $contractor['name']]
    );

    return $response
      ->withBody(new Stream($stream))
      ->withHeader('Content-Type', 'application/pdf')
      ->withHeader(
          'Cache-Control',
          'no-store, no-cache, must-revalidate'
        )
      ->withHeader('Expires', 'Sun, 1 Jan 2000 12:00:00 GMT')
      ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT')
    ;
  }

  /**
   * Recupera a informação das entidades relacionadas à um contratante
   * e/ou a relação de contratantes em formato JSON no padrão dos campos
   * de preenchimento automático.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getAutocompletionData(
    Request $request,
    Response $response
  ): Response
  {
    $this->debug("Acesso à relação de entidades para preenchimento "
      . "automático."
    );

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do searchbox
    $name         = $postParams['searchTerm'];
    $type         = $postParams['type'];
    $contractorID = intval($postParams['contractorID']);

    // Determina os limites e parâmetros da consulta
    $start        = 0;
    $length       = 10;
    $ORDER        = 'name ASC';

    // Registra o acesso
    $typeNames = [
      'contractor' => 'contratantes(s)',
      'entity'     => 'empresas(s)'
    ];

    $this->debug("Acesso aos dados de preenchimento automático de "
      . "{type} que contenha(m) '{name}'",
      [
        'type' => $typeNames[$type],
        'name' => $name
      ]
    );

    try {
      // Localiza as entidades na base de dados
      if ($type === 'contractor') {
        // Recupera os dados de contratantes
        $entities = Contractor::where("contractor", "true")
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
          ->skip($start)
          ->take($length)
          ->orderByRaw($ORDER)
          ->get([
              'entityid as id',
              'name',
              'tradingname'
            ])
        ;
      } else {
        // Recupera os dados de entidades pertencentes ao contratante
        // especificado (podem ser clientes, fornecedores e/ou o
        // próprio contratante)
        $entities = Contractor::where('contractorid', $contractorID)
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
          ->skip($start)
          ->take($length)
          ->orderByRaw($ORDER)
          ->get([
              'entityid as id',
              'name',
              'tradingname'
            ])
        ;
      }

      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => "Entidades cujo nome contém '$name'",
            'data' => $entities
          ])
      ;
    } catch (QueryException $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [
          'module' => 'contratantes',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "contratante para preenchimento automático. Erro interno no "
        . "banco de dados."
      ;
    } catch (Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [
          'module' => 'contratantes',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "contratante para preenchimento automático. Erro interno."
      ;
    }

    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => $error,
          'data' => NULL
        ])
    ;
  }
}
