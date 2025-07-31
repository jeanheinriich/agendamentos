// Um formatador (render) de coluna para exibir um valor de quantidade
// monetária em que valores negativos são exibidos de maneira diferente
var monetaryFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    var
      formatedValue = '0.00'
    ;

    if (value === null) {
      return '';
    }

    // Modificamos os valores para exibí-los corretamente
    if ( typeof value == 'number' ) {
      formatedValue = value.toFixed(2);
    }
    if ( typeof value == 'string' ) {
      formatedValue = value;
    }

    // Se o valor for negativo, exibidos de maneira diferenciada
    if ( parseFloat(value) >= 0.00 ) {
      // Retornamos o valor formatado
      formatedValue = '<span class="bold">' + formatedValue.replace('.', ',') + '</span>';
    } else {
      formatedValue = '<span class="darkred bold">' + formatedValue.replace('.', ',') + '</span>';
    }

    // Acrescentamos o símbolo monetário
    value = '<span class="symbol">R$</span> ' + formatedValue;
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};
