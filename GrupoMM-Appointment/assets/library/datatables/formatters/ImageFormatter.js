// Um formatador (render) de coluna para exibir o valor como uma imagem
var imageFormatter = function( width, heigth ) {
  return function ( value, type, row, meta ) {
    // Verifica se foi solicitada a exibição do valor
    if ( type === 'display' ) {
      if (value) {
        // Retornamos o valor como o conteúdo de uma imagem
        return '<img class="ui fluid image" src="' + value + '" width="' + width + '" heigth="' + heigth + '">';
      } else {
        // Retornamos um ícone vazio
        return '<i class="icon"></i>';
      }
    }

    // Pesquisas, ordenamentos e tipos podem usar os dados originais
    return value;
  }
};
