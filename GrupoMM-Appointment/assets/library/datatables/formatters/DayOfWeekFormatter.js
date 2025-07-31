// Um formatador (render) de coluna para exibir o nome do dia da semana
// colorido, colocando sábados e domingos com cores diferentes
var dayOfWeekFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    // Colorimos o nome do dia da semana
    var classNames = 'dayOfWeek';

    switch (value) {
      case 'Sábado':
        classNames += ' saturday';

        break;
      case 'Domingo':
        classNames += ' sunday';
        
        break;
      default:
        classNames += ' weekday';
        
        break;
    }

    return '<span class="' + classNames + '">' + value + '</span>';
  }
  
  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};
