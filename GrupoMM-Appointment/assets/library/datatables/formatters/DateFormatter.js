// Um formatador (render) de coluna para exibir o ícone de expiração
var dateFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    // Modificamos os valores para exibí-los corretamente
    if ( value ) {
      // Se a data vier acompanhada da hora, extrai somente a data
      if ( value.length > 10 ) {
        value = value.substr(0, 10);
      }
      
      // Dividimos as partes da data
      var dateParts = value.split("-");

      // Retornamos a data devidamente formatada
      value = '<i class="mono darkorange">' + dateParts[2] + '/' + dateParts[1] + '/' + dateParts[0] + '</i>';
    } else {
      // Retornamos uma string mostrando uma data em branco
      value = '<i class="mono lightgrey">--/--/----</i>';
    }
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};
