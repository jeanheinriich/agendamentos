// Um formatador (render) de coluna para exibir a data e hora
var datetimeFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    // Modificamos os valores para exibí-los corretamente
    if ( value ) {
      if( typeof value === 'string' ) {
        var timestamp = value.split(/[- :]/);
        var dayNames = new Array('Dom', 'Seg', 'Ter', 'Qua', 'Qui',
          'Sex', 'Sáb');
        var monthNames = new Array('Jan', 'Fev', 'Mar', 'Abr', 'Mai',
          'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez');

        // Converte a data/hora em um objeto Date. Se timestamp[3],
        // timestamp[4] e timestamp[5] estiverem ausentes, define como
        // zero
        var date = new Date(timestamp[0], timestamp[1] - 1,
          timestamp[2], timestamp[3] || 0, timestamp[4] || 0,
          timestamp[5] || 0);
        
        // Retornamos a data devidamente formatada
        value = ''
          +   dayNames[date.getDay()] + ', ' + date.getDate()
          +   ' de ' + monthNames[date.getMonth()]
          +   ' de ' + date.getFullYear()
          +   ' às ' + date.getHours()
          +   ':' + ('0' + date.getMinutes()).slice(-2);
      } else {
        // Retornamos uma string mostrando uma data em branco
        value = '---';
      }
    } else {
      // Retornamos uma string mostrando uma data em branco
      value = '---';
    }
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};