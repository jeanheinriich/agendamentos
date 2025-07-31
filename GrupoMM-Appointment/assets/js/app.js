$(function () {
  // ===============================================[ Componentes ]=====
  // Ativa as funcionalidades dos componentes
  // -------------------------------------------------------------------

  // Carregamos o estado inicial do menu
  var
    menuToggled = getCookie('MenuToggled')
  ;
  if (menuToggled === 'active') {
    var
      $wrapper = $("div.ui.wrapper")
    ;

    $wrapper.toggleClass("only-icons");
  }
  
  // --------------------------------------------------[ Calendário ]---
  $(".calendar.wrap").simplecalendar({
    firstDayOfWeek: 1
  });

  // ----------------------------------------------[ Menus Dropdown ]---
  $('.ui.menu .ui.dropdown').dropdown({
    on: 'hover'
  });

  // -----------------------------------------------[ Barra lateral ]---
  $("#ToggleSidebarMenu").click(function(e) {
    e.preventDefault();

    var
      $wrapper = $("div.ui.wrapper"),
      $pusher  = $('div.pusher'),
      $toggled = $wrapper.hasClass("toggled")
    ;

    if (!$toggled) {
      $pusher.bind( "click", function(event) {
        event.preventDefault();

        var
          $wrapper = $("div.ui.wrapper"),
          $pusher = $('div.pusher')
        ;
        
        if ($wrapper.hasClass("toggled")) {
          $wrapper.toggleClass("toggled");
          $pusher.off("click");
        } else {
          $wrapper.toggleClass("toggled");
        }
      });
    } else {
      $wrapper.toggleClass("toggled");
    }
  });
  
  $("#ResizeSidebarMenu").click(function(e) {
    e.preventDefault();

    var
      $wrapper = $("div.ui.wrapper")
    ;

    // Lidamos com o estado atual do menu armazenado no cookie
    if ($wrapper.hasClass("only-icons")) {
      setCookie('MenuToggled', 'inactive');
    } else {
      setCookie('MenuToggled', 'active');
    }
    $wrapper.toggleClass("only-icons");
    $('#menu ul').hide();
  });

  // Menu tipo sanfona na barra lateral
  $(".ui.vertical.accordion.menu")
    .accordionmenu();

  // --------------------------------------[ Mensagens Instantâneas ]---
  $('.flash.message .close').on('click', function () {
    $(this).closest('.flash.message').hide('500');
  });
});
