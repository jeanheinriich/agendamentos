// Um formatador (render) de coluna para exibir um valor de quantidade
// em que valores zerados são exibidos de maneira diferente
var amountFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    // Modificamos os valores para exibí-los corretamente
    if ( typeof value == 'number' ) {
      // Se o valor for um número, identificamos se o mesmo é positivo
      if ( value > 0 ) {
        // Retornamos o valor formatado
        value = '<i class="darkgrey">' + value + '</i>';
      } else {
        value = '<i class="lightgrey">--</i>';
      }
    } else {
      // Retornamos uma string mostrando um valor inexistente
      value = '<i class="lightgrey">--</i>';
    }
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};
