// Um formatador (render) de coluna para exibir um valor de quantidade
// percentual em que valores negativos são exibidos de maneira diferente
var percentageFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    // Modificamos os valores para exibí-los corretamente
    if ( typeof value == 'number' ) {
      value = value.toFixed(2);
    }

    // Se o valor for negativo, exibidos de maneira diferenciada
    if ( parseFloat(value) >= 0 ) {
      // Retornamos o valor formatado
      value = '<i class="darkgrey">' + value.replace('.', ',') + '%</i>';
    } else {
      value = '<i class="darkred">' + value.replace('.', ',') + '%</i>';
    }
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};
